<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dashboard' }} — KDMP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-paper-100 font-sans text-ink-900 antialiased">
    <div class="flex h-full">
        <x-sidebar />

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center justify-between border-b border-paper-300 bg-paper-50 px-6 py-3">
                <div>
                    <p class="font-mono text-[11px] uppercase tracking-widest text-ink-600/60">{{ $eyebrow ?? 'KDMP' }}</p>
                    <h1 class="font-display text-xl font-semibold text-ink-900">{{ $title ?? 'Dashboard' }}</h1>
                </div>
                <div class="flex items-center gap-4">
                    @auth
                        <div class="text-right leading-tight">
                            <p class="text-sm font-medium">{{ auth()->user()->nama }}</p>
                            <p class="text-xs text-ink-600/60">{{ auth()->user()->koperasi?->nama_koperasi ?? 'Tingkat Pusat' }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="rounded-sm border border-paper-300 px-3 py-1.5 text-xs font-medium text-ink-700 hover:border-merah-500 hover:text-merah-600">
                                Keluar
                            </button>
                        </form>
                    @endauth
                </div>
            </header>

            <main class="flex-1 overflow-y-auto px-6 py-6">
                @if (session('info'))
                    <div class="mb-4 rounded-sm border border-padi-500/40 bg-padi-100 px-4 py-3 text-sm text-ink-900">
                        {{ session('info') }}
                    </div>
                @endif
                @if (session('status'))
                    <div class="mb-4 rounded-sm border border-sawah-500/40 bg-sawah-100 px-4 py-3 text-sm text-ink-900">
                        {{ session('status') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
