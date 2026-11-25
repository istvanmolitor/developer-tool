# Projektek és agentek

## Projektek

Egy projekt a projektek könyvtárának (`DEVELOPER_TOOL_PROJECTS_PATH`) egy mappája. Az URL-ekben a **név** a route key (`app/Models/Project.php`, `getRouteKeyName`). Mezők: `name`, `path`, `description`, `git_url`, `provision_status`, `provision_error`, `is_system`. A modell számított mezői: `exists`, `is_git_repository`, `last_modified_at`.

### Rendszerprojekt

A `developer-tool` nevű (`Project::SYSTEM_NAME`) projekt az alkalmazás saját kódbázisa (`is_system`). Nem törölhető (modell-esemény, `ProjectController::destroy`), az útvonala nem módosítható (`ProjectController::update` kényszeríti, a modell kivételt dob). Az `is_system` és `provision_status` nem tömegesen kitölthető. A dokumentációját az alkalmazás saját `docs/documentations` mappájából tölti be a rendszer (`DocumentationService::directoryFor`).

### Létrehozási módok

`ProjectService::creationRules` / `createProject`, `StoreProjectRequest`:

| `mode` | Működés | Kezdő `provision_status` |
| ------ | ------- | ------------------------ |
| `existing` | a projektek könyvtárának egy még nem regisztrált mappáját (`directory`) regisztrálja | `ready` |
| `new` | új üres mappa, `name` kötelező | `pending` → `ProvisionProject` job: `mkdir` |
| `git` | repó klónozása a `git_url`-ből; a név elhagyható (a repó nevéből származik); üres link esetén üres mappa | `pending` → `ProvisionProject` job: `git clone` |

Szabályok: a név egyedi, nem tartalmazhat `/` vagy `\` jelet; `new`/`git` esetén a mappa nem létezhet; a git link csak `https://`, `ssh://` vagy `user@host:útvonal` lehet (`ProjectService::isAllowedGitUrl`).

Állapotok (`app/Enums/ProjectProvisionStatus.php`): `ready`, `pending`, `provisioning`, `failed`. A `ProvisionProject` job (`app/Jobs/ProvisionProject.php`, timeout 1200 s, 1 próba) `provisioning` → `ready`; hiba esetén `failed` és a `provision_error` kitöltődik.

`php artisan projects:import` (`ProjectService::importFromDisk`) a lemezen lévő, még nem regisztrált mappákat projektként felveszi.

## Agentek

`AgentController` (resource) + extra oldalak:

| Útvonal | Tartalom |
| ------- | -------- |
| `agents.show` | chat az agenttel: utolsó 20 futtatása és projektenként a felhasználó aktuális kontextusa |
| `agents/{agent}/profile` | modell, eszközök, forrás, system prompt |
| `agents/{agent}/tasks` | az agenthez rendelt feladatok (keresőből; alapból a nem kész feladatok, prioritás szerint) |
| `agents/{agent}/messages` | az agent beszélgetései más agentekkel (partner + projekt szerint), max. 200 üzenet |
| `POST agents/sync` | agentek frissítése a definíciós fájlokból (`AgentSyncService::sync`) |

Mezők (`agents` tábla): `name`, `slug`, `description`, `model`, `tools` (JSON lista; `Agent::normalizeTools` a vesszős szöveget is elfogadja), `system_prompt`, `source_path`.

Az agent szerkesztése/létrehozása a definíciós fájlt is visszaírja (`AgentSyncService::writeFile`), törléskor a fájlt is törli (`deleteFile`); fájlhiba esetén toast jelzi, az adatbázis-változás megmarad (`AgentController::writeDefinition`).

### Agent-kontextus (`AgentContext`)

Egy kontextus = egy projekt + (opcionális) agent + felhasználó + claude `session_id`. Egy beszélgetés futásai ugyanabban a kontextusban folytatják a session-t (`--resume`). `AgentContext::start` új kontextust hoz létre, `current` a felhasználó legutóbbit adja, `startNew` ugyanarra a projekt/agent párra új sessiont indít (`POST agent-contexts/{agentContext}/new`, `AgentContextController`). Lásd [prompt-futtatas.md](prompt-futtatas.md).

## Seederek (`database/seeders`)

`DatabaseSeeder` → `UserSeeder` (admin felhasználó; a jelszót lásd a seederben, éles környezetben ne használd), `ProjectSeeder` (rendszerprojekt), `AgentSeeder` (agentek betöltése az `agents_path` alól, `name` alapján upsert), `MailboxSeeder` (lásd [postafiok.md](postafiok.md)).
