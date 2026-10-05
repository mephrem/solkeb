<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * The logged-in staff member changes their own password.
 */
class AccountController extends Controller
{
    public function edit()
    {
        return view('account.edit');
    }

    public function update(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.current_password' => 'Your current password is wrong.',
        ]);

        $request->user()->update(['password' => $request->input('password')]);

        return back()->with('success', 'Password changed.');
    }
}
