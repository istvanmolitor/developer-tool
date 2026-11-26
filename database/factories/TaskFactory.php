<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => $this->faker->sentence(4),
            'type' => $this->faker->randomElement(TaskType::cases()),
            'description' => $this->faker->optional()->paragraph(),
            'status' => $this->faker->randomElement(TaskStatus::cases()),
            // Determinisztikus alapérték: az auto_run most már futást indíthat
            // (agent-átrendeléskor), ezért a véletlen true flaky tesztet okozna.
            // Aki az automatikus indítást teszteli, explicit állítsa true-ra.
            'auto_run' => false,
            'priority' => $this->faker->randomElement(TaskPriority::cases()),
            'due_date' => $this->faker->optional()->dateTimeBetween('now', '+1 month'),
            'position' => 0,
        ];
    }

    public function done(): static
    {
        return $this->state(fn (): array => ['status' => TaskStatus::Done]);
    }
}
