<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\AgentMessage;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentMessage>
 */
class AgentMessageFactory extends Factory
{
    protected $model = AgentMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'sender_agent_id' => Agent::factory(),
            'recipient_agent_id' => Agent::factory(),
            'body' => fake()->paragraph(),
        ];
    }

    /**
     * A message the recipient has already read.
     */
    public function read(): static
    {
        return $this->state(fn (): array => [
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
