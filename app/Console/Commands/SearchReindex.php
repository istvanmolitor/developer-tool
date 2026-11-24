<?php

namespace App\Console\Commands;

use App\Services\Search\ElasticsearchEngine;
use App\Services\Search\SearchUnavailableException;
use Illuminate\Console\Command;

class SearchReindex extends Command
{
    protected $signature = 'search:reindex';

    protected $description = 'Az Elasticsearch index újraépítése: új index létrehozása, minden kereshető rekord betöltése, majd az alias átállítása.';

    public function handle(ElasticsearchEngine $elasticsearch): int
    {
        if (config('search.driver') !== 'elasticsearch') {
            $this->warn('A keresés nem Elasticsearch-csel fut (SEARCH_DRIVER), nincs mit indexelni.');

            return self::SUCCESS;
        }

        try {
            $count = $elasticsearch->rebuild(fn (int $indexed) => $this->output->write("\r{$indexed} dokumentum indexelve…"));
        } catch (SearchUnavailableException $exception) {
            $this->newLine();
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Kész: {$count} dokumentum a(z) „{$elasticsearch->alias()}” indexben.");

        return self::SUCCESS;
    }
}
