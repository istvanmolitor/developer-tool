# MCP szerverek

A `routes/ai.php` helyi (stdio) MCP szervereket regisztrál (`laravel/mcp`); indítás: `php artisan mcp:start <név>`. A `.mcp.json` a Claude Code számára a `tasks`, `agents`, `projects`, `messages`, `docs` szervereket (és a `laravel-boost`-ot) tartalmazza. Szerverek: `app/Mcp/Servers`, eszközök: `app/Mcp/Tools`.

| Szerver (`mcp:start`) | Osztály | Eszközök | Megjegyzés |
| --------------------- | ------- | -------- | ---------- |
| `tasks` | `TaskServer` | `list-projects`, `list-agents`, `list-tasks`, `next-task`, `create-task`, `update-task`, `delete-task`, `list-task-comments`, `add-task-comment` | agent-futásnál a futáshoz kötött (`PROMPT_RUN_ENV`), így a változások az agent nevén naplózódnak; csak `mcp__tasks__*` eszközzel rendelkező agentnek |
| `agents` | `AgentServer` | `list-agents`, `list-projects`, `run-agent`, `get-prompt-run` | prompt sorba állítása agentnek; prompt-futásnak csak opt-in (`mcp__agents__*`) |
| `projects` | `ProjectServer` | `list-projects`, `list-project-directories`, `create-project` | futásoknak nem adjuk át |
| `messages` | `MessageServer` | `list-agents`, `send-message`, `list-messages` | minden agent-futás megkapja, a futáshoz kötve |
| `mail` | `MailServer` | `reply-email` | csak bejövő levélhez tartozó futásnál, a futáshoz kötve |
| `docs` | `DocumentationServer` | `sync-documentation` | futásnak csak opt-in (`mcp__docs__*`) |

Közös segédek: `app/Mcp/Concerns/FormatsTasks.php`, `ResolvesMessagingContext.php`. A futásokhoz adott szerverek összeállítása: `ClaudePromptRunner::command` (lásd [prompt-futtatas.md](prompt-futtatas.md)).
