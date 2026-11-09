@extends('superadmin.layout')

@section('title', 'Mandatory 2FA Setup')

@section('content')
    <div class="text-center mb-6">
        <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-500/10 text-amber-400 border border-amber-500/30 mb-2">
            First-time Login Requirement
        </span>
        <h2 class="text-lg font-semibold text-white">Setup Two-Factor Authentication</h2>
        <p class="text-xs text-slate-400 mt-1">
            Scan this QR code with Google Authenticator, 1Password, or Authy.
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-950/60 border border-rose-800 text-rose-300 text-xs">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <!-- QR Code Container -->
    <div class="bg-white p-4 rounded-xl flex items-center justify-center mx-auto w-52 h-52 shadow-inner mb-4">
        {!! $qrCodeSvg !!}
    </div>

    <!-- Secret Key Display -->
    <div class="mb-6 p-3 rounded-xl bg-slate-950 border border-slate-800 text-center">
        <span class="text-[10px] uppercase font-semibold text-slate-500 tracking-wider block mb-1">Manual Setup Key</span>
        <code class="text-xs font-mono font-bold text-amber-400 tracking-widest break-all select-all">{{ $secret }}</code>
    </div>

    <!-- 8 Recovery Codes -->
    <div class="mb-6 p-4 rounded-xl bg-slate-950/80 border border-amber-500/20">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider">Emergency Recovery Codes (8)</h3>
            <span class="text-[10px] text-slate-400">Save these now</span>
        </div>
        <p class="text-[11px] text-slate-400 mb-3">
            If you ever lose access to your authenticator device, each code can be used exactly once.
        </p>
        <div class="grid grid-cols-2 gap-2 text-center">
            @foreach ($recoveryCodes as $code)
                <div class="p-1.5 rounded-lg bg-slate-900 border border-slate-800 text-xs font-mono font-semibold text-slate-200 select-all">
                    {{ $code }}
                </div>
            @endforeach
        </div>
    </div>

    <!-- Verification Form -->
    <form method="POST" action="/{{ $secretPath }}/two-factor-setup" class="space-y-4">
        @csrf

        <div>
            <label for="code" class="block text-xs font-medium text-slate-300 mb-1.5 text-center">
                Enter the 6-Digit Code From Your App
            </label>
            <input
                id="code"
                type="text"
                name="code"
                maxlength="6"
                required
                autofocus
                inputmode="numeric"
                autocomplete="one-time-code"
                placeholder="123456"
                class="w-full text-center tracking-[0.5em] text-lg font-mono px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
            />
        </div>

        <button
            type="submit"
            class="w-full py-2.5 px-4 rounded-xl bg-amber-600 hover:bg-amber-500 text-slate-950 font-semibold text-sm shadow-lg shadow-amber-600/20 transition duration-150 ease-in-out"
        >
            Confirm & Enter Control Panel &rarr;
        </button>
    </form>
@endsection
