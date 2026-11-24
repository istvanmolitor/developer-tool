<?php

namespace Database\Factories;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->slug(2),
            'description' => $this->faker->sentence(),
            'model' => $this->faker->randomElement([
                'claude-opus-4-8',
                'claude-sonnet-5',
                'claude-haiku-4-5',
            ]),
            'tools' => $this->faker->randomElements(
                ['Read', 'Grep', 'Glob', 'Bash', 'Edit', 'Write'],
                $this->faker->numberBetween(2, 4),
            ),
            'system_prompt' => $this->faker->paragraphs(2, true),
            'source_path' => null,
        ];
    }
}
