<?php

namespace App\Http\Middleware\Databackd;

use App\Models\User;
use App\Models\UserAuth;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * AFV (S6 / P9): authentication hardening for upstream AFV endpoints that we must not edit.
 * Registered in the "web" middleware group (app/Http/Kernel.php); acts only on the routes listed below.
 *
 *  - setup_account            : the first-run wizard is closed as soon as any user exists.
 *  - choose_workspace         : the posted user/company must belong to the logged-in identity (UserAuth).
 *  - check_code / resend_code : the e-mail 2FA code endpoints are bound to the login challenge in the session
 *                               (no password-less login for an arbitrary user_id), expire and are throttled.
 *  - 2FA changes              : turning 2FA on/off on the server side requires a recent password confirmation.
 *  - public auth endpoints    : rate limits (login per IP, e-mail check, reset password, 2FA challenge, sign-ups).
 *  - password change/reset    : rotates remember_token so "remember me" cookies elsewhere stop working. This does
 *                               NOT end already-open sessions on other devices - the "web" group has no
 *                               Illuminate\Session\Middleware\AuthenticateSession, which AFV's custom
 *                               impersonation/workspace-switch flows (Auth::loginUsingId bypassing guard->login())
 *                               would need auditing against before it could be added; see docs/mvp/security/findings/P9.md P9-06.
 *
 * NOTE: the IP based limits use $request->ip(); they are only as good as App\Http\Middleware\TrustProxies
 * (currently trusts every proxy - see docs/mvp/security/findings/P9.md, P9-08).
 */
class AuthHardening
{

    /** route name => [bucket, max attempts, decay seconds] - keyed by client IP */
    private const IP_LIMITS = [
        'login.store' => ['login', 30, 60],
        'check_email' => ['check-email', 20, 60],
        'password.email' => ['forgot-password', 5, 60],
        'password.update' => ['reset-password', 10, 60],
        'password.confirm.store' => ['confirm-password', 10, 60],
        'register.store' => ['register', 10, 3600],
        'front.signup.store' => ['company-signup', 10, 3600],
        'front.client-register' => ['client-signup', 10, 3600],
        'accept_invite' => ['accept-invite', 20, 3600],
    ];

    /** a password confirmation (Fortify: auth.password_confirmed_at) must be this fresh to change 2FA */
    private const PASSWORD_CONFIRM_TTL = 600;

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $name = $route ? (string)$route->getName() : '';

        if ($name === '') {
            return $next($request);
        }

        if (isset(self::IP_LIMITS[$name]) && $request->isMethod('POST')) {
            [$bucket, $max, $decay] = self::IP_LIMITS[$name];

            if ($response = $this->throttle($request, $bucket . ':' . $request->ip(), $max, $decay)) {
                return $response;
            }
        }

        switch ($name) {
        case 'setup_account':
            // The wizard creates a super admin and rewrites global settings: only for an empty installation.
            if (User::withoutGlobalScopes()->exists()) {
                return $this->deny($request, 403);
            }

            break;

        case 'superadmin.superadmin.choose_workspace':
            if (!$this->ownsWorkspace($request)) {
                return $this->deny($request, 403);
            }

            break;

        case 'check_code':
        case 'resend_code':
            if ($response = $this->guardEmailTwoFactor($request, $name)) {
                return $response;
            }

            break;

        case 'two-factor.login.store':
            $loginId = $request->session()->get('login.id');

            if ($response = $this->throttle($request, '2fa-user:' . ($loginId ?: 'none') . '|' . $request->ip(), 5, 60)
                ?: $this->throttle($request, '2fa-user:' . ($loginId ?: 'none'), 20, 900)) {
                return $response;
            }

            break;

        case 'two-fa-settings.update':
        case 'two-fa-settings.destroy':
        case 'two-factor.enable':
        case 'two-factor.disable':
            if (!$this->passwordRecentlyConfirmed($request)) {
                return $this->deny($request, 423);
            }

            break;

        case 'user-password.update':
        case 'password.update':
            // AFV (S10): rotate only when the password really changed. A failed guest reset (bad/expired
            // token) is a 302 back with errors as well, so the status code alone would let anyone invalidate a
            // victim's "remember me" just by posting their e-mail address.
            $hashBefore = $this->storedPasswordHash($request, $name);
            $response = $next($request);

            if ($hashBefore !== null && $response->getStatusCode() < 400) {
                $hashAfter = $this->storedPasswordHash($request, $name);

                if ($hashAfter !== null && !hash_equals($hashBefore, $hashAfter)) {
                    $this->rotateRememberToken($request, $name);
                }
            }

            return $response;

        case 'password.email':
            // Let the real side effect happen (an e-mail is sent only for a known, non-throttled account), then
            // always answer the same way so the response itself cannot be used to test whether an address is
            // registered (Laravel's stock Fortify response differs: 200 "sent" vs 422 "we can't find a user ...",
            // and for a JSON request the "not found" case is a thrown ValidationException, not a returned
            // Response - caught here too).
            try {
                $next($request);
            } catch (\Illuminate\Validation\ValidationException $e) {
                // expected for an unregistered / inactive e-mail - fall through to the uniform response
            }

            return $this->uniformPasswordEmailResponse($request);
        }

