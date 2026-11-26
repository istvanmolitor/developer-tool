---
name: creating-tasks
description: >-
  Akkor használd, amikor feladatot (taskot) kell felvenni a developer-tool
  feladatkezelőjébe a tasks MCP create-task eszközével — például amikor egy
  beérkező kérésből, e-mailből vagy promptból feladat születik, amikor
  feladatot kell agenthez rendelni, prioritást/típust/határidőt beállítani,
  vagy amikor a feladathoz kapcsolódó csatolmányokat (pl. a levél mellékleteit)
  is rögtön a feladathoz kell kötni. Kiváltó szavak: feladat/task létrehozása,
  create-task, feladat felvétele, feladat csatolmánnyal, feladat agentnek.
---

# Feladat (task) létrehozása

A feladatokat a **tasks MCP `create-task`** eszközével hozd létre. Minden
feladat egy projekthez tartozik. Kódot ettől nem futtatsz — csak felveszed a
teendőt a feladatkezelőbe.

## Mielőtt létrehozod

1. **Projekt neve** — ha nem biztos, hívd a `list-projects` eszközt. A `project`
   paraméter a projekt **neve** (vagy azonosítója).
2. **Agent (opcionális)** — ha a feladatot rögtön egy szakértőhöz kötöd, a
   neveket a `list-agents` adja. A hozzárendelés nem indítja el a futtatást.
3. **Csatolmányok (opcionális)** — ha a feladathoz tartoznak fájlok, szerezd meg
   az azonosítóikat (lásd lentebb), és add át őket egyben a létrehozáskor.

## A create-task paraméterei

| Paraméter | Kötelező | Alapértelmezett | Megjegyzés |
|-----------|----------|-----------------|------------|
| `project` | ✔ | — | A projekt neve vagy azonosítója. |
| `title` | ✔ | — | Tömör, cselekvő cím. |
| `description` | — | — | Részletes leírás, kontextus, elfogadási feltételek. |
| `type` | — | `development` | `development`, `design`, `bug`, `documentation`, `testing`, `other`. |
| `priority` | — | `medium` | `low`, `medium`, `high` — a sürgősség szerint. |
| `status` | — | `todo` | `draft`, `todo`, `in_progress`, `done`. |
| `agent` | — | — | A felelős agent neve. |
| `auto_run` | — | `false` | Ha igaz, automatikus futtatásra jelölt. |
| `due_date` | — | — | Határidő `ÉÉÉÉ-HH-NN` formátumban. |
| `attachment_ids` | — | — | Már létező csatolmányok azonosítóinak listája. |

Csak a `title` és a `project` kötelező; a többinek van értelmes alapértéke.

## Csatolmányok hozzákötése

Ha a feladathoz **kapcsolódó csatolmány** van (például az e-mail mellékletei,
amiből a feladat született), add át az azonosítóikat az `attachment_ids`
mezőben — ekkor a fájlok rögtön a feladathoz kötődnek, nem kell külön lépés.

A csatolmány-azonosítók forrásai:

- **Levélből jött kérés**: a levél mellékletei már el vannak mentve a projekthez
  (forrás: `mail`). Az azonosítókat a `list-attachments` adja a projektre
  szűrve (`project`), vagy — ha már van feladat — `task_id` alapján.
- **Lemezen lévő fájl**: előbb mentsd el a `create-attachment` eszközzel
  (projekt + `file_path`), az visszaadja az új csatolmány azonosítóját.
- **Meglévő csatolmány**: listázd a `list-attachments` eszközzel.

Szabály: minden megadott csatolmánynak a **feladat projektjéhez** (vagy egyik
projekthez sem) kell tartoznia — másik projekt csatolmánya hibát ad, és a
feladat ilyenkor nem jön létre. Érvénytelen azonosító esetén is hiba keletkezik,
feladat nélkül, így nem marad fájl nélküli, félkész feladat.

Ha a csatolmányt csak **később** akarod hozzátenni, használd az `attach-to-task`
eszközt (`task_id` + `attachment_id`).

## Példák

Egyszerű feladat:

```
create-task  project="hr"  title="Szabadság-igénylő űrlap hibájának javítása"
             type="bug"  priority="high"
```

Feladat agentnek, részletes leírással:

```
create-task  project="hr"  title="Új beléptető folyamat megtervezése"
             type="design"  agent="ui-designer"
             description="A HR a toborzási folyamat képernyőit kéri… elfogadási feltételek: …"
```

Feladat a levél mellékleteivel:

```
list-attachments  project="hr"            # → az azonosítók, pl. 12 és 13
create-task  project="hr"  title="Ügyfél által küldött számla feldolgozása"
             attachment_ids=[12, 13]
```

## Jó gyakorlatok

- A **cím** legyen rövid és cselekvő; a részleteket a `description`-be tedd.
- A **prioritást** a tényleges sürgősséghez igazítsd, ne alapból `high`-ra.
- Ha van hozzá fájl, **a létrehozáskor** kösd hozzá az `attachment_ids`-szal,
  ne külön lépésben — így a feladat egyből teljes.
- Csak akkor rendelj **agentet**, ha egyértelmű a felelős; különben hagyd üresen.
