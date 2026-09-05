<x-layouts.app title="Rekonsiliasi Konsinyasi" eyebrow="Validasi #8 Tutup Bulan">
    <div class="mb-5 rounded-sm border border-paper-300 bg-paper-50 p-4 text-sm text-ink-700">
        Daftar ini membaca <code class="rounded bg-paper-200 px-1 py-0.5 font-mono text-xs">v_rekonsiliasi_konsinyasi</code>
        — hanya menampilkan pasangan desa di mana Piutang Konsinyasi pemilik
        <strong>tidak sama</strong> dengan Hutang Konsinyasi penerima. Idealnya
        daftar ini selalu kosong.
    </div>

    <div class="overflow-hidden rounded-sm border border-paper-300 bg-paper-50">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-paper-300 bg-paper-200/60 font-mono text-[11px] uppercase tracking-wide text-ink-600/70">
                    <th class="px-4 py-3">Kolom</th>
                    <th class="px-4 py-3">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($selisih as $row)
                    @foreach ((array) $row as $col => $val)
                        <tr class="border-b border-paper-200 last:border-0">
                            <td class="px-4 py-2 font-mono text-xs text-ink-600">{{ $col }}</td>
                            <td class="px-4 py-2">{{ $val }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="2" class="px-4 py-10 text-center text-sm text-sawah-600">
                            Kosong — semua piutang/hutang konsinyasi antar desa cocok. (Atau view SQL-nya
                            belum dibuat — dibahas saat pendalaman modul Finance.)
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
