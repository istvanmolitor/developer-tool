<?php

namespace Database\Seeders;

use App\Services\AgentSyncService;
use Illuminate\Database\Seeder;
use RuntimeException;

class AgentSeeder extends Seeder
{
    /**
     * Load every Claude Code agent definition from the agents directory into
     * the agents table via {@see AgentSyncService}.
     */
    public function run(AgentSyncService $agentSync): void
    {
        try {
            $agentSync->sync();
        } catch (RuntimeException $exception) {
            $this->command?->warn($exception->getMessage());
        }
    }
}
