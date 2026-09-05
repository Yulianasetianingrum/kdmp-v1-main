<x-layouts.app title="Catat Pelunasan" eyebrow="Keuangan">

    <div class="mx-auto max-w-4xl">

        {{-- Error global --}}
        @if (session('error'))
            <div class="mb-5 rounded-sm border border-merah-200 bg-merah-50 p-4 text-sm text-merah-800">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-5 rounded-sm border border-merah-200 bg-merah-50 p-4 text-sm text-merah-800">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('keuangan.pelunasan.store') }}" id="form-pelunasan">
            @csrf

            {{-- ============================================================
                 BAGIAN 1: Header Transaksi
            ============================================================ --}}
            <div class="mb-6 rounded-sm border border-paper-300 bg-paper-50 p-6">
                <h2 class="mb-5 text-sm font-semibold uppercase tracking-wider text-ink-700">
                    Informasi Transaksi
                </h2>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

                    {{-- Jenis --}}
                    <div>
                        <label for="jenis" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-ink-600">
                            Jenis Transaksi <span class="text-merah-500">*</span>
                        </label>
                        <select
                            id="jenis"
                            name="jenis"
                            required
                            class="w-full rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm text-ink-900 focus:border-merah-400 focus:outline-none @error('jenis') border-merah-400 @enderror"
                        >
                            <option value="">— Pilih jenis —</option>
                            <option value="terima_piutang" @selected(old('jenis') === 'terima_piutang')>
                                Terima Pelunasan Piutang (dari Anggota/Pembeli)
                            </option>
                            <option value="bayar_hutang" @selected(old('jenis') === 'bayar_hutang')>
                                Bayar Hutang ke Supplier
                            </option>
                        </select>
                    </div>

                    {{-- Tanggal --}}
                    <div>
                        <label for="tanggal" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-ink-600">
                            Tanggal <span class="text-merah-500">*</span>
                        </label>
                        <input
                            type="date"
                            id="tanggal"
                            name="tanggal"
                            value="{{ old('tanggal', date('Y-m-d')) }}"
                            required
                            class="w-full rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm text-ink-900 focus:border-merah-400 focus:outline-none @error('tanggal') border-merah-400 @enderror"
                        >
                    </div>

                    {{-- Pihak --}}
                    <div>
                        <label for="id_pihak" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-ink-600">
                            Pihak <span class="text-merah-500">*</span>
                        </label>
                        <select
                            id="id_pihak"
                            name="id_pihak"
                            required
                            class="w-full rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm text-ink-900 focus:border-merah-400 focus:outline-none @error('id_pihak') border-merah-400 @enderror"
                        >
                            <option value="">— Pilih pihak —</option>
                            @foreach ($pihaks as $pihak)
                                <option value="{{ $pihak->id_pihak }}" @selected(old('id_pihak') == $pihak->id_pihak)>
                                    {{ $pihak->nama }}
                                    @if($pihak->tipe) ({{ $pihak->tipe }}) @endif
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-ink-500">Pilih jenis dulu agar daftar piutang/hutang muncul di bawah.</p>
                    </div>

                    {{-- Kas/Bank --}}
                    <div>
                        <label for="id_kas_bank" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-ink-600">
                            Kas / Bank <span class="text-merah-500">*</span>
                        </label>
                        <select
                            id="id_kas_bank"
                            name="id_kas_bank"
                            required
                            class="w-full rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm text-ink-900 focus:border-merah-400 focus:outline-none @error('id_kas_bank') border-merah-400 @enderror"
                        >
                            <option value="">— Pilih kas/bank —</option>
                            @foreach ($kasBanks as $kas)
                                <option value="{{ $kas->id_kas_bank }}" @selected(old('id_kas_bank') == $kas->id_kas_bank)>
                                    {{ $kas->nama }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Catatan --}}
                    <div class="sm:col-span-2">
                        <label for="catatan" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-ink-600">
                            Catatan (opsional)
                        </label>
                        <textarea
                            id="catatan"
                            name="catatan"
                            rows="2"
                            placeholder="Keterangan tambahan…"
                            class="w-full rounded-sm border border-paper-300 bg-white px-3 py-2 text-sm text-ink-900 placeholder:text-ink-400 focus:border-merah-400 focus:outline-none"
                        >{{ old('catatan') }}</textarea>
                    </div>

                </div>
            </div>

            {{-- ============================================================
                 BAGIAN 2: Tabel Piutang / Hutang Terbuka
            ============================================================ --}}
            <div class="mb-6 rounded-sm border border-paper-300 bg-paper-50">
                <div class="flex items-center justify-between border-b border-paper-300 px-6 py-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-700" id="tabel-label">
                        Pilih Piutang / Hutang yang Dibayar
                    </h2>
                    <span id="loading-indicator" class="hidden text-xs text-ink-500">Memuat data…</span>
                </div>

                {{-- Placeholder saat belum pilih pihak --}}
                <div id="tabel-placeholder" class="px-6 py-10 text-center text-sm text-ink-600/50">
                    Pilih <strong>Jenis</strong> dan <strong>Pihak</strong> di atas untuk menampilkan daftar piutang/hutang terbuka.
                </div>

                {{-- Tabel akan di-inject oleh JS --}}
                <div id="tabel-wrapper" class="hidden">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-paper-300 bg-paper-200/60 font-mono text-[11px] uppercase tracking-wide text-ink-600/70">
                                <th class="px-4 py-3 font-medium w-8">
                                    <input type="checkbox" id="check-all" class="rounded" title="Pilih semua">
                                </th>
                                <th class="px-4 py-3 font-medium">Sumber</th>
                                <th class="px-4 py-3 font-medium">Tanggal</th>
                                <th class="px-4 py-3 font-medium">Jatuh Tempo</th>
                                <th class="px-4 py-3 text-right font-medium">Nilai Awal (Rp)</th>
                                <th class="px-4 py-3 text-right font-medium">Sudah Dibayar (Rp)</th>
                                <th class="px-4 py-3 text-right font-medium">Sisa (Rp)</th>
                                <th class="px-4 py-3 text-right font-medium">Bayar Sekarang (Rp)</th>
                            </tr>
                        </thead>
                        <tbody id="tabel-body">
                            {{-- Diisi oleh JS --}}
                        </tbody>
                    </table>

                    {{-- Kosong --}}
                    <div id="tabel-kosong" class="hidden px-6 py-10 text-center text-sm text-ink-600/50">
                        Pihak ini tidak memiliki piutang/hutang yang belum lunas.
                    </div>
                </div>
            </div>

            {{-- ============================================================
                 BAGIAN 3: Ringkasan & Submit
            ============================================================ --}}
            <div class="mb-6 flex items-center justify-between gap-4 rounded-sm border border-paper-300 bg-paper-100 px-6 py-4">
                <div>
                    <p class="text-xs uppercase tracking-wider text-ink-500">Total Pembayaran</p>
                    <p class="mt-0.5 text-xl font-bold text-ink-900" id="summary-total">Rp 0</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('keuangan.pelunasan.index') }}"
                       class="rounded-sm border border-paper-300 px-4 py-2 text-sm text-ink-700 hover:border-merah-400 bg-white">
                        Batal
                    </a>
                    <button
                        type="submit"
                        id="btn-submit"
                        disabled
                        class="rounded-sm bg-merah-500 px-5 py-2 text-sm font-medium text-white hover:bg-merah-600 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Simpan & Posting Jurnal
                    </button>
                </div>
            </div>

        </form>
    </div>

    {{-- ====================================================================
         JavaScript: AJAX load piutang/hutang + interaksi tabel
    ==================================================================== --}}
    <script>
    (function () {
        const elJenis    = document.getElementById('jenis');
        const elPihak    = document.getElementById('id_pihak');
        const elPlaceholder = document.getElementById('tabel-placeholder');
        const elWrapper  = document.getElementById('tabel-wrapper');
        const elBody     = document.getElementById('tabel-body');
        const elKosong   = document.getElementById('tabel-kosong');
        const elLoading  = document.getElementById('loading-indicator');
        const elTotal    = document.getElementById('summary-total');
        const elSubmit   = document.getElementById('btn-submit');
        const elCheckAll = document.getElementById('check-all');

        let rowData = []; // data JSON dari server

        function formatRp(num) {
            return 'Rp ' + num.toLocaleString('id-ID', { minimumFractionDigits: 2 });
        }

        function hitungTotal() {
            let total = 0;
            document.querySelectorAll('.input-nilai-bayar').forEach(function (inp) {
                const cb = inp.closest('tr').querySelector('.cb-baris');
                if (cb && cb.checked) {
                    total += parseFloat(inp.value) || 0;
                }
            });
            elTotal.textContent = formatRp(total);
            const adaCentang = document.querySelectorAll('.cb-baris:checked').length > 0;
            elSubmit.disabled = !adaCentang || total <= 0;
        }

        function renderTabel(data) {
            rowData = data;
            elBody.innerHTML = '';

            if (data.length === 0) {
                elWrapper.classList.remove('hidden');
                elKosong.classList.remove('hidden');
                elPlaceholder.classList.add('hidden');
                hitungTotal();
                return;
            }

            elKosong.classList.add('hidden');
            elWrapper.classList.remove('hidden');
            elPlaceholder.classList.add('hidden');

            data.forEach(function (row, idx) {
                const tr = document.createElement('tr');
                tr.className = 'border-b border-paper-200 last:border-0 hover:bg-paper-100/50';

                const inputName = row.type === 'piutang'
                    ? `detail[${idx}][id_piutang]`
                    : `detail[${idx}][id_hutang]`;

                const badgeWarna = row.status === 'belum_lunas'
                    ? 'bg-merah-100 text-merah-700'
                    : 'bg-amber-100 text-amber-700';

                tr.innerHTML = `
                    <td class="px-4 py-3">
                        <input type="checkbox" class="cb-baris rounded" data-idx="${idx}" data-sisa="${row.sisa}">
                        <input type="hidden" name="${inputName}" value="${row.id}">
                    </td>
                    <td class="px-4 py-3 text-ink-800">
                        <span class="rounded-full px-2 py-0.5 text-xs ${badgeWarna}">${row.sumber_tipe}</span>
                        <span class="ml-1 font-mono text-xs text-ink-500">${row.kode_akun}</span>
                    </td>
                    <td class="px-4 py-3 text-ink-700">${row.tanggal}</td>
                    <td class="px-4 py-3 text-ink-700">${row.jatuh_tempo}</td>
                    <td class="px-4 py-3 text-right text-ink-900">${formatRp(row.nilai_awal)}</td>
                    <td class="px-4 py-3 text-right text-ink-700">${formatRp(row.nilai_terbayar)}</td>
                    <td class="px-4 py-3 text-right font-medium text-ink-900">${formatRp(row.sisa)}</td>
                    <td class="px-4 py-3 text-right">
                        <input
                            type="number"
                            name="detail[${idx}][nilai_bayar]"
                            class="input-nilai-bayar w-36 rounded-sm border border-paper-300 bg-white px-2 py-1 text-right text-sm focus:border-merah-400 focus:outline-none disabled:bg-paper-100 disabled:text-ink-400"
                            min="0.01"
                            max="${row.sisa}"
                            step="0.01"
                            value="${row.sisa}"
                            disabled
                        >
                    </td>
                `;
                elBody.appendChild(tr);
            });

            // Event: centang baris → aktifkan input
            document.querySelectorAll('.cb-baris').forEach(function (cb) {
                cb.addEventListener('change', function () {
                    const idx  = this.dataset.idx;
                    const sisa = parseFloat(this.dataset.sisa);
                    const inp  = document.querySelector(`input[name="detail[${idx}][nilai_bayar]"]`);
                    inp.disabled = !this.checked;
                    if (this.checked && (!inp.value || parseFloat(inp.value) <= 0)) {
                        inp.value = sisa.toFixed(2);
                    }
                    hitungTotal();
                });
            });

            // Event: ubah nilai bayar → hitung ulang total
            document.querySelectorAll('.input-nilai-bayar').forEach(function (inp) {
                inp.addEventListener('input', hitungTotal);
            });

            hitungTotal();
        }

        // Check-all
        elCheckAll.addEventListener('change', function () {
            document.querySelectorAll('.cb-baris').forEach(function (cb) {
                if (cb.checked !== elCheckAll.checked) {
                    cb.checked = elCheckAll.checked;
                    cb.dispatchEvent(new Event('change'));
                }
            });
        });

        function loadTerbuka() {
            const jenis    = elJenis.value;
            const idPihak  = elPihak.value;

            if (!jenis || !idPihak) {
                elWrapper.classList.add('hidden');
                elPlaceholder.classList.remove('hidden');
                elPlaceholder.textContent = 'Pilih Jenis dan Pihak di atas untuk menampilkan daftar piutang/hutang terbuka.';
                hitungTotal();
                return;
            }

            elLoading.classList.remove('hidden');
            elWrapper.classList.add('hidden');
            elPlaceholder.classList.add('hidden');

            const url = `{{ route('keuangan.pelunasan.terbuka', ':pihak') }}`
                .replace(':pihak', idPihak) + `?jenis=${jenis}`;

            fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (res) {
                if (!res.ok) throw new Error('Gagal memuat data.');
                return res.json();
            })
            .then(function (data) {
                elLoading.classList.add('hidden');
                renderTabel(data);
            })
            .catch(function (err) {
                elLoading.classList.add('hidden');
                elPlaceholder.classList.remove('hidden');
                elPlaceholder.textContent = 'Gagal memuat data: ' + err.message;
            });
        }

        elJenis.addEventListener('change', loadTerbuka);
        elPihak.addEventListener('change', loadTerbuka);
    })();
    </script>

</x-layouts.app>
