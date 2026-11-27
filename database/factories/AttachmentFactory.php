<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = $this->faker->unique()->slug(2).'.txt';

        return [
            'project_id' => Project::factory(),
            'disk' => 'local',
            'path' => 'attachments/'.$this->faker->numberBetween(1, 999).'/'.Str::uuid().'.txt',
            'filename' => $filename,
            'mime_type' => 'text/plain',
            'size' => $this->faker->numberBetween(1, 10_000),
            'checksum' => hash('sha256', $this->faker->sentence()),
            'source' => 'upload',
            'relative_path' => null,
        ];
    }

    /**
     * A file synced from the project's attachments folder.
     */
    public function synced(?string $relativePath = null): static
    {
        return $this->state(fn (): array => [
            'source' => 'sync',
            'relative_path' => $relativePath ?? $this->faker->unique()->slug(2).'.txt',
        ]);
    }
}
