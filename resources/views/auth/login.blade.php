<x-layouts.guest title="Masuk">
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <div class="mb-8 text-center">
                <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-sm bg-merah-500 font-display text-xl font-semibold text-paper-50">K</span>
                <h1 class="font-display text-2xl font-semibold text-paper-50">Koperasi Desa Merah Putih</h1>
                <p class="mt-1 font-mono text-xs uppercase tracking-widest text-paper-300/60">Sistem ERP — Masuk</p>
            </div>

            <div class="rounded-sm border border-ink-700 bg-ink-800 p-6">
                @if ($errors->any())
                    <div class="mb-4 rounded-sm border border-merah-500/40 bg-merah-500/10 px-3 py-2 text-sm text-merah-100">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-medium text-paper-300/70">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus
                               class="w-full rounded-sm border border-ink-600 bg-ink-900 px-3 py-2 text-sm text-paper-50 focus:border-merah-400 focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-paper-300/70">Kata Sandi</label>
                        <input type="password" name="password" required
                               class="w-full rounded-sm border border-ink-600 bg-ink-900 px-3 py-2 text-sm text-paper-50 focus:border-merah-400 focus:outline-none">
                    </div>
                    <label class="flex items-center gap-2 text-xs text-paper-300/70">
                        <input type="checkbox" name="remember" class="rounded-sm border-ink-600 bg-ink-900">
                        Ingat saya
                    </label>
                    <button class="w-full rounded-sm bg-merah-500 px-4 py-2.5 text-sm font-medium text-paper-50 hover:bg-merah-600">
                        Masuk
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center font-mono text-[11px] text-paper-300/40">
                Akun contoh: manajer@kdmp-a.test / manajer@kdmp-b.test — kata sandi: password
            </p>
        </div>
    </div>
</x-layouts.guest>
