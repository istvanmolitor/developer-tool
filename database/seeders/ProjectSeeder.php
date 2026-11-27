<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Services\DocumentService;
use App\Services\ProjectService;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Seed the projects and their document.
     *
     * First the system project: the developer tool's own codebase. This is not
     * one of the projects living under the configured projects directory — it
     * points at the application itself (base_path()) so claude prompts can be
     * run against the tool's own source. It is flagged `is_system` and
     * therefore cannot be deleted.
     *
     * Then every directory under the configured projects directory is imported
     * as a project, and each project's `docs` folder is loaded
     * into the documents table, so a fresh migration reflects what is
     * already on disk.
     */
    public function run(ProjectService $projects, DocumentService $document): void
    {
        $system = Project::updateOrCreate(
            ['name' => Project::SYSTEM_NAME],
            [
                'path' => base_path(),
                'description' => 'A fejlesztői eszköz saját kódbázisa — a rendszer önmaga. Ez a projekt nem törölhető.',
            ],
        );

        // `is_system` is guarded against mass assignment, so set it explicitly.
        $system->forceFill(['is_system' => true])->save();

        // Register any project directory on disk that is not yet a project.
        $imported = $projects->importFromDisk();

        foreach ($imported as $name) {
            $this->command?->info("Projekt importálva: {$name}");
        }

        // Load each project's document folder into the database.
        Project::query()->each(function (Project $project) use ($document): void {
            $document->syncProject($project);
        });
    }
}
