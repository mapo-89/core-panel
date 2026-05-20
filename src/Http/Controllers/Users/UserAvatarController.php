<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Users;

use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Media\MediaService;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UserAvatarController extends Controller
{
    public function __construct(
        private readonly UserModelManager $users,
        private readonly MediaService $media,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function store(Request $request, string $user): RedirectResponse|JsonResponse
    {
        $target = $this->users->findOrFail($user, true);
        Gate::authorize('update', $target);

        if (! $this->users->supportsMedia()) {
            throw new NotFoundHttpException;
        }

        $allowedMimeTypes = (array) config('core-panel.files.avatar.allowed_mime_types', [
            'image/jpeg',
            'image/png',
            'image/webp',
        ]);
        $maxUploadSize = (int) config(
            'core-panel.files.avatar.max_upload_size',
            config('core-panel.files.max_upload_size', 10240),
        );

        $validated = $request->validate([
            'avatar' => [
                'required',
                'mimetypes:'.implode(',', $allowedMimeTypes),
                'max:'.$maxUploadSize,
            ],
        ]);

        if (method_exists($target, 'clearMediaCollection')) {
            $target->clearMediaCollection('avatars');
        }

        $media = $this->media->upload($target, $validated['avatar'], 'avatars');

        $this->activityLog
            ->withCauser($request->user())
            ->log($target, 'updated', [
                'collection' => 'avatars',
                'media_url' => $this->media->url($media),
                'subject_type' => 'media',
            ]);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'avatar_url' => $this->users->avatarUrl($target->refresh()),
                ],
                'message' => __('page-users.users.avatar_updated'),
            ]);
        }

        return back()->with('status', __('page-users.users.avatar_updated'));
    }

    public function destroy(Request $request, string $user): RedirectResponse|JsonResponse
    {
        $target = $this->users->findOrFail($user, true);
        Gate::authorize('update', $target);

        if (! $this->users->supportsMedia()) {
            throw new NotFoundHttpException;
        }

        if (method_exists($target, 'getFirstMedia')) {
            $media = $target->getFirstMedia('avatars');

            if ($media !== null) {
                $this->media->delete($media);

                $this->activityLog
                    ->withCauser($request->user())
                    ->log($target, 'deleted', [
                        'collection' => 'avatars',
                        'subject_type' => 'media',
                    ]);
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'avatar_url' => null,
                ],
                'message' => __('page-users.users.avatar_removed'),
            ]);
        }

        return back()->with('status', __('page-users.users.avatar_removed'));
    }
}
