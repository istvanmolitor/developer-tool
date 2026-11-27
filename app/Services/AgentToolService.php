<?php

namespace App\Services;

use App\Mcp\Servers\AgentServer;
use App\Mcp\Servers\AttachmentServer;
use App\Mcp\Servers\DocumentServer;
use App\Mcp\Servers\MailServer;
use App\Mcp\Servers\MessageServer;
use App\Mcp\Servers\ProjectServer;
use App\Mcp\Servers\TaskServer;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;

/**
 * The tools an agent may be allowed, with their Hungarian names — Claude Code's
 * built-in tools from a hand-written catalogue, and this application's MCP tools
 * read from the MCP server definitions, so their Hungarian titles stay in sync
 * with the tool classes.
 *
 * The admin UI shows these names (agent profile, agent form); the agent itself
 * only ever sees the raw tool names (see {@see AgentMcpConfig}).
 */
class AgentToolService
{
    /**
     * Claude Code's own tools, and this application's MCP tools — the UI puts the
     * two on separate tabs.
     */
    public const KIND_BUILTIN = 'builtin';

    public const KIND_MCP = 'mcp';

    /**
     * Tools an agent does not get from its tools list at all: the `mail` server's
     * reply tool follows the mailbox assigned to the agent (see
     * {@see AgentMcpConfig::sharedTools()}). The catalogue still names it, so the
     * UI can label it, but the agent form offers no checkbox for it.
     */
    public const KIND_MAILBOX = 'mailbox';

    /**
     * Claude Code's built-in tools, grouped, with their Hungarian names.
     *
     * @var array<string, array{label: string, description: string, tools: array<string, array{label: string, description: string}>}>
     */
    private const BUILTIN_GROUPS = [
        'files' => [
            'label' => 'Fájlok',
            'description' => 'Olvasás és írás a projekt fájljaiban.',
            'tools' => [
                'Read' => ['label' => 'Fájl olvasása', 'description' => 'Egy fájl tartalmának beolvasása (kép és PDF is).'],
                'Write' => ['label' => 'Fájl írása', 'description' => 'Új fájl létrehozása vagy meglévő teljes felülírása.'],
                'Edit' => ['label' => 'Fájl szerkesztése', 'description' => 'Szövegrész cseréje egy meglévő fájlban.'],
                'NotebookEdit' => ['label' => 'Notebook szerkesztése', 'description' => 'Jupyter notebook (.ipynb) celláinak módosítása.'],
            ],
        ],
        'search' => [
            'label' => 'Keresés',
            'description' => 'Fájlok megtalálása név és tartalom szerint.',
            'tools' => [
                'Glob' => ['label' => 'Fájlkeresés név szerint', 'description' => 'Fájlok keresése minta alapján (pl. `**/*.php`).'],
                'Grep' => ['label' => 'Keresés a fájlok tartalmában', 'description' => 'Mintaillesztés a fájlok tartalmára (ripgrep).'],
            ],
        ],
        'shell' => [
            'label' => 'Parancsfuttatás',
            'description' => 'Parancsok futtatása a projekt könyvtárában.',
            'tools' => [
                'Bash' => ['label' => 'Parancs futtatása', 'description' => 'Shell parancsok futtatása a projekt könyvtárában.'],
                'BashOutput' => ['label' => 'Háttérparancs kimenete', 'description' => 'Egy háttérben futó parancs kimenetének olvasása.'],
                'KillShell' => ['label' => 'Háttérparancs leállítása', 'description' => 'Egy háttérben futó parancs leállítása.'],
            ],
        ],
        'web' => [
            'label' => 'Web',
            'description' => 'Tartalom lekérése az internetről.',
            'tools' => [
                'WebFetch' => ['label' => 'Weboldal letöltése', 'description' => 'Egy megadott URL tartalmának lekérése és feldolgozása.'],
                'WebSearch' => ['label' => 'Webes keresés', 'description' => 'Keresés az interneten.'],
            ],
        ],
        'workflow' => [
            'label' => 'Munkaszervezés',
            'description' => 'Az agent saját munkájának szervezése.',
            'tools' => [
                'TodoWrite' => ['label' => 'Teendőlista vezetése', 'description' => 'Az agent a munka közben saját teendőlistát vezet.'],
                'Task' => ['label' => 'Alagent indítása', 'description' => 'Részfeladat kiadása egy alagentnek.'],
            ],
        ],
    ];

    /**
     * This application's MCP servers — handle, Hungarian name and server class —
     * in the order they should appear in the UI. Mirrors `routes/ai.php`.
     *
     * @var array<string, array{label: string, description: string, server: class-string<Server>, kind?: string}>
     */
    private const MCP_GROUPS = [
        'tasks' => [
            'label' => 'Feladatok',
            'description' => 'A developer-tool feladatkezelője.',
            'server' => TaskServer::class,
        ],
        'agents' => [
            'label' => 'Agentek',
            'description' => 'Prompt küldése a többi agentnek és a futások lekérdezése.',
            'server' => AgentServer::class,
        ],
        'messages' => [
            'label' => 'Üzenetek',
            'description' => 'Üzenetváltás egy projekt agentjei között. Minden agent-futás megkapja.',
            'server' => MessageServer::class,
        ],
        'mail' => [
            'label' => 'Levelezés',
            'description' => 'Válasz a postafiókba érkezett levélre. A hozzárendelt postafiók adja, nem az eszközlista.',
            'server' => MailServer::class,
            'kind' => self::KIND_MAILBOX,
        ],
        'docs' => [
            'label' => 'Dokumentumok',
            'description' => 'A projekt docs mappájának betöltése a dokumentumok táblába.',
            'server' => DocumentServer::class,
        ],
        'attachments' => [
            'label' => 'Csatolmányok',
            'description' => 'Csatolt fájlok tárolása és feladatokhoz kötése.',
            'server' => AttachmentServer::class,
        ],
        'projects' => [
            'label' => 'Projektek',
            'description' => 'Projektek regisztrálása és listázása.',
            'server' => ProjectServer::class,
        ],
    ];

