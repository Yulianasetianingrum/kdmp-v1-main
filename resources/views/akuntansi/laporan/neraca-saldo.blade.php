<x-layouts.app title="Neraca Saldo" eyebrow="Laporan Keuangan">

    {{-- Filter --}}
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-ink-600">Tahun</label>
            <input type="number" name="tahun" value="{{ $tahun }}" min="2000" max="2099"
                class="w-28 rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm text-ink-900 focus:border-merah-400 focus:outline-none">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-ink-600">Bulan</label>
            <select name="bulan" class="rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm text-ink-900 focus:border-merah-400 focus:outline-none">
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
        {{-- Link ke laporan lain --}}
        <div class="ml-auto flex gap-2 text-xs">
            <a href="{{ route('akuntansi.laporan.buku-besar', request()->only('tahun','bulan')) }}" class="rounded-sm border border-paper-300 px-3 py-2 text-ink-600 hover:border-merah-400">Buku Besar</a>
            <a href="{{ route('akuntansi.laporan.laba-rugi', ['tahun'=>$tahun]) }}" class="rounded-sm border border-paper-300 px-3 py-2 text-ink-600 hover:border-merah-400">Laba/Rugi</a>
            <a href="{{ route('akuntansi.laporan.neraca', request()->only('tahun','bulan')) }}" class="rounded-sm border border-paper-300 px-3 py-2 text-ink-600 hover:border-merah-400">Neraca</a>
        </div>
    </form>

    {{-- Alert tidak balance --}}
    @if($rows->isNotEmpty() && !$isBalance)
        <div class="mb-4 rounded-sm border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
            ⚠️ <strong>Tidak Balance!</strong> Total Debet (Rp {{ number_format($totalDebet,2,',','.') }})
            ≠ Total Kredit (Rp {{ number_format($totalKredit,2,',','.') }}).
            Selisih: Rp {{ number_format(abs($totalDebet-$totalKredit),2,',','.') }}.
            Jalankan <code class="bg-amber-100 px-1 font-mono text-xs">bangunBukuBesar()</code> untuk rebuild.
        </div>
    @endif

    @if($rows->isEmpty())
        <div class="rounded-sm border border-dashed border-paper-300 bg-paper-50 p-10 text-center text-sm text-ink-600/60">
            Tidak ada data untuk periode
            <strong>{{ \Carbon\Carbon::create()->month($bulan)->locale('id')->monthName }} {{ $tahun }}</strong>.
            Pastikan sudah ada jurnal yang di-POST di periode ini.
        </div>
    @else
    {{-- Tabel --}}
    <div class="overflow-hidden rounded-sm border border-paper-300 bg-paper-50">
        <div class="border-b border-paper-300 bg-paper-100 px-5 py-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink-800">
                Neraca Saldo — {{ \Carbon\Carbon::create()->month($bulan)->locale('id')->monthName }} {{ $tahun }}
            </h2>
            @if($isBalance)
                <span class="rounded-full bg-sawah-100 px-2 py-0.5 text-xs text-sawah-700">✓ Balance</span>
            @endif
        </div>

        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-paper-300 bg-paper-200/60 font-mono text-[11px] uppercase tracking-wide text-ink-600/70">
                    <th class="px-4 py-3 font-medium">Kode</th>
                    <th class="px-4 py-3 font-medium">Nama Rekening</th>
                    <th class="px-4 py-3 font-medium">Kelompok</th>
                    <th class="px-4 py-3 text-right font-medium">Saldo Debet (Rp)</th>
                    <th class="px-4 py-3 text-right font-medium">Saldo Kredit (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b border-paper-200 last:border-0 hover:bg-paper-100/60">
                        <td class="px-4 py-2.5 font-mono text-xs text-ink-700">{{ $row->kode_anak }}</td>
                        <td class="px-4 py-2.5 text-ink-900">{{ $row->nama_rekening }}</td>
                        <td class="px-4 py-2.5 text-xs text-ink-600">{{ $row->kelompok }}</td>
                        <td class="px-4 py-2.5 text-right font-medium text-ink-900">
                            @if($row->saldo_d > 0)
                                {{ number_format($row->saldo_d, 2, ',', '.') }}
                            @else
                                <span class="text-ink-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right font-medium text-ink-900">
                            @if($row->saldo_k > 0)
                                {{ number_format($row->saldo_k, 2, ',', '.') }}
                            @else
                                <span class="text-ink-400">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-paper-400 bg-paper-100 font-semibold">
                    <td colspan="3" class="px-4 py-3 text-right text-xs uppercase tracking-wider text-ink-600">
                        TOTAL
                    </td>
                    <td class="px-4 py-3 text-right text-ink-900">
                        {{ number_format($totalDebet, 2, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 text-right text-ink-900">
                        {{ number_format($totalKredit, 2, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif

</x-layouts.app>
