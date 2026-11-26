# Developer Tool

## Mi ez?

Laravel + Inertia (Vue) alapú fejlesztői eszköz, amely **projekteken futtat `claude -p` promptokat**, opcionálisan egy kiválasztott **agenttel**. A futtatás sorba kerül (queue job), a kimenet, a kilépési kód és a költség (USD) a `prompt_runs` táblába kerül. A prompt-futtatásra épül egy feladatkezelő (automatikus indítással), agentek közötti üzenetküldés, postafiókok (e-mail → agent → válasz), keresés (Elasticsearch), projekt-dokumentáció és több MCP szerver.

Verziók (`composer.json`, `package.json`): PHP ^8.3 (a Sail konténer PHP 8.5, `compose.yaml`), Laravel framework ^13.17, Inertia ^3.0 (`inertia-laravel`, `@inertiajs/vue3`), Vue ^3.5.

## Dokumentumok

| Fájl | Téma |
| ---- | ---- |
| [projektek-es-agentek.md](projektek-es-agentek.md) | projektek (létrehozási módok, provisioning), agentek, agent-szinkron, seederek |
| [prompt-futtatas.md](prompt-futtatas.md) | `ClaudePromptRunner`, queue job, kontextusok (chat), költség, dashboard |
| [feladatok.md](feladatok.md) | feladatok, státuszok/típusok, kommentek, változásnapló, play, auto-run, agentek közti üzenetek |
| [postafiok.md](postafiok.md) | postafiókok, bejövő/kimenő levelek, `mailboxes:fetch` |
| [kereses-es-dokumentacio.md](kereses-es-dokumentacio.md) | Elasticsearch keresés, `search:reindex`, dokumentáció-modul |
| [mcp-szerverek.md](mcp-szerverek.md) | `routes/ai.php` MCP szerverek és eszközeik |

## Fő funkciók

- **Projektek és agentek** – CRUD, rendszerprojekt, agent-definíciók `*.md` fájlokból.
- **Prompt-futtatás és chat** – projekt „prompts” oldal, dashboard chat, folytatható claude session (kontextus).
- **Feladatkezelő** – státuszok, típusok, kommentek, változásnapló, agenthez rendelés, „play” és automatikus futtatás.
- **Üzenetek** – agentek egymásnak írnak projekten belül (`messages` MCP).
- **Postafiókok** – IMAP letöltés, levél átadása az agentnek, válasz (piszkozat vagy azonnali küldés).
- **Keresés** – globális keresés feladatok, dokumentáció, promptok, üzenetek, levelek, kommentek között.
- **Beállítások** – alkalmazásszintű kulcs-érték tár (`settings` tábla). Jelenleg egy kapcsoló van: `auto_start_tasks` (az új feladatok alapból automatikusan indulnak). `SettingController`, `UpdateSettingsRequest`, `app/Models/Setting.php`.
- **Hitelesítés / profil** – Fortify auth oldalak (`resources/js/pages/auth`), profil, jelszó, security (jelszó-megerősítés kell), megjelenés (`routes/settings.php`).

## Felépítés röviden

### Útvonalak

Mind `auth` + `verified` mögött (`routes/web.php`), kivéve `/` (átirányít) és a `settings/*` profil részei (`routes/settings.php`).

| Útvonal | Controller |
| ------- | ---------- |
| `GET /dashboard` | `DashboardController` |
| `GET /search`, `GET /search/suggest` | `SearchController` |
| `GET/PATCH /app-settings` | `SettingController` |
| `resource agents` + `agents/sync`, `{agent}/profile`, `{agent}/tasks`, `{agent}/messages` | `AgentController` |
| `resource mailboxes`, `mailboxes/{m}/fetch`, `…/emails/*`, `…/replies/*` | `MailboxController`, `IncomingEmailController`, `OutgoingEmailController` |
| `resource projects`, `projects/{p}/prompts`, `projects/{p}/runs` | `ProjectController`, `PromptRunController` |
| `POST agent-contexts/{c}/new` | `AgentContextController` |
| `projects/{p}/documentations` (resource + `sync`, `pdf`) | `DocumentationController` |
| `projects/{p}/tasks` (resource + `run`, `comments`), `projects/{p}/task-changes` | `TaskController`, `TaskCommentController`, `TaskChangeController` |

### Modellek (`app/Models`)

`Project`, `Agent`, `AgentContext`, `PromptRun`, `Task`, `TaskComment`, `TaskChange`, `AgentMessage`, `Documentation`, `Mailbox`, `IncomingEmail`, `IncomingEmailAttachment`, `OutgoingEmail`, `Setting`, `User`. A sémát a `database/migrations` írja le.

### Service-ek, jobok, parancsok

