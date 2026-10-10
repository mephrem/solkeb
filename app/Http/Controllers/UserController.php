<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Staff accounts (admin): add, edit, deactivate. Each account has one role.
 */
class UserController extends Controller
{
    public function index()
    {
        return view('users.index', [
            'users' => User::orderByDesc('is_active')->orderBy('role')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['role' => 'receptionist', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $request->merge(['phone' => PhoneNumber::normalize($request->input('phone'))]);
        $data = $request->validate($this->rules() + [
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $user = User::create($data);

        return redirect()->route('users.index')->with('success', "Account created for {$user->name}.");
    }

    public function edit(User $user)
    {
        return view('users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $request->merge(['phone' => PhoneNumber::normalize($request->input('phone'))]);
        $data = $request->validate($this->rules($user) + [
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        if (empty($data['password'])) {
            unset($data['password']);
        }

        // Never lock the hotel out of its own system
        $losingAdmin = $user->role === 'admin' && ($data['role'] !== 'admin' || ! $data['is_active']);
        if ($losingAdmin && User::where('role', 'admin')->where('is_active', true)->count() <= 1) {
            return back()->withInput()->with('error', 'This is the only active admin. Make another admin first.');
        }
        if ($user->is($request->user()) && ! $data['is_active']) {
            return back()->withInput()->with('error', 'You cannot disable your own account.');
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', "{$user->name} updated.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        if ($user->hasHistory()) {
            return back()->with('error', "{$user->name} has handled bookings or payments, so the account can't be deleted. Disable it instead.");
        }
        if ($user->role === 'admin' && $user->is_active && User::where('role', 'admin')->where('is_active', true)->count() <= 1) {
            return back()->with('error', 'This is the only active admin.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', "{$user->name} deleted.");
    }

    private function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20', new PhoneNumber],
            'role' => ['required', Rule::in(User::ROLES)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
