<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
  public function showRegister()
  {
    return view('auth.register');
  }

  public function register(Request $request)
  {
    $validated = $request->validate([
      'first_name' => ['required', 'string', 'max:100'],
      'last_name' => ['required', 'string', 'max:100'],
      'school_email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
      'school_id' => ['required', 'string', 'max:20', 'unique:users,school_id'],
      'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
    ]);

    $user = User::create([
      'first_name' => $validated['first_name'],
      'last_name' => $validated['last_name'],
      'school_id' => $validated['school_id'],
      'email' => $validated['school_email'],
      'password' => Hash::make($validated['password']),
      'role_type' => 'ORG_OFFICER',
      'account_status' => 'ACTIVE',
    ]);

    Auth::login($user);

    return redirect()
      ->route('login')
      ->with('success', 'Account created successfully.');
  }

  public function showLogin()
  {
    return view('auth.login');
  }

  public function login(Request $request)
  {
    $credentials = $request->validate([
      'email' => ['required', 'string', 'email'],
      'password' => ['required', 'string'],
    ]);

    $remember = (bool) $request->boolean('remember');

    if (!Auth::attempt($credentials, $remember)) {
      return back()
        ->withInput($request->only('email'))
        ->with('error', 'Invalid email or password.');
    }

    $request->session()->regenerate();

    /** @var \App\Models\User $user */
    $user = $request->user();

    if ($user && $user->role_type === 'ORG_OFFICER') {
      return redirect()->route('register-organization');
    }

    return redirect()->route('login')->with('success', 'Logged in successfully.');
  }
}
