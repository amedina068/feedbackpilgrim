<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The settings of the instance, for the owner.
 */
class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', [
            'registrationMode' => Registration::mode(),
            'defaultMode' => Registration::defaultMode(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'registration_mode' => ['required', 'string', Rule::in(Registration::MODES)],
        ]);

        Registration::setMode($validated['registration_mode']);

        return redirect('/admin/settings')->with('status', 'The settings are saved.');
    }
}
