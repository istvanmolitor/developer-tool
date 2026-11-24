<?php

use App\Mcp\Servers\MailServer;
use App\Mcp\Servers\TaskServer;
use Laravel\Mcp\Facades\Mcp;

// Local (stdio) MCP server for task management. Start with:
//   php artisan mcp:start tasks
// Registered for Claude Code in .mcp.json.
Mcp::local('tasks', TaskServer::class);

// Local (stdio) MCP server that lets an agent answer the e-mail it was handed.
// Started per prompt run by ClaudePromptRunner with the run's id in its environment.
Mcp::local('mail', MailServer::class);
