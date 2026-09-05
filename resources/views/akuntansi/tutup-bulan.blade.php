<x-layouts.app title="Tutup Bulan" eyebrow="Akuntansi">
    {{-- ===== Header ===== --}}
    <div class="mb-6">
        <h1 class="font-display text-xl font-semibold text-ink-900">Tutup Bulan</h1>
        <p class="mt-1 text-sm text-ink-600">
            Mengunci periode bulan agar tidak ada input jurnal baru yang masuk ke periode tersebut.
            Pastikan semua 8 validasi di bawah ini berstatus <span class="font-semibold text-emerald-600">Lulus</span> sebelum menekan tombol Tutup Bulan.
        </p>
    </div>

    {{-- ===== Pilih Periode ===== --}}
    <form method="GET" action="{{ route('akuntansi.tutup-bulan.index') }}"
          class="mb-6 flex flex-wrap items-end gap-3 rounded border border-paper-200 bg-paper-50 p-4">
        <div>
            <label class="block text-xs font-medium text-ink-700 mb-1">Tahun</label>
            <input type="number" name="tahun" value="{{ $tahun }}" min="2000" max="2099"
                   class="rounded border border-paper-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400 w-24">
        </div>
        <div>
            <label class="block text-xs font-medium text-ink-700 mb-1">Bulan</label>
            <select name="bulan" class="rounded border border-paper-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                @foreach(range(1,12) as $b)
                    <option value="{{ $b }}" @selected($b === $bulan)>
                        {{ \Carbon\Carbon::create()->month($b)->translatedFormat('F') }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded bg-primary-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-primary-700 transition">
            Cek Validasi
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

    {{-- ===== Tabel Daftar Validasi ===== --}}
    <div class="overflow-hidden rounded border border-paper-200">
        <table class="w-full text-sm">
            <thead class="bg-paper-100 text-xs uppercase text-ink-600">
                <tr>
                    <th class="px-4 py-2 text-left w-8">#</th>
                    <th class="px-4 py-2 text-left">Validasi</th>
                    <th class="px-4 py-2 text-center w-24">Status</th>
                    <th class="px-4 py-2 text-left">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-paper-100">
                @foreach($validasi as $v)
                    <tr class="{{ $v['lulus'] ? '' : 'bg-red-50' }}">
                        <td class="px-4 py-3 text-ink-500">{{ $v['no'] }}</td>
                        <td class="px-4 py-3 font-medium text-ink-800">{{ $v['label'] }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($v['lulus'])
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    Lulus
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700">
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                    Gagal
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-ink-600">
                            {{ $v['detail'] ?? '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ===== Form Eksekusi Tutup Bulan ===== --}}
    <div class="mt-6">
        @if($semuaLulus)
            <div class="mb-4 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <strong>⚠️ Perhatian:</strong>
                Setelah periode ini ditutup, semua input jurnal dengan tanggal di bulan
                <strong>{{ \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->translatedFormat('F Y') }}</strong>
                akan ditolak secara otomatis oleh sistem.
                Koreksi hanya bisa dilakukan melalui <strong>Jurnal Pembalik</strong> di bulan berjalan.
            </div>

            @php $namaBulan = \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->translatedFormat('F Y'); @endphp

            <form method="POST" action="{{ route('akuntansi.tutup-bulan.store') }}"
                  onsubmit="return confirm('Anda yakin ingin menutup periode {{ $namaBulan }}? Tindakan ini tidak bisa dibatalkan.')">
                @csrf
                <input type="hidden" name="tahun" value="{{ $tahun }}">
                <input type="hidden" name="bulan" value="{{ $bulan }}">
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded bg-red-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-red-700 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Tutup Periode {{ \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->translatedFormat('F Y') }}
                </button>
            </form>
        @else
            <p class="rounded border border-paper-200 bg-paper-50 px-4 py-3 text-sm text-ink-600">
                🔒 Tombol Tutup Bulan akan muncul setelah semua validasi di atas berstatus <strong>Lulus</strong>.
            </p>
        @endif
    </div>
</x-layouts.app>
