<?php

namespace App\Http\Requests\Keuangan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePelunasanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'jenis'                  => ['required', Rule::in(['terima_piutang', 'bayar_hutang'])],
            'id_pihak'               => ['required', 'exists:master_pihak,id_pihak'],
            'tanggal'                => ['required', 'date'],
            'id_kas_bank'            => ['required', 'exists:master_kas_bank,id_kas_bank'],
            'catatan'                => ['nullable', 'string', 'max:500'],

            // Detail wajib ada minimal 1 baris
            'detail'                 => ['required', 'array', 'min:1'],
            'detail.*.nilai_bayar'   => ['required', 'numeric', 'min:0.01'],

            // Salah satu dari keduanya wajib ada, tergantung jenis
            'detail.*.id_piutang'   => ['nullable', 'integer'],
            'detail.*.id_hutang'    => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'detail.required'              => 'Pilih minimal satu piutang/hutang yang akan dilunasi.',
            'detail.min'                   => 'Pilih minimal satu piutang/hutang yang akan dilunasi.',
            'detail.*.nilai_bayar.min'     => 'Nilai bayar harus lebih dari 0.',
            'detail.*.nilai_bayar.required'=> 'Nilai bayar wajib diisi untuk setiap baris.',
        ];
    }
}
