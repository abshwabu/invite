@extends('superadmin.layout')

@section('title', 'Sign In')

@section('content')
    <h2 class="text-lg font-semibold text-white mb-2">Sign in to Management</h2>
    <p class="text-xs text-slate-400 mb-6">Enter your administrator credentials to proceed to 2FA verification.</p>

    @if (session('status'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-950/60 border border-emerald-800 text-emerald-300 text-xs">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-950/60 border border-rose-800 text-rose-300 text-xs space-y-1">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="/{{ $secretPath }}/login" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-xs font-medium text-slate-300 mb-1.5">Email Address</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                placeholder="superadmin@invite.et"
            />
        </div>

        <div>
            <label for="password" class="block text-xs font-medium text-slate-300 mb-1.5">Password</label>
            <input
                id="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                placeholder="••••••••••••"
            />
        </div>

        <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2 cursor-pointer">
                <input
                    type="checkbox"
                    name="remember"
                    class="rounded bg-slate-950 border-slate-700 text-amber-500 focus:ring-amber-500/50 focus:ring-offset-0"
                />
                <span class="text-xs text-slate-400">Remember session</span>
            </label>
        </div>

        <button
            type="submit"
            class="w-full py-2.5 px-4 rounded-xl bg-amber-600 hover:bg-amber-500 text-slate-950 font-semibold text-sm shadow-lg shadow-amber-600/20 transition duration-150 ease-in-out"
        >
            Authenticate Credentials &rarr;
        </button>
    </form>
@endsection
