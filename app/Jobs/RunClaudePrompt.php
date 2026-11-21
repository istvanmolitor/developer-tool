<?php

namespace App\Jobs;

use App\Enums\PromptRunStatus;
use App\Models\PromptRun;
use App\Services\ClaudePromptRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunClaudePrompt implements ShouldQueue
{
    use Queueable;

    /**
     * The claude run can take a long while; give it room beyond the CLI timeout.
     */
    public int $timeout = 2100;

    public int $tries = 1;

    public function __construct(public PromptRun $promptRun) {}

    public function handle(ClaudePromptRunner $runner): void
    {
        $runner->run($this->promptRun);
    }

    public function failed(?Throwable $exception): void
    {
        $this->promptRun->forceFill([
            'status' => PromptRunStatus::Failed,
            'error_output' => $exception?->getMessage() ?? 'The job failed unexpectedly.',
            'finished_at' => now(),
        ])->save();
    }
}
