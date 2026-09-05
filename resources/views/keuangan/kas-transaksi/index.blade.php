<x-layouts.app title="Kas Masuk / Keluar / Mutasi" eyebrow="Keuangan">
    
    <!-- Widget Saldo Realtime -->
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($kasBanks as $kas)
            @php 
                $saldo = $saldoKas->get($kas->kode_akun);
                $total = $saldo ? $saldo->total_saldo : 0;
            @endphp
            <div class="rounded-sm border border-paper-300 bg-paper-50 p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-sawah-100 text-sawah-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-ink-500">{{ $kas->nama }}</p>
                        <h3 class="mt-1 text-lg font-bold text-ink-900">Rp {{ number_format($total, 2, ',', '.') }}</h3>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Header & Search -->
    <div class="mb-5 flex items-center justify-between gap-4">
        <form method="GET" class="flex items-center gap-2">
            <input
                type="text"
                name="q"
                value="{{ request('q') }}"
                placeholder="Cari transaksi..."
                class="w-64 rounded-sm border border-paper-300 bg-paper-50 px-3 py-2 text-sm placeholder:text-ink-600/40 focus:border-merah-400 focus:outline-none"
            >
            <button class="rounded-sm border border-paper-300 px-3 py-2 text-sm text-ink-700 hover:border-merah-400 bg-white">
                Cari
            </button>
        </form>

        <a href="{{ route('keuangan.kas-transaksi.create') }}"
           class="rounded-sm bg-merah-500 px-4 py-2 text-sm font-medium text-paper-50 hover:bg-merah-600">
            + Transaksi Baru
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-sm border border-sawah-200 bg-sawah-50 p-4 text-sm text-sawah-800">
            {{ session('success') }}
        </div>
    @endif
    
    @if (session('error'))
        <div class="mb-4 rounded-sm border border-merah-200 bg-merah-50 p-4 text-sm text-merah-800">
            {{ session('error') }}
        </div>
    @endif

    <!-- Data Table -->
    <div class="overflow-hidden rounded-sm border border-paper-300 bg-paper-50">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-paper-300 bg-paper-200/60 font-mono text-[11px] uppercase tracking-wide text-ink-600/70">
                    <th class="px-4 py-3 font-medium">Tanggal</th>
                    <th class="px-4 py-3 font-medium">No. Transaksi</th>
                    <th class="px-4 py-3 font-medium">Jenis</th>
                    <th class="px-4 py-3 font-medium">Kas/Bank</th>
                    <th class="px-4 py-3 font-medium">Keterangan</th>
                    <th class="px-4 py-3 text-right font-medium">Nilai (Rp)</th>
                    <th class="px-4 py-3 text-center font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr class="border-b border-paper-200 last:border-0 hover:bg-paper-100/70">
                        <td class="px-4 py-3 text-ink-800">{{ $item->tanggal->format('d M Y') }}</td>
                        <td class="px-4 py-3 font-medium text-ink-900">{{ $item->kode_trx }}</td>
                        <td class="px-4 py-3 text-ink-800">
                            @if($item->jenis === 'masuk')
                                <span class="rounded-full bg-sawah-100 px-2 py-0.5 text-xs text-sawah-700">Masuk</span>
                            @elseif($item->jenis === 'keluar')
                                <span class="rounded-full bg-merah-100 px-2 py-0.5 text-xs text-merah-700">Keluar</span>
                            @else
                                <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">Mutasi</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-ink-800">
                            {{ $item->kasBank->nama ?? '-' }}
                            @if($item->jenis === 'mutasi_antar_kas' && $item->id_kas_bank_tujuan)
                                @php $kasTujuan = $kasBanks->firstWhere('id_kas_bank', $item->id_kas_bank_tujuan); @endphp
                                <span class="text-ink-500 text-xs block">&rarr; {{ $kasTujuan->nama ?? 'Unknown' }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-ink-800">{{ \Illuminate\Support\Str::limit($item->keterangan, 40) }}</td>
                        <td class="px-4 py-3 text-right font-medium text-ink-900">{{ number_format($item->nilai, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($item->status_posting === 'T')
                                <span class="rounded-full bg-sawah-100 px-2 py-0.5 text-xs text-sawah-700" title="Jurnal #{{ $item->id_jurnal }}">Posted</span>
                            @else
                                <span class="rounded-full bg-paper-200 px-2 py-0.5 text-xs text-ink-600">Draft</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-sm text-ink-600/60">
                            Belum ada transaksi kas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $items->links() }}
    </div>
</x-layouts.app>
