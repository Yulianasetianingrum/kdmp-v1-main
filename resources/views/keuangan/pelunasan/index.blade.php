<x-layouts.app title="Riwayat Pelunasan" eyebrow="Keuangan">

    {{-- Filter bar --}}
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input
                type="text"
                name="q"
                value="{{ request('q') }}"
                placeholder="Cari pihak / kode…"
                class="w-52 rounded-sm border border-paper-300 bg-paper-50 px-3 py-2 text-sm placeholder:text-ink-600/40 focus:border-merah-400 focus:outline-none"
            >

            <select name="jenis" class="rounded-sm border border-paper-300 bg-paper-50 px-3 py-2 text-sm text-ink-700 focus:border-merah-400 focus:outline-none">
                <option value="">Semua Jenis</option>
                <option value="terima_piutang" @selected(request('jenis') === 'terima_piutang')>Terima Piutang</option>
                <option value="bayar_hutang"   @selected(request('jenis') === 'bayar_hutang')>Bayar Hutang</option>
            </select>

            <select name="status" class="rounded-sm border border-paper-300 bg-paper-50 px-3 py-2 text-sm text-ink-700 focus:border-merah-400 focus:outline-none">
                <option value="">Semua Status</option>
                <option value="T" @selected(request('status') === 'T')>Posted</option>
                <option value="F" @selected(request('status') === 'F')>Draft</option>
            </select>

            <button class="rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm text-ink-700 hover:border-merah-400">
                Cari
            </button>

            @if(request()->hasAny(['q','jenis','status']))
                <a href="{{ route('keuangan.pelunasan.index') }}" class="text-sm text-ink-500 hover:text-merah-500 underline">Reset</a>
            @endif
        </form>

        <a href="{{ route('keuangan.pelunasan.create') }}"
           class="rounded-sm bg-merah-500 px-4 py-2 text-sm font-medium text-paper-50 hover:bg-merah-600">
            + Catat Pelunasan
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

    {{-- Tabel --}}
    <div class="overflow-hidden rounded-sm border border-paper-300 bg-paper-50">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-paper-300 bg-paper-200/60 font-mono text-[11px] uppercase tracking-wide text-ink-600/70">
                    <th class="px-4 py-3 font-medium">Tanggal</th>
                    <th class="px-4 py-3 font-medium">Kode</th>
                    <th class="px-4 py-3 font-medium">Jenis</th>
                    <th class="px-4 py-3 font-medium">Pihak</th>
                    <th class="px-4 py-3 font-medium">Kas / Bank</th>
                    <th class="px-4 py-3 text-right font-medium">Total (Rp)</th>
                    <th class="px-4 py-3 text-center font-medium">Status</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr class="border-b border-paper-200 last:border-0 hover:bg-paper-100/70">
                        <td class="px-4 py-3 text-ink-800">{{ $item->tanggal->format('d M Y') }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-ink-900">{{ $item->kode_pelunasan }}</td>
                        <td class="px-4 py-3">
                            @if($item->jenis === 'terima_piutang')
                                <span class="rounded-full bg-sawah-100 px-2 py-0.5 text-xs text-sawah-700">Terima Piutang</span>
                            @else
                                <span class="rounded-full bg-merah-100 px-2 py-0.5 text-xs text-merah-700">Bayar Hutang</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-ink-800">{{ $item->pihak->nama ?? '-' }}</td>
                        <td class="px-4 py-3 text-ink-800">{{ $item->kasBank->nama ?? '-' }}</td>
                        <td class="px-4 py-3 text-right font-medium text-ink-900">
                            {{ number_format($item->total_nilai, 2, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($item->status_posting === 'T')
                                <span class="rounded-full bg-sawah-100 px-2 py-0.5 text-xs text-sawah-700" title="Jurnal #{{ $item->id_jurnal }}">Posted</span>
                            @else
                                <span class="rounded-full bg-paper-200 px-2 py-0.5 text-xs text-ink-600">Draft</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('keuangan.pelunasan.show', $item) }}"
                               class="text-xs text-merah-500 hover:underline">Detail →</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-sm text-ink-600/60">
                            Belum ada data pelunasan.
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
