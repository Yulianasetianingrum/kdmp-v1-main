<x-layouts.guest title="KDMP — Sistem ERP Koperasi Desa">
    <div class="mx-auto flex min-h-screen max-w-5xl flex-col justify-center px-6 py-16">
        <p class="font-mono text-xs uppercase tracking-[0.2em] text-merah-400">Capstone Project — Teknik Informatika</p>
        <h1 class="mt-4 max-w-2xl font-display text-5xl font-semibold leading-[1.1] text-paper-50">
            Sistem ERP untuk Koperasi Desa Merah Putih
        </h1>
        <p class="mt-5 max-w-xl text-paper-300/80">
            Sembilan modul dari survei kebutuhan warga sampai laporan keuangan
            konsolidasi — dibangun agar setiap desa punya pembukuan sendiri,
            dan bisa saling menitipkan barang antar desa lewat modul konsinyasi.
        </p>

        <div class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-5">
            @foreach ([
                ['M0-1', 'Master & Survey'],
                ['M2-3', 'Kalkulasi & Rencana'],
                ['M4', 'Gudang'],
                ['M5-6', 'Beli & Jual'],
                ['M7', 'Konsinyasi'],
            ] as [$no, $label])
                <div class="rounded-sm border border-ink-700 bg-ink-800 px-3 py-3">
                    <p class="font-mono text-[11px] text-merah-400">{{ $no }}</p>
                    <p class="mt-1 text-xs text-paper-200">{{ $label }}</p>
                </div>
            @endforeach
        </div>

        <a href="{{ route('login') }}"
           class="mt-10 inline-flex w-fit items-center gap-2 rounded-sm bg-merah-500 px-5 py-3 text-sm font-medium text-paper-50 hover:bg-merah-600">
            Masuk ke Sistem →
        </a>
    </div>
</x-layouts.guest>
