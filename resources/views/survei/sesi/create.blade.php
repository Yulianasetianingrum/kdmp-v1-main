<x-layouts.app :title="$title" eyebrow="Manajemen Survei">
    <div class="mb-5">
        <h1 class="font-display text-2xl font-semibold">{{ $title }}</h1>
        <p class="mt-1 text-sm text-ink-600">Buat sesi survei baru untuk menghasilkan tautan unik pengisian data di lapangan.</p>
    </div>

    <div class="mx-auto max-w-2xl rounded-sm border border-paper-300 bg-paper-50 p-8">
        <form action="{{ route($routeBase . '.store') }}" method="POST">
            @csrf

            <div class="mb-5">
                <label for="id_wilayah" class="mb-1 block text-sm font-medium text-ink-800">Wilayah / Desa Target</label>
                <select name="id_wilayah" id="id_wilayah" required class="w-full rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm focus:border-merah-400 focus:outline-none">
                    <option value="">-- Pilih Wilayah --</option>
                    @foreach($wilayahs as $wilayah)
                        <option value="{{ $wilayah->id }}" {{ old('id_wilayah') == $wilayah->id ? 'selected' : '' }}>
                            {{ $wilayah->nama }}
                        </option>
                    @endforeach
                </select>
                @error('id_wilayah')
                    <p class="mt-1 text-xs text-merah-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-4 mb-5">
                <div>
                    <label for="tahun" class="mb-1 block text-sm font-medium text-ink-800">Tahun Periode</label>
                    <input type="number" name="tahun" id="tahun" value="{{ old('tahun', date('Y')) }}" required min="2000" max="2100" class="w-full rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm focus:border-merah-400 focus:outline-none">
                    @error('tahun')
                        <p class="mt-1 text-xs text-merah-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="bulan" class="mb-1 block text-sm font-medium text-ink-800">Bulan Periode</label>
                    <select name="bulan" id="bulan" required class="w-full rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm focus:border-merah-400 focus:outline-none">
                        <option value="">-- Pilih Bulan --</option>
                        @foreach($bulans as $key => $nama)
                            <option value="{{ $key }}" {{ old('bulan', date('n')) == $key ? 'selected' : '' }}>
                                {{ $nama }}
                            </option>
                        @endforeach
                    </select>
                    @error('bulan')
                        <p class="mt-1 text-xs text-merah-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mb-8">
                <label for="tanggal_survei" class="mb-1 block text-sm font-medium text-ink-800">Tanggal Pelaksanaan Survei</label>
                <input type="date" name="tanggal_survei" id="tanggal_survei" value="{{ old('tanggal_survei', date('Y-m-d')) }}" required class="w-full rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm focus:border-merah-400 focus:outline-none">
                @error('tanggal_survei')
                    <p class="mt-1 text-xs text-merah-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-paper-200 pt-5">
                <a href="{{ route($routeBase . '.index') }}" class="text-sm font-medium text-ink-600 hover:text-ink-800 hover:underline">Batal</a>
                <button type="submit" class="rounded-sm bg-merah-500 px-5 py-2 text-sm font-medium text-paper-50 hover:bg-merah-600">Buat Sesi & Generate Link</button>
            </div>
        </form>
    </div>
</x-layouts.app>
