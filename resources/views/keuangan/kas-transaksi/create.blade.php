<x-layouts.app title="Tambah Kas Transaksi" eyebrow="Keuangan">
    <div class="max-w-2xl overflow-hidden rounded-sm border border-paper-300 bg-paper-50 shadow-sm"
         x-data="{ jenis: '{{ old('jenis', 'masuk') }}' }">
         
        <div class="border-b border-paper-200 bg-paper-100/50 px-6 py-4">
            <h2 class="text-lg font-medium text-ink-900">Form Transaksi Kas</h2>
            <p class="mt-1 text-sm text-ink-600">Catat pemasukan, pengeluaran, atau mutasi antar rekening kas/bank.</p>
        </div>

        <div class="p-6">
            <form action="{{ route('keuangan.kas-transaksi.store') }}" method="POST" class="space-y-6">
                @csrf

                <!-- Tanggal -->
                <div>
                    <label for="tanggal" class="mb-1 block text-sm font-medium text-ink-700">Tanggal Transaksi <span class="text-merah-500">*</span></label>
                    <input type="date" id="tanggal" name="tanggal" value="{{ old('tanggal', date('Y-m-d')) }}"
                           class="block w-full rounded-sm border border-paper-300 bg-paper-50 px-3 py-2 text-ink-900 focus:border-merah-500 focus:ring-merah-500 sm:text-sm" required>
                    @error('tanggal')
                        <p class="mt-1 text-xs text-merah-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Jenis Transaksi -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink-700">Jenis Transaksi <span class="text-merah-500">*</span></label>
                    <div class="mt-2 grid grid-cols-3 gap-3">
                        <label class="flex cursor-pointer items-center justify-center rounded-sm border border-paper-300 bg-paper-50 px-3 py-2 text-sm hover:bg-paper-100"
                               :class="{'bg-sawah-50 border-sawah-300 text-sawah-700 font-medium': jenis === 'masuk'}">
                            <input type="radio" name="jenis" value="masuk" x-model="jenis" class="sr-only">
                            Kas Masuk
                        </label>
                        <label class="flex cursor-pointer items-center justify-center rounded-sm border border-paper-300 bg-paper-50 px-3 py-2 text-sm hover:bg-paper-100"
                               :class="{'bg-merah-50 border-merah-300 text-merah-700 font-medium': jenis === 'keluar'}">
                            <input type="radio" name="jenis" value="keluar" x-model="jenis" class="sr-only">
                            Kas Keluar
                        </label>
                        <label class="flex cursor-pointer items-center justify-center rounded-sm border border-paper-300 bg-paper-50 px-3 py-2 text-sm hover:bg-paper-100"
                               :class="{'bg-blue-50 border-blue-300 text-blue-700 font-medium': jenis === 'mutasi_antar_kas'}">
                            <input type="radio" name="jenis" value="mutasi_antar_kas" x-model="jenis" class="sr-only">
                            Mutasi Antar Kas
                        </label>
                    </div>
                    @error('jenis')
                        <p class="mt-1 text-xs text-merah-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Kas/Bank -->
                <div>
                    <label for="id_kas_bank" class="mb-1 block text-sm font-medium text-ink-700"
                           x-text="jenis === 'mutasi_antar_kas' ? 'Rekening Asal (Keluar)' : 'Rekening Kas/Bank'">Rekening Kas/Bank</label>
                    <select id="id_kas_bank" name="id_kas_bank" class="block w-full rounded-sm border border-paper-300 bg-paper-50 px-3 py-2 text-ink-900 focus:border-merah-500 focus:ring-merah-500 sm:text-sm" required>
                        <option value="">-- Pilih Rekening Kas/Bank --</option>
                        @foreach($kasBanks as $kas)
                            <option value="{{ $kas->id_kas_bank }}" {{ old('id_kas_bank') == $kas->id_kas_bank ? 'selected' : '' }}>
                                {{ $kas->nama }} {{ $kas->no_rekening ? ' - ' . $kas->no_rekening : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('id_kas_bank')
                        <p class="mt-1 text-xs text-merah-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Kas/Bank Tujuan (Hanya Mutasi) -->
                <div x-show="jenis === 'mutasi_antar_kas'" x-cloak>
                    <label for="id_kas_bank_tujuan" class="mb-1 block text-sm font-medium text-ink-700">Rekening Tujuan (Masuk) <span class="text-merah-500">*</span></label>
                    <select id="id_kas_bank_tujuan" name="id_kas_bank_tujuan" class="block w-full rounded-sm border border-paper-300 bg-paper-50 px-3 py-2 text-ink-900 focus:border-merah-500 focus:ring-merah-500 sm:text-sm"
                            :required="jenis === 'mutasi_antar_kas'">
                        <option value="">-- Pilih Rekening Tujuan --</option>
                        @foreach($kasBanks as $kas)
                            <option value="{{ $kas->id_kas_bank }}" {{ old('id_kas_bank_tujuan') == $kas->id_kas_bank ? 'selected' : '' }}>
                                {{ $kas->nama }} {{ $kas->no_rekening ? ' - ' . $kas->no_rekening : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('id_kas_bank_tujuan')
                        <p class="mt-1 text-xs text-merah-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Akun Lawan (Masuk/Keluar) -->
                <div x-show="jenis !== 'mutasi_antar_kas'" x-cloak>
                    <label for="kode_akun_lawan" class="mb-1 block text-sm font-medium text-ink-700"
                           x-text="jenis === 'masuk' ? 'Akun Sumber (Pendapatan/Modal/Lainnya)' : 'Akun Tujuan (Biaya/Pengeluaran/Lainnya)'">Akun Lawan</label>
                    <select id="kode_akun_lawan" name="kode_akun_lawan" class="block w-full rounded-sm border border-paper-300 bg-paper-50 px-3 py-2 text-ink-900 focus:border-merah-500 focus:ring-merah-500 sm:text-sm"
                            :required="jenis !== 'mutasi_antar_kas'">
                        <option value="">-- Pilih Akun --</option>
                        @foreach($akunLawan as $akun)
                            <option value="{{ $akun->kode_anak }}" {{ old('kode_akun_lawan') == $akun->kode_anak ? 'selected' : '' }}>
                                {{ $akun->kode_anak }} - {{ $akun->nama_rekening }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-ink-500">Pilih akun yang sesuai untuk menjurnal transaksi ini.</p>
                    @error('kode_akun_lawan')
                        <p class="mt-1 text-xs text-merah-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Nilai -->
                <div>
                    <label for="nilai" class="mb-1 block text-sm font-medium text-ink-700">Nilai (Rp) <span class="text-merah-500">*</span></label>
                    <div class="relative mt-1 rounded-sm shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <span class="text-ink-500 sm:text-sm">Rp</span>
                        </div>
                        <input type="number" id="nilai" name="nilai" value="{{ old('nilai') }}" min="1" step="0.01"
                               class="block w-full rounded-sm border border-paper-300 bg-paper-50 pl-10 pr-3 py-2 text-ink-900 focus:border-merah-500 focus:ring-merah-500 sm:text-sm" required>
                    </div>
                    @error('nilai')
                        <p class="mt-1 text-xs text-merah-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Keterangan -->
                <div>
                    <label for="keterangan" class="mb-1 block text-sm font-medium text-ink-700">Keterangan <span class="text-merah-500">*</span></label>
                    <textarea id="keterangan" name="keterangan" rows="3"
                              class="block w-full rounded-sm border border-paper-300 bg-paper-50 px-3 py-2 text-ink-900 focus:border-merah-500 focus:ring-merah-500 sm:text-sm" required>{{ old('keterangan') }}</textarea>
                    @error('keterangan')
                        <p class="mt-1 text-xs text-merah-600">{{ $message }}</p>
                    @enderror
                </div>
                
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-paper-200">
                    <a href="{{ route('keuangan.kas-transaksi.index') }}" class="rounded-sm border border-paper-300 px-4 py-2 text-sm font-medium text-ink-700 hover:bg-paper-100">
                        Batal
                    </a>
                    <button type="submit" class="rounded-sm bg-merah-500 px-4 py-2 text-sm font-medium text-paper-50 hover:bg-merah-600">
                        Simpan Transaksi
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
