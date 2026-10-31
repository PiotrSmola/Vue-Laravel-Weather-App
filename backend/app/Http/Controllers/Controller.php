<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    // Język opisów pogody z parametru ?lang= (pl|en, domyślnie pl)
    protected function lang(Request $request): string
    {
        $request->validate([
            'lang' => 'nullable|in:' . implode(',', config('weather.langs', ['pl', 'en'])),
        ]);

        return $request->query('lang') ?: config('weather.default_lang', 'pl');
    }
}
