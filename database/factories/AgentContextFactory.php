<?php

namespace Database\Factories;

use App\Models\AgentContext;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentContext>
 */
class AgentContextFactory extends Factory
{
    protected $model = AgentContext::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'agent_id' => null,
            'user_id' => null,
            'session_id' => null,
        ];
    }
}
