<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class Project implements Arrayable
{
    public function __construct(
        public string $name,
        public string $path,
        public ?CarbonImmutable $lastModifiedAt,
        public bool $isGitRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'last_modified_at' => $this->lastModifiedAt?->toIso8601String(),
            'is_git_repository' => $this->isGitRepository,
        ];
    }
}
