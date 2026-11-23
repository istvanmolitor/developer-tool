# Developer Tool — Task-kezelés terv

> **Cél:** a fejlesztési munka egysége a **task** (feladat). A task írja le, *mit* kell csinálni; az agentek
> ezeket hajtják végre, a futások (`prompt_runs`) pedig a *hogyan megtörtént* nyomát őrzik. A task köti össze
> a tervezést, a végrehajtást és a review-t egy követhető folyamattá.

Kapcsolódó: [FEATURES.md](FEATURES.md) §2 (Feladat/Issue kezelő), [AGENTS.md](AGENTS.md) (agentek, `prompt_runs.task_id`).

---

## 0. Alapfogalmak

| Fogalom                | Jelentés                                                                                                                                     |
| ---------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| **Task**               | Egy elvégzendő munkadarab egy projekten (cím, leírás, spec, státusz). Ez a központi entitás.                                                 |
| **Hierarchia**         | `Epic → Task → Subtask` — egy nagy cél lebontható kisebb, önállóan végrehajtható darabokra.                                                  |
| **Futás (Run)**        | Egy `prompt_runs` sor, ami egy taskhoz tartozik (`task_id`). Egy task alatt több futás is lehet (pl. fejlesztés, majd review, majd javítás). |
| **Hozzárendelt agent** | A task végrehajtásáért felelős agent (opcionális; indításkor felül is bírálható).                                                            |
| **Függőség**           | Egy task megkezdése más taskok elkészültéhez kötött.                                                                                         |

**Alapelv:** a task a *terv és állapot*, a futás az *elvégzett munka*. Minden task-állapotváltozás nyomot hagy.

---

## 1. Adatmodell

### `tasks` tábla

| Mező                                       | Típus               | Leírás                                                                                                                       |
| ------------------------------------------ | ------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| `id`                                       | pk                  |                                                                                                                              |
| `project_id`                               | FK → `projects`     | Melyik projekthez tartozik.                                                                                                  |
| `parent_task_id`                           | FK → `tasks`, null  | Szülő-feladat (Epic/Task), hierarchiához. `null` = gyökér.                                                                   |
| `type`                                     | enum                | `epic` \| `task` \| `subtask` \| `bug` \| `chore`.                                                                           |
| `title`                                    | string              | Rövid cím.                                                                                                                   |
| `description`                              | text                | Részletes leírás, kontextus.                                                                                                 |
| `spec`                                     | text, null          | Strukturált specifikáció (cél, scope, nem-cél, **elfogadási kritériumok**, érintett fájlok). Az Analyst/Planner agent tölti. |
| `status`                                   | enum                | Lásd §2.                                                                                                                     |
| `priority`                                 | enum                | `low` \| `normal` \| `high` \| `urgent`.                                                                                     |
| `assigned_agent_id`                        | FK → `agents`, null | Alapértelmezett végrehajtó agent (munkakör szerint).                                                                         |
| `created_by`                               | FK → `users`, null  | Ki hozta létre (ember). `null`, ha AI generálta (pl. Planner).                                                               |
| `created_by_agent_id`                      | FK → `agents`, null | Ha agent hozta létre (pl. Log Analyst hibából, Planner bontásból).                                                           |
| `estimate`                                 | int, null           | Becslés (pl. pontszám vagy perc). Az agent javasolhatja.                                                                     |
| `labels`                                   | json                | Címkék (`backend`, `ui`, `tech-debt`…).                                                                                      |
| `position`                                 | int                 | Sorrend a board oszlopában / backlogban.                                                                                     |
| `due_at`                                   | datetime, null      | Határidő (opcionális).                                                                                                       |
| `created_at`, `updated_at`, `completed_at` | datetime            | Idővonal.                                                                                                                    |

### `task_dependencies` tábla (függőségek)

| Mező                 | Leírás                                                |
| -------------------- | ----------------------------------------------------- |
| `task_id`            | A függő task.                                         |
| `depends_on_task_id` | Amire vár (annak `done`-nak kell lennie a kezdéshez). |

> Önhivatkozó M:N — egy task több másikra is várhat. Körfüggőséget a UI/validáció tilt.

### `task_comments` tábla (napló + megjegyzések)

| Mező                                 | Leírás                                                            |
| ------------------------------------ | ----------------------------------------------------------------- |
| `task_id`                            | Melyik taskhoz.                                                   |
| `author_user_id` / `author_agent_id` | Ember vagy agent írta (az egyik kitöltve).                        |
| `body`                               | Szöveg (megjegyzés, állapotváltás indoklása, agent-összefoglaló). |
| `kind`                               | `comment` \| `status_change` \| `run_summary` \| `system`.        |
| `created_at`                         |                                                                   |

