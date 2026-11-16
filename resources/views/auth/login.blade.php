<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In · KGOLD AUTOMATION TEAM KPI</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-surface text-ink grid place-items-center p-4">
    <div id="top-nav-progress" aria-hidden="true"
         class="fixed top-0 left-0 z-[60] h-[2.5px] w-0 bg-linear-to-r from-primary via-indigo-400 to-sky-400 opacity-0 transition-[width,opacity] duration-300 ease-out pointer-events-none"></div>
    <main class="w-full max-w-md">
        <div class="card p-7 sm:p-8 space-y-6">
            <div class="flex flex-col items-center text-center">
                <x-brand-logo size="size-11 !rounded-2xl" iconSize="25" class="mb-3"/>
                <h1 class="text-lg font-extrabold tracking-tight text-ink">KGOLD AUTOMATION TEAM <span class="text-primary">KPI</span></h1>
                <p class="text-xs text-muted mt-1">Sales Source Attribution · Broadcasts, Sequences, AI & POS</p>
            </div>

            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-xs font-medium text-bad flex items-center gap-2">
                    <x-icon name="alert" class="size-4 shrink-0"/>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}" class="space-y-4">
                @csrf
                <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-ink">Email address</span>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                           placeholder="you@company.com" class="field !min-h-[40px] !text-sm">
                </label>

                <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-ink">Password</span>
                    <x-password-field name="password" id="login-password" required autocomplete="current-password" placeholder="Enter your password" class="!min-h-[40px] !text-sm"/>
                </label>

                <label class="inline-flex items-center gap-2 text-xs text-muted cursor-pointer select-none">
                    <input type="checkbox" name="remember" value="1" checked class="rounded border-slate-300 text-primary focus:ring-primary">
                    <span>Keep me signed in on this computer</span>
                </label>

                <button type="submit" data-loading-text="Signing in…" class="btn-primary w-full !min-h-[42px] !text-sm">
                    Sign In
                </button>
            </form>
        </div>
    </main>
</body>
</html>
