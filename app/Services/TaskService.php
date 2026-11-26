<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Jobs\RunClaudePrompt;
use App\Mcp\Servers\TaskServer;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\SettingRepository;
use App\Repositories\Contracts\TaskRepository;
use RuntimeException;

/**
 * Minden feladat-létrehozás és -módosítás egyetlen belépési pontja.
 *
 * Azért fut itt össze minden írás, hogy az automatikus indítás (auto_run)
 * logikája egy helyen, kézben tartva dőljön el, és NE keletkezzenek hurkok:
 *
 *  - Futást KIZÁRÓLAG az {@see autoRun()} indíthat magától, és csak akkor, ha a
 *    mentés valódi átadás volt (a felelős agent változott, vagy az auto_run most
 *    kapcsolódott be) — a dolgozó agent saját státusz-/komment-módosítása soha
 *    nem indít újra futást, így egy agent nem tudja saját magát körbe-indítani.
 *  - A {@see run()} az in_progress státuszt közvetlenül a repón át írja, kikerülve
 *    az {@see update()}-et, ezért ez az írás sosem lép vissza az autoRun őrbe.
 *  - Az agentek közti „oda-vissza adogatás" ellen a futtatási prompt
 *    ({@see TaskPromptBuilder}) ad fegyelmet; a vészfék a `pause_prompt_runs`
 *    kapcsoló (lásd {@see RunClaudePrompt}).
 */
class TaskService
{
    public function __construct(
        private ClaudePromptRunner $runner,
        private TaskPromptBuilder $prompts,
        private TaskRepository $tasks,
        private SettingRepository $settings,
    ) {}

    /**
     * A create-nél beállítandó auto_run alapértelmezés: a globális „feladatok
     * automatikus indítása" (auto_start_tasks) kapcsoló értéke. Ha be van
     * kapcsolva, az új feladatok auto_run-nal jönnek létre (és agenthez rendelve
     * azonnal indulnak); ha ki, kézi indításra várnak.
     */
    public function getDefaultAutoRun(): bool
    {
        return $this->settings->flag('auto_start_tasks');
    }

    /**
     * Feladat létrehozása a megadott projekt alatt.
     *
     * A feladat-létrehozás egyetlen belépési pontja: a hívó a validált
     * attribútumokat és a tulajdonos projektet adja át, a projekthez kötést
     * pedig itt tesszük rá, hogy egyetlen hívó se írja közvetlenül a modellre.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(Project $project, array $attributes): Task
    {
        // Az auto_run értékét create-nél MINDIG a rendszer alapértelmezése adja
        // (getDefaultAutoRun), függetlenül attól, mit adott át a hívó. Így azt,
        // hogy egy új feladat automatikusan induljon-e, egységesen az
        // „auto_start_tasks" kapcsoló dönti el, nem hívásonként.
        $attributes['auto_run'] = $this->getDefaultAutoRun();

        // A project_id védett (guarded), ezért a feladatot a projekt reláción át
        // állítjuk össze (ez beállítja az idegen kulcsot), a mentést a repó végzi.
        $task = $project->tasks()->make($attributes);

        // A létrehozó rögzítése ugyanazzal a logikával, mint a változásnapló
        // (TaskObserver): ha van bejelentkezett felhasználó, ő a létrehozó;
        // ha a hívás a tasks MCP-ből jön egy agent prompt-futása alatt, az agent.
        $userId = auth()->id();
        $task->created_by_user_id = $userId;
        $task->created_by_agent_id = $userId === null ? TaskServer::currentAgentId() : null;

        $task = $this->tasks->save($task);

        $task->setRelation('project', $project);

        // Egy frissen létrehozott feladat abban a pillanatban átadódik a felelős
        // agentnek, amint agenttel együtt születik meg.
        $this->autoRun($task, handedOff: $task->agent_id !== null);

        return $task;
    }

    /**
     * A megadott attribútumok alkalmazása egy meglévő feladatra és mentés.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Task $task, array $attributes): Task
    {
        $task = $this->tasks->update($task, $attributes);

        // Átadásnak KIZÁRÓLAG az számít, ha a feladat egy (másik) agenthez kerül,
        // azaz az agent_id változik. Az auto_run csak KAPU (lásd autoRun()), nem
        // kiváltó ok: a változását itt szándékosan NEM nézzük — önmagában a
        // be-/kikapcsolása nem indít futást. Minden más módosítás (amit a dolgozó
        // agent a saját feladatán végez: státusz, komment, leírás) érintetlenül
        // hagyja az agent_id-t, ezért nem indít újra futást — így nincs önhurok.
        $this->autoRun($task, handedOff: $task->wasChanged('agent_id'));

        return $task;
    }

    /**
     * Egy már összeállított (új vagy módosított) feladat mentése.
     */
    public function save(Task $task): Task
    {
        return $this->tasks->save($task);
    }

    /**
     * A megadott feladat törlése.
     */
    public function delete(Task $task): void
    {
        $this->tasks->delete($task);
    }

    /**
     * A feladat automatikus átadása az agentjének, ha a feladat engedi (auto_run),
     * és ez a módosítás valódi átadás volt. Ez az EGYETLEN út, ami magától futást
     * indít, ezért az itteni őrfeltételek azok, amik megakadályozzák a hurkot:
     *
     *  - Az auto_run a KAPU: ha false, itt azonnal kilépünk, és bármi is változott,
     *    SOHA nem jön létre prompt/futás.
     *  - A $handedOff csak akkor igaz, ha a feladat egy (másik) agenthez került
     *    (az agent_id változott) — sosem egy státuszváltásra, kommentre vagy más
     *    szerkesztésre, amit a dolgozó agent a saját feladatán végez. Egy agent
     *    tehát nem tudja saját magát újraindítani; futás csak akkor indul, ha a
     *    feladatot EGY MÁSIK agentnek adja át.
     *  - A feladatnak futtatható státuszban kell lennie (nem piszkozat és nem
     *    lezárt), és kell lennie agentnek, aki megkapja.
     *
     * A lenti run() az in_progress státuszt közvetlenül a repón át írja, kikerülve
     * a fenti update()-et, így az az írás sosem lép vissza ebbe az őrbe.
     */
    private function autoRun(Task $task, bool $handedOff): void
    {
        // Kapu: auto_run == false → sosem jön létre prompt, bármi is változott.
        if (! $task->auto_run) {
            return;
        }

        if (! $handedOff || $task->agent_id === null || ! $task->status->isRunnable()) {
            return;
        }

        $this->run($task);
    }

    /**
     * A feladat átadása a hozzárendelt agentnek: beállít egy prompt-futást, ami
     * elvégzi a munkát, és a feladatot in_progress státuszba állítja, hogy a
     * következő kör már ne kapja fel újra. A beállított futást adja vissza.
     *
     * Az opcionális user ahhoz köti a futást, aki elindította (pl. aki a Futtatás
     * gombra kattintott); az automatikus indítás null-t hagy.
     *
     * @throws RuntimeException ha a feladatnak nincs projektje vagy hozzárendelt agentje.
     */
    public function run(Task $task, ?User $user = null): PromptRun
    {
        $project = $task->project;
        $agent = $task->agent;

        if ($project === null || $agent === null) {
            throw new RuntimeException("A(z) #{$task->id} feladatnak nincs projektje vagy hozzárendelt agentje.");
        }

        $run = $this->runner->queueAgent($project, $agent, $this->prompts->build($project, $task), $user);

        $this->tasks->update($task, ['status' => TaskStatus::InProgress]);

        return $run;
    }
}
