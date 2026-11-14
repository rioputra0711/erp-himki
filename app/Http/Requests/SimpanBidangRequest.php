<?php

namespace App\Http\Requests;

use App\Models\Bidang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanBidangRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bidang = $this->route('bidang');

        return $bidang instanceof Bidang
            ? $this->user()->can('update', $bidang)
            : $this->user()->can('create', Bidang::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $bidang = $this->route('bidang');
        $id = $bidang instanceof Bidang ? $bidang->getKey() : null;

        return [
            'kode' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('bidangs', 'kode')->ignore($id),
            ],
            'nama' => [
                'required',
                'string',
                'max:100',
                Rule::unique('bidangs', 'nama')->ignore($id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode.regex' => 'Kode bidang hanya boleh huruf kapital, angka, dan garis bawah.',
        ];
    }
}
