<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(
        protected SmsGateway $smsGateway
    ) {}

    /**
     * Send an OTP code to an Ethiopian phone number.
     * Throttled to 3 requests per hour per phone number.
     * Expiry is 10 minutes.
     *
     * @return array{phone: string, message: string}
     * @throws ValidationException
     */
    public function sendOtp(string $phone): array
    {
        $normalized = PhoneNormalizer::normalize($phone);

        if (! $normalized) {
            throw ValidationException::withMessages([
                'phone' => __('Please enter a valid Ethiopian mobile number (+2519... or +2517...).'),
            ]);
        }

        // Throttled to 3 per hour per phone
        $recentRequests = OtpCode::where('phone', $normalized)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentRequests >= 3) {
            throw ValidationException::withMessages([
                'phone' => __('Too many OTP requests. Please wait before requesting another code.'),
            ]);
        }

        // Generate 6 digit code
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Invalidate active pending codes by expiring them
        OtpCode::where('phone', $normalized)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        // Save new OTP record (10 minute expiry)
        OtpCode::create([
            'phone' => $normalized,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        // Send SMS
        $message = __("Your Teri verification code is :code. Valid for 10 minutes.", ['code' => $code]);
        $this->smsGateway->send($normalized, $message);

        return [
            'phone' => $normalized,
            'message' => __('Verification code sent to your phone.'),
        ];
    }

    /**
     * Verify OTP code and return the authenticated/created User.
     *
     * @throws ValidationException
     */
    public function verifyOtp(string $phone, string $code): User
    {
        $normalized = PhoneNormalizer::normalize($phone);

        if (! $normalized) {
            throw ValidationException::withMessages([
                'phone' => __('Invalid phone number.'),
            ]);
        }

        $otp = OtpCode::where('phone', $normalized)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $otp || $otp->isExpired()) {
            throw ValidationException::withMessages([
                'code' => __('Verification code has expired or is invalid. Please request a new one.'),
            ]);
        }

        if ($otp->attempts >= 5) {
            throw ValidationException::withMessages([
                'code' => __('Too many incorrect attempts. Please request a new code.'),
            ]);
        }

        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            throw ValidationException::withMessages([
                'code' => __('The verification code is incorrect.'),
            ]);
        }

        $otp->update(['verified_at' => now()]);

        // Find or create User
        $user = User::where('phone', $normalized)->first();

        if ($user) {
            if ($user->is_suspended) {
                throw ValidationException::withMessages([
                    'phone' => __('Your account has been suspended. Please contact support.'),
                ]);
            }

            $user->update([
                'last_login_at' => now(),
            ]);
        } else {
            $user = User::create([
                'name' => 'User ' . substr($normalized, -4),
                'phone' => $normalized,
                'email' => null,
                'password' => null,
                'locale' => session('locale', app()->getLocale() ?? 'en'),
                'country' => GeoIpService::getCountry(request()->ip()),
                'registered_ip' => request()->ip(),
                'registration_source' => request('registration_source') ?? request()->headers->get('referer') ?? 'otp_login',
                'is_suspended' => false,
                'last_login_at' => now(),
            ]);
        }

        return $user;
    }
}
