# MCP szerverek

A `routes/ai.php` helyi (stdio) MCP szervereket regisztrál (`laravel/mcp`); indítás: `php artisan mcp:start <név>`. A `.mcp.json` a Claude Code számára a `tasks`, `agents`, `projects`, `messages`, `docs` szervereket (és a `laravel-boost`-ot) tartalmazza. Szerverek: `app/Mcp/Servers`, eszközök: `app/Mcp/Tools`.

| Szerver (`mcp:start`) | Osztály | Eszközök | Megjegyzés |
| --------------------- | ------- | -------- | ---------- |
| `tasks` | `TaskServer` | `list-tasks`, `next-task`, `create-task`, `update-task`, `delete-task`, `list-task-comments`, `add-task-comment` | opt-in (`mcp__tasks__*`); a futáshoz kötött (`PROMPT_RUN_ENV`), így a változások az agent nevén naplózódnak |
| `agents` | `AgentServer` | `list-agents`, `run-agent`, `get-prompt-run` | prompt sorba állítása agentnek; opt-in (`mcp__agents__*`) |
| `projects` | `ProjectServer` | `list-projects`, `list-project-directories`, `create-project` | opt-in (`mcp__projects__*`) |
| `messages` | `MessageServer` | `send-message`, `list-messages` | opt-in (`mcp__messages__*`); a futáshoz kötve |
| `mail` | `MailServer` | `reply-email` | nem az eszközlista adja: annak az agentnek a futása kapja meg (a futáshoz kötve), akihez postafiók van rendelve |
| `docs` | `DocumentationServer` | `sync-documentation` | opt-in (`mcp__docs__*`) |
| `attachments` | `AttachmentServer` | `sync-attachments`, `create-attachment`, `list-attachments`, `attach-to-task`, `detach-from-task`, `delete-attachment` | opt-in (`mcp__attachments__*`) |

Minden eszköz pontosan egy szerveren él: a projektek listázása a `projects`, az agentek listázása az `agents` szerver dolga, a többi szerver innen várja a neveket (korábban négy, illetve három szerveren is regisztrálva volt ugyanaz az eszköz). Mivel az agent eszközönként kapja az engedélyt, a `list-projects` engedélyezése nem jár együtt a `create-project` joggal — csak a szerver indul el hozzá.

Egy futás MCP szerverei **opt-in** alapon állnak össze: a szerver csak akkor indul el, ha az agent eszközlistáján van `mcp__<szerver>__*` bejegyzés (eszközlista nélküli agent — aki minden eszközt örököl — mindet megkapja). Egyetlen kivétel a `mail`: a kimenő levelezés a **postafiók-hozzárendelésen** múlik — ha az agenthez tartozik `Mailbox`, a futása az eszközlistájától függetlenül megkapja a szervert és a `reply-email` eszközt (a válasz maga továbbra is csak a futásnak átadott levélre, egyszer adható). Ezért a `mail` eszköz az agent űrlapján nem is jelölhető be.

Közös segédek: `app/Mcp/Concerns/FormatsTasks.php`, `ResolvesMessagingContext.php`. A futásokhoz adott szerverek és a futás agentjének adott extra MCP eszközök összeállítása: `AgentMcpConfig` (`app/Services/AgentMcpConfig.php`), amit a `ClaudePromptRunner::command` használ (lásd [prompt-futtatas.md](prompt-futtatas.md)).
