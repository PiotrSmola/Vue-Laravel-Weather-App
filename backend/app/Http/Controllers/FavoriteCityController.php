<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Zarządzanie listą ulubionych miast: kolejność (position) i własne nazwy (alias).
 * {cityId} i identyfikatory w kolejności to ID miast w OpenWeather (openweather_id).
 */
class FavoriteCityController extends Controller
{
    /**
     * PUT /api/cities/user/order  {"order": [756135, 759734, ...]}
     * Lista musi zawierać dokładnie wszystkie ulubione miasta użytkownika (bez duplikatów).
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'order' => 'present|array',
            'order.*' => 'integer|min:1',
        ]);

        $order = array_map('intval', $request->input('order', []));
        $user = $request->user();

        // openweather_id => id miasta w bazie
        $favorites = $user->cities()->pluck('cities.id', 'cities.openweather_id')->all();

        $sameSet = count($order) === count(array_unique($order))
            && count($order) === count($favorites)
            && empty(array_diff($order, array_keys($favorites)));

        if (!$sameSet) {
            return response()->json([
                'error' => 'Lista musi zawierać dokładnie wszystkie ulubione miasta',
            ], 422);
        }

        DB::transaction(function () use ($user, $order, $favorites) {
            foreach ($order as $position => $openweatherId) {
                $user->cities()->updateExistingPivot($favorites[$openweatherId], ['position' => $position]);
            }
        });

        return response()->json(['message' => 'Zapisano kolejność ulubionych miast']);
    }

    /**
     * PATCH /api/cities/user/{cityId}  {"alias": "Dom"} - pusty alias lub null usuwa własną nazwę.
     */
    public function update(Request $request, int $cityId)
    {
        $request->validate([
            'alias' => 'present|nullable|string|max:50',
        ]);

        $city = City::where('openweather_id', $cityId)->first();
        $user = $request->user();

        if (!$city || !$user->cities()->where('city_id', $city->id)->exists()) {
            return response()->json(['error' => 'Miasto nie jest w ulubionych'], 404);
        }

        $alias = trim((string) $request->input('alias'));
        $alias = $alias === '' ? null : $alias;

        $user->cities()->updateExistingPivot($city->id, ['alias' => $alias]);

        return response()->json([
            'message' => $alias === null ? 'Usunięto własną nazwę miasta' : 'Zapisano własną nazwę miasta',
            'city_id' => $cityId,
            'alias' => $alias,
        ]);
    }
}
