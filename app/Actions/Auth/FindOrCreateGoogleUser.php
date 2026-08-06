<?php

namespace App\Actions\Auth;

use App\Actions\Fortify\CreatesProviderProfile;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Finds or creates the local account behind a Google sign-in.
 *
 * Matches by email only — Google already proved ownership of it, which is at
 * least as strong a proof as this app's own signed verification link. So an
 * existing account that never clicked that link gets verified here instead
 * of staying stuck, and a brand new one skips the email-verification step
 * entirely.
 */
class FindOrCreateGoogleUser
{
    public function __construct(private readonly CreatesProviderProfile $providerProfiles) {}

    public function handle(SocialiteUser $googleUser): User
    {
        $email = Str::lower(trim((string) $googleUser->getEmail()));

        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return $user;
        }

        return DB::transaction(function () use ($googleUser, $email): User {
            $name = $googleUser->getName() ?: Str::before($email, '@');

            $user = User::create([
                'name' => $name,
                'username' => $this->uniqueUsername($email),
                'email' => $email,
                'phone' => null,
                // No password yet: this account exists solely because Google
                // vouched for it. The user sets one in the next step, and
                // is_null($user->password) is how the rest of the app knows
                // whether that has happened.
                'password' => null,
            ]);

            // Not inside create(): email_verified_at isn't in User's
            // #[Fillable(...)], so create() would either silently drop it
            // (in production) or throw (everywhere else, since
            // preventSilentlyDiscardingAttributes is on outside production).
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->providerProfiles->create($user, $name, $user->username);

            event(new Registered($user));

            return $user;
        });
    }

    /**
     * Google gives no username, and this app's is required, unique, and
     * limited to `[A-Za-z0-9._]`. Derived from the email's local part,
     * sanitised and truncated to leave room for a disambiguating suffix
     * within the column's max:30.
     */
    private function uniqueUsername(string $email): string
    {
        $base = Str::lower(preg_replace('/[^A-Za-z0-9._]/', '', Str::before($email, '@')) ?? '');
        $base = strlen($base) >= 3 ? substr($base, 0, 24) : 'provider';

        $username = $base;
        $suffix = 1;

        while (User::where('username', $username)->exists()) {
            $username = "{$base}-".++$suffix;
        }

        return $username;
    }
}
