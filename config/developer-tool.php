<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Projects Directory
    |--------------------------------------------------------------------------
    |
    | Absolute path, as seen by the PHP/queue process, to the directory that
    | holds the projects this tool develops. Each immediate subdirectory is
    | treated as a single project that claude prompts are run against.
    |
    */

    'projects_path' => env('DEVELOPER_TOOL_PROJECTS_PATH', '/projects'),

    /*
    |--------------------------------------------------------------------------
    | Claude CLI
    |--------------------------------------------------------------------------
    |
    | Configuration for the headless `claude -p` runs. "skip_permissions" runs
    | claude with --dangerously-skip-permissions so it can edit project files
    | autonomously (there is no interactive prompt to answer in -p mode).
    | "home" is exported as HOME so claude finds its mounted credentials.
    |
    */

    'claude' => [
        'binary' => env('DEVELOPER_TOOL_CLAUDE_BIN', 'claude'),
        'skip_permissions' => env('DEVELOPER_TOOL_CLAUDE_SKIP_PERMISSIONS', true),
        'timeout' => (int) env('DEVELOPER_TOOL_CLAUDE_TIMEOUT', 1800),
        'home' => env('DEVELOPER_TOOL_CLAUDE_HOME'),
    ],

];
