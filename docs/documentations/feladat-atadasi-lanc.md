# Feladat-átadási lánc (ki kinek adja tovább)

Ez a dokumentum azt írja le, hogy egy feladat **melyik agenttől melyikhez kerül**,
amíg el nem készül és le nem zárják. A viselkedést az agentek system promptjai
határozzák meg (`.claude/agents/*.md`), az átadás eszközeit a `tasks` MCP szerver
adja (`app/Mcp/Servers/TaskServer.php`).

## A teljes folyamat (pipeline)

```
igény
  → product-owner        (spec)
  → architect            (megvalósítási terv)
  → backend-developer / database-expert / frontend-developer / ui-designer   (megvalósítás)
  → tester               (tesztek)
  → code-reviewer        (kódátnézés)
  → security-reviewer    (biztonsági audit)
  → project-manager      → [Lezárt]
```

Nem minden feladat megy végig minden lépésen — a lánc a feladat jellegéhez
igazodik (pl. egy bug a `debugger`-hez, egy dokumentáció-frissítés a
`documentarian`-hoz kerül). A kiindulópont mindig a **project-manager**, aki a
beérkező igényből feladatot hoz létre és a megfelelő szakértőhöz rendeli
(`.claude/agents/project-manager.md`).

## Ki kinek adja tovább

| Agent | A része kész → következő | Feltétel |
| ----- | ----------------------- | -------- |
| **product-owner** | **architect** | ha technikai terv kell; különben vissza **project-manager** (`done`) |
| **architect** | az első implementáló: **backend-developer** / **frontend-developer** / **database-expert** | a terv szerinti legfontosabb réteg |
| **database-expert** | **backend-developer** | ha a backend ráépül; különben **tester** |
| **backend-developer** | **tester** | a megvalósítás tesztelésre |
| **frontend-developer** | **tester** | a megvalósítás tesztelésre |
| **ui-designer** | **frontend-developer** | a dizájnhoz a klienslogikát a frontendes köti be |
| **debugger** | **tester** | a javítás megerősítésére; vagy vissza **project-manager** |
| **tester** | **code-reviewer** (zöld) · vissza **debugger**/fejlesztő (valós bug) | a tesztfutás eredménye dönt |
| **code-reviewer** | **security-reviewer** (tiszta) · vissza fejlesztő/**debugger** (hiba) | az átnézés eredménye dönt |
| **security-reviewer** | **project-manager** `done` (tiszta) · vissza fejlesztő/**debugger** (sérülékenység) | az audit eredménye dönt |
| **devops** | **project-manager** (`done`) | a lánc vége |
| **documentarian** | **project-manager** (`done`) | a lánc vége |
| **project-manager** | **[Lezárt]** (`closed`) | átnézi a `done` feladatot és lezárja |

> A **support** agent nem része a láncnak: ő a felvételi pont — a beérkező kérésből
> (pl. e-mail) feladatot hoz létre, de nem dolgozik rajta (`.claude/agents/support.md`).

## Hogyan történik az átadás

Minden szakértő agentnek megvan az átadáshoz szükséges eszköze (a tools-listáján:
`mcp__tasks__update-task`, `mcp__agents__list-agents`, `mcp__messages__send-message`).
Az átadás három lépése (lásd az agentek „Átadás a következő agentnek” szakaszát):

1. **Átrendelés** — `update-task` a feladaton `agent: <slug>` a következő agentre; a
   státusz **`in_progress` marad**, amíg a láncban halad.
2. **Naplózás** — `add-task-comment`: mit végzett és mi a következő agent dolga. Minden
   agent **mindig** naplóz a feladat alatt, így a hozzászólásokból követhető, ki mit
   csinált a feladattal.
3. **Értesítés** (opcionális) — `send-message` a következő agentnek.

A lánc végén (nincs értelmes következő szakértő) az agent a feladatot **`done`**
státuszra állítja, és **vissza a `project-manager`-re** rendeli.

## Lezárás — a „Lezárt” (`closed`) státusz

A `closed` (Lezárt) a feladat végső, terminális állapota, és **kizárólag a
project-manager** állíthatja be. A szakértő agentek soha nem zárnak le feladatot;
ők legfeljebb `done`-ra állítják és visszaadják. A project-manager a neki `done`
státusszal visszaadott feladatokat (`list-tasks status=done`) átnézi a
hozzászólások alapján (`list-task-comments`), és ha elkészült, `update-task
status: closed` lépéssel lezárja; ha mégsem teljes, visszarendeli a megfelelő
szakértőre `in_progress` státuszban.

## Státuszok a folyamatban

| Státusz | Jelentés a folyamatban |
| ------- | ---------------------- |
| `todo` (Teendő) | létrejött, még nem indult el |
| `in_progress` (Folyamatban) | a láncban halad, épp egy agentnél van |
| `done` (Kész) | a munka kész, visszaadva a project-managernek jóváhagyásra |
| `closed` (Lezárt) | a project-manager lezárta — végső állapot |

(`draft` is létezik, de a folyamatban nem használt — `app/Enums/TaskStatus.php`.)
A `done` és a `closed` feladatok kiesnek a „nyitott” nézetekből: a `next-task` és az
agent-feladatlista „open” szűrője sem adja vissza őket
(`app/Mcp/Tools/NextTaskTool.php`, `app/Http/Controllers/AgentController.php`).

## A feladat beolvasása — `get-task`

Amikor egy feladat egy agenthez kerül, a prompt már csak a feladatot nevezi meg,
és a `get-task` eszközre utal (`app/Services/TaskPromptBuilder.php`). Az agent a
`get-task`-kal olvassa be a **teljes kontextust**: a feladat adatait, a projektet, a
felelős agentet (és hogy **rá van-e osztva**), a teljes hozzászólás-előzményt, a
változásnaplót és a csatolt fájlokat (`app/Mcp/Tools/GetTaskTool.php`). Ez minden
agentnél elérhető (`mcp__tasks__get-task` a tools-listájukon), így az átadás után a
következő agent a hozzászólásokból látja, mi történt eddig és mi a dolga.

## Kapcsolódó források

- `.claude/agents/*.md` — az agentek system promptjai (az átadási és lezárási szabályok)
- `app/Services/TaskService.php`, `TaskPromptBuilder.php` — a feladat indítása és a prompt
- `app/Mcp/Tools/` — `get-task`, `update-task`, `next-task`, `add-task-comment` eszközök
- `app/Enums/TaskStatus.php` — a státuszok
- Lásd még: [feladatok.md](feladatok.md) (feladatkezelő általában), [projektek-es-agentek.md](projektek-es-agentek.md) (agentek, agent-szinkron).
