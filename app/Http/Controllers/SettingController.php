<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Repositories\Contracts\SettingRepository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(private SettingRepository $settings) {}

    /**
     * Show the application settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('AppSettings/Index', [
            'settings' => [
                'auto_start_tasks' => $this->settings->flag('auto_start_tasks'),
                'pause_prompt_runs' => $this->settings->flag('pause_prompt_runs'),
                'daily_cost_limit_usd' => $this->settings->get('daily_cost_limit_usd'),
                'weekly_cost_limit_usd' => $this->settings->get('weekly_cost_limit_usd'),
            ],
        ]);
    }

    /**
     * Persist the application settings.
     */
    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $this->settings->setFlag('auto_start_tasks', $request->boolean('auto_start_tasks'));
        $this->settings->setFlag('pause_prompt_runs', $request->boolean('pause_prompt_runs'));

        foreach (['daily_cost_limit_usd', 'weekly_cost_limit_usd'] as $limit) {
            $value = $request->input($limit);

            // An empty field means "no limit", stored as a missing value.
            $this->settings->set($limit, $value === null || $value === '' ? null : (string) (float) $value);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Beállítások mentve.')]);

        return to_route('app-settings.edit');
    }
}
