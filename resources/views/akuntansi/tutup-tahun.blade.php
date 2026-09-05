<x-layouts.app title="Tutup Tahun" eyebrow="Akuntansi">
    {{-- ===== Header ===== --}}
    <div class="mb-6">
        <h1 class="font-display text-xl font-semibold text-ink-900">Finalisasi Tutup Tahun</h1>
        <p class="mt-1 text-sm text-ink-600">
            Hard-close tahun buku secara permanen. Sistem akan membuat <strong>Jurnal Penutup otomatis</strong>
            yang meng-NOL-kan semua akun nominal (Pendapatan &amp; Biaya) dan memindahkan laba ke Modal/SHU
            sesuai konfigurasi persentase distribusi.
        </p>
    </div>

    {{-- ===== Pilih Tahun ===== --}}
    <form method="GET" action="{{ route('akuntansi.tutup-tahun.index') }}"
          class="mb-6 flex flex-wrap items-end gap-3 rounded border border-paper-200 bg-paper-50 p-4">
        <div>
            <label class="block text-xs font-medium text-ink-700 mb-1">Tahun Buku</label>
            <input type="number" name="tahun" value="{{ $tahun }}" min="2000" max="2099"
                   class="rounded border border-paper-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400 w-28">
        </div>
        <button type="submit" class="rounded bg-primary-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-primary-700 transition">
            Cek Status
        </button>
    </form>

    {{-- ===== Notifikasi ===== --}}
    @if(session('success'))
        <div class="mb-4 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">

        {{-- ===== Kolom Kiri: Pra-Kondisi & Preview Laba/Rugi ===== --}}
        <div class="lg:col-span-3 space-y-6">

            {{-- Pra-Kondisi --}}
            <div>
                <h2 class="mb-3 text-sm font-semibold text-ink-800">Pra-Kondisi Sebelum Finalisasi</h2>
                <div class="overflow-hidden rounded border border-paper-200">
                    <table class="w-full text-sm">
                        <thead class="bg-paper-100 text-xs uppercase text-ink-600">
                            <tr>
                                <th class="px-4 py-2 text-left w-8">#</th>
                                <th class="px-4 py-2 text-left">Kondisi</th>
                                <th class="px-4 py-2 text-center w-24">Status</th>
                                <th class="px-4 py-2 text-left">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-100">
                            @foreach($praKondisi as $p)
                                <tr class="{{ $p['lulus'] ? '' : 'bg-red-50' }}">
                                    <td class="px-4 py-3 text-ink-500">{{ $p['no'] }}</td>
                                    <td class="px-4 py-3 font-medium text-ink-800">{{ $p['label'] }}</td>
                                    <td class="px-4 py-3 text-center">
                                        @if($p['lulus'])
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                Siap
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700">
                                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                                Belum
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-ink-600">
                                        {{ $p['detail'] ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Preview Laba/Rugi --}}
            <div>
                <h2 class="mb-3 text-sm font-semibold text-ink-800">Preview Laba/Rugi Tahun {{ $tahun }}</h2>
                <div class="rounded border border-paper-200 divide-y divide-paper-200">
                    <div class="flex justify-between items-center px-4 py-3">
                        <span class="text-sm text-ink-700">Total Pendapatan</span>
                        <span class="font-mono text-sm font-semibold text-emerald-700">
                            Rp {{ number_format($ringkasan['total_pendapatan'], 0, ',', '.') }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center px-4 py-3">
                        <span class="text-sm text-ink-700">Total Biaya + HPP</span>
                        <span class="font-mono text-sm font-semibold text-red-600">
                            (Rp {{ number_format($ringkasan['total_biaya'], 0, ',', '.') }})
                        </span>
                    </div>
                    <div class="flex justify-between items-center px-4 py-3 bg-paper-50">
                        <span class="text-sm font-bold text-ink-900">Laba / Rugi Bersih</span>
                        <span class="font-mono text-base font-bold {{ $ringkasan['laba_bersih'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ $ringkasan['laba_bersih'] >= 0 ? '' : '(' }}Rp {{ number_format(abs($ringkasan['laba_bersih']), 0, ',', '.') }}{{ $ringkasan['laba_bersih'] >= 0 ? '' : ')' }}
                        </span>
                    </div>
                </div>
                <p class="mt-2 text-xs text-ink-500">
                    * Dihitung dari <code class="rounded bg-paper-200 px-1 font-mono">buku_besar_periode</code>
                    seluruh bulan tahun {{ $tahun }}. Nilai di atas adalah yang akan diproses jurnal penutup.
                </p>
            </div>
        </div>

        {{-- ===== Kolom Kanan: Form Finalisasi ===== --}}
        <div class="lg:col-span-2">
            <div class="rounded border {{ $semuaLulus ? 'border-red-300 bg-red-50' : 'border-paper-200 bg-paper-50' }} p-5 space-y-4">
                <h2 class="text-sm font-bold text-ink-900">
                    🔒 Finalisasi Tutup Tahun {{ $tahun }}
                </h2>

                @if($semuaLulus)
                    <div class="rounded bg-amber-100 border border-amber-300 px-3 py-2 text-xs text-amber-800">
                        <strong>Peringatan Penting:</strong>
                        <ul class="mt-1 list-disc list-inside space-y-1">
                            <li>Tindakan ini <strong>PERMANEN</strong> dan tidak bisa dibatalkan.</li>
                            <li>Jurnal Penutup otomatis akan dibuat tertanggal 31 Desember {{ $tahun }}.</li>
                            <li>Semua akun Pendapatan &amp; Biaya tahun {{ $tahun }} akan di-NOL-kan.</li>
                            <li>Laba/Rugi dipindahkan ke Modal/SHU sesuai konfigurasi.</li>
                            <li>Semua periode tahun {{ $tahun }} akan di-LOCK permanen.</li>
                        </ul>
                    </div>

                    <form method="POST" action="{{ route('akuntansi.tutup-tahun.store') }}">
                        @csrf
                        <input type="hidden" name="tahun" value="{{ $tahun }}">

                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="konfirmasi" value="1" id="cb_konfirmasi"
                                   class="mt-0.5 h-4 w-4 rounded border-paper-400 text-red-600 focus:ring-red-500"
                                   onchange="document.getElementById('btn_finalisasi').disabled = !this.checked">
                            <span class="text-xs text-ink-700">
                                Saya memahami bahwa tindakan ini permanen dan tidak bisa dibatalkan.
                                Jurnal Penutup akan otomatis dibuat oleh sistem.
                            </span>
                        </label>

                        <button type="submit" id="btn_finalisasi" disabled
                                class="mt-4 w-full rounded bg-red-600 px-4 py-2 text-sm font-bold text-white
                                       shadow hover:bg-red-700 transition disabled:opacity-40 disabled:cursor-not-allowed">
                            ⚠️ Finalisasi Tutup Tahun {{ $tahun }}
                        </button>
                    </form>
                @else
                    <p class="text-xs text-ink-600">
                        Tombol Finalisasi akan muncul setelah semua pra-kondisi di sebelah kiri berstatus <strong>Siap</strong>.
                    </p>

                    @php
                        $belumLulus = collect($praKondisi)->where('lulus', false);
                    @endphp
                    <ul class="space-y-1">
                        @foreach($belumLulus as $bl)
                            <li class="flex items-start gap-2 text-xs text-red-700">
                                <span class="mt-0.5 shrink-0">✗</span>
                                <span>{{ $bl['label'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
