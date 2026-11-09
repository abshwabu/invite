<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <title>@yield('title', 'Super Admin') - Invite</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#fffbeb',
                            100: '#fef3c7',
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="font-sans antialiased bg-slate-950 text-slate-100 min-h-screen flex flex-col justify-center items-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo / Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-500 mb-3 shadow-lg shadow-amber-500/10">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Invite Control Panel</h1>
            <p class="text-xs text-slate-400 mt-1">Authorized personnel only &bull; High security zone</p>
        </div>

        <!-- Main Card -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-8 backdrop-blur-sm">
            @yield('content')
        </div>

        <p class="text-center text-[11px] text-slate-600 mt-6 font-mono">
            Invite Secure Core &bull; IP: {{ request()->ip() }}
        </p>
    </div>
</body>
</html>
