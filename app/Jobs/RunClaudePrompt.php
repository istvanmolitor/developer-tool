<?php

namespace App\Jobs;

use App\Enums\PromptRunStatus;
use App\Models\PromptRun;
use App\Repositories\Contracts\PromptRunRepository;
use App\Repositories\Contracts\SettingRepository;
use App\Services\ClaudePromptRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunClaudePrompt implements ShouldQueue
{
    use Queueable;

    /**
     * How long to wait before re-checking a paused run.
     */
    private const PAUSE_RECHECK_SECONDS = 30;

    /**
     * The claude run can take a long while; give it room beyond the CLI timeout.
     */
    public int $timeout = 2100;

    public int $tries = 1;

    public function __construct(public PromptRun $promptRun) {}

    public function handle(ClaudePromptRunner $runner, SettingRepository $settings): void
    {
        // The global pause switch holds every pending run in the queue without
        // executing it (the emergency brake for a run-away loop). Keep the run
        // pending and re-queue a fresh check — a new dispatch, not release(),
        // so the single try isn't spent while paused.
        if ($settings->flag('pause_prompt_runs')) {
            self::dispatch($this->promptRun)->delay(now()->addSeconds(self::PAUSE_RECHECK_SECONDS));

            return;
        }

        $runner->run($this->promptRun);
    }

    public function failed(?Throwable $exception): void
    {
        $this->promptRun->forceFill([
            'status' => PromptRunStatus::Failed,
            'error_output' => $exception?->getMessage() ?? 'A feladat váratlanul meghiúsult.',
            'finished_at' => now(),
        ]);
        app(PromptRunRepository::class)->save($this->promptRun);
    }
}
