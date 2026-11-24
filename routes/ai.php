<?php

use App\Mcp\Servers\TaskServer;
use Laravel\Mcp\Facades\Mcp;

// Local (stdio) MCP server for task management. Start with:
//   php artisan mcp:start tasks
// Registered for Claude Code in .mcp.json.
Mcp::local('tasks', TaskServer::class);
