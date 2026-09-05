<x-layouts.app
    title="Detail Pelunasan — {{ $pelunasan->kode_pelunasan }}"
    eyebrow="Keuangan"
>

    {{-- Flash message --}}
    @if (session('success'))
        <div class="mb-5 rounded-sm border border-sawah-200 bg-sawah-50 p-4 text-sm text-sawah-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="mx-auto max-w-4xl space-y-6">

        {{-- ================================================================
             Header Pelunasan
        ================================================================ --}}
        <div class="rounded-sm border border-paper-300 bg-paper-50 p-6">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-xs text-ink-500 uppercase tracking-wider">
                        {{ $pelunasan->jenis === 'terima_piutang' ? 'Terima Pelunasan Piutang' : 'Bayar Hutang' }}
                    </p>
                    <h1 class="mt-1 text-xl font-bold text-ink-900">{{ $pelunasan->kode_pelunasan }}</h1>
                </div>

                @if($pelunasan->status_posting === 'T')
                    <span class="rounded-full bg-sawah-100 px-3 py-1 text-xs font-medium text-sawah-700">
                        ✓ Posted — Jurnal #{{ $pelunasan->id_jurnal }}
                    </span>
                @else
                    <span class="rounded-full bg-paper-200 px-3 py-1 text-xs font-medium text-ink-600">
                        Draft
                    </span>
                @endif
            </div>

            <dl class="grid grid-cols-2 gap-x-8 gap-y-4 sm:grid-cols-4 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wider text-ink-500">Tanggal</dt>
                    <dd class="mt-1 font-medium text-ink-900">{{ $pelunasan->tanggal->format('d M Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wider text-ink-500">Pihak</dt>
                    <dd class="mt-1 font-medium text-ink-900">{{ $pelunasan->pihak->nama ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wider text-ink-500">Kas / Bank</dt>
                    <dd class="mt-1 font-medium text-ink-900">{{ $pelunasan->kasBank->nama ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wider text-ink-500">Total Nilai</dt>
                    <dd class="mt-1 text-lg font-bold text-ink-900">
                        Rp {{ number_format($pelunasan->total_nilai, 2, ',', '.') }}
                    </dd>
                </div>
                @if($pelunasan->catatan)
                    <div class="col-span-2 sm:col-span-4">
                        <dt class="text-xs uppercase tracking-wider text-ink-500">Catatan</dt>
                        <dd class="mt-1 text-ink-800">{{ $pelunasan->catatan }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- ================================================================
             Alokasi Detail
        ================================================================ --}}
        <div class="rounded-sm border border-paper-300 bg-paper-50">
            <div class="border-b border-paper-300 px-6 py-4">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-700">
                    Alokasi Pembayaran
                </h2>
            </div>
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-paper-300 bg-paper-200/60 font-mono text-[11px] uppercase tracking-wide text-ink-600/70">
                        <th class="px-4 py-3 font-medium">#</th>
                        <th class="px-4 py-3 font-medium">Jenis</th>
                        <th class="px-4 py-3 font-medium">Akun</th>
                        <th class="px-4 py-3 font-medium">Sumber</th>
                        <th class="px-4 py-3 text-right font-medium">Nilai Awal (Rp)</th>
                        <th class="px-4 py-3 text-right font-medium">Dibayar Sekarang (Rp)</th>
                        <th class="px-4 py-3 text-center font-medium">Status Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pelunasan->detail as $i => $det)
                        @php
                            $ref = $det->piutang ?? $det->hutang;
                            $tipe = $det->piutang ? 'Piutang' : 'Hutang';
                        @endphp
                        <tr class="border-b border-paper-200 last:border-0">
                            <td class="px-4 py-3 text-ink-500">{{ $i + 1 }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $tipe === 'Piutang' ? 'bg-sawah-100 text-sawah-700' : 'bg-merah-100 text-merah-700' }}">
                                    {{ $tipe }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-ink-600">{{ $ref->kode_akun ?? '-' }}</td>
                            <td class="px-4 py-3 text-xs text-ink-600">{{ $ref->sumber_tipe ?? '-' }}</td>
                            <td class="px-4 py-3 text-right text-ink-900">
                                {{ $ref ? number_format($ref->nilai_awal, 2, ',', '.') : '-' }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-ink-900">
                                {{ number_format($det->nilai_bayar, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($ref)
                                    @if($ref->status === 'lunas')
                                        <span class="rounded-full bg-sawah-100 px-2 py-0.5 text-xs text-sawah-700">Lunas</span>
                                    @elseif($ref->status === 'sebagian')
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">Sebagian</span>
                                    @else
                                        <span class="rounded-full bg-merah-100 px-2 py-0.5 text-xs text-merah-700">Belum Lunas</span>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-paper-300 bg-paper-100">
                        <td colspan="5" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-ink-600">
                            Total Dibayar
                        </td>
                        <td class="px-4 py-3 text-right text-base font-bold text-ink-900">
                            Rp {{ number_format($pelunasan->total_nilai, 2, ',', '.') }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- ================================================================
             Navigasi bawah
        ================================================================ --}}
        <div class="flex items-center justify-between">
            <a href="{{ route('keuangan.pelunasan.index') }}"
               class="text-sm text-merah-500 hover:underline">
                ← Kembali ke Daftar Pelunasan
            </a>

            @if($pelunasan->status_posting === 'T')
                <a href="{{ route('akuntansi.jurnal.show', $pelunasan->id_jurnal) }}"
                   class="text-sm text-ink-500 hover:text-merah-500 hover:underline">
                    Lihat Jurnal #{{ $pelunasan->id_jurnal }} →
                </a>
            @endif
        </div>

    </div>

</x-layouts.app>
