<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        /** @var GoogleProvider $provider */
        $provider = Socialite::driver('google');

        return $provider
            ->scopes(['openid', 'email', 'profile', 'https://www.googleapis.com/auth/calendar.readonly'])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        // Server-side domain restriction — do not trust the client
        $hd = $googleUser->user['hd'] ?? null;
        $email = $googleUser->getEmail() ?? '';
        $emailVerified = $googleUser->user['email_verified'] ?? false;

        if (
            $hd !== 'filter.agency' ||
            ! str_ends_with($email, '@filter.agency') ||
            ! $emailVerified
        ) {
            return redirect()->route('auth.error')
                ->with('error', 'Access is restricted to filter.agency accounts.');
        }

        $user = User::where('google_sub', $googleUser->getId())
            ->orWhere('email', $email)
            ->first();

        if ($user === null) {
            $user = User::create([
                'google_sub' => $googleUser->getId(),
                'email' => $email,
                'name' => $googleUser->getName(),
                'role' => Role::User,
                'is_active' => true,
                'google_access_token' => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken,
                'google_token_expires_at' => now()->addSeconds(max(0, ($googleUser->expiresIn ?? 3600) - 60)),
            ]);
        } else {
            $user->update([
                'google_sub' => $googleUser->getId(),
                'name' => $googleUser->getName(),
                'last_login_at' => now(),
                'google_access_token' => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken ?? $user->google_refresh_token,
                'google_token_expires_at' => now()->addSeconds(max(0, ($googleUser->expiresIn ?? 3600) - 60)),
            ]);
        }

        if (! $user->is_active) {
            return redirect()->route('auth.error')
                ->with('error', 'Your account has been deactivated. Contact an administrator.');
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('timesheet'));
    }
}
