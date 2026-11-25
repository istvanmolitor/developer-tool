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
    | Clone Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds a background `git clone` may run when creating
    | a project from a git link before it is aborted.
    |
    */

    'clone_timeout' => (int) env('DEVELOPER_TOOL_CLONE_TIMEOUT', 900),

    /*
    |--------------------------------------------------------------------------
    | Agents Directory
    |--------------------------------------------------------------------------
    |
    | Absolute path to the directory holding the Claude Code agent definitions
    | (`*.md` files with YAML front matter). The AgentSeeder reads every file
    | here through the MarkdownFileReader and upserts it into the agents table.
    |
    */

    'agents_path' => env('DEVELOPER_TOOL_AGENTS_PATH', base_path('.claude/agents')),

    /*
    |--------------------------------------------------------------------------
    | Mailbox Passwords
    |--------------------------------------------------------------------------
    |
    | The agent mailboxes the MailboxSeeder creates, keyed by e-mail address,
    | with their passwords from the environment. A mailbox without a password
    | is skipped by the seeder.
    |
    */

    'mailbox_passwords' => [
        'molitor.istvan.aiagent1@dh.hu' => env('MAILBOX_AIAGENT1_PASSWORD'),
        'molitor.istvan.aiagent2@dh.hu' => env('MAILBOX_AIAGENT2_PASSWORD'),
        'molitor.istvan.aiagent3@dh.hu' => env('MAILBOX_AIAGENT3_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mailbox Server Host
    |--------------------------------------------------------------------------
    |
    | The IMAP and SMTP host the MailboxSeeder assigns to every agent mailbox.
    | Without it the seeder creates no mailboxes.
    |
    */

    'mailbox_server_host' => env('MAILBOX_SERVER_HOST'),

    /*
    |--------------------------------------------------------------------------
    | Mailbox Fetch Schedule
    |--------------------------------------------------------------------------
    |
    | Once a day, at this time (HH:MM in the given timezone), the scheduler runs
    | `mailboxes:fetch` to download the new messages of every active mailbox.
    |
    */

    'mailbox_fetch_time' => env('DEVELOPER_TOOL_MAILBOX_FETCH_TIME', '06:00'),

    'mailbox_fetch_timezone' => env('DEVELOPER_TOOL_MAILBOX_FETCH_TIMEZONE', 'Europe/Budapest'),

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
