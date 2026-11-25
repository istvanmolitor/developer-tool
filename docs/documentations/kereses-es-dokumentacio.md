# Keresés és dokumentáció

## Keresés

`GET /search` (teljes oldal, típus szerint szűrhető, 20/oldal) és `GET /search/suggest` (fejléc-javaslatok, legalább 2 karakter, 8 találat) – `SearchController`, `resources/js/pages/Search/Index.vue`.

Típusok (`app/Enums/SearchType.php`): `task`, `documentation`, `prompt`, `message`, `mail`, `comment` – modellek: `Task`, `Documentation`, `PromptRun`, `AgentMessage`, `IncomingEmail`+`OutgoingEmail`, `TaskComment`.

- `SearchService` (`app/Services/Search`): `SEARCH_DRIVER=elasticsearch` esetén az `ElasticsearchEngine`-t használja; elérhetetlen cluster esetén adatbázisos `CollectionEngine`-re esik vissza, és az eredmény `degraded` jelzést kap (a felületen `search_degraded`). `SEARCH_DRIVER=collection` Elasticsearch nélkül, PHP-ban szűr (tesztekhez, kis helyi környezethez).
- A modellek az `IndexedInSearch` trait-tel mentéskor/törléskor (tranzakció után) frissítik az indexet.
- A feladat-, levél- és agent-feladatlisták is a keresőn keresztül szűrnek.
- Konfiguráció (`config/search.php`): `ELASTICSEARCH_HOST`, `ELASTICSEARCH_USERNAME`, `ELASTICSEARCH_PASSWORD`, `ELASTICSEARCH_INDEX` (alias, alapból `developer_tool`), `ELASTICSEARCH_TIMEOUT`, `ELASTICSEARCH_CONNECT_TIMEOUT`.
- **`php artisan search:reindex`** (`SearchReindex`): új, verziózott indexet épít minden kereshető rekordból, majd átállítja az aliast. Nem Elasticsearch driver mellett nem csinál semmit. Használd első indításkor és kiesés után.

## Dokumentáció-modul

Projektenkénti dokumentumok (`Documentation`: `project_id`, `path`, `title`, `content`; kereshető), `DocumentationController` (resource `projects/{p}/documentations`).

- **Betöltés lemezről**: `POST projects/{p}/documentations/sync` (és a `docs` MCP szerver `sync-documentation` eszköze) → `DocumentationService::syncProject`: a projekt `docs/documentations` mappájának minden `.md` fájlját útvonal szerint upsertálja; a már nem létező fájlok sorait törli, így a tábla a mappát tükrözi. A rendszerprojektnél az alkalmazás saját `base_path()` mappája az alap.
- **Cím**: front matter `title`/`name`, különben az első H1, különben a fájlnév (`resolveTitle`).
- A tartalom Markdown-ból HTML-re renderelődik (`content_html`, nyers HTML eltávolítva).
- `GET projects/{p}/documentations/pdf` – a teljes dokumentáció egyetlen PDF-ben (`barryvdh/laravel-dompdf`, nézet: `documentations.pdf`).