### Kapcsolat a futásokkal

A `prompt_runs.task_id` (lásd [AGENTS.md](AGENTS.md) §1) köti a futásokat a taskhoz. Így egy task
oldalán végignézhető a teljes történet: minden agent-futás, diff, eredmény, költség — ki (`user_id`),
melyik agenttel (`agent_id`), milyen modellel (`model`).

```
Project 1───* Task *───1 Agent (assigned_agent_id)
                │  └──* Task (parent_task_id — hierarchia)
                │  └──* TaskDependency (depends_on)
                │  └──* TaskComment (napló)
                └──* PromptRun (task_id — a tényleges futások)
```

---

## 2. Állapotgép (status workflow)

```
  backlog ──> todo ──> in_progress ──> in_review ──> done
    │           │          │               │
    │           │          ▼               ▼
    │           └──────> blocked        changes_requested ──> in_progress
    │                                       
    └──────────────────────────────────> cancelled
```

| Státusz             | Jelentés                                              | Tipikus kiváltó                     |
| ------------------- | ----------------------------------------------------- | ----------------------------------- |
| `backlog`           | Felvéve, de nincs beütemezve.                         | Létrehozás.                         |
| `todo`              | Kész a munkára, függőségei rendben.                   | Priorizálás / függőség feloldódott. |
| `in_progress`       | Egy agent épp dolgozik rajta (fut egy `prompt_runs`). | Végrehajtás indítása.               |
| `in_review`         | A munka kész, review/verify zajlik.                   | Developer befejezte, diff kész.     |
| `changes_requested` | A review hibát talált, javítás kell.                  | Reviewer/QA elvetette.              |
| `blocked`           | Külső akadály / nyitott kérdés / jóváhagyásra vár.    | Függőség, emberi döntés hiánya.     |
| `done`              | Elfogadva, befejezve (`completed_at` kitöltve).       | Jóváhagyott merge.                  |
| `cancelled`         | Elvetve, nem készül el.                               | Ember dönt.                         |

- Az átmeneteket **agent is** kiválthatja (pl. Developer `in_progress → in_review`), de a `done` jellemzően
  emberi/orchestrator jóváhagyáshoz kötött (lásd jogosultságok, [AGENTS.md](AGENTS.md) §5).
- Minden státuszváltás `task_comments` bejegyzést (`status_change`) hoz létre — teljes audit.

---

## 3. Életciklus: tervtől a kész feladatig

A task a teljes fejlesztési folyamatot végigköveti, agentekkel (lásd [AGENTS.md](AGENTS.md) §3):

```
  Ötlet/igény
     │  (Product Analyst agent)
     ▼
  Task + spec + elfogadási kritériumok      ──> status: backlog/todo
     │  (Planner agent)
     ▼
  Lebontás: Epic → Taskok → Subtaskok, függőségekkel
     │  (hozzárendelt Developer agent — soros futtatás)
     ▼
  in_progress  → diff keletkezik (prompt_runs)
     │  (Reviewer / Security / Test / QA agentek, egyesével)
     ▼
  in_review  → finding? ─igen─> changes_requested → vissza in_progress
     │ nem
     ▼  (emberi/orchestrator jóváhagyás)
  done  → Documenter agent frissíti a changelogot/dokumentációt
```

Mivel **nincs párhuzamos futtatás** (lásd [AGENTS.md](AGENTS.md) §4), egy projekten a taskok futásai
egymás után, egyesével hajtódnak végre. A sorrendet a prioritás, a függőségek és a `position` adja.

---

## 4. Task → végrehajtás

