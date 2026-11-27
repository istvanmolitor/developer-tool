<?php

namespace App\Services;

use App\Models\Agent;
use App\Repositories\Contracts\AgentRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The agents' profile pictures. The bytes live on the private `local` disk
 * under {@see DIRECTORY}, so they are only ever served through the
 * `agents.avatar` route, never straight from the public folder.
 */
class AgentAvatarService
{
    /**
     * Folder on the private disk that holds the pictures.
     */
    public const DIRECTORY = 'agent-avatars';

    public function __construct(private AgentRepository $agents) {}

    /**
     * The disk the pictures are stored on.
     */
    public function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    /**
     * Store an uploaded picture as the agent's avatar, replacing the previous
     * one, and return the stored path.
     */
    public function store(Agent $agent, UploadedFile $file): string
    {
        $this->deleteFile($agent);

        $path = $file->storeAs(
            self::DIRECTORY,
            $agent->slug.'-'.Str::random(8).'.'.($file->guessExtension() ?? $file->getClientOriginalExtension()),
            ['disk' => 'local'],
        );

        if ($path === false) {
            throw new RuntimeException("Nem sikerült menteni a(z) „{$agent->name}” agent profilképét.");
        }

        $this->agents->update($agent, ['avatar_path' => $path]);

        return $path;
    }

    /**
     * Drop the agent's avatar: the file and the stored path.
     */
    public function remove(Agent $agent): void
    {
        $this->deleteFile($agent);

        $this->agents->update($agent, ['avatar_path' => null]);
    }

    /**
     * Draw a default avatar for the agent — its initials on a colour derived
     * from the slug — and store it as a PNG. Used by the seeder so a freshly
     * migrated install already shows pictures.
     */
    public function generateDefault(Agent $agent): string
    {
        $size = 256;
        $image = imagecreatetruecolor($size, $size);

        [$red, $green, $blue] = $this->colorFor($agent->slug);

        $background = (int) imagecolorallocate($image, $red, $green, $blue);
        $white = (int) imagecolorallocate($image, 255, 255, 255);

        imagefilledrectangle($image, 0, 0, $size, $size, $background);

        $initials = $this->initials($agent->name);
        $font = $this->fontPath();
        $box = $font === null ? false : imagettfbbox(96, 0, $font, $initials);

        if ($font !== null && $box !== false) {
            $textWidth = $box[2] - $box[0];
            $textHeight = $box[1] - $box[7];

            imagettftext($image, 96, 0, (int) (($size - $textWidth) / 2), (int) (($size + $textHeight) / 2), $white, $font, $initials);
        } else {
            // No TTF to draw with: fall back to GD's built-in bitmap font.
            $textWidth = imagefontwidth(5) * mb_strlen($initials);
            imagestring($image, 5, (int) (($size - $textWidth) / 2), (int) ($size / 2) - 8, $initials, $white);
        }

        $path = self::DIRECTORY.'/'.$agent->slug.'-default.png';

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        $this->disk()->put($path, $bytes);

        $this->agents->update($agent, ['avatar_path' => $path]);

        return $path;
    }

    /**
     * A TrueType font to draw the initials with: DejaVu Sans, shipped with the
     * PDF renderer, with the system copy as a fallback. Null when neither is
     * present, in which case GD's bitmap font is used instead.
     */
    private function fontPath(): ?string
    {
        $candidates = [
            base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Remove the agent's stored file, if it still has one.
     */
    private function deleteFile(Agent $agent): void
    {
        if ($agent->avatar_path !== null && $this->disk()->exists($agent->avatar_path)) {
            $this->disk()->delete($agent->avatar_path);
        }
    }

    /**
     * Up to two uppercase initials from the agent's name.
     */
    private function initials(string $name): string
    {
        $words = preg_split('/[\s\-_]+/u', trim($name)) ?: [];

        $initials = collect($words)
            ->filter(fn (string $word): bool => $word !== '')
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials === '' ? '?' : $initials;
    }

    /**
     * A stable, pleasantly dark colour for the slug, so every agent keeps the
     * same background across re-seeds.
     *
     * @return array{int<0, 255>, int<0, 255>, int<0, 255>}
     */
    private function colorFor(string $slug): array
    {
        $hash = crc32($slug);

        return [
            $this->channel(60 + ($hash & 0x3F)),
            $this->channel(60 + (($hash >> 8) & 0x5F)),
            $this->channel(90 + (($hash >> 16) & 0x5F)),
        ];
    }

    /**
     * Clamp a derived value into a colour channel.
     *
     * @return int<0, 255>
     */
    private function channel(int $value): int
    {
        return max(0, min(255, $value));
    }
}
