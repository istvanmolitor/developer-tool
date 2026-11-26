# Prompt-futtatás, chat, költség

## Folyamat

1. A projekt „prompts” oldala (`GET projects/{project}/prompts`, `ProjectController::prompts`) vagy a dashboard chat prompt és/vagy agent választását engedi; a küldés `POST projects/{project}/runs` (`PromptRunController::store`, `StorePromptRunRequest`: `prompt` 3–20000 karakter, kötelező, ha nincs `agent_id`; az `agent_id` létező agent).
2. `ClaudePromptRunner` (`app/Services/ClaudePromptRunner.php`): `queue`/`queueAgent` új `AgentContext`-et indít, `queueInContext` egy meglévőben `PromptRun`-t (`pending`) hoz létre és `RunClaudePrompt` jobot küld (`afterCommit`).
3. `RunClaudePrompt` (`app/Jobs/RunClaudePrompt.php`, timeout 2100 s, 1 próba) hívja `ClaudePromptRunner::run`-t, ami a projekt könyvtárában futtatja a `claude -p <prompt> --output-format json` parancsot (`Process`), `HOME` = `DEVELOPER_TOOL_CLAUDE_HOME`, timeout = `DEVELOPER_TOOL_CLAUDE_TIMEOUT`.
4. Az eredmény: `status` (`completed` / `failed`), `exit_code`, `output`, `error_output`, `cost_usd`, `started_at`, `finished_at`. Ha a projekt már nem létezik vagy kivétel történik, a futás `failed`.

Státuszok: `app/Enums/PromptRunStatus.php` – `pending`, `running`, `completed`, `failed`.

## Agent esetén

- Agent esetén `--agents <json> --agent <slug>` inline definíciót kap (leírás, system prompt, modell, eszközök) – így bármely projekten fut.
- Prompt nélkül az agent leírása (vagy „Kezdd el a feladatodat a projekten.”) a feladat (`agentPrompt`).
- `--resume <session_id>`, ha a kontextusnak már van session-je; az új `session_id` a futás végén a kontextusra mentődik.
- MCP szerverek a futáshoz (`AgentMcpConfig`, `--mcp-config`, `php artisan mcp:start <szerver>`): minden szerver (`tasks`, `agents`, `messages`, `docs`, `attachments`, `projects`) opt-in — akkor indul el, ha az agent eszközlistáján van `mcp__<szerver>__*` bejegyzés; eszközlista nélküli agent mindet megkapja. A `tasks` és a `messages` a futáshoz kötve indul (`PROMPT_RUN_ENV`). Kivétel a `mail`: azt a postafiók-hozzárendelés adja — ha az agenthez tartozik postafiók, a futása az eszközlistától függetlenül megkapja a szervert és a `reply-email` eszközt.
- `--dangerously-skip-permissions`, ha `DEVELOPER_TOOL_CLAUDE_SKIP_PERMISSIONS` igaz.

## Költség („kreditek")

A JSON kimenetből a `total_cost_usd` kerül a `prompt_runs.cost_usd` mezőbe, `result` az `output`-ba; nem JSON kimenetnél a nyers kimenet és `null` költség (`parseResult`).

## Modellek

- `PromptRun`: `agent_context_id`, `prompt`, `status`, `exit_code`, `output`, `error_output`, `cost_usd`, `started_at`, `finished_at`, csak `created_at`; a `project`, `agent`, `user` a kontextuson keresztül érhető el; kereshető (`SearchType::Prompt`).
- `AgentContext`: `project_id`, `agent_id`, `user_id`, `session_id` (lásd [projektek-es-agentek.md](projektek-es-agentek.md)).

## Dashboard és chat

`DashboardController` (`resources/js/pages/Dashboard.vue`):

- **Prompt-sor**: futó (`running`) és várakozó (`pending`, max. 10 listázva + összes darabszám) futások.
- **Chat**: projekt (alapból a rendszerprojekt) és agent választható; a kiválasztott kontextus utolsó 30 futása üzenetként jelenik meg; „Új beszélgetés” új kontextust indít; a legutóbbi 20 kontextus listázva (cím = a kontextus első promptja). A `?context=`, `?project=`, `?agent=` query paraméterek választják ki a nézetet.
