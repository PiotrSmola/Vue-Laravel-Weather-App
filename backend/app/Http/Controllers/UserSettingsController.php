<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserSettingsController extends Controller
{
    // GET /api/user/settings
    public function show(Request $request)
    {
        return response()->json($request->user()->settings);
    }

    // PUT /api/user/settings - częściowa aktualizacja, zwraca pełne ustawienia
    public function update(Request $request)
    {
        $fields = $request->validate([
            'units' => 'sometimes|required|string|in:metric,imperial',
            'wind_unit' => 'sometimes|required|string|in:ms,kmh,mph',
            'lang' => 'sometimes|required|string|in:pl,en',
            'home_city_id' => 'sometimes|nullable|integer|min:1',
        ]);

        $user = $request->user();
        $user->fill($fields)->save();

        return response()->json($user->fresh()->settings);
    }
}
