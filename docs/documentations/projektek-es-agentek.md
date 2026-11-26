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

Mezők (`agents` tábla): `name`, `slug`, `description`, `model`, `tools` (JSON lista; `AgentToolService::normalize` a vesszős szöveget is elfogadja és a duplikátumokat eldobja), `system_prompt`, `source_path`.

#### Eszközök (`AgentToolService`)

`app/Services/AgentToolService.php` adja az eszközök katalógusát magyar megnevezésekkel, csoportosítva: a Claude Code beépített eszközei (Fájlok, Keresés, Parancsfuttatás, Web, Munkaszervezés) kézzel karbantartott listából, az alkalmazás MCP eszközei (`mcp__<szerver>__<eszköz>`) pedig az MCP szerverek definíciójából, az eszközosztályok `#[Title]` / `#[Description]` attribútumaiból — így új MCP eszköz külön munka nélkül megjelenik.

- `groups()` – a teljes katalógus (az agent űrlap checkboxai ezt kapják `tool_groups` propként); minden csoport `kind` mezője `builtin` (Claude Code saját eszközei) vagy `mcp`.
- `describe($tools)` – egy agent eszközei megjelenítéshez: magyar név, nyers eszköznév, csoport, `known` (katalóguson kívüli eszköz is látszik; a profil ezt kapja `tools` propként).
- `label($tool)` – egy eszköz magyar neve, ismeretlenre a nyers név.
- `normalize($tools)` – a tools érték tisztítása (szöveg vagy lista → lista).

Az agent szerkesztő/létrehozó űrlapja (`AgentForm.vue`) teljes szélességű, és három fülre oszlik:

| Fül | Tartalom |
| --- | -------- |
| Alapadatok | név, azonosító, modell, leírás, system prompt |
| Jogosultságok | a Claude Code saját eszközei checkboxokkal (`kind: builtin`) |
| MCP | az alkalmazás MCP eszközei (`kind: mcp`) és az „Egyéb eszközök” mező a katalóguson kívüli nevekhez; egy MCP szerver csak akkor indul el a futáshoz, ha innen be van jelölve belőle eszköz |

A levelezés (`kind: mailbox`) szándékosan hiányzik a fülekről: a `reply-email` eszközt a postafiók-hozzárendelés adja (lásd [postafiok.md](postafiok.md)), nem az eszközlista — a katalógus csak a magyar nevét tartja nyilván.

A checkboxokat az `AgentToolPicker.vue` rajzolja (csoportonként „Mind” / „Egyik sem”, `v-model` a `form.tools` listára; a nyers eszköznevet a `show-tool-names` kapcsolja — az MCP fülön ki van kapcsolva); a fülek jelvénye a bejelölt eszközök száma, hibás mentésnél a hibás fül nyílik meg. Üres eszközlista = az agent minden eszközt megkap. Az agent maga mindig a nyers eszközneveket kapja (`AgentMcpConfig`, lásd [prompt-futtatas.md](prompt-futtatas.md)).

Az agent szerkesztése/létrehozása a definíciós fájlt is visszaírja (`AgentSyncService::writeFile`), törléskor a fájlt is törli (`deleteFile`); fájlhiba esetén toast jelzi, az adatbázis-változás megmarad (`AgentController::writeDefinition`).

### Agent-kontextus (`AgentContext`)

Egy kontextus = egy projekt + (opcionális) agent + felhasználó + claude `session_id`. Egy beszélgetés futásai ugyanabban a kontextusban folytatják a session-t (`--resume`). `AgentContext::start` új kontextust hoz létre, `current` a felhasználó legutóbbit adja, `startNew` ugyanarra a projekt/agent párra új sessiont indít (`POST agent-contexts/{agentContext}/new`, `AgentContextController`). Lásd [prompt-futtatas.md](prompt-futtatas.md).

## Seederek (`database/seeders`)

`DatabaseSeeder` → `UserSeeder` (admin felhasználó; a jelszót lásd a seederben, éles környezetben ne használd), `ProjectSeeder` (rendszerprojekt), `AgentSeeder` (agentek betöltése az `agents_path` alól, `name` alapján upsert), `MailboxSeeder` (lásd [postafiok.md](postafiok.md)).
