# Developer Tool — Funkcióterv

> **Cél:** a teljes szoftverfejlesztési folyamatot egyetlen felületről menedzselni, ahol az AI
> (Claude) mindent *lát*, mindent *dokumentál*, és a feladatokat *végre is hajtja* — a tervezéstől
> a kódoláson és review-n át a kiszállításig.

Ez a dokumentum a lehetséges funkciókat gyűjti össze és priorizálja. Nem minden épül meg egyszerre;
a lényeg, hogy legyen egy közös kép arról, mivé nőheti ki magát az eszköz.

---

## 0. Alapelvek

1. **Az AI mindent lát.** Minden projekt teljes forráskódja, git története, futási logjai, adatbázis-sémája,
   korábbi promptfuttatásai és dokumentációja elérhető a kontextusában. Nincs vakfolt.
2. **Minden művelet nyomot hagy.** Minden AI-futás (prompt, diff, parancs, eredmény) eltárolódik és
   visszakereshető — ez egyszerre audit-napló és tanulási anyag.
3. **Ember a hurokban (opcionálisan).** A kockázatos műveletek (merge, deploy, törlés) jóváhagyás-kötelesek,
   de konfigurálható, mi fusson automatikusan.
4. **A dokumentáció melléktermék, nem külön munka.** Ami megtörténik, az automatikusan dokumentálódik.
5. **Projekt-agnosztikus.** Az eszköz nem köti ki a nyelvet/keretrendszert; a `projects/` alatti bármely repón működik.

---

## 1. Jelenlegi állapot (kiindulás)

- Projektek DB-ből (`projects` tábla), CRUD felülettel.
- Projekt oldalról prompt beküldése → `PromptRun` → queue job → `claude -p … --dangerously-skip-permissions`.
- Státusz/kimenet/exit-code tárolása, megjelenítése.

A lenti funkciók ezt az alapot bővítik ki teljes fejlesztési platformmá.

---

## 2. Tervezés és specifikáció (Plan)

| Funkció                          | Leírás                                                                                                                                                                                            |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Feladat/Issue kezelő**         | Beépített task-tábla (cím, leírás, státusz, prioritás, címke, felelős, becslés). Az AI tud feladatot létrehozni, bontani, becsülni. Opcionális kétirányú szinkron Linear/Jira/GitHub Issues felé. |
| **Epic → Task → Subtask bontás** | Egy nagy kérésből az AI automatikusan lebontott feladatfát javasol, függőségekkel.                                                                                                                |
| **Specifikáció-generátor**       | Rövid leírásból az AI strukturált spec-et ír (cél, scope, nem-cél, elfogadási kritériumok, érintett fájlok). Ember jóváhagyja, ez lesz a munka alapja.                                            |
| **Roadmap / Milestone nézet**    | Idővonal, mérföldkövek, kanban. Az AI frissíti a státuszokat a tényleges munka alapján.                                                                                                           |
| **"Kérdezz vissza" fázis**       | Munka előtt az AI feltárja a hiányzó döntéseket és kérdéseket tesz fel (nem vakon kezd kódolni).                                                                                                  |

---

## 3. Design és architektúra (Design)

| Funkció                                | Leírás                                                                                                                     |
| -------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| **Architektúra-feltérképezés**         | Az AI a teljes repót átnézve diagramot és leírást készít (modulok, függőségek, adatfolyam). Mermaid diagramok a felületen. |
| **ADR (Architecture Decision Record)** | Döntési naplók generálása és tárolása: mit, miért, milyen alternatívák helyett.                                            |
| **Adatbázis-séma vizualizáció**        | ER-diagram a migrációkból/modellekből; változások hatáselemzése.                                                           |
| **UI/UX terv**                         | Komponens-vázlatok, wireframe-leírás; opcionális Figma-kapcsolat a design tokenek beolvasására.                            |
| **API-szerződés tervezés**             | OpenAPI/endpoint-leírás generálása, előre, a megvalósítás előtt.                                                           |
| **Technológia-javaslat**               | Új funkcióhoz az AI javasol könyvtárat/mintát, a repo meglévő konvencióihoz igazítva.                                      |

---

## 4. Fejlesztés (Build)

