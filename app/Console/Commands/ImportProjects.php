<?php

namespace App\Console\Commands;

use App\Services\ProjectService;
use Illuminate\Console\Command;

class ImportProjects extends Command
{
    protected $signature = 'projects:import';

    protected $description = 'A projektek útvonala alatti, az adatbázisban még nem szereplő projektkönyvtárak regisztrálása.';

    public function handle(ProjectService $projects): int
    {
        $imported = $projects->importFromDisk();

        if ($imported === []) {
            $this->info('Nincs új importálható projektkönyvtár.');

            return self::SUCCESS;
        }

        $this->info(count($imported).' projekt importálva:');

        foreach ($imported as $name) {
            $this->line('  • '.$name);
        }

        return self::SUCCESS;
    }
}