        return $next($request);
    }

    private function uniformPasswordEmailResponse(Request $request): Response
    {
        $message = __('passwords.sent');

        return $this->wantsJson($request)
            ? response()->json(['status' => 'success', 'message' => $message], 200)
            : back()->with('status', $message);
    }

    /**
     * AFV (S10): the stored password hash of the account a password change/reset targets - the logged-in
     * identity for user-password.update, the posted e-mail for the guest password.update; null when there is none.
     */
    private function storedPasswordHash(Request $request, string $routeName): ?string
    {
        if ($routeName === 'user-password.update') {
            $authId = auth()->guard('web')->id();

            if (!$authId) {
                return null;
            }

            $hash = UserAuth::whereKey($authId)->value('password');
        }
        else {
            $email = $request->input('email');

            if (!is_string($email) || $email === '') {
                return null;
            }

            $hash = UserAuth::where('email', $email)->value('password');
        }

        return is_string($hash) ? $hash : null;
    }

    /**
     * After a successful password change (self-service) or reset (forgot-password), rotate remember_token so
     * any "remember me" cookie issued before the change stops working. Does not invalidate already-open sessions
     * on other devices - see the class docblock.
     */
    private function rotateRememberToken(Request $request, string $routeName): void
    {
        if ($routeName === 'user-password.update') {
            $guard = auth()->guard('web');
            $user = $guard->user();
            $password = $request->input('password');

            if (!$user) {
                return;
            }

            try {
                // AFV (S10): logoutOtherDevices() alone keeps remember_token - rotate it explicitly first;
                // logoutOtherDevices() then re-queues this device's "remember me" cookie with the new token.
                $guard->getProvider()->updateRememberToken($user, Str::random(60));

                if (is_string($password) && $password !== '') {
                    $guard->logoutOtherDevices($password);
                }
            } catch (\Throwable $e) {
                // best effort - never break the already-sent password-change response
            }

            return;
        }

        // password.update (guest "forgot password" reset): no authenticated guard user to key off, update directly
        $email = $request->input('email');

        if (is_string($email) && $email !== '') {
            UserAuth::where('email', $email)->update(['remember_token' => Str::random(60)]);
        }
    }

    /**
     * The workspace switcher logs the request in as the posted user. Allow it only for a "users" row
     * of the current identity and only together with that row's own company.
     */
    private function ownsWorkspace(Request $request): bool
    {
        $authId = auth()->id();
        $userId = $request->input('user_id');
        $companyId = $request->input('company_id');

        if (!$authId) {
            return true; // the "auth" middleware of the route rejects guests
        }

        if (!is_scalar($userId) || !is_scalar($companyId)) {
            return false;
        }

        $user = User::withoutGlobalScopes()->find($userId);

        return $user
            && (int)$user->user_auth_id === (int)$authId
            && (int)$user->company_id === (int)$companyId;
    }

    /**
     * check-code / resend-code belong to the "login with an e-mail code" step after a correct password:
     * the session holds the challenged identity (login.id) and the way to verify (login.authenticate_via).
     */
    private function guardEmailTwoFactor(Request $request, string $name): ?Response
    {
        $loginId = $request->session()->get('login.id');
        $via = $request->session()->get('login.authenticate_via');
        $userId = $request->input('user_id');

        // AFV (upgrade 6.0.19): the upstream challenge form no longer posts user_id (LoginController takes the
        // identity from login.id only) - user_id is optional now, but when it is sent it must still be the challenged one
        if (!$loginId || ($userId !== null && (!is_scalar($userId) || (string)$userId !== (string)$loginId)) || !in_array($via, ['email', 'both'], true)) {
            return $this->deny($request, 403);
        }

        if ($name === 'resend_code') {
            // one mail per minute, five per quarter of an hour
            return $this->throttle($request, 'resend-code:' . $loginId, 1, 60)
                ?: $this->throttle($request, 'resend-code-long:' . $loginId, 5, 900);
        }

        if ($response = $this->throttle($request, 'check-code:' . $loginId, 5, 600)) {
            return $response;
        }

        $userAuth = UserAuth::find($loginId);

        if (!$userAuth || !$userAuth->two_factor_expires_at || $userAuth->two_factor_expires_at->isPast()) {
            $message = __('messages.codeNotMatch');

            return $this->wantsJson($request)
                ? response()->json(['status' => 'fail', 'message' => $message], 422)
                : redirect()->back()->withErrors(['two_factor_code' => $message]);
        }

        return null;
    }

    private function passwordRecentlyConfirmed(Request $request): bool
    {
        if (!auth()->check()) {
            return true; // the "auth" middleware of the route rejects guests
        }

        return (time() - (int)$request->session()->get('auth.password_confirmed_at', 0)) <= self::PASSWORD_CONFIRM_TTL;
    }

    /** Counts the attempt; returns a 429 response when the limit is already used up. */
    private function throttle(Request $request, string $key, int $max, int $decaySeconds): ?Response
    {
        $key = 'databackd-auth:' . sha1($key);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $seconds = RateLimiter::availableIn($key);
            $message = __('auth.throttle', ['seconds' => $seconds]);

            return $this->wantsJson($request)
                ? response()->json(['status' => 'fail', 'message' => $message], 429, ['Retry-After' => $seconds])
                : response($message, 429, ['Retry-After' => $seconds]);
        }

        RateLimiter::hit($key, $decaySeconds);

        return null;
    }

    private function deny(Request $request, int $status): Response
    {
        $message = __('messages.permissionDenied');

        return $this->wantsJson($request)
            ? response()->json(['status' => 'fail', 'message' => $message], $status)
            : response($message, $status);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->ajax();
    }

}