| Funkció                         | Leírás                                                                                                                 |
| ------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| **Feladat → kód futtatás**      | A task-ból közvetlenül indítható AI-futás, ami a spec alapján módosítja a kódot.                                       |
| **Diff-előnézet és jóváhagyás** | A futás eredménye git-diffként jelenik meg; soronként átnézhető, elfogadható/elvethető a commit előtt.                 |
| **Soros futtatás (nincs párhuzam)** | Projektenként egyszerre **egy** agent fut; a futások szekvenciális sorban dolgoznak a közös working tree-n, így nem ütköznek. Nincs párhuzamos futtatás, nincs branch/worktree izoláció. |
| **Interaktív munkamenet**       | Nem csak egyszeri `-p` prompt: többfordulós beszélgetés egy feladaton, állapotmegőrzéssel.                             |
| **Parancsfuttató terminál**     | Az AI (jóváhagyással) futtathat tetszőleges parancsot a projektben (artisan, npm, composer, test). Kimenet streamelve. |
| **Sablonok / Scaffolding**      | Ismétlődő minták (CRUD, komponens, endpoint) generátorai, a projekt konvenciói szerint.                                |
| **Többprojektes feladat**       | Egy feladat több repót is érinthet (pl. shared library + fogyasztó app), koordinált futással.                          |

---

## 5. Minőség és review (Review)

| Funkció                      | Leírás                                                                                                            |
| ---------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| **Automatikus kód-review**   | Minden diffre AI-review: hibák, egyszerűsítés, teljesítmény, konvenció-sértés. Súlyozott findingok.               |
| **Security review**          | Sérülékenység-keresés (injection, auth, secret-szivárgás) a változásokon.                                         |
| **Teszt-generálás**          | Hiányzó tesztek írása a módosított kódhoz; coverage-rés jelzése.                                                  |
| **Test runner + eredmények** | Tesztek futtatása, piros/zöld összegzés, hibás tesztek AI-elemzése és javítási javaslat.                          |
| **Lint / statikus analízis** | Pint, PHPStan, ESLint stb. integrálva; az AI maga javítja a jelzett problémákat.                                  |
| **"Verify" fázis**           | A változás tényleges, end-to-end kipróbálása (nem csak teszt): a funkció meghajtása és a viselkedés megfigyelése. |
| **PR-összeállítás**          | Az AI PR-leírást, changelog-bejegyzést és review-összefoglalót generál.                                           |

---

## 6. Dokumentáció (Document)

| Funkció                      | Leírás                                                                                                      |
| ---------------------------- | ----------------------------------------------------------------------------------------------------------- |
| **Élő projekt-dokumentáció** | Az AI karbantartja a README-t, architektúra-leírást, modul-dokumentációt a tényleges kód alapján.           |
| **Automatikus changelog**    | Minden elfogadott változás bekerül a changelogba, ember-olvasható összefoglalóval.                          |
| **Kód-magyarázat on-demand** | Bármely fájl/függvény kijelölve: "mit csinál ez?" — azonnali magyarázat.                                    |
| **Onboarding-guide**         | Új fejlesztőnek/AI-nak szóló belépő dokumentum generálása a repóból.                                        |
| **Döntés- és futásnapló**    | Minden AI-futás promptja, diffje, eredménye kereshető archívumban — "mikor és miért változott ez?".         |
| **Tudásbázis / Memory**      | Projektenkénti tartós memória (konvenciók, döntések, buktatók), amit az AI minden futáskor figyelembe vesz. |

---

## 7. Megfigyelhetőség és üzemeltetés (Operate)

| Funkció                        | Leírás                                                                                                         |
| ------------------------------ | -------------------------------------------------------------------------------------------------------------- |
| **Log-nézegető + AI-elemzés**  | A `storage/logs` és futási logok böngészése; az AI felismeri a hibamintákat és javaslatot ad.                  |
| **Hibából → feladat**          | Egy stacktrace-ből az AI reprodukciós lépést, gyökérok-elemzést és javítási feladatot készít.                  |
| **Deploy / release folyamat**  | Verziózás, release-jegyzék, (jóváhagyással) deploy-parancs futtatása.                                          |
| **Health / státusz dashboard** | Projektenként: git-státusz, utolsó futás, tesztek állapota, nyitott feladatok, build-állapot.                  |
| **Ütemezett AI-feladatok**     | Cron-szerű rutinok: napi dependency-ellenőrzés, teszt-futtatás, dokumentáció-frissítés, "mi változott" riport. |

