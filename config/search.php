<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Search Driver
    |--------------------------------------------------------------------------
    |
    | "elasticsearch" keeps the documents in an Elasticsearch index and runs
    | every search there (falling back to the database when the cluster is
    | unreachable). "collection" skips Elasticsearch entirely and filters the
    | database rows in PHP — meant for tests and small local setups.
    |
    */

    'driver' => env('SEARCH_DRIVER', 'elasticsearch'),

    'elasticsearch' => [
        'host' => env('ELASTICSEARCH_HOST', 'http://elasticsearch:9200'),
        'username' => env('ELASTICSEARCH_USERNAME'),
        'password' => env('ELASTICSEARCH_PASSWORD'),

        // The alias every read and write goes through; `search:reindex`
        // builds a fresh versioned index behind it and swaps it in.
        'index' => env('ELASTICSEARCH_INDEX', 'developer_tool'),

        'timeout' => (int) env('ELASTICSEARCH_TIMEOUT', 5),
        'connect_timeout' => (int) env('ELASTICSEARCH_CONNECT_TIMEOUT', 2),
    ],

];
