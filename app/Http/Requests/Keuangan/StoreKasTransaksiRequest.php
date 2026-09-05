<?php

namespace App\Http\Requests\Keuangan;

use App\Rules\TenantOwnershipRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKasTransaksiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tanggal'           => ['required', 'date'],
            'jenis'             => ['required', Rule::in(['masuk', 'keluar', 'mutasi_antar_kas'])],
            // TenantOwnershipRule: memastikan id_kas_bank milik koperasi aktif, bukan desa lain
            'id_kas_bank'       => ['required', new TenantOwnershipRule('master_kas_bank', 'id_kas_bank')],
            'id_kas_bank_tujuan'=> ['required_if:jenis,mutasi_antar_kas', 'nullable',
                                    new TenantOwnershipRule('master_kas_bank', 'id_kas_bank')],
            'kode_akun_lawan'   => ['required_unless:jenis,mutasi_antar_kas', 'nullable', 'exists:master_coa,kode_anak'],
            'nilai'             => ['required', 'numeric', 'min:1'],
            'keterangan'        => ['required', 'string'],
        ];
    }
}

