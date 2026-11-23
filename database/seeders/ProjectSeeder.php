<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Seed the system project: the developer tool's own codebase.
     *
     * This is not one of the projects living under the configured projects
     * directory — it points at the application itself (base_path()) so claude
     * prompts can be run against the tool's own source. It is flagged
     * `is_system` and therefore cannot be deleted.
     */
    public function run(): void
    {
        $project = Project::updateOrCreate(
            ['name' => Project::SYSTEM_NAME],
            [
                'path' => base_path(),
                'description' => 'A fejlesztői eszköz saját kódbázisa — a rendszer önmaga. Ez a projekt nem törölhető.',
            ],
        );

        // `is_system` is guarded against mass assignment, so set it explicitly.
        $project->forceFill(['is_system' => true])->save();
    }
}