| Fájl | Szerep |
| ---- | ------ |
| `app/Services/ClaudePromptRunner.php` | claude futtatás, kontextusok |
| `app/Services/AgentMcpConfig.php` | a futáshoz adott MCP szerverek és extra MCP eszközök |
| `app/Services/AgentToolService.php` | eszköz-katalógus magyar megnevezésekkel (űrlap, profil) |
| `app/Services/TaskService.php`, `TaskPromptBuilder.php`, `TaskChangeFormatter.php` | feladat indítása, prompt, változásnapló megjelenítése |
| `app/Services/MailboxService.php`, `Mail/ImapMailFetcher.php` | levelezés |
| `app/Services/Search/*` | keresés |
| `app/Services/DocumentationService.php` | dokumentáció betöltése lemezről |
| `app/Services/AgentSyncService.php`, `ProjectService.php`, `MarkdownFileReader.php` | agent/projekt kezelés |
| `app/Jobs/RunClaudePrompt.php`, `ProvisionProject.php` | queue jobok |
| `app/Observers/TaskObserver.php` | `auto_run` beállítás, változásnapló |

Artisan parancsok (`app/Console/Commands`):

| Parancs | Leírás | Ütemezés (`routes/console.php`) |
| ------- | ------ | ------------------------------- |
| `projects:import` | a projects könyvtár még nem regisztrált mappái → projektek | – |
| `mailboxes:fetch {mailbox?}` | levelek letöltése (alapból minden aktív postafiók) | naponta, `DEVELOPER_TOOL_MAILBOX_FETCH_TIME` (alapból 06:00, `Europe/Budapest`) |
| `tasks:run-auto` | `auto_run` + agent + `todo` feladatok indítása | percenként |
| `search:reindex` | Elasticsearch index újraépítése | – |

## Futtatás és konfiguráció

Konfiguráció: `config/developer-tool.php`, `config/search.php`; minták: `.env.example`. Titkok (jelszavak) értékét ne commitold.

| Env változó | Alapértelmezés | Jelentés |
| ----------- | -------------- | -------- |
| `DEVELOPER_TOOL_PROJECTS_PATH` | `/projects` | a projektek szülőkönyvtára (PHP/queue folyamat szerint) |
| `DEVELOPER_TOOL_PROJECTS_HOST_PATH` | `../projects` | host oldali könyvtár, ami a konténerben `/projects`-be mountolódik (`compose.yaml`) |
| `DEVELOPER_TOOL_AGENTS_PATH` | `.claude/agents` (base_path) | agent `*.md` definíciók |
| `DEVELOPER_TOOL_CLONE_TIMEOUT` | `900` | `git clone` max. ideje (mp) |
| `DEVELOPER_TOOL_CLAUDE_BIN` | `claude` | claude futtatható |
| `DEVELOPER_TOOL_CLAUDE_SKIP_PERMISSIONS` | `true` | `--dangerously-skip-permissions` használata |
| `DEVELOPER_TOOL_CLAUDE_TIMEOUT` | `1800` | egy futtatás max. ideje (mp) |
| `DEVELOPER_TOOL_CLAUDE_HOME` | (`.env.example`: `/home/sail`) | `HOME` a claude folyamatnak a hitelesítő adatokhoz |
| `DB_QUEUE_RETRY_AFTER` | (`.env.example`: `2200`) | queue újrapróbálás ideje; a job timeout (2100 s) fölött kell lennie |
| `MAILBOX_SERVER_HOST`, `MAILBOX_AIAGENT{1,2,3}_PASSWORD` | – | `MailboxSeeder` host és jelszavak (lásd [postafiok.md](postafiok.md)) |
| `DEVELOPER_TOOL_MAILBOX_FETCH_TIME`, `…_TIMEZONE` | `06:00`, `Europe/Budapest` | napi levélletöltés ideje |
| `SEARCH_DRIVER`, `ELASTICSEARCH_*` | `elasticsearch`, `http://elasticsearch:9200`, `developer_tool` | lásd [kereses-es-dokumentacio.md](kereses-es-dokumentacio.md) |

- **Docker**: Laravel Sail (`compose.yaml`): `laravel.test` (PHP 8.5), `mysql` 8.4, `redis`, `elasticsearch` 8.17.0 (single-node, security kikapcsolva). A projektek könyvtára `/projects`-be, a `~/.claude` és `~/.claude.json` a konténerbe mountolódik.
- **Telepítés**: `composer setup` (install, `.env` másolás, `key:generate`, `migrate`, `npm install`, `npm run build`). Seedelés: `php artisan db:seed` (`DatabaseSeeder`: `UserSeeder`, `ProjectSeeder`, `AgentSeeder`, `MailboxSeeder`).
- **Fejlesztői indítás**: `composer dev` → `php artisan dev` (`dev:list`: `serve`, `queue:listen --tries=1 --timeout=0`, `pail`, `vite`). A **scheduler nincs** köztük: az ütemezett parancsokhoz (`tasks:run-auto`, `mailboxes:fetch`) külön kell futnia a `php artisan schedule:work`-nek (vagy cron `schedule:run`).
- **Queue**: `QUEUE_CONNECTION=database` – queue worker nélkül a promptok és a projekt-provisioning nem fut le.
- **Tesztek**: Pest, `tests/Feature`; `composer test` (pint ellenőrzés + phpstan + `php artisan test`). A `SEARCH_DRIVER=collection` az Elasticsearch nélküli működésre való.

> Biztonsági megjegyzés: a `--dangerously-skip-permissions` alapból be van kapcsolva, így a claude felügyelet nélkül módosít fájlokat a projektekben.
