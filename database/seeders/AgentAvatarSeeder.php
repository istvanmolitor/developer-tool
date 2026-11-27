<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Services\AgentAvatarService;
use Illuminate\Database\Seeder;

class AgentAvatarSeeder extends Seeder
{
    /**
     * Give every agent that has no profile picture yet a generated default one
     * (its initials on a colour derived from the slug), so a freshly migrated
     * install already shows pictures in the agent list.
     */
    public function run(AgentAvatarService $avatars): void
    {
        Agent::query()
            ->whereNull('avatar_path')
            ->each(function (Agent $agent) use ($avatars): void {
                $avatars->generateDefault($agent);
            });
    }
}
