<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

final class CorePanelAuthApiTest extends TestCase
{
    public function test_it_completes_the_auth_flow_through_fortify_login(): void
    {
        $password = 'password';
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => bcrypt($password),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_it_returns_authenticated_api_responses_with_the_package_response_shape(): void
    {
        $user = $this->createSuperAdmin(['email' => 'api@example.test']);

        $response = $this->actingAs($user)->getJson('/api/me');

        $response->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'api@example.test')
            ->assertJsonPath('meta.version', config('core-panel.api.version', 'v1'));
    }

    public function test_it_serves_security_headers_on_public_api_responses(): void
    {
        $response = $this->getJson('/admin/ping');

        $response->assertSuccessful();

        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertNotNull($response->headers->get('Referrer-Policy'));
        $this->assertNotNull(
            $response->headers->get('Content-Security-Policy')
                ?: $response->headers->get('Content-Security-Policy-Report-Only')
        );
    }

    public function test_it_exposes_passport_client_management_when_passport_is_enabled(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->get(route('core-panel.oauth-clients.index'));

        $response->assertSuccessful();
    }
}
