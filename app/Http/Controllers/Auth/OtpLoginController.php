<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EventDraftImporter;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OtpLoginController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    /**
     * Send OTP verification code to Ethiopian phone number.
     */
    public function send(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'cf-turnstile-response' => ['nullable', new \App\Rules\TurnstileRule],
        ]);

        $result = $this->otpService->sendOtp($validated['phone']);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return back()->with('status', $result['message'])->with('otp_phone', $result['phone']);
    }

    /**
     * Verify code and login / register user.
     */
    public function verify(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
            'draft' => ['nullable', 'array'],
        ]);

        $user = $this->otpService->verifyOtp($validated['phone'], $validated['code']);

        if ($user->is_suspended) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'phone' => __('Your account has been suspended. Please contact support.'),
            ]);
        }

        Auth::login($user, $request->boolean('remember', true));
        $request->session()->regenerate();

        if (! empty($validated['draft'])) {
            EventDraftImporter::importForUser($user, $validated['draft']);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => route('dashboard'),
            ]);
        }

        return redirect()->intended(route('dashboard'));
    }
}
