# Developer Tool — Agent-rendszer terv

> **Cél:** minden munkakört egy-egy **definiált agent** lát el. Az agent egy szerepkör (pl. tervező,
> fejlesztő, reviewer), amelynek saját utasításkészlete (system prompt), jogosultsága, eszköz-hozzáférése
> és modell-beállítása van. A felhasználó agenteket definiál, az eszköz pedig lefuttatja és összehangolja őket.

Ez a dokumentum az agentek adatmodelljét, definícióját, katalógusát és együttműködését tervezi meg.
Kapcsolódó: [FEATURES.md](FEATURES.md) — a funkciók, amelyeket ezek az agentek végrehajtanak.

---

## 0. Alapfogalmak

| Fogalom                  | Jelentés                                                                                                                   |
| ------------------------ | -------------------------------------------------------------------------------------------------------------------------- |
| **Agent**                | Egy szerepkör definíciója: név, leírás, system prompt, eszközök, modell, jogosultság. Újrafelhasználható sablon.           |
| **Agent-futás (Run)**    | Egy agent konkrét indítása egy feladaton, egy projekt kontextusában. Megfelel a mai `PromptRun`-nak, de agenthez kötve.    |
| **Munkamenet (Session)** | Egy feladathoz tartozó, több agent-futásból álló, állapotmegőrző folyamat.                                                 |
| **Orchestrator**         | Az a komponens (vagy maga egy agent), amely eldönti, melyik agent mikor fusson, és továbbadja a kimenetet.                 |
| **Munkakör (Role)**      | Az agent "foglalkozása" — ez adja az alapértelmezett promptot és jogosultságot (pl. `architect`, `developer`, `reviewer`). |

**Alapelv:** az agent a *szerep*, a futás az *elvégzett munka*. Egy agentet sokszor futtatunk; minden futás nyomot hagy.

---

## 1. Agent adatmodell

### `agents` tábla (agent-definíció)

| Mező               | Típus          | Leírás                                                                                           |
| ------------------ | -------------- | ------------------------------------------------------------------------------------------------ |
| `id`               | pk             |                                                                                                  |
| `name`             | string, unique | Gép-olvasható azonosító, pl. `backend-developer`.                                                |
| `display_name`     | string         | Ember-olvasható név, pl. „Backend fejlesztő".                                                    |
| `role`             | enum           | Munkakör-kategória (lásd §3).                                                                    |
| `description`      | text           | Mire való, mikor hívjuk.                                                                         |
| `system_prompt`    | text           | Az agent instrukciói — ez a lelke.                                                               |
| `model`            | string         | Az agent alapértelmezett modellje. Pl. `claude-opus-4-8`, `claude-sonnet-5`, `claude-haiku-4-5`. |
| allowed_too`ls`    | json           | Mely eszközöket használhatja (olvasás, írás, bash, git, deploy…).                                |
| `permission_level` | enum           | `read_only` \| `propose` (diffet javasol) \| `auto_commit` \| `full`.                            |
| `enabled`          | bool           | Aktív-e.                                                                                         |
| `is_builtin`       | bool           | Beépített (gyári) vagy felhasználó által létrehozott.                                            |
| `metadata`         | json           | Egyéb: címkék, becsült költség, max futásidő.                                                    |

> **Az agentek globálisak:** minden agent minden projektre érvényes, nincs projekt-kötés. Egy agentet
> egyszer definiálunk, és bármely projekt futtatásánál választható. (Ezért nincs `project_scope` mező.)

### `prompt_runs` tábla (futás) — a **meglévő** tábla bővítése

A futásokat **nem** nevezzük át és **nem** hozunk létre új táblát. A mai `prompt_runs`/`PromptRun`
marad a futás-napló — **ez az a tábla, amely sorról sorra rögzíti, mely promptok futottak le** —,
csak két új oszlopot kap: `agent_id` és `user_id`. Nincs szükség külön "melyik agent futtatta"
leírásra: ez minden sornál magából a `prompt_runs` rekordból (az `agent_id` mezőből, ill. annak
hiányában a nyers promptból) közvetlenül kiderül.

**Új oszlopok:**

| Mező       | Típus               | Leírás                                                                                                                                                   |
| ---------- | ------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `agent_id` | FK → `agents`, null | Melyik agent definíciója alapján futott. `null`, ha agent nélküli nyers prompt.                                                                          |
| `user_id`  | FK → `users`, null  | Melyik felhasználó indította / kinek a nevében futott (lásd lentebb). `null`, ha rendszer/automata futás.                                                |
| `model`    | string              | A futáshoz **ténylegesen használt** modell. Agent-futásnál az agent `model` mezőjéről öröklődik; kézi futtatásnál a felhasználó által választott modell. |

