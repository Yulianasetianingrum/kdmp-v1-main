<x-layouts.app title="Neraca (Balance Sheet)" eyebrow="Laporan Keuangan">

    {{-- Filter --}}
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-ink-600">Tahun</label>
            <input type="number" name="tahun" value="{{ $tahun }}" min="2000" max="2099"
                class="w-28 rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm focus:border-merah-400 focus:outline-none">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-ink-600">Per Akhir Bulan</label>
            <select name="bulan" class="rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm focus:border-merah-400 focus:outline-none">
                @foreach(range(1,12) as $b)
                    <option value="{{ $b }}" @selected($b == $bulan)>
                        {{ \Carbon\Carbon::create()->month($b)->locale('id')->monthName }}
                    </option>
                @endforeach
            </select>
        </div>
        <button class="rounded-sm bg-merah-500 px-4 py-2 text-sm font-medium text-white hover:bg-merah-600">
            Tampilkan
        </button>
        <div class="ml-auto flex gap-2 text-xs">
            <a href="{{ route('akuntansi.laporan.neraca-saldo', request()->only('tahun','bulan')) }}" class="rounded-sm border border-paper-300 px-3 py-2 text-ink-600 hover:border-merah-400">Neraca Saldo</a>
            <a href="{{ route('akuntansi.laporan.laba-rugi', ['tahun'=>$tahun]) }}" class="rounded-sm border border-paper-300 px-3 py-2 text-ink-600 hover:border-merah-400">Laba/Rugi</a>
        </div>
    </form>

    {{-- Alert tidak balance --}}
    @if($totalAktiva > 0 && !$isBalance)
        <div class="mb-4 rounded-sm border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
            ⚠️ <strong>Tidak Balance!</strong> Total Aset (Rp {{ number_format($totalAktiva,2,',','.') }})
            ≠ Kewajiban + Modal (Rp {{ number_format($totalPassiva,2,',','.') }}).
            Selisih: Rp {{ number_format(abs($totalAktiva-$totalPassiva),2,',','.') }}.
            Pastikan Laba/Rugi tahun berjalan sudah ditutup ke akun Modal.
        </div>
    @endif

    {{-- Header --}}
    <div class="mb-6 border-b border-paper-300 bg-paper-50 px-6 py-4 text-center">
        <h2 class="font-semibold text-ink-900">Neraca (Balance Sheet)</h2>
        <p class="mt-0.5 text-sm text-ink-600">
            Per 31 {{ \Carbon\Carbon::create()->month($bulan)->locale('id')->monthName }} {{ $tahun }}
        </p>
    </div>

    {{-- Laporan 2 Kolom --}}
    <div class="grid gap-6 md:grid-cols-2">

        {{-- KOLOM KIRI: AKTIVA --}}
        <div class="rounded-sm border border-paper-300 bg-white">
            <div class="border-b border-paper-300 bg-paper-100 px-5 py-3">
                <h3 class="font-semibold uppercase tracking-wider text-ink-700">Aktiva (Aset)</h3>
            </div>
            <div class="p-5">
                {{-- Helper macro/function if we need nested groups later, for now flat iteration --}}
                @forelse ($aktiva as $row)
                    <div class="flex justify-between py-1.5 text-sm border-b border-dashed border-paper-200 last:border-0">
                        <span class="text-ink-800">
                            <span class="font-mono text-xs text-ink-500 mr-2">{{ $row->kode_anak }}</span>
                            {{ $row->nama_rekening }}
                        </span>
                        <span class="font-medium text-ink-900">{{ number_format($row->saldo, 2, ',', '.') }}</span>
                    </div>
                @empty
                    <p class="py-4 text-center text-sm italic text-ink-500">Tidak ada data aktiva.</p>
                @endforelse
            </div>
            <div class="border-t-2 border-ink-300 bg-paper-50 px-5 py-4">
                <div class="flex items-center justify-between font-bold">
                    <span class="text-ink-900">TOTAL AKTIVA</span>
                    <span class="text-lg text-ink-900">Rp {{ number_format($totalAktiva, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>

        {{-- KOLOM KANAN: KEWAJIBAN & MODAL --}}
        <div class="space-y-6">
            {{-- Kewajiban --}}
            <div class="rounded-sm border border-paper-300 bg-white">
                <div class="border-b border-paper-300 bg-paper-100 px-5 py-3">
                    <h3 class="font-semibold uppercase tracking-wider text-ink-700">Kewajiban (Hutang)</h3>
                </div>
                <div class="p-5">
                    @forelse ($kewajiban as $row)
                        <div class="flex justify-between py-1.5 text-sm border-b border-dashed border-paper-200 last:border-0">
                            <span class="text-ink-800">
                                <span class="font-mono text-xs text-ink-500 mr-2">{{ $row->kode_anak }}</span>
                                {{ $row->nama_rekening }}
                            </span>
                            <span class="font-medium text-ink-900">{{ number_format($row->saldo, 2, ',', '.') }}</span>
                        </div>
                    @empty
                        <p class="py-4 text-center text-sm italic text-ink-500">Tidak ada data kewajiban.</p>
                    @endforelse
                    <div class="mt-2 flex justify-between border-t border-paper-300 pt-2 text-sm font-semibold">
                        <span class="text-ink-700">Total Kewajiban</span>
                        <span class="text-ink-900">{{ number_format($totalKewajiban, 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            {{-- Modal --}}
            <div class="rounded-sm border border-paper-300 bg-white">
                <div class="border-b border-paper-300 bg-paper-100 px-5 py-3">
                    <h3 class="font-semibold uppercase tracking-wider text-ink-700">Modal (Ekuitas)</h3>
                </div>
                <div class="p-5">
                    @forelse ($modal as $row)
                        <div class="flex justify-between py-1.5 text-sm border-b border-dashed border-paper-200 last:border-0">
                            <span class="text-ink-800">
                                <span class="font-mono text-xs text-ink-500 mr-2">{{ $row->kode_anak }}</span>
                                {{ $row->nama_rekening }}
                            </span>
                            <span class="font-medium text-ink-900">{{ number_format($row->saldo, 2, ',', '.') }}</span>
                        </div>
                    @empty
                        <p class="py-4 text-center text-sm italic text-ink-500">Tidak ada data modal.</p>
                    @endforelse
                    <div class="mt-2 flex justify-between border-t border-paper-300 pt-2 text-sm font-semibold">
                        <span class="text-ink-700">Total Modal</span>
                        <span class="text-ink-900">{{ number_format($totalModal, 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            {{-- Total Passiva --}}
            <div class="rounded-sm border-2 {{ $isBalance ? 'border-sawah-500 bg-sawah-50' : 'border-merah-500 bg-merah-50' }} px-5 py-4 shadow-sm">
                <div class="flex items-center justify-between font-bold">
                    <span class="{{ $isBalance ? 'text-sawah-900' : 'text-merah-900' }}">TOTAL KEWAJIBAN + MODAL</span>
                    <span class="text-lg {{ $isBalance ? 'text-sawah-900' : 'text-merah-900' }}">
                        Rp {{ number_format($totalPassiva, 2, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>

    </div>

</x-layouts.app>
