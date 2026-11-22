<?php

namespace App\Console\Commands;

use App\Services\ProjectService;
use Illuminate\Console\Command;

class ImportProjects extends Command
{
    protected $signature = 'projects:import';

    protected $description = 'Register any project directory under the projects path that is not yet in the database.';

    public function handle(ProjectService $projects): int
    {
        $imported = $projects->importFromDisk();

        if ($imported === []) {
            $this->info('No new project directories to import.');

            return self::SUCCESS;
        }

        $this->info('Imported '.count($imported).' project(s):');

        foreach ($imported as $name) {
            $this->line('  • '.$name);
        }

        return self::SUCCESS;
    }
}
