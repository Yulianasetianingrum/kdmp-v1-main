<x-layouts.app title="Buku Besar" eyebrow="Laporan Keuangan">

    {{-- Filter --}}
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-ink-600">Tahun</label>
            <input type="number" name="tahun" value="{{ $tahun }}" min="2000" max="2099"
                class="w-28 rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm focus:border-merah-400 focus:outline-none">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-ink-600">Bulan</label>
            <select name="bulan" class="rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm focus:border-merah-400 focus:outline-none">
                @foreach(range(1,12) as $b)
                    <option value="{{ $b }}" @selected($b == $bulan)>
                        {{ \Carbon\Carbon::create()->month($b)->locale('id')->monthName }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[240px]">
            <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-ink-600">Akun <span class="text-merah-500">*</span></label>
            <select name="kode_anak" required class="w-full rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm focus:border-merah-400 focus:outline-none">
                <option value="">— Pilih akun —</option>
                @foreach($akunList->groupBy('kelompok') as $grp => $items)
                    <optgroup label="{{ $grp }}">
                        @foreach($items as $akun)
                            <option value="{{ $akun->kode_anak }}" @selected($akun->kode_anak === $kodeAnak)>
                                {{ $akun->kode_anak }} — {{ $akun->nama_rekening }}
                            </option>
                        @endforeach
                    </optgroup>
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

    @if(!$kodeAnak)
        <div class="rounded-sm border border-dashed border-paper-300 bg-paper-50 p-10 text-center text-sm text-ink-600/60">
            Pilih <strong>Akun</strong> di atas untuk menampilkan mutasi buku besar.
        </div>
    @elseif($mutasi->isEmpty())
        <div class="rounded-sm border border-dashed border-paper-300 bg-paper-50 p-10 text-center text-sm text-ink-600/60">
            Tidak ada mutasi untuk akun <strong>{{ $kodeAnak }} — {{ $akunDipilih?->nama_rekening }}</strong>
            pada <strong>{{ \Carbon\Carbon::create()->month($bulan)->locale('id')->monthName }} {{ $tahun }}</strong>.
        </div>
    @else
    {{-- Header akun --}}
    <div class="mb-4 rounded-sm border border-paper-300 bg-paper-50 px-5 py-4">
        <p class="font-mono text-xs text-ink-500">{{ $kodeAnak }} · {{ $akunDipilih?->kelompok }}</p>
        <h2 class="mt-0.5 text-base font-semibold text-ink-900">{{ $akunDipilih?->nama_rekening }}</h2>
        <p class="mt-1 text-xs text-ink-600">
            {{ \Carbon\Carbon::create()->month($bulan)->locale('id')->monthName }} {{ $tahun }}
            · Posisi Normal: {{ $akunDipilih?->posisi_normal === 'D' ? 'Debet' : 'Kredit' }}
        </p>
    </div>

    {{-- Tabel mutasi --}}
    <div class="overflow-hidden rounded-sm border border-paper-300 bg-paper-50">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-paper-300 bg-paper-200/60 font-mono text-[11px] uppercase tracking-wide text-ink-600/70">
                    <th class="px-4 py-3 font-medium">Tanggal</th>
                    <th class="px-4 py-3 font-medium">No. Jurnal</th>
                    <th class="px-4 py-3 font-medium">Jenis</th>
                    <th class="px-4 py-3 font-medium">Keterangan</th>
                    <th class="px-4 py-3 text-right font-medium">Debet (Rp)</th>
                    <th class="px-4 py-3 text-right font-medium">Kredit (Rp)</th>
                    <th class="px-4 py-3 text-right font-medium">Saldo (Rp)</th>
                </tr>
            </thead>
            <tbody>
                {{-- Baris saldo awal --}}
                <tr class="border-b border-paper-200 bg-paper-100/80">
                    <td colspan="4" class="px-4 py-2.5 text-xs font-medium text-ink-600 italic">
                        Saldo Awal (per akhir bulan sebelumnya)
                    </td>
                    <td class="px-4 py-2.5 text-right text-ink-500">—</td>
                    <td class="px-4 py-2.5 text-right text-ink-500">—</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-ink-900">
                        {{ number_format($saldoAwal, 2, ',', '.') }}
                    </td>
                </tr>

                @foreach ($mutasi as $row)
                    <tr class="border-b border-paper-200 last:border-0 hover:bg-paper-100/60">
                        <td class="px-4 py-2.5 text-ink-800">
                            {{ \Carbon\Carbon::parse($row->tanggal_jurnal)->format('d M Y') }}
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs text-ink-700">{{ $row->no_jurnal }}</td>
                        <td class="px-4 py-2.5 text-xs text-ink-600">{{ $row->kode_transaksi ?? 'MANUAL' }}</td>
                        <td class="px-4 py-2.5 text-ink-800">
                            {{ \Illuminate\Support\Str::limit($row->keterangan, 45) }}
                        </td>
                        <td class="px-4 py-2.5 text-right text-ink-900">
                            @if($row->debet > 0)
                                {{ number_format($row->debet, 2, ',', '.') }}
                            @else
                                <span class="text-ink-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right text-ink-900">
                            @if($row->kredit > 0)
                                {{ number_format($row->kredit, 2, ',', '.') }}
                            @else
                                <span class="text-ink-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right font-semibold
                            @if($row->saldo_berjalan < 0) text-merah-600 @else text-ink-900 @endif">
                            {{ number_format($row->saldo_berjalan, 2, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-paper-400 bg-paper-100">
                    <td colspan="4" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-ink-600">
                        Saldo Akhir
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-ink-900">
                        {{ number_format($mutasi->sum('debet'), 2, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-ink-900">
                        {{ number_format($mutasi->sum('kredit'), 2, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 text-right text-base font-bold text-ink-900">
                        {{ number_format($mutasi->last()->saldo_berjalan ?? $saldoAwal, 2, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif

</x-layouts.app>