**A `user_id` kitöltési szabálya:**
- A felhasználó **kézzel** futtat egy promptot → `user_id` = az indító felhasználó.
- Egy agent **felhasználó nevében** fut (a felhasználó indította a feladatot/láncot) → `user_id` öröklődik az indítótól.
- Tisztán rendszer által indított futás (ütemezett rutin, orchestrator saját döntése felhasználói kérés nélkül) → `user_id` = `null`.

**A modell kiválasztása:**
- **Agent-futás** → alapból az agent `model` mezője dönt (az a definíció része).
- **Kézi promptfuttatás** → a felhasználó választ modellt az indításkor (pl. egy legördülőből); ez kerül a `prompt_runs.model`-be.
- A ténylegesen használt modellt mindig a `prompt_runs.model` rögzíti (visszakereshetőség, költségszámítás).

**Opcionális, későbbi oszlopok** (a §4/§8 funkciókhoz, nem kötelezők az első körben):
`session_id` (munkamenet), `parent_run_id` (al-futás orchestrációnál), `cost_tokens`.
(A task-kapcsolatot — melyik feladathoz tartozik egy futás — a [TASKS.md](TASKS.md) írja le, nem ez a dokumentum.) A már meglévő `status`, `output`, `exit_code` oszlopok változatlanok.

> Lényeg: a futás-tábla a helyén marad, csak `agent_id` + `user_id` kerül bele. Nincs `agent_runs` átnevezés.

---

## 2. Agent-definíció formátuma

Az agent **az adatbázisban él** — az `agents` tábla az egyetlen igazságforrás. Az agenteket az UI-ból
hozzuk létre és szerkesztjük (CRUD), nem fájlokból töltődnek be. (Opcionális, későbbi kényelmi funkció lehet
egy markdown export/import a megosztáshoz vagy mentéshez, de ez **nem** a forrás — a futtató mindig a DB-ből olvassa az agentet.)

A rekord mezői megfelelnek a §1 `agents` táblájának; markdown-ban ábrázolva egy agent így néz ki
(ugyanezt a struktúrát szerkeszti az UI, pusztán szemléltetésként a Claude Code `.claude/agents/*.md` mintájára):

```markdown
---
name: backend-developer
display_name: Backend fejlesztő
role: developer
model: claude-opus-4-8
permission_level: propose
allowed_tools: [read, write, bash, git]
---

Te egy tapasztalt Laravel backend fejlesztő vagy. A feladatod a megadott spec alapján
megírni/módosítani a kódot a projekt meglévő konvencióit követve.

Mindig:
- Tartsd be a projekt kódstílusát (Pint, PHPStan).
- Írj tesztet az új logikához.
- A végén foglald össze a változást és a kockázatokat.

Soha ne: migrálj adatbázist jóváhagyás nélkül, ne törölj fájlt indoklás nélkül.
```

Az UI pontosan ezeket a mezőket szerkeszti az adatbázisban.

---

## 3. Munkakör-katalógus (gyári agentek)

A §FEATURES.md életciklusához igazítva. Minden munkakör egy vagy több agenttel tölthető ki.

### Tervezés / Design
| Agent                   | Munkakör    | Fő feladat                                             | Jogosultság |
| ----------------------- | ----------- | ------------------------------------------------------ | ----------- |
| **Product Analyst**     | `analyst`   | Ötletből spec, elfogadási kritériumok, visszakérdezés. | read_only   |
| **Architect**           | `architect` | Architektúra-terv, ADR, technológia-választás, séma.   | read_only   |
| **UX Designer**         | `designer`  | UI-vázlat, komponens-terv, design tokenek.             | read_only   |
| **Planner / Tech Lead** | `planner`   | Feladatbontás (epic→task), függőségek, becslés.        | propose     |

### Fejlesztés
| Agent                  | Munkakör    | Fő feladat                                              | Jogosultság           |
| ---------------------- | ----------- | ------------------------------------------------------- | --------------------- |
| **Backend Developer**  | `developer` | Szerveroldali kód a spec alapján.                       | propose / auto_commit |
| **Frontend Developer** | `developer` | UI-kód (Vue/Inertia), komponensek.                      | propose / auto_commit |
| **Database Engineer**  | `developer` | Migrációk, séma, lekérdezés-optimalizálás.              | propose               |
| **Refactorer**         | `developer` | Tisztítás, egyszerűsítés viselkedés-változtatás nélkül. | propose               |