| Lépés              | Leírás                                                                                                                                                                                          |
| ------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Indítás**        | A task oldaláról („Végrehajtás" gomb) vagy az orchestrator indít egy futást. A hozzárendelt agent (`assigned_agent_id`) az alapértelmezett, de indításkor másik agent és modell is választható. |
| **Sorba állítás**  | A futás a projekt szekvenciális sorába kerül (queue). Ha már fut valami a projekten, megvárja.                                                                                                  |
| **Kontextus**      | Az agent megkapja: a task `description` + `spec`, a kapcsolódó taskok, a korábbi futások összefoglalója, a projekt kódja és memóriája.                                                          |
| **Eredmény**       | A futás diffet/összefoglalót ad; a task státusza frissül (`in_review`), a `run_summary` bekerül a naplóba.                                                                                      |
| **Visszacsatolás** | `changes_requested` esetén az új futás ugyanahhoz a taskhoz kötődik — a történet egyben marad.                                                                                                  |

**Automatizmus-szintek (konfigurálható):**
- *Kézi:* minden lépést ember indít.
- *Fél-automata:* az orchestrator agent végigviszi a láncot (Developer → Reviewer → …), de a `done`/merge jóváhagyásra vár.
- *Automata:* az orchestrator a `done`-ig viszi, csak a `full` jogú lépéseknél (deploy) kér jóváhagyást.

---

## 5. Feladatbontás (AI-vezérelt)

- A **Planner agent** egy nagy kérésből/epicből lebontott task-fát javasol: Task- és Subtask-címek,
  leírások, függőségek, becslés. Az ember jóváhagyja/szerkeszti, mielőtt létrejönnek.
- A **Product Analyst** a nyers leírásból `spec`-et (elfogadási kritériumokkal) ír a taskhoz.
- A **Log Analyst** egy hibából (`storage/logs`, stacktrace) automatikusan `bug` típusú taskot hoz létre
  reprodukcióval és gyökérok-elemzéssel (`created_by_agent_id` kitöltve).

Ezek mind rendes taskokat hoznak létre a `tasks` táblában — a forrás (ember vs. agent) a `created_by` /
`created_by_agent_id` mezőkből látszik.

---

## 6. UI — task-kezelő felület

- **Kanban board** (`/projects/{project}/tasks`): oszlopok a §2 státuszok szerint, drag&drop, szűrés
  prioritás/címke/típus/agent szerint. Projektenként.
- **Lista / backlog nézet**: rendezés, tömeges műveletek, `position` állítás.
- **Task-részletek**: cím, leírás, `spec` (szerkeszthető), elfogadási kritériumok, függőségek, hozzárendelt
  agent, becslés, címkék. Jobb oldalon az **idővonal**: minden futás (`prompt_runs`), diff, és a `task_comments` napló.
- **Hierarchia-nézet**: Epic kinyitva mutatja a Taskokat/Subtaskokat és a készültséget.
- **„Végrehajtás" gomb**: agent + modell választása, indítás a soros sorba.
- **Függőség-jelzés**: egy `blocked`/várakozó task mutatja, mire vár.

---

## 7. Külső integráció (opcionális, későbbi)

- Kétirányú szinkron **GitHub Issues / Linear / Jira** felé: task ↔ issue megfeleltetés (`external_id`,
  `external_url` mező a `tasks`-on), státusz- és komment-tükrözés.
- Commit-/PR-kapcsolás: a futás által létrehozott commit üzenete hivatkozik a task azonosítójára.

---

## 8. Javasolt megvalósítási sorrend

**1. fázis — Task mint entitás**
- `tasks` tábla (hierarchia, státusz, prioritás) + `task_comments`.
- `prompt_runs.task_id` bekötése (a futás egy taskhoz tartozik).
- Alap UI: lista + task-részletek az idővonallal (futások).

**2. fázis — Board és végrehajtás**
- Kanban board a státusz-workflow-val, drag&drop.
- „Végrehajtás" gomb: task → agent-futás a soros sorba, státusz-automatizmus (`in_progress`/`in_review`).

**3. fázis — Függőségek és bontás**
- `task_dependencies`, `blocked` kezelés.
- Planner/Analyst agentek: AI-feladatbontás és spec-generálás.

**4. fázis — Automatizmus és integráció**
- Orchestrator-vezérelt lánc a taskokon (fél-/teljes automata szintek).
- Külső issue-tracker szinkron.

---

## 9. Nyitott kérdések (döntésre)

- **Elfogadási kritérium ellenőrzése:** a `done`-hoz kötelező-e, hogy a QA/Test agent zölden lefusson, vagy elég az emberi jóváhagyás?
- **Becslés mértékegysége:** story point, óra/perc, vagy T-shirt méret (S/M/L)?
- **Epic-szint futtatható-e?** Csak Task/Subtask indít agent-futást, vagy egy Epic „egyben" is nekiadható egy orchestrator agentnek?
- **Backlog automatizmus:** a `todo`-ba kerülés kézi priorizálás, vagy az agent is átrakhat taskot, ha a függőségek feloldódtak?
- **Több-projektes epic:** egy epic átnyúlhat-e több projektre (vö. FEATURES.md „többprojektes feladat"), vagy a task mindig egy `project_id`-hoz kötött?
