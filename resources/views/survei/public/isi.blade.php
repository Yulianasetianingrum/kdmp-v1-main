<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <title>Form Survei Desa {{ $sesi->wilayah->nama ?? '' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper-100 font-sans text-ink-900 antialiased min-h-screen pb-10">
    
    <header class="bg-merah-600 text-white shadow-md p-4 sticky top-0 z-10">
        <div class="max-w-xl mx-auto flex flex-col">
            <h1 class="font-semibold text-lg">Survei KDMP</h1>
            <p class="text-sm opacity-90">{{ $sesi->wilayah->nama ?? 'Wilayah Tidak Diketahui' }} &bull; Periode {{ $sesi->bulan }}/{{ $sesi->tahun }}</p>
        </div>
    </header>

    <main class="max-w-xl mx-auto p-4 mt-4">
        @if(session('success'))
            <div class="bg-sawah-100 border border-sawah-300 text-sawah-800 px-4 py-3 rounded-md mb-6 shadow-sm">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('survei.public.store', $sesi->token_publik) }}" method="POST" class="space-y-6">
            @csrf

            @if($pertanyaans->isEmpty())
                <div class="bg-white p-6 rounded-md shadow-sm border border-paper-200 text-center text-ink-600">
                    Belum ada pertanyaan yang dikonfigurasi untuk survei ini.
                </div>
            @endif

            @foreach($pertanyaans as $p)
                <div class="bg-white p-5 rounded-md shadow-sm border border-paper-200">
                    <label for="p_{{ $p->id }}" class="block font-medium text-ink-800 mb-2">
                        {{ $p->teks_pertanyaan }}
                        @if($p->wajib_diisi)
                            <span class="text-red-500">*</span>
                        @endif
                    </label>
                    
                    <div class="flex items-center gap-2 relative">
                        <input 
                            type="{{ strtolower($p->tipe_jawaban) === 'angka' ? 'number' : 'text' }}" 
                            id="p_{{ $p->id }}"
                            name="jawaban[{{ $p->id }}]"
                            {{ strtolower($p->tipe_jawaban) === 'angka' ? 'step=any' : '' }}
                            {{ $p->wajib_diisi ? 'required' : '' }}
                            class="w-full rounded-md border border-paper-300 bg-white px-3 py-2.5 text-sm focus:border-merah-500 focus:ring-1 focus:ring-merah-500 focus:outline-none placeholder:text-ink-400"
                            placeholder="Ketik jawaban di sini..."
                        >
                        
                        <!-- Mockup Microphone Button -->
                        <button type="button" onclick="alert('Fitur input suara belum terhubung ke API Speech-to-Text.')" class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-full bg-paper-100 text-ink-600 hover:bg-paper-200 hover:text-merah-600 transition-colors border border-paper-200" title="Gunakan suara">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                            </svg>
                        </button>
                    </div>
                    
                    @if($p->satuan)
                        <p class="text-xs text-ink-500 mt-1">Satuan: {{ $p->satuan }}</p>
                    @endif
                </div>
            @endforeach

            @if($pertanyaans->isNotEmpty())
                <button type="submit" class="w-full bg-merah-600 text-white font-medium text-lg py-3 rounded-md shadow-md hover:bg-merah-700 active:bg-merah-800 transition-colors">
                    Kirim Data Survei
                </button>
            @endif
        </form>
    </main>

</body>
</html>
