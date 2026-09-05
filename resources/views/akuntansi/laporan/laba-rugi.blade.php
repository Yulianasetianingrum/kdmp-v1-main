<x-layouts.app title="Laporan Laba/Rugi" eyebrow="Laporan Keuangan">

    {{-- Filter --}}
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-ink-600">Tahun</label>
            <input type="number" name="tahun" value="{{ $tahun }}" min="2000" max="2099"
                class="w-28 rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm focus:border-merah-400 focus:outline-none">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-ink-600">Bulan Dari</label>
            <select name="bulan_dari" class="rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm focus:border-merah-400 focus:outline-none">
                @foreach(range(1,12) as $b)
                    <option value="{{ $b }}" @selected($b == $bulanDari)>
                        {{ \Carbon\Carbon::create()->month($b)->locale('id')->monthName }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-ink-600">s.d. Bulan</label>
            <select name="bulan_sampai" class="rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm focus:border-merah-400 focus:outline-none">
                @foreach(range(1,12) as $b)
                    <option value="{{ $b }}" @selected($b == $bulanSampai)>
                        {{ \Carbon\Carbon::create()->month($b)->locale('id')->monthName }}
                    </option>
                @endforeach
            </select>
        </div>
        <button class="rounded-sm bg-merah-500 px-4 py-2 text-sm font-medium text-white hover:bg-merah-600">
            Tampilkan
        </button>
        <div class="ml-auto flex gap-2 text-xs">
            <a href="{{ route('akuntansi.laporan.neraca-saldo', ['tahun'=>$tahun,'bulan'=>$bulanSampai]) }}" class="rounded-sm border border-paper-300 px-3 py-2 text-ink-600 hover:border-merah-400">Neraca Saldo</a>
            <a href="{{ route('akuntansi.laporan.neraca', ['tahun'=>$tahun,'bulan'=>$bulanSampai]) }}" class="rounded-sm border border-paper-300 px-3 py-2 text-ink-600 hover:border-merah-400">Neraca</a>
        </div>
    </form>

    @php
        $judulPeriode = \Carbon\Carbon::create()->month($bulanDari)->locale('id')->monthName
            . ($bulanDari !== $bulanSampai ? ' – ' . \Carbon\Carbon::create()->month($bulanSampai)->locale('id')->monthName : '')
            . ' ' . $tahun;
    @endphp

    {{-- Laporan --}}
    <div class="mx-auto max-w-2xl space-y-0 rounded-sm border border-paper-300 bg-paper-50 overflow-hidden">

        {{-- Header --}}
        <div class="border-b border-paper-300 bg-paper-100 px-6 py-4 text-center">
            <h2 class="font-semibold text-ink-900">Laporan Laba / Rugi</h2>
            <p class="mt-0.5 text-sm text-ink-600">{{ $judulPeriode }}</p>
        </div>

        {{-- SEKSI 1: Pendapatan --}}
        <div class="px-6 pt-5">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-500">Pendapatan</h3>
            @forelse ($pendapatan as $row)
                <div class="flex justify-between py-1 text-sm">
                    <span class="text-ink-800">{{ $row->nama_rekening }}</span>
                    <span class="font-medium text-ink-900">{{ number_format($row->nilai, 2, ',', '.') }}</span>
                </div>
            @empty
                <p class="py-2 text-sm italic text-ink-500">Tidak ada data pendapatan.</p>
            @endforelse
            <div class="mt-2 flex justify-between border-t border-paper-300 pt-2 text-sm font-semibold">
                <span class="text-ink-700">Total Pendapatan</span>
                <span class="text-ink-900">{{ number_format($totalPendapatan, 2, ',', '.') }}</span>
            </div>
        </div>

        <div class="my-4 border-t border-dashed border-paper-300"></div>

        {{-- SEKSI 2: HPP --}}
        <div class="px-6">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-500">Harga Pokok Penjualan (HPP)</h3>
            @forelse ($hpp as $row)
                <div class="flex justify-between py-1 text-sm">
                    <span class="text-ink-800">{{ $row->nama_rekening }}</span>
                    <span class="text-merah-700">({{ number_format($row->nilai, 2, ',', '.') }})</span>
                </div>
            @empty
                <p class="py-2 text-sm italic text-ink-500">Tidak ada data HPP.</p>
            @endforelse
            <div class="mt-2 flex justify-between border-t border-paper-300 pt-2 text-sm font-semibold">
                <span class="text-ink-700">Total HPP</span>
                <span class="text-merah-700">({{ number_format($totalHPP, 2, ',', '.') }})</span>
            </div>
        </div>

        {{-- Laba Kotor --}}
        <div class="mx-6 my-4 flex items-center justify-between rounded-sm bg-paper-200 px-4 py-3">
            <span class="text-sm font-bold text-ink-800">Laba Kotor</span>
            <span class="text-base font-bold {{ $labaKotor >= 0 ? 'text-sawah-700' : 'text-merah-700' }}">
                {{ number_format($labaKotor, 2, ',', '.') }}
            </span>
        </div>

        {{-- SEKSI 3: Biaya Operasional --}}
        <div class="px-6">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-500">Biaya Operasional</h3>
            @forelse ($biaya as $row)
                <div class="flex justify-between py-1 text-sm">
                    <span class="text-ink-800">{{ $row->nama_rekening }}</span>
                    <span class="text-merah-700">({{ number_format($row->nilai, 2, ',', '.') }})</span>
                </div>
            @empty
                <p class="py-2 text-sm italic text-ink-500">Tidak ada data biaya operasional.</p>
            @endforelse
            <div class="mt-2 flex justify-between border-t border-paper-300 pt-2 text-sm font-semibold">
                <span class="text-ink-700">Total Biaya Operasional</span>
                <span class="text-merah-700">({{ number_format($totalBiaya, 2, ',', '.') }})</span>
            </div>
        </div>

        {{-- Laba Operasi --}}
        <div class="mx-6 my-4 flex items-center justify-between rounded-sm bg-paper-200 px-4 py-3">
            <span class="text-sm font-bold text-ink-800">Laba Operasi</span>
            <span class="text-base font-bold {{ $labaOperasi >= 0 ? 'text-sawah-700' : 'text-merah-700' }}">
                {{ number_format($labaOperasi, 2, ',', '.') }}
            </span>
        </div>

        {{-- SEKSI 4: Non-Operasional --}}
        @if($nonOperasional->isNotEmpty())
        <div class="px-6">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-500">Non-Operasional</h3>
            @foreach ($nonOperasional as $row)
                <div class="flex justify-between py-1 text-sm">
                    <span class="text-ink-800">{{ $row->nama_rekening }}</span>
                    <span class="{{ $row->nilai >= 0 ? 'text-ink-900' : 'text-merah-700' }}">
                        {{ $row->nilai >= 0 ? '' : '(' }}{{ number_format(abs($row->nilai), 2, ',', '.') }}{{ $row->nilai >= 0 ? '' : ')' }}
                    </span>
                </div>
            @endforeach
        </div>
        @endif

        {{-- LABA BERSIH --}}
        <div class="border-t-2 border-ink-800 px-6 pb-6 pt-4">
            <div class="flex items-center justify-between">
                <span class="text-base font-bold text-ink-900">Laba Bersih</span>
                <span class="text-xl font-bold {{ $labaBersih >= 0 ? 'text-sawah-700' : 'text-merah-700' }}">
                    Rp {{ number_format($labaBersih, 2, ',', '.') }}
                </span>
            </div>
            @if($labaBersih < 0)
                <p class="mt-1 text-xs text-merah-600">⚠ Koperasi mengalami kerugian pada periode ini.</p>
            @endif
        </div>
    </div>

</x-layouts.app>
