<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\UserAvatarService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function __construct(private UserAvatarService $avatars) {}

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->safe()->only(['name', 'email']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // The picture lives only on the private disk and in the database, so it
        // is applied separately from the profile fields.
        if ($request->hasFile('avatar')) {
            $this->avatars->store($user, $request->file('avatar'));
        } elseif ($request->boolean('remove_avatar')) {
            $this->avatars->remove($user);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profil frissítve.')]);

        return to_route('profile.edit');
    }

    /**
     * The authenticated user's profile picture, streamed from the private disk.
     * Only raster images are ever stored, so serving it inline is safe.
     */
    public function avatar(Request $request): StreamedResponse
    {
        $user = $request->user();
        $disk = $this->avatars->disk();

        abort_if($user->avatar_path === null || ! $disk->exists($user->avatar_path), 404);

        return $disk->response($user->avatar_path, null, [
            'Content-Type' => $disk->mimeType($user->avatar_path) ?: 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
