<x-layouts.app :title="$title" eyebrow="Form Belum Diimplementasikan">
    <div class="mx-auto max-w-xl rounded-sm border border-dashed border-paper-300 bg-paper-50 p-8 text-center">
        <span class="mx-auto mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-padi-100 font-mono text-sm text-padi-600">
            !
        </span>
        <h2 class="font-display text-lg font-semibold">{{ $title }}</h2>
        <p class="mt-2 text-sm text-ink-600">
            Form ini bagian dari kerangka — validasi, penyimpanan, dan (kalau
            relevan) posting jurnal belum ditulis. Ini menyusul saat modul
            terkait didalami satu per satu.
        </p>
        @if (($mode ?? null) === 'edit' && isset($item))
            <p class="mt-3 font-mono text-xs text-ink-600/60">ID: {{ $item->getKey() }}</p>
        @endif
        <a href="{{ url()->previous() }}" class="mt-5 inline-block text-sm font-medium text-merah-600 hover:underline">
            ← Kembali
        </a>
    </div>
</x-layouts.app>