---

## 8. Platform-szintű funkciók

| Funkció                               | Leírás                                                                                                            |
| ------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| **Teljes rálátás (context)**          | Az AI minden futáskor hozzáfér a repo kódjához, git-történetéhez, sémájához, logjaihoz és a projekt memóriájához. |
| **Audit-napló**                       | Ki/mi, mikor, milyen promptot futtatott, milyen eredménnyel — teljes nyomon követhetőség.                         |
| **Jogosultság / jóváhagyási szintek** | Konfigurálható, mely műveletek futnak automatikusan és melyek igényelnek emberi jóváhagyást.                      |
| **Értesítések**                       | Hosszú futás vége, review-eredmény, hibajelzés → push/e-mail/Slack.                                               |
| **Költség-követés**                   | Token-/futásköltség projektenként és feladatonként.                                                               |
| **Párhuzamos futtatók**               | Több queue worker, feladatok párhuzamosítása, sor-prioritás.                                                      |
| **Keresés mindenben**                 | Globális kereső: kód, feladatok, futások, dokumentáció, logok egy helyen.                                         |
| **Integrációk**                       | Git host (GitHub/GitLab), issue-tracker, chat (Slack), design (Figma), CI.                                        |

---

## 9. A teljes életciklus egy nézetben

```
  ÖTLET ──> SPEC ──> DESIGN ──> FELADATOK
                                   │
                                   ▼
                        ┌──> AI fejleszt (branch) ──> Diff
                        │                               │
                        │                               ▼
                        │        Review + Teszt + Security + Verify
                        │                               │
                        │        (javítás szükséges) ◄──┤
                        │                               │ (ok)
                        │                               ▼
                        │                    Jóváhagyás ──> Merge
                        │                               │
                        └───────────────────────────────┤
                                                         ▼
                              Dokumentáció + Changelog (auto)
                                                         │
                                                         ▼
                                            Release / Deploy
                                                         │
                                                         ▼
                                   Monitoring ──> hiba? ──> új FELADAT
```

Minden nyíl mellett az AI jelen van: lát, dokumentál, végrehajt — az ember a döntési pontokon avatkozik be.

---

## 10. Javasolt megvalósítási sorrend

**1. fázis — Munkafolyamat alap (a jelenlegi futtatóra építve)**
- Feladat/Issue kezelő (§2)
- Diff-előnézet + jóváhagyás + commit (§4)
- Branch-izolált futtatás (§4)
- Futásnapló / audit (§8)

**2. fázis — Minőség**
- Automatikus kód-review és teszt-futtatás (§5)
- Log-nézegető + hibából-feladat (§7)
- Élő dokumentáció + changelog (§6)

**3. fázis — Tervezés és rálátás**
- Spec-generátor és feladatbontás (§2)
- Architektúra-feltérképezés + diagramok (§3)
- Projekt-memória / tudásbázis (§6)

**4. fázis — Üzemeltetés és skálázás**
- Deploy/release folyamat (§7)
- Ütemezett rutinok (§7)
- Integrációk, értesítések, költség-követés (§8)

---

## 11. Nyitott kérdések (döntésre)

- **Jóváhagyási modell:** mennyi fusson teljesen automatikusan vs. emberi jóváhagyással?
- ~~**Izoláció:**~~ **Eldöntve:** nincs párhuzamos futtatás — projektenként egyszerre egy agent dolgozik, soros sorban, a közös working tree-n (nincs branch/worktree/konténer izoláció). Lásd [AGENTS.md](AGENTS.md) §4–§5.
- **Több felhasználó:** egyszemélyes eszköz marad, vagy csapatban használható (jogosultságok, hozzárendelés)?
- **Külső integrációk köre:** melyik issue-tracker / git host / chat legyen elsőként bekötve?
- **Interaktív vs. batch:** a többfordulós munkamenet mennyire prioritás az egyszeri prompthoz képest?