### Minőség / Review
| Agent                | Munkakör   | Fő feladat                                      | Jogosultság |
| -------------------- | ---------- | ----------------------------------------------- | ----------- |
| **Code Reviewer**    | `reviewer` | Hibák, egyszerűsítés, konvenció a diffen.       | read_only   |
| **Security Auditor** | `reviewer` | Sérülékenység-vizsgálat.                        | read_only   |
| **Test Engineer**    | `tester`   | Teszt-írás és -futtatás, coverage.              | propose     |
| **QA / Verifier**    | `tester`   | End-to-end kipróbálás, viselkedés megfigyelése. | propose     |

### Dokumentáció / Üzemeltetés
| Agent                | Munkakör     | Fő feladat                                        | Jogosultság |
| -------------------- | ------------ | ------------------------------------------------- | ----------- |
| **Documentarian**    | `documenter` | README, changelog, architektúra-doc karbantartás. | auto_commit |
| **DevOps / Release** | `operator`   | Build, verziózás, deploy (jóváhagyással).         | propose     |
| **Log Analyst**      | `operator`   | Logelemzés, hibából feladat.                      | read_only   |

### Koordináció
| Agent                 | Munkakör       | Fő feladat                                              | Jogosultság   |
| --------------------- | -------------- | ------------------------------------------------------- | ------------- |
| **Orchestrator / PM** | `orchestrator` | Eldönti, melyik agent mikor fusson; összefűzi a láncot. | full (deleg.) |

> A felhasználó ezeket szabadon módosíthatja, törölheti, vagy újakat hozhat létre. A katalógus csak kiindulás.

---

## 4. Agentek együttműködése (orchestráció)

> **Végrehajtási modell:** **nincs párhuzamos futtatás** — egyszerre csak egy agent fut (szekvenciális sor).
> Az agentek fizikailag ugyanazon a projekt-könyvtáron dolgoznak, ezért soha nincs egyidejű fájlírás vagy ütközés.
> A lenti minták (lánc, fan-out, orchestráció) **logikai** sorrendet írnak le — a futtató ezeket is egyesével,
> egymás után hajtja végre, soha nem párhuzamosan.

### Minták

1. **Egyszeri futás** — egy agent, egy feladat. (Mai működés.)

2. **Lánc (pipeline)** — az egyik agent kimenete a következő bemenete:
   ```
   Analyst → Architect → Planner → Developer → Reviewer → Tester → Documenter
   ```
   Minden lépés után opcionális emberi jóváhagyási pont.

3. **Fan-out (több szempont)** — egy feladat több agenthez, de **egyesével, egymás után** lefuttatva
   (nem fizikai párhuzam), majd az eredmények összefésülve:
   ```
                   ┌─ Code Reviewer
   Developer diff ─┼─ Security Auditor   (soros végrehajtás) → eredmények összefésülése
                   └─ Test Engineer
   ```
   Mivel ezek jellemzően `read_only` elemzések (nem írnak), a sorrend köztük nem számít — csak nem futnak egyszerre.

4. **Orchestrator-vezérelt** — az orchestrator **maga is egy agent** (`orchestrator` munkakör, ugyanúgy az
   `agents` táblában, saját system prompttal). Dinamikusan eldönti, mi a következő lépés, és ő indítja a megfelelő
   szak-agenteket al-futásként (`parent_run_id`-vel láncolva). A kódolt réteg csak a végrehajtást és a jóváhagyási
   kapukat adja; a „ki jön most" döntés az agenté.

5. **Verifikációs hurok** — Reviewer talál hibát → visszaadja a Developernek → újra review, amíg tiszta.

### Átadás (handoff)
Az agentek közötti átadás tartalma strukturált: `{ összefoglaló, diff/branch, nyitott kérdések, következő ajánlott agent }`.
Így a következő agent tiszta kontextust kap, nem a teljes nyers beszélgetést.

---

## 5. Jogosultság és biztonság

| Szint | Mit tehet |
|---|---|
| `read_only` | Olvas, elemez, javasol — nem módosít fájlt. (Reviewer, Analyst, Architect.) |
| `propose` | Módosít egy izolált branchen, de a merge emberi/orchestrator jóváhagyáshoz kötött. |
| `auto_commit` | Commitolhat a saját branchére jóváhagyás nélkül (pl. Documenter). |
| `full` | Mindent, beleértve deploy/törlés — csak kifejezett engedéllyel. |

