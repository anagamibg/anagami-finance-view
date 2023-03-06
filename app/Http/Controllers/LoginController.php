<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Helper\Reply;
use App\Models\Social;
use App\Models\UserAuth;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use App\Events\TwoFactorCodeEvent;
use App\Traits\SocialAuthSettings;
use Froiden\Envato\Traits\AppBoot;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Auth;
use App\Providers\RouteServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use \Illuminate\Validation\ValidationException;

class LoginController extends Controller
{

    use AppBoot, SocialAuthSettings;

    protected $redirectTo = 'account/dashboard';

    public function checkEmail(LoginRequest $request)
    {
        $user = UserAuth::where('email', $request->email)->first();

        if (is_null($user)) {
            throw ValidationException::withMessages([
                Fortify::username() => __('messages.invalidOrInactiveAccount'),
            ]);
        }

        return response([
            'status' => 'success'
        ]);
    }

    public function checkCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = $this->twoFactorLoginUser($request);

        $submittedCode = (string) $request->input('code');
        $storedCode = (string) ($user->two_factor_code ?? '');

        $codeMatches = $storedCode !== ''
            && hash_equals($storedCode, $submittedCode)
            && $user->two_factor_expires_at
            && !$user->two_factor_expires_at->isPast();

        if ($codeMatches) {
            $remember = (bool) $request->session()->get('login.remember');
            $user->resetTwoFactorCode();
            $request->session()->forget(['login.id', 'login.remember', 'login.authenticate_via']);

            Auth::login($user, $remember);

            // WORKSUITESAAS
            if ($user->user && $user->user->is_superadmin) {
                return redirect()->route('superadmin.super_admin_dashboard');
            }

            return redirect()->route('dashboard');
        }

        return redirect()->back()->withErrors(['two_factor_code' => __('messages.codeNotMatch')]);
    }

    public function resendCode(Request $request)
    {
        $user = $this->twoFactorLoginUser($request);

        if (!in_array($user->two_fa_verify_via, ['email', 'both'], true)) {
            abort(403);
        }

        $user->generateTwoFactorCode();
        event(new TwoFactorCodeEvent($user->user));

        return Reply::success(__('messages.codeSent'));
    }

    /**
     * Resolve the auth user only from a password-verified 2FA login session.
     */
    protected function twoFactorLoginUser(Request $request): UserAuth
    {
        $sessionUserId = $request->session()->get('login.id');

        if (!$sessionUserId) {
            abort(403);
        }

        // Ignore client-supplied user_id; only the post-password session may identify the user.
        $user = UserAuth::where('id', $sessionUserId)->firstOrFail();

        if ($user->two_fa_verify_via === '' || $user->two_fa_verify_via === null) {
            abort(403);
        }

        return $user;
    }

    public function redirect($provider)
    {
        $this->setSocailAuthConfigs();

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, $provider)
    {
        $this->setSocailAuthConfigs();

        try {
            try {
                if ($provider != 'twitter' && $provider != 'linkedin') {
                    $data = Socialite::driver($provider)->stateless()->user();
                    /* @phpstan-ignore-line */
                }
                elseif ($provider == 'twitter') {
                    $data = Socialite::driver('twitter-oauth-2')->user(); /* @phpstan-ignore-line */
                }
                elseif ($provider == 'linkedin') {
                    $data = Socialite::driver('linkedin-openid')->user(); /* @phpstan-ignore-line */
                }
                else {
                    $data = Socialite::driver($provider)->user();
                }
            } catch (Exception $e) {

                return redirect()->route('login')->with(['message' => $e->getMessage()]);
            }

            if ($provider == 'twitter') {
                $user = UserAuth::where(['twitter_id' => $data->id])->first();
            }
            else {
                $user = UserAuth::where(['email' => $data->email])->first();
            }

            if (!$user) {
                return redirect()->route('login')->with(['message' => __('messages.unAuthorisedUser')]);
            }

            if ($user->status === 'deactive') {
                return redirect()->route('login')->with(['message' => __('auth.failedBlocked')]);
            }

            if ($user->login === 'disable') {
                return redirect()->route('login')->with(['message' => __('auth.failedLoginDisabled')]);
            }

            // User found
            DB::beginTransaction();

            Social::updateOrCreate(['user_id' => $user->id], [
                'social_id' => $data->id,
                'social_service' => $provider,
            ]);

            DB::commit();

            Auth::login($user, true);

            return redirect()->intended($this->redirectPath());

        } catch (Exception $e) {

            return redirect()->route('login')->with(['message' => $e->getMessage()]);
        }
    }

    public function redirectPath()
    {

        if (isWorksuiteSaas()) {
            session(['user' => User::find(user()->id)]);

            if (auth()->user() && auth()->user()->user->is_superadmin) {
                return (session()->has('url.intended') ? session()->get('url.intended') : RouteServiceProvider::SUPER_ADMIN_HOME);
            }

            $emailCountInCompanies = DB::table('users')->where('email', user()->email)->count();
            session()->forget('user_company_count');

            if ($emailCountInCompanies > 1) {
                if (module_enabled('Subdomain')) {
                    UserAuth::multipleUserLoginSubdomain();
                }
                else {
                    session(['user_company_count' => $emailCountInCompanies]);

                    return route('superadmin.superadmin.workspaces');
                }

            }

            return (session()->has('url.intended') ? session()->get('url.intended') : RouteServiceProvider::HOME);
        }

        if (method_exists($this, 'redirectTo')) {
            return $this->redirectTo();
        }

        return property_exists($this, 'redirectTo') ? $this->redirectTo : '/login';
    }

    public function username()
    {
        return 'email';
    }

}
