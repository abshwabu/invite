<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Switch app locale and persist to cookie & authenticated user.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:en,am'],
        ]);

        $locale = $validated['locale'];

        session(['locale' => $locale]);
        app()->setLocale($locale);

        if ($user = $request->user()) {
            $user->update(['locale' => $locale]);
        }

        return redirect()->back()->withCookie(cookie()->forever('locale', $locale));
    }
}
