<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    /**
     * Show the application settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('AppSettings/Index', [
            'settings' => [
                'auto_start_tasks' => Setting::flag('auto_start_tasks'),
            ],
        ]);
    }

    /**
     * Persist the application settings.
     */
    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        Setting::setFlag('auto_start_tasks', $request->boolean('auto_start_tasks'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Beállítások mentve.')]);

        return to_route('app-settings.edit');
    }
}
