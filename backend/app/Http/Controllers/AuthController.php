<?php

namespace App\Http\Controllers;

use App\Policies\AdminPolicy;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $d['email'] = strtolower($d['email']);
        if (! Auth::guard('web')->attempt($d + ['role' => 'owner', 'owner_slot' => 1])) {
            throw ValidationException::withMessages(['email' => [__('These credentials do not match our records.')]]);
        }
        if (! app(AdminPolicy::class)->view($r->user())) {
            Auth::guard('web')->logout();
            abort(403);
        }
        $r->session()->regenerate();

        return $this->me($r);
    }

    public function me(Request $r)
    {
        abort_unless(app(AdminPolicy::class)->view($r->user()), 403);

        return $r->user()->only(['id', 'name', 'email', 'role']);
    }

    public function logout(Request $r)
    {
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        Auth::forgetGuards();

        return ['message' => 'Signed out.'];
    }

    public function forgot(Request $r)
    {
        $d = $r->validate(['email' => 'required|email']);
        Password::sendResetLink($d + ['owner_slot' => 1, 'role' => 'owner']);

        return ['message' => 'If that account exists, reset instructions will be sent.'];
    }

    public function reset(Request $r)
    {
        $d = $r->validate(['token' => 'required|string', 'email' => 'required|email', 'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(12)->mixedCase()->numbers()]]);
        $status = Password::reset($d + ['owner_slot' => 1, 'role' => 'owner'], function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return ['message' => 'Password reset.'];
    }

    public function changePassword(Request $r): array
    {
        abort_unless(app(AdminPolicy::class)->view($r->user()), 403);
        $data = $r->validate(['current_password' => ['required', 'current_password:web'], 'password' => ['required', 'confirmed', 'different:current_password', \Illuminate\Validation\Rules\Password::min(12)->mixedCase()->numbers()]]);
        $r->user()->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        DB::table('sessions')->where('user_id', $r->user()->id)->delete();

        return $this->logout($r);
    }
}
