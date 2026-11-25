<?php

namespace Database\Factories;

use App\Enums\PromptRunStatus;
use App\Models\AgentContext;
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
            'agent_context_id' => AgentContext::factory(),
            'prompt' => $this->faker->sentence(),
            'status' => PromptRunStatus::Pending,
            'exit_code' => null,
            'output' => null,
            'error_output' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    /**
     * Run the prompt in a new context with the given attributes (project_id, agent_id, user_id, session_id).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function inContext(array $attributes): static
    {
        return $this->for(AgentContext::factory()->state($attributes));
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