    /**
     * The catalogue, once built.
     *
     * @var list<array{key: string, kind: string, label: string, description: string, tools: list<array{name: string, label: string, description: string}>}>|null
     */
    private ?array $groups = null;

    /**
     * Normalize a tools value into a clean list of tool names, accepting either
     * a comma-separated string (as used in the `.md` front matter) or an
     * already-split array, and dropping duplicates. Returns null when nothing
     * remains.
     *
     * @return list<string>|null
     */
    public static function normalize(mixed $tools): ?array
    {
        if (is_string($tools)) {
            $tools = explode(',', $tools);
        }

        if (! is_array($tools)) {
            return null;
        }

        $normalized = array_values(array_unique(array_filter(
            array_map(fn ($tool): string => trim((string) $tool), $tools),
            fn (string $tool): bool => $tool !== '',
        )));

        return $normalized === [] ? null : $normalized;
    }

    /**
     * The whole catalogue, grouped: Claude Code's built-in tools first
     * (`kind: builtin`), then one group per MCP server of this application
     * (`kind: mcp`, except the mailbox-driven `mail` server — see
     * {@see KIND_MAILBOX}). The UI shows the builtin and mcp kinds on tabs of
     * their own.
     *
     * @return list<array{key: string, kind: string, label: string, description: string, tools: list<array{name: string, label: string, description: string}>}>
     */
    public function groups(): array
    {
        if ($this->groups !== null) {
            return $this->groups;
        }

        $groups = [];

        foreach (self::BUILTIN_GROUPS as $key => $group) {
            $groups[] = [
                'key' => $key,
                'kind' => self::KIND_BUILTIN,
                'label' => $group['label'],
                'description' => $group['description'],
                'tools' => array_map(
                    fn (array $tool, string $name): array => [
                        'name' => $name,
                        'label' => $tool['label'],
                        'description' => $tool['description'],
                    ],
                    $group['tools'],
                    array_keys($group['tools']),
                ),
            ];
        }

        foreach (self::MCP_GROUPS as $handle => $group) {
            $groups[] = [
                'key' => "mcp-{$handle}",
                'kind' => $group['kind'] ?? self::KIND_MCP,
                'label' => $group['label'],
                'description' => $group['description'],
                'tools' => $this->mcpTools($handle, $group['server']),
            ];
        }

        return $this->groups = $groups;
    }

    /**
     * Every catalogued tool name.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->index());
    }

    /**
     * The Hungarian name of a tool, falling back to the raw tool name for one
     * that is not in the catalogue (another MCP server's tool, say).
     */
    public function label(string $tool): string
    {
        return $this->index()[$tool]['label'] ?? $tool;
    }

    /**
     * Describe an agent's tools for display: the Hungarian name, the raw tool
     * name the agent actually gets, and the group it belongs to. A tool outside
     * the catalogue is returned with `known: false`, so the UI can still show it.
     *
     * @return list<array{name: string, label: string, description: string|null, group: string|null, known: bool}>
     */
    public function describe(mixed $tools): array
    {
        $index = $this->index();

        return array_map(fn (string $tool): array => [
            'name' => $tool,
            'label' => $index[$tool]['label'] ?? $tool,
            'description' => $index[$tool]['description'] ?? null,
            'group' => $index[$tool]['group'] ?? null,
            'known' => isset($index[$tool]),
        ], self::normalize($tools) ?? []);
    }

    /**
     * The catalogue flattened to a tool name => entry lookup.
     *
     * @return array<string, array{label: string, description: string, group: string}>
     */
    private function index(): array
    {
        $index = [];

        foreach ($this->groups() as $group) {
            foreach ($group['tools'] as $tool) {
                $index[$tool['name']] = [
                    'label' => $tool['label'],
                    'description' => $tool['description'],
                    'group' => $group['label'],
                ];
            }
        }

        return $index;
    }

    /**
     * The tools of one MCP server, named the way Claude Code names them
     * (`mcp__<server>__<tool>`), with the Hungarian title and description the
     * tool class declares.
     *
     * @param  class-string<Server>  $server
     * @return list<array{name: string, label: string, description: string}>
     */
    private function mcpTools(string $handle, string $server): array
    {
        $tools = [];

        foreach ($this->toolClasses($server) as $toolClass) {
            // Through the container: a tool may take its own dependencies.
            $tool = app($toolClass);

            $tools[] = [
                'name' => "mcp__{$handle}__{$tool->name()}",
                'label' => $tool->title(),
                'description' => $tool->description(),
            ];
        }

        return $tools;
    }

    /**
     * The tool classes an MCP server registers, read off the server class without
     * booting it.
     *
     * @param  class-string<Server>  $server
     * @return list<class-string<Tool>>
     */
    private function toolClasses(string $server): array
    {
        $tools = (new ReflectionClass($server))->getDefaultProperties()['tools'] ?? [];

        return is_array($tools) ? array_values($tools) : [];
    }
}
