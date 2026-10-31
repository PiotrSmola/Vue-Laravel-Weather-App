<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    // GET /api/user - zalogowany użytkownik wraz z kluczem "settings"
    public function show(Request $request)
    {
        $user = $request->user();

        return array_merge($user->toArray(), ['settings' => $user->settings]);
    }
}
