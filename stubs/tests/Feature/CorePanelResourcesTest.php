<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use CorePanel\Models\Form;
use CorePanel\Models\FormSubmission;
use CorePanel\Models\ManagedFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CorePanelResourcesTest extends TestCase
{
    public function test_it_supports_user_crud_from_the_skeleton_host_app(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->post(route('core-panel.users.store'), [
            'first_name' => 'Created',
            'last_name' => 'User',
            'email' => 'created-user@example.test',
            'status' => 'active',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role_names' => ['super-admin'],
        ]);

        $createdUser = User::query()->where('email', 'created-user@example.test')->firstOrFail();

        $response->assertRedirect(route('core-panel.users.show', $createdUser->getKey()));

        $this->assertSame('Created', $createdUser->first_name);
        $this->assertSame('User', $createdUser->last_name);
    }

    public function test_it_uploads_files_through_the_managed_file_flow(): void
    {
        Storage::fake('public');

        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->post(route('core-panel.files.store'), [
            'collection' => 'files',
            'file' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $response->assertRedirect();

        $this->assertSame(1, ManagedFile::query()->count());
    }

    public function test_it_creates_dynamic_forms_and_stores_public_submissions(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->post(route('core-panel.forms.store'), [
            'name' => 'Contact Form',
            'slug' => 'contact-form',
            'status' => Form::STATUS_PUBLISHED,
            'schema_json' => [
                [
                    'name' => 'name',
                    'type' => 'text',
                    'label' => 'Name',
                    'required' => true,
                    'rules' => ['string', 'required'],
                ],
            ],
            'settings_json' => [],
        ]);

        $form = Form::query()->where('slug', 'contact-form')->firstOrFail();

        $response->assertRedirect(route('core-panel.forms.edit', $form->getKey()));

        $this->post(route('core-panel.forms.public.store', $form->getAttribute('slug')), [
            'data' => [
                'name' => 'Jane Doe',
            ],
        ])->assertRedirect();

        $this->assertSame(1, FormSubmission::query()->where('form_id', $form->getKey())->count());
    }
}
