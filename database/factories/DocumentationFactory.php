<?php

namespace Database\Factories;

use App\Models\Documentation;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Documentation>
 */
class DocumentationFactory extends Factory
{
    protected $model = Documentation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(3);

        return [
            'project_id' => Project::factory(),
            'path' => $this->faker->unique()->slug(2).'.md',
            'title' => $title,
            'content' => "# {$title}\n\n".$this->faker->paragraph(),
        ];
    }
}
