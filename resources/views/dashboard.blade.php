<x-layouts.app title="Dashboard" eyebrow="Ringkasan">
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-sm border border-paper-300 bg-paper-50 p-5">
            <p class="font-mono text-[11px] uppercase tracking-wide text-ink-600/60">Jenis Barang</p>
            <p class="mt-2 font-display text-3xl font-semibold">{{ $ringkasan['jumlah_barang'] }}</p>
        </div>
        <div class="rounded-sm border border-paper-300 bg-paper-50 p-5">
            <p class="font-mono text-[11px] uppercase tracking-wide text-ink-600/60">Transaksi Penjualan</p>
            <p class="mt-2 font-display text-3xl font-semibold">{{ $ringkasan['jumlah_penjualan'] }}</p>
        </div>
        <div class="rounded-sm border border-merah-500/30 bg-merah-50 p-5">
            <p class="font-mono text-[11px] uppercase tracking-wide text-merah-600/70">Piutang Terbuka</p>
            <p class="mt-2 font-display text-3xl font-semibold text-merah-700">{{ $ringkasan['piutang_terbuka'] }}</p>
        </div>
        <div class="rounded-sm border border-padi-500/30 bg-padi-100 p-5">
            <p class="font-mono text-[11px] uppercase tracking-wide text-padi-600/80">Hutang Terbuka</p>
            <p class="mt-2 font-display text-3xl font-semibold text-padi-600">{{ $ringkasan['hutang_terbuka'] }}</p>
        </div>
    </div>

    <div class="mt-6 rounded-sm border border-paper-300 bg-paper-50 p-6">
        <h2 class="font-display text-lg font-semibold">Kerangka Aplikasi</h2>
        <p class="mt-2 max-w-2xl text-sm text-ink-700">
            Ini kerangka Laravel yang dibangun ulang dari sistem KDMP asli — skema
            database sama persis, model dan routing sudah lengkap untuk sembilan
            modul, tapi logika bisnis (mesin jurnal, HPP moving average, tutup
            buku) belum diisi. Modul <strong class="text-merah-600">Keuangan &amp; Akuntansi</strong>
            akan didalami lebih dulu pada sesi berikutnya.
        </p>
    </div>
</x-layouts.app>
