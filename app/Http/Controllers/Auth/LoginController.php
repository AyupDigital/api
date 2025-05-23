<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Sms\OtpLoginCode\UserSms;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('otp')->only('showOtpForm', 'otp');
    }

    /**
     * The user has been authenticated.
     *
     * @return mixed
     */
    protected function authenticated(Request $request, User $user)
    {
        // If OTP is disabled then skip this method.
        if (! config('local.otp_enabled')) {
            return;
        }

        // Log user out.
        $this->guard()->logout();

        // Place the user ID in the session.
        session()->put('otp.user_id', $user->id);

        // Generate and send the OTP code.
        $otpCode = mt_rand(10000, 99999);
        session()->put('otp.code', $otpCode);
        $user->sendSms(new UserSms($user->phone, ['OTP_CODE' => $otpCode]));

        // Forward the user to the code page.
        return redirect('/login/code');
    }

    /**
     * Show the one time password form.
     */
    public function showOtpForm(): View
    {
        $userId = session()->get('otp.user_id');
        $user = User::findOrFail($userId);
        $phoneLastFour = mb_substr($user->phone, -4);

        $email = config('local.global_admin.email');
        $appName = config('app.name');
        $subject = "{$user->full_name} - New Phone Number";
        $subject = rawurlencode($subject);
        $body = <<<EOT
{$user->full_name}:
Requires a new phone number for their account on $appName.
New number: xxxx-xxx-xxxx
EOT;
        $body = rawurlencode($body);
        $newNumberLink = "mailto:$email?subject=$subject&body=$body";

        return view('auth.code', [
            'phoneLastFour' => $phoneLastFour,
            'newNumberLink' => $newNumberLink,
        ]);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws ValidationException
     */
    public function otp(Request $request)
    {
        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendOtpLockoutResponse($request);
        }

        // Validate the OTP code and login if correct.
        if ($request->token == $request->session()->get('otp.code')) {
            $userId = $request->session()->get('otp.user_id');
            $this->guard()->login(User::findOrFail($userId));

            $request->session()->regenerate();

            $this->clearLoginAttempts($request);

            session()->forget(['otp.user_id', 'otp.code']);

            return redirect()->intended($this->redirectPath());
        }

        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->incrementLoginAttempts($request);

        return $this->sendFailedOtpResponse($request);
    }

    /**
     * Redirect the user after determining they are locked out.
     *
     * @throws ValidationException
     */
    protected function sendOtpLockoutResponse(Request $request)
    {
        $seconds = $this->limiter()->availableIn(
            $this->throttleKey($request)
        );

        throw ValidationException::withMessages([
            'token' => [Lang::get('auth.throttle', ['seconds' => $seconds])],
        ])->status(429);
    }

    /**
     * Get the failed login response instance.
     *
     * @throws ValidationException
     */
    protected function sendFailedOtpResponse(Request $request): Response
    {
        throw ValidationException::withMessages([
            'token' => ['The token provided is incorrect.'],
        ]);
    }

    /**
     * Get the throttle key for the given request.
     */
    protected function throttleKey(Request $request): string
    {
        $key = session()->get(
            'otp.user_id',
            Str::lower($request->input($this->username()))
        );

        return $key.'|'.$request->ip();
    }
}
