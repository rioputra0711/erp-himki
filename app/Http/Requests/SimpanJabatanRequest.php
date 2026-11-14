<?php

namespace App\Http\Requests;

use App\Enums\KategoriPersonel;
use App\Models\Jabatan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanJabatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $jabatan = $this->route('jabatan');

        return $jabatan instanceof Jabatan
            ? $this->user()->can('update', $jabatan)
            : $this->user()->can('create', Jabatan::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $jabatan = $this->route('jabatan');
        $id = $jabatan instanceof Jabatan ? $jabatan->getKey() : null;

        return [
            'kode' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('jabatans', 'kode')->ignore($id),
            ],
            'nama' => [
                'required',
                'string',
                'max:100',
                Rule::unique('jabatans', 'nama')->ignore($id),
            ],
            'kelompok' => ['required', Rule::enum(KategoriPersonel::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode.regex' => 'Kode jabatan hanya boleh huruf kapital, angka, dan garis bawah.',
        ];
    }
}
