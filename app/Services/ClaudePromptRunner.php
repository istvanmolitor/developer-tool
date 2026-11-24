<?php

namespace App\Services;

use App\Enums\PromptRunStatus;
use App\Jobs\RunClaudePrompt;
use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Throwable;

class ClaudePromptRunner
{
    /**
     * Create a pending prompt run for the given project and queue it for
     * execution. Returns the freshly created record.
     */
    public function queue(Project $project, string $prompt, ?User $user = null, ?Agent $agent = null): PromptRun
    {
        $run = PromptRun::create([
            'project_id' => $project->id,
            'user_id' => $user?->id,
            'agent_id' => $agent?->id,
            'prompt' => $prompt,
            'status' => PromptRunStatus::Pending,
        ]);

        RunClaudePrompt::dispatch($run);

        return $run;
    }

    /**
     * Run the prompt of the given PromptRun through `claude -p` inside the
     * project's directory, streaming the result back onto the record.
     */
    public function run(PromptRun $run): PromptRun
    {
        $project = $run->project;

        if ($project === null) {
            return $this->fail($run, 'A futtatáshoz tartozó projekt már nem létezik.');
        }

        $run->forceFill([
            'status' => PromptRunStatus::Running,
            'started_at' => now(),
        ])->save();

        try {
            $result = Process::path($project->path)
                ->env($this->environment())
                ->timeout($this->timeout())
                ->run($this->command($run->prompt));
        } catch (Throwable $exception) {
            return $this->fail($run, $exception->getMessage());
        }

        $run->forceFill([
            'status' => $result->successful() ? PromptRunStatus::Completed : PromptRunStatus::Failed,
            'exit_code' => $result->exitCode(),
            'output' => $result->output(),
            'error_output' => $result->errorOutput(),
            'finished_at' => now(),
        ])->save();

        return $run;
    }

    /**
     * @return list<string>
     */
    public function command(string $prompt): array
    {
        $command = [config('developer-tool.claude.binary'), '-p', $prompt];

        if (config('developer-tool.claude.skip_permissions')) {
            $command[] = '--dangerously-skip-permissions';
        }

        return $command;
    }

    private function fail(PromptRun $run, string $message): PromptRun
    {
        $run->forceFill([
            'status' => PromptRunStatus::Failed,
            'error_output' => $message,
            'finished_at' => now(),
        ])->save();

        return $run;
    }

    /**
     * @return array<string, string>
     */
    private function environment(): array
    {
        $home = config('developer-tool.claude.home');

        return $home ? ['HOME' => $home] : [];
    }

    private function timeout(): int
    {
        return (int) config('developer-tool.claude.timeout', 1800);
    }
}
