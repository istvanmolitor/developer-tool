<?php

namespace App\Services;

use App\Enums\PromptRunStatus;
use App\Models\Project;
use App\Models\PromptRun;
use Illuminate\Support\Facades\Process;
use Throwable;

class ClaudePromptRunner
{
    /**
     * Run the prompt of the given PromptRun through `claude -p` inside the
     * project's directory, streaming the result back onto the record.
     */
    public function run(PromptRun $run): PromptRun
    {
        $project = Project::query()->where('name', $run->project)->first();

        if ($project === null) {
            return $this->fail($run, "Project [{$run->project}] no longer exists.");
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
