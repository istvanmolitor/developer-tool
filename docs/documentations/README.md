# Developer Tool

## Mi ez?

Laravel + Inertia (Vue) alapú fejlesztői eszköz, amely **projekteken futtat `claude -p` promptokat**, opcionálisan egy kiválasztott **agenttel**. A futtatás sorba kerül (queue job), a kimenet, a kilépési kód és a költség (USD) a `prompt_runs` táblába kerül.

Verziók (`composer.json`, `package.json`): PHP ^8.3, Laravel framework ^13.17, Inertia ^3.0 (`inertia-laravel`, `@inertiajs/vue3`), Vue ^3.5.

## Fő funkciók

- **Projektek** – egy projekt egy könyvtár (`name`, `path`, `description`). Az URL-ekben a név a route key (`app/Models/Project.php`, `getRouteKeyName`). A `developer-tool` nevű **rendszerprojekt** (`is_system`) az alkalmazás saját kódbázisa: nem törölhető és az útvonala nem módosítható (modell-események + `ProjectController::update/destroy`).
- **Agentek** – CRUD (`name`, `description`, `model`, `tools`, `system_prompt`, `source_path`). Betölthetők `*.md` fájlokból (YAML front matter + törzs) az `AgentSeeder`-rel.
- **Prompt-futtatás** – egy projekt „prompts" oldalán prompt és/vagy agent választható. Agent esetén prompt nélkül az agent leírása (vagy egy általános kezdő szöveg) lesz a feladat (`ClaudePromptRunner::agentPrompt`). Az oldal az utolsó 50 futtatást listázza.
- **Költség („kreditek")** – a `claude --output-format json` kimenetéből a `total_cost_usd` mentődik a `prompt_runs.cost_usd` mezőbe (null, ha nem JSON a kimenet).
- **Hitelesítés / beállítások** – Fortify alapú auth oldalak (`resources/js/pages/auth`), profil, biztonság, megjelenés (`routes/settings.php`).

## Felépítés

### Útvonalak (`routes/web.php`, auth + verified)

| Útvonal                          | Leírás                                                                |
| -------------------------------- | --------------------------------------------------------------------- |
| `GET /`                          | belépve → dashboard, különben login                                   |
| `GET /dashboard`                 | `Dashboard.vue`                                                       |
| `resource agents`                | `AgentController` (index, create, store, show, edit, update, destroy) |
| `resource projects`              | `ProjectController` (ugyanígy)                                        |
| `GET projects/{project}/prompts` | `projects.prompts` – agentek + legutóbbi 50 futtatás                  |
| `POST projects/{project}/runs`   | `projects.runs.store` – `PromptRunController@store`                   |
| `settings/*`                     | profil, jelszó, security (jelszó-megerősítés kell), appearance        |

Validáció (`app/Http/Requests`): a futtatásnál `prompt` (3–20000 karakter) kötelező, ha nincs `agent_id`; az `agent_id` létező agent kell legyen. Projekt/agent `name` egyedi és nem tartalmazhat `/` vagy `\` jelet.

### Modellek (`app/Models`)

| Modell      | Lényeg                                                                                                                                                                                     |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `Project`   | `is_system`, számított mezők: `exists`, `is_git_repository`, `last_modified_at`; `hasMany PromptRun`                                                                                       |
| `Agent`     | `tools` JSON lista (`Agent::normalizeTools` vesszős stringet is elfogad); `hasMany PromptRun`                                                                                              |
| `PromptRun` | `project_id`, `agent_id`, `user_id` (mind nullable, törléskor null), `prompt`, `status`, `exit_code`, `output`, `error_output`, `cost_usd`, `started_at`, `finished_at`, csak `created_at` |
| `User`      | Fortify felhasználó                                                                                                                                                                        |

Státuszok (`app/Enums/PromptRunStatus.php`): `pending`, `running`, `completed`, `failed`.

### Szolgáltatások, job, parancsok

| Fájl                                                        | Szerep                                                                                                                                                                                                                                                                        |
| ----------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Services/ClaudePromptRunner.php`                       | `queue`/`queueAgent`: `PromptRun` létrehozása + job dispatch; `run`: `claude -p <prompt> --output-format json` futtatása a projekt könyvtárában (`Process`); agent esetén `--agents <json> --agent <slug>` inline definícióval; opcionálisan `--dangerously-skip-permissions` |
| `app/Jobs/RunClaudePrompt.php`                              | queue job (timeout 2100 s, 1 próbálkozás); hiba esetén `failed` státusz                                                                                                                                                                                                       |
| `app/Services/ProjectService.php`                           | projektek könyvtárának kezelése, importálás lemezről                                                                                                                                                                                                                          |
| `app/Services/MarkdownFileReader.php`, `ParsedMarkdown.php` | Markdown + YAML front matter beolvasás                                                                                                                                                                                                                                        |
| `php artisan projects:import`                               | a projects könyvtár még nem regisztrált alkönyvtárait felveszi projektként                                                                                                                                                                                                    |

### Seederek (`database/seeders`)

`DatabaseSeeder` → `UserSeeder` (admin felhasználó; a jelszót lásd a seederben, ne használd éles környezetben), `ProjectSeeder` (rendszerprojekt), `AgentSeeder` (agentek betöltése az `agents_path` alól, `name` alapján upsert).

## Futtatás és konfiguráció

Konfiguráció: `config/developer-tool.php`, minták: `.env.example`.

| Env változó                              | Alapértelmezés                 | Jelentés                                                                            |
| ---------------------------------------- | ------------------------------ | ----------------------------------------------------------------------------------- |
| `DEVELOPER_TOOL_PROJECTS_PATH`           | `/projects`                    | a projektek szülőkönyvtára (PHP/queue folyamat szerint)                             |
| `DEVELOPER_TOOL_PROJECTS_HOST_PATH`      | `../projects`                  | host oldali könyvtár, ami a konténerben `/projects`-be mountolódik (`compose.yaml`) |
| `DEVELOPER_TOOL_AGENTS_PATH`             | `.claude/agents` (base_path)   | agent `*.md` definíciók                                                             |
| `DEVELOPER_TOOL_CLAUDE_BIN`              | `claude`                       | claude futtatható                                                                   |
| `DEVELOPER_TOOL_CLAUDE_SKIP_PERMISSIONS` | `true`                         | `--dangerously-skip-permissions` használata                                         |
| `DEVELOPER_TOOL_CLAUDE_TIMEOUT`          | `1800`                         | egy futtatás max. ideje (mp)                                                        |
| `DEVELOPER_TOOL_CLAUDE_HOME`             | (`.env.example`: `/home/sail`) | `HOME` a claude folyamatnak a hitelesítő adatokhoz                                  |

- **Docker**: Laravel Sail (`compose.yaml`): `laravel.test` (PHP 8.5 runtime), `mysql` 8.4, `redis`. A `~/.claude` és `~/.claude.json` be van mountolva a konténerbe.
- **Queue**: `.env.example` szerint `QUEUE_CONNECTION=database` – a promptok csak futó queue workerrel hajtódnak végre (TODO: ellenőrizni, hogy az `artisan dev` indítja-e a workert).
- **Parancsok** (`composer.json`): `composer setup`, `composer dev`, `composer test` (lint + types check + `php artisan test`).
- **Tesztek**: Pest, `tests/Feature` (pl. `ClaudePromptRunnerTest`, `AgentsTest`, `ProjectsTest`, `PromptRunTest`, `AgentSeederTest`, `MarkdownFileReaderTest`).

> Biztonsági megjegyzés: a `--dangerously-skip-permissions` alapból be van kapcsolva, így a claude felügyelet nélkül módosít fájlokat a projektekben.
