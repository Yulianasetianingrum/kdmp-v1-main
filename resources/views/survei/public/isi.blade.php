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
                    <div class="flex items-center justify-between mb-2">
                        <label for="p_{{ $p->id }}" class="block font-medium text-ink-800">
                            {{ $p->teks_pertanyaan }}
                            @if($p->wajib_diisi)
                                <span class="text-red-500">*</span>
                            @endif
                        </label>
                        <!-- TTS Speaker Button -->
                        <button type="button" onclick="speakText('{{ addslashes($p->teks_pertanyaan) }}')" class="flex-shrink-0 ml-2 h-8 w-8 flex items-center justify-center rounded-full text-ink-500 hover:bg-paper-200 hover:text-ink-700 transition-colors" title="Dengarkan pertanyaan">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="flex items-center gap-2 relative">
                        <input 
                            type="text" 
                            inputmode="{{ strtolower($p->tipe_jawaban) === 'angka' ? 'decimal' : 'text' }}"
                            id="p_{{ $p->id }}"
                            name="jawaban[{{ $p->id }}]"
                            {{ $p->wajib_diisi ? 'required' : '' }}
                            class="w-full rounded-md border border-paper-300 bg-white px-3 py-2.5 text-sm focus:border-merah-500 focus:ring-1 focus:ring-merah-500 focus:outline-none placeholder:text-ink-400"
                            placeholder="Ketik jawaban di sini..."
                        >
                        
                        <!-- STT Microphone Button -->
                        <button type="button" onclick="startSpeechToText('p_{{ $p->id }}', this)" class="btn-mic flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-full bg-paper-100 text-ink-600 hover:bg-paper-200 hover:text-merah-600 transition-colors border border-paper-200 hidden" title="Gunakan suara">
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

    <script>
        // Check if Web Speech API is supported
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        
        if (SpeechRecognition) {
            // Show mic buttons if supported
            document.querySelectorAll('.btn-mic').forEach(btn => btn.classList.remove('hidden'));
        }

        function speakText(text) {
            if ('speechSynthesis' in window) {
                // Cancel any ongoing speech
                window.speechSynthesis.cancel();

                const utterance = new SpeechSynthesisUtterance(text);
                utterance.lang = 'id-ID';
                window.speechSynthesis.speak(utterance);
            } else {
                alert('Browser Anda tidak mendukung fitur membaca teks.');
            }
        }

        function startSpeechToText(inputId, btnElement) {
            if (!SpeechRecognition) {
                alert('Browser Anda tidak mendukung fitur input suara. Silakan gunakan Google Chrome.');
                return;
            }

            const recognition = new SpeechRecognition();
            recognition.lang = 'id-ID';
            recognition.interimResults = true; // Set true agar terlihat real-time
            recognition.maxAlternatives = 1;

            // Visual feedback: change button color while listening
            const originalClass = btnElement.className;
            btnElement.classList.remove('text-ink-600', 'bg-paper-100', 'border-paper-200');
            btnElement.classList.add('bg-red-100', 'text-red-600', 'border-red-300', 'animate-pulse');

            recognition.onresult = (event) => {
                let resultText = '';
                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    resultText += event.results[i][0].transcript;
                }
                
                const inputElement = document.getElementById(inputId);
                if (inputElement) {
                    // Hapus tanda baca di akhir kalimat yang sering ditambahkan otomatis oleh Google STT (misal titik)
                    let finalStr = resultText.replace(/[.,!?]+$/, '').trim();
                    
                    // Fallback mapping for common spoken Indonesian numbers
                    const wordMap = {
                        'satu': '1', 'dua': '2', 'tiga': '3', 'empat': '4', 'lima': '5',
                        'enam': '6', 'tujuh': '7', 'delapan': '8', 'sembilan': '9', 'sepuluh': '10',
                        'sebelas': '11', 'dua belas': '12', 'nol': '0', 'kosong': '0',
                        'seratus': '100', 'seribu': '1000'
                    };
                    let textClean = resultText.toLowerCase().trim();
                    if (wordMap[textClean]) {
                        finalStr = wordMap[textClean];
                    }

                    inputElement.value = finalStr;
                }
            };

            recognition.onerror = (event) => {
                console.error('Speech recognition error', event.error);
                if (event.error === 'not-allowed') {
                    alert('Izin mikrofon ditolak. Pastikan Anda mengizinkan akses mikrofon dan situs dibuka lewat HTTPS atau localhost.');
                }
            };

            recognition.onend = () => {
                // Restore button appearance
                btnElement.className = originalClass;
            };

            recognition.start();
        }
    </script>
</body>
</html>
