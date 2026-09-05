<x-layouts.app :title="$title" eyebrow="Daftar Data">
    <div class="mb-5 flex items-center justify-between gap-4">
        <form method="GET" class="flex items-center gap-2">
            <input
                type="text"
                name="q"
                value="{{ request('q') }}"
                placeholder="Cari..."
                class="w-64 rounded-sm border border-paper-300 bg-paper-50 px-3 py-2 text-sm placeholder:text-ink-600/40 focus:border-merah-400 focus:outline-none"
            >
            <button class="rounded-sm border border-paper-300 px-3 py-2 text-sm text-ink-700 hover:border-merah-400">
                Cari
            </button>
        </form>

        @if (Route::has($routeBase . '.create'))
            <a href="{{ route($routeBase . '.create') }}"
               class="rounded-sm bg-merah-500 px-4 py-2 text-sm font-medium text-paper-50 hover:bg-merah-600">
                + Tambah
            </a>
        @else
            <span class="rounded-sm border border-paper-300 px-3 py-2 text-xs text-ink-600/60">
                Read-only — dibuat otomatis oleh service transaksi
            </span>
        @endif
    </div>

    <div class="overflow-hidden rounded-sm border border-paper-300 bg-paper-50">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-paper-300 bg-paper-200/60 font-mono text-[11px] uppercase tracking-wide text-ink-600/70">
                    @foreach ($columns as $col)
                        <th class="px-4 py-3 font-medium">{{ str_replace('_', ' ', $col) }}</th>
                    @endforeach
                    <th class="px-4 py-3 text-right font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr class="border-b border-paper-200 last:border-0 hover:bg-paper-100/70">
                        @foreach ($columns as $col)
                            <td class="px-4 py-3 text-ink-800">
                                @php $value = $item->{$col} ?? null; @endphp
                                @if (is_bool($value))
                                    <span class="rounded-full px-2 py-0.5 text-xs {{ $value ? 'bg-sawah-100 text-sawah-600' : 'bg-paper-200 text-ink-600' }}">
                                        {{ $value ? 'Ya' : 'Tidak' }}
                                    </span>
                                @elseif ($value instanceof \Illuminate\Support\Carbon)
                                    {{ $value->format('d M Y') }}
                                @else
                                    {{ \Illuminate\Support\Str::limit((string) $value, 40) }}
                                @endif
                            </td>
                        @endforeach
                        <td class="px-4 py-3 text-right">
                            @if (Route::has($routeBase . '.show'))
                                <a href="{{ route($routeBase . '.show', $item->getKey()) }}"
                                   class="text-xs font-medium text-ink-700 hover:text-merah-600 mr-2">Detail</a>
                            @endif
                            @if (Route::has($routeBase . '.destroy'))
                                <button type="button" class="text-xs font-medium text-red-600 hover:text-red-800" onclick="openDeleteModal('{{ route($routeBase . '.destroy', $item->getKey()) }}')">Hapus</button>
                            @endif
                            @if (!Route::has($routeBase . '.destroy') && !Route::has($routeBase . '.show'))
                                <span class="text-xs text-ink-600/40">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) + 1 }}" class="px-4 py-10 text-center text-sm text-ink-600/60">
                            Belum ada data. Silahkan tambah data baru
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $items->links() }}
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-ink-900/50 backdrop-blur-sm">
        <div class="w-full max-w-sm rounded-sm border border-paper-300 bg-paper-50 p-6 shadow-xl">
            <h3 class="mb-2 font-display text-lg font-semibold text-ink-900">Konfirmasi Hapus</h3>
            <p class="mb-6 text-sm text-ink-600">Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.</p>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeDeleteModal()" class="rounded-sm bg-paper-200 px-4 py-2 text-sm font-medium text-ink-700 hover:bg-paper-300">Batal</button>
                <form id="deleteForm" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-sm bg-merah-500 px-4 py-2 text-sm font-medium text-white hover:bg-merah-600">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openDeleteModal(actionUrl) {
            document.getElementById('deleteForm').action = actionUrl;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('flex');
        }
    </script>
</x-layouts.app>
