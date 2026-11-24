<?php

namespace Database\Factories;

use App\Enums\PromptRunStatus;
use App\Models\Project;
use App\Models\PromptRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromptRun>
 */
class PromptRunFactory extends Factory
{
    protected $model = PromptRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'agent_id' => null,
            'user_id' => null,
            'prompt' => $this->faker->sentence(),
            'status' => PromptRunStatus::Pending,
            'exit_code' => null,
            'output' => null,
            'error_output' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => PromptRunStatus::Completed,
            'exit_code' => 0,
            'output' => $this->faker->paragraph(),
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }
}
