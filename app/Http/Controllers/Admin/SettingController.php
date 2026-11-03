<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        $groups = AppSetting::orderBy('group')->orderBy('id')->get()->groupBy('group');

        return view('content.admin.settings.index', compact('groups'));
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $known = AppSetting::pluck('type', 'key');
        $changed = 0;

        foreach ($request->input('settings', []) as $key => $value) {
            // Only keys that already exist may be written.
            if (! $known->has($key)) {
                continue;
            }

            $value = $this->castForStorage($known[$key], $value);

            AppSetting::where('key', $key)->update(['value' => $value, 'updated_at' => now()]);
            $changed++;
        }


        return back()->with('success', 'Settings saved successfully.');
    }

    private function castForStorage(string $type, mixed $value): string
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'integer' => (string) (int) $value,
            'decimal' => (string) (float) $value,
            default => (string) ($value ?? ''),
        };
    }
}
