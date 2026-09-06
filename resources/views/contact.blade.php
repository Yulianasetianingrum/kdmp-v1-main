<x-layouts.guest title="Kontak — KDMP">
    <div class="mx-auto flex min-h-screen max-w-3xl flex-col justify-center px-6 py-16">
        <h1 class="font-display text-4xl font-semibold text-paper-50">Hubungi Kami</h1>
        <p class="mt-4 text-paper-300/80">
            Punya pertanyaan atau butuh bantuan terkait sistem ini? Silakan isi form di bawah ini.
        </p>

        <form class="mt-8 space-y-6">
            <div>
                <label for="name" class="block text-sm font-medium text-paper-200">Nama Lengkap</label>
                <input type="text" id="name" name="name" class="mt-2 block w-full rounded-sm border border-ink-700 bg-ink-900 px-4 py-2 text-paper-100 focus:border-merah-500 focus:outline-none focus:ring-1 focus:ring-merah-500" placeholder="Masukkan nama Anda">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-paper-200">Alamat Email</label>
                <input type="email" id="email" name="email" class="mt-2 block w-full rounded-sm border border-ink-700 bg-ink-900 px-4 py-2 text-paper-100 focus:border-merah-500 focus:outline-none focus:ring-1 focus:ring-merah-500" placeholder="contoh@email.com">
            </div>

            <div>
                <label for="message" class="block text-sm font-medium text-paper-200">Pesan</label>
                <textarea id="message" name="message" rows="5" class="mt-2 block w-full rounded-sm border border-ink-700 bg-ink-900 px-4 py-2 text-paper-100 focus:border-merah-500 focus:outline-none focus:ring-1 focus:ring-merah-500" placeholder="Tuliskan pesan Anda di sini..."></textarea>
            </div>

            <button type="button" class="inline-flex w-full justify-center rounded-sm bg-merah-500 px-5 py-3 text-sm font-medium text-paper-50 hover:bg-merah-600">
                Kirim Pesan
            </button>
        </form>

        <div class="mt-10 border-t border-ink-700 pt-6 text-center text-sm text-paper-400">
            <p>Atau hubungi kami via email: <a href="mailto:support@kdmp.id" class="text-merah-400 hover:underline">support@kdmp.id</a></p>
        </div>
    </div>
</x-layouts.guest>
