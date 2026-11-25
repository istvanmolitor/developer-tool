<?php

use App\Mcp\Servers\AgentServer;
use App\Mcp\Servers\DocumentationServer;
use App\Mcp\Servers\MailServer;
use App\Mcp\Servers\MessageServer;
use App\Mcp\Servers\ProjectServer;
use App\Mcp\Servers\TaskServer;
use Laravel\Mcp\Facades\Mcp;

// Local (stdio) MCP server for task management. Start with:
//   php artisan mcp:start tasks
// Registered for Claude Code in .mcp.json.
Mcp::local('tasks', TaskServer::class);

// Local (stdio) MCP server that lets an agent answer the e-mail it was handed.
// Started per prompt run by ClaudePromptRunner with the run's id in its environment.
Mcp::local('mail', MailServer::class);

// Local (stdio) MCP server for handing prompts to the agents. Start with:
//   php artisan mcp:start agents
// Registered for Claude Code in .mcp.json; not given to prompt runs, so an
// agent cannot keep starting further agent runs.
Mcp::local('agents', AgentServer::class);

// Local (stdio) MCP server for messages between the agents of a project. Started
// per prompt run by ClaudePromptRunner with the run's id in its environment; also
// registered in .mcp.json, where the acting agent and project are named per call.
Mcp::local('messages', MessageServer::class);

// Local (stdio) MCP server for creating projects. Start with:
//   php artisan mcp:start projects
// Registered for Claude Code in .mcp.json; not given to prompt runs, so an
// agent cannot keep registering further projects.
Mcp::local('projects', ProjectServer::class);

// Local (stdio) MCP server for loading a project's written documentation into
// the database. Start with:
//   php artisan mcp:start docs
// Registered for Claude Code in .mcp.json; given to a prompt run only when the
// agent's tools opt into it (an `mcp__docs__*` entry), so the orchestrator can
// persist the docs it produced while ordinary agents cannot.
Mcp::local('docs', DocumentationServer::class);