- **Eszköz-whitelist agentenként:** egy Reviewernek nincs `write`/`git push` joga, egy Analystnak nincs `bash`-e.
- **Soros végrehajtás:** nincs párhuzamos futtatás — egyszerre egy agent fut (szekvenciális sor). Mivel mind
  ugyanazon a projekt-könyvtáron dolgozik fizikailag, ez zárja ki az ütközést/egyidejű fájlírást. Nincs szükség
  párhuzamos branch/worktree izolációra; a `propose` szint diff-jóváhagyása a közös working tree-n történik, egyesével.
- **Audit:** minden futás (agent, prompt, diff, parancs, eredmény) naplózva — ki/mi mit tett.
- **Kill switch:** futó agent leállítható; költség-/időkorlát agentenként.

---

## 6. UI — agent-kezelő felület

- **Agent-lista** (`/agents`): gyári + saját agentek, státusz, munkakör szerint szűrve.
- **Agent-szerkesztő**: név, munkakör, system prompt (nagy szövegmező élő előnézettel), modell, eszközök,
  jogosultság. (Nincs projekt-kötés — minden agent globális.)
- **Agent próba (dry-run)**: egy agent futtatása egy mintafeladaton, hogy lássuk, hogyan viselkedik.
- **Munkamenet-nézet**: egy feladat agent-láncának idővonala (ki futott, mit adott át, hol a jóváhagyási pont).
- **Agent-teljesítmény**: futások száma, átlag költség, sikerarány, visszadobások — melyik agent mennyire megbízható.

---

## 7. Adatmodell-kapcsolatok (összefoglaló)

```
Project 1───* PromptRun *───1 Agent   (agent_id, nullable)
                  │      └───1 User    (user_id, nullable)
                  │
                  ├── session_id ──> Session (feladat agent-lánca)
                  └── parent_run_id ─> PromptRun (orchestráció, al-futás)
```

> A Task-kapcsolatot (melyik futás melyik feladathoz tartozik) a [TASKS.md](TASKS.md) §1 modellezi.

---

## 8. Javasolt megvalósítási sorrend

**1. fázis — Agent mint entitás**
- `agents` tábla + CRUD UI.
- A meglévő `prompt_runs` táblához `agent_id` (FK → `agents`) és `user_id` (FK → `users`) oszlop hozzáadása.
- 3–4 gyári agent: Developer, Reviewer, Documenter, Architect. (`agent_id` = `null` → mai nyers prompt viselkedés.)

**2. fázis — Jogosultság és soros futtatás**
- `permission_level` + eszköz-whitelist betartatása a futtatóban.
- Projektenkénti szekvenciális sor (egyszerre egy agent), diff-jóváhagyás a `propose` szinthez a közös working tree-n.

**3. fázis — Munkamenet és lánc**
- `Session` entitás, kétlépéses kézi lánc (pl. Developer → Reviewer) jóváhagyási pontokkal.

**4. fázis — Orchestrator**
- Dinamikus, PM-agent által vezérelt többlépéses folyamat, párhuzamos fan-out, verifikációs hurok.

---

## 9. Nyitott kérdések (döntésre)

- ~~**Hol az agent-definíció forrása?**~~ **Eldöntve:** az adatbázis (`agents` tábla) az egyetlen igazságforrás, UI-first. (Markdown export/import csak opcionális kényelmi funkció.)
- ~~**Az orchestrator kódolt logika vagy maga is egy agent?**~~ **Eldöntve:** az orchestrator **maga is egy agent** (`orchestrator` munkakör) — ugyanúgy az `agents` táblában él, saját system prompttal. Ő indítja a szak-agenteket al-futásként (`parent_run_id`). A kódolt réteg csak a végrehajtást biztosítja (futtatás, jóváhagyási kapuk), a döntést az agent hozza.
- ~~**Projekt-specifikus vs. globális agentek:**~~ **Eldöntve:** az agentek **globálisak** — minden agent minden projektre érvényes, nincs projekt-kötés (nincs `project_scope`).
- ~~**Modell-választás:**~~ **Eldöntve:** az agentnek beállítható modellje van (`agents.model`), ez az alapértelmezett az agent-futásnál. Kézi promptfuttatásnál a felhasználó választ modellt. A ténylegesen használt modellt a `prompt_runs.model` rögzíti.
- ~~**Párhuzamos futások határa:**~~ **Eldöntve:** **nincs párhuzamos futtatás** — egyszerre csak egy agent dolgozik, a futások szekvenciális sorban futnak. Mivel fizikailag ugyanazon a projekt-könyvtáron dolgoznak, így nem ütköznek (nincs egyidejű fájlírás). Lásd §4.
