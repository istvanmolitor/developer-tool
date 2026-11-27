---
name: editing-agents
description: >-
  Use when changing, adding, removing, or reviewing the capabilities of this
  project's agents (the orchestrator/project-manager and the specialist agents:
  product-owner, architect, backend/frontend-developer, database-expert,
  ui-designer, tester, code-reviewer, security-reviewer, debugger, devops,
  documentarian, support). Triggers on edits to .claude/agents/*.md, to an
  agent's system prompt, model, or tools list, and on questions about why an
  agent behaves a certain way or how an agent's edits reach the running app.
  Explains the two-way sync between the .md definition files and the agents
  database table, and which MCP servers a prompt run gets.
---

# Agent-definíciók szerkesztése (.md ⇄ adatbázis)

Az agentek definíciója **két helyen** él, és a kettőt szinkronban kell tartani:

1. **`.claude/agents/<slug>.md`** — a forrás. YAML front matter (`name`=slug,
   `title`=megjelenített név, `description`, `model`, `tools`) + a Markdown törzs,
   ami az agent **system promptja**. Ezt használja a Claude Code interaktív
   munkamenet és az `Agent` sub-agent eszköz.
2. **`agents` adatbázis tábla** — ebből dolgoznak a **prompt run-ok** (pl. a
   Dashboard chatből indított agentek). A `ClaudePromptRunner::command()` ebből a
   rekordból injektálja a `--agents`/`--agent` flageket a `claude -p` hívásba.

**Kulcsszabály:** a prompt run-ok a DB rekordból futnak, NEM a fájlból. Egy `.md`
szerkesztés önmagában nem hat a chatből indított agentekre — vissza kell tölteni
a DB-be.

## Két irány

### A) Fájl szerkesztése → betöltés az adatbázisba

1. Szerkeszd a `.claude/agents/<slug>.md` fájlt (`tools`, `model`, system prompt).
2. Töltsd be a DB-be az alábbiak egyikével:
   - **UI:** Agentek oldal → „Agentek szinkronizálása" gomb (`AgentController@sync`
     → `AgentSyncService::sync()`).
   - **CLI (konténerben):** `php artisan db:seed --class=AgentSeeder`
     (host-on nincs MySQL — lásd a memóriát; Sail/konténer kell).
   A sync slug (fájlnév) szerint upsertel, meglévő sorokat frissít, nem duplikál.

### B) Admin UI-n mentés → fájl frissítése

Ha az Agentek admin felületén **mentesz** (létrehozás/szerkesztés), a
`AgentController` a DB mentés után visszaírja a `.md` fájlt is
(`AgentSyncService::writeFile()`): slug → fájlnév, név → `title`, system prompt →
törzs. Slug átnevezésekor a régi fájl törlődik; agent törlésekor a fájl is törlődik
(`deleteFile()`). Így a fájlok és a DB mindkét irányban szinkronban maradnak —
nem kell kézzel is átírni a `.md`-t.

## A `tools` mező

Vesszővel elválasztott lista (a fájlban és a formon is). Kétféle elem:
- **Beépített eszközök:** `Read, Grep, Glob, Bash, Write, Edit, TodoWrite, Agent`.
- **MCP eszközök:** `mcp__<szerver>__<tool>` alakban, pl. `mcp__tasks__create-task`,
  `mcp__agents__run-agent`, `mcp__docs__sync-documents`.

A prompt run **csak azokat az MCP szervereket kapja meg**, amelyeket a `tools`
lista beenged (`ClaudePromptRunner::command()`):
- `mcp__tasks__*` → `tasks` szerver (feladatkezelő). A bővebb task-eszközök
  (`list-tasks`, `create-task`, `delete-task`) a `tools` listából jönnek.
- `mcp__agents__*` → `agents` szerver (`run-agent` — más agentek futtatása).
  **Csak az orchestrátor (project-manager) kapja meg**, hogy egy agent ne tudjon
  korlátlanul újabb futásokat indítani.
- `mcp__docs__*` → `docs` szerver (`sync-documents` — a megírt dokumentáció
  betöltése a DB-be).
- **Minden agent-futás megkapja** a `tasks` szervert (a futáshoz kötve) és vele
  a `next-task`, `update-task`, `add-task-comment` eszközt (`TASK_TOOLS`), hogy
  bármely agent felvehesse a következő feladatát, állíthassa a státuszt és
  hozzászólhasson — a `tools` listájától függetlenül.
- A `messages` szervert minden futás megkapja; a `mail`-t csak e-mailt kezelő
  futás; a `projects` szervert prompt run SOHA.

Vagyis a bővebb képességekhez (pl. `create-task`, `run-agent`) **a megfelelő
`mcp__…__…` eszközt kell felvenni a `tools` listába** — a szerver bekötése ebből
következik; a fenti közös task-eszközök viszont minden agentnél alapból ott vannak.

## Ellenőrzés

- A `.md` ⇄ DB kör: `tests/Feature/AgentSeederTest.php`, `tests/Feature/AgentsTest.php`.
- A prompt-run MCP bekötés: `tests/Feature/ClaudePromptRunnerTest.php`.
- Futtatás host-on: `DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_HOST= DB_URL= php artisan test <path>`.
