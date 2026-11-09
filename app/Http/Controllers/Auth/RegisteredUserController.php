<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EventDraftImporter;
use App\Services\GeoIpService;
use App\Services\PhoneNormalizer;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'phone' => 'nullable|string|max:20|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $phone = null;
        if ($request->filled('phone')) {
            $phone = PhoneNormalizer::normalize($request->phone);
            if (! $phone) {
                throw ValidationException::withMessages([
                    'phone' => __('Please enter a valid Ethiopian mobile number (+2519... or +2517...).'),
                ]);
            }
        }

        $source = $request->input('utm_source')
            ?? $request->input('registration_source')
            ?? ($request->headers->get('referer') ? parse_url($request->headers->get('referer'), PHP_URL_HOST) : 'direct');

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $phone,
            'password' => Hash::make($request->password),
            'locale' => session('locale', app()->getLocale() ?? 'en'),
            'country' => GeoIpService::getCountry($request->ip()),
            'registered_ip' => $request->ip(),
            'registration_source' => $source,
            'is_suspended' => false,
            'last_login_at' => now(),
        ]);

        event(new Registered($user));

        Auth::login($user);

        if ($draft = $request->input('draft')) {
            EventDraftImporter::importForUser($user, $draft);
        }

        return redirect(route('dashboard', absolute: false));
    }
}
