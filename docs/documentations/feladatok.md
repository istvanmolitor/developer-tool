# Feladatkezelő és agent-üzenetek

## Feladatok

Minden feladat egy projekthez tartozik (`Task`, `app/Models/Task.php`), opcionálisan egy agenthez rendelhető. Mezők: `title`, `description`, `type`, `status`, `priority`, `agent_id`, `due_date`, `position`, `auto_run`.

| Enum | Értékek |
| ---- | ------- |
| `TaskStatus` | `draft` (Piszkozat), `todo` (Teendő), `in_progress` (Folyamatban), `done` (Kész), `closed` (Lezárt) |
| `TaskPriority` | `low`, `medium`, `high` |
| `TaskType` | `development`, `design`, `bug`, `documentation`, `testing`, `other` |

Oldalak (`TaskController`, `routes/web.php`): lista (`projects/{p}/tasks`, a keresőből, szűrhető szövegre, státuszra, prioritásra, típusra, agentre; 20/oldal, `position` szerint), létrehozás, részletek (kommentekkel és változásnaplóval), szerkesztés, törlés. A feladat kereshető (`SearchType::Task`).

### Feladat futtatása („play")

`POST projects/{p}/tasks/{t}/run` → `TaskController::run` → `TaskService::run`: a feladat agentje új kontextusban megkapja a feladatot promptként, a feladat `in_progress` lesz. A prompt (`TaskPromptBuilder`) ma már **minimális**: csak a feladatot nevezi meg (`#id` + projekt) és utasít a teljes kontextus beolvasására a `get-task` eszközzel — a cím, leírás, előzmény és a csatolmányok onnan jönnek. A státusz állítása, naplózás és átadás az agent system promptjából következik (lásd [feladat-atadasi-lanc.md](feladat-atadasi-lanc.md)). Agent nélküli feladat nem indítható (`RuntimeException` / üzenet a felületen).

### Automatikus futtatás

- A feladat `auto_run` mezője jelöli az automatikus indítást. Ha az `auto_start_tasks` beállítás be van kapcsolva (`/app-settings`), minden új feladat `auto_run = true`-val jön létre (`TaskObserver::creating`).
- `php artisan tasks:run-auto` (`RunAutoTasks`; percenként ütemezve, `withoutOverlapping`) elindítja azokat a feladatokat, amelyeknél `auto_run`, van agent és a státusz `todo` (`position` sorrendben). Az indított feladat `in_progress` lesz, így nem indul újra. Működéséhez futnia kell a schedulernek és a queue workernek.

### Kommentek

`TaskComment` (`task_id`, `user_id`, `agent_id`, `body`); `POST projects/{p}/tasks/{t}/comments` (`TaskCommentController`, `StoreTaskCommentRequest`). Agent a `tasks` MCP szerver `add-task-comment` eszközével is ír; futás közben a komment az agent nevén jelenik meg. Kereshető (`SearchType::Comment`).

### Változásnapló

`TaskChange` (`app/Models/TaskChange.php`): a `TaskObserver::updated` naplózza a követett mezők (`status`, `priority`, `type`, `agent_id`, `due_date`) régi és új értékét. A változtatót a bejelentkezett felhasználó, vagy – a `tasks` MCP szerverről, agent-futásból – az agent adja. A projekt összesített naplója: `GET projects/{p}/task-changes` (`TaskChangeController`, 50/oldal), a feladat oldalán a saját története (`TaskChangeFormatter`).

## Agentek közötti üzenetek

`AgentMessage` (`project_id`, `sender_agent_id`, `recipient_agent_id`, `body`, `is_read`, `read_at`), kereshető (`SearchType::Message`). Egy projekten belül az agentek a `messages` MCP szerver eszközeivel (`list-agents`, `send-message`, `list-messages`) kommunikálnak; az olvasott üzenetek olvasottra állnak. A szervert az az agent-futás kapja meg (a futáshoz kötve), amelyik agent eszközlistáján van `mcp__messages__*` bejegyzés (`AgentMcpConfig`). Az emberi felület az `agents/{agent}/messages` oldal (beszélgetések partner + projekt szerint, olvasatlan számlálóval).
