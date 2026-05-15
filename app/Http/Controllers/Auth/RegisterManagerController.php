<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RegisterManagerController extends Controller
{
    public function create(): RedirectResponse
    {
        return redirect()->route('register.manager.plan');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('register.manager.plan');
    }
}
