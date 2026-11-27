<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The users' profile pictures. The bytes live on the private `local` disk
 * under {@see DIRECTORY}, so they are only ever served through the
 * `profile.avatar` route, never straight from the public folder. Mirrors
 * {@see AgentAvatarService}.
 */
class UserAvatarService
{
    /**
     * Folder on the private disk that holds the pictures.
     */
    public const DIRECTORY = 'user-avatars';

    /**
     * The disk the pictures are stored on.
     */
    public function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    /**
     * Store an uploaded picture as the user's avatar, replacing the previous
     * one, and return the stored path.
     */
    public function store(User $user, UploadedFile $file): string
    {
        $this->deleteFile($user);

        $path = $file->storeAs(
            self::DIRECTORY,
            $user->id.'-'.Str::random(8).'.'.($file->guessExtension() ?? $file->getClientOriginalExtension()),
            ['disk' => 'local'],
        );

        if ($path === false) {
            throw new RuntimeException("Nem sikerült menteni a(z) „{$user->name}” felhasználó profilképét.");
        }

        $user->update(['avatar_path' => $path]);

        return $path;
    }

    /**
     * Drop the user's avatar: the file and the stored path.
     */
    public function remove(User $user): void
    {
        $this->deleteFile($user);

        $user->update(['avatar_path' => null]);
    }

    /**
     * Remove the user's stored file, if they still have one.
     */
    private function deleteFile(User $user): void
    {
        if ($user->avatar_path !== null && $this->disk()->exists($user->avatar_path)) {
            $this->disk()->delete($user->avatar_path);
        }
    }
}
