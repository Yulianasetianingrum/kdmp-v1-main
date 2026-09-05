<x-layouts.app :title="$judul" eyebrow="Laporan Keuangan">
    <div class="mx-auto max-w-xl rounded-sm border border-dashed border-paper-300 bg-paper-50 p-8 text-center">
        <h2 class="font-display text-lg font-semibold">{{ $judul }}</h2>
        <p class="mt-2 text-sm text-ink-700">
            Belum diimplementasikan. Laporan ini akan dihitung langsung dari
            <code class="rounded bg-paper-200 px-1 py-0.5 font-mono text-xs">jurnal_header</code> /
            <code class="rounded bg-paper-200 px-1 py-0.5 font-mono text-xs">jurnal_detail</code>
            yang berstatus POSTED — menyusul saat pendalaman modul Finance.
        </p>
    </div>
</x-layouts.app>
