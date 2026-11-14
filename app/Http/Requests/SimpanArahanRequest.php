<?php

namespace App\Http\Requests;

use App\Models\Feedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi formulir arahan (fitur.pdf Tahap 5).
 *
 * Identitas pemberi, jabatan, dan asal arahan tidak diterima dari formulir —
 * model mengisinya dari sesi. `jenis` dibatasi empat jenis non-persetujuan:
 * persetujuan formal selalu berupa baris baru dari Pengurus, bukan pilihan
 * pada formulir arahan.
 */
class SimpanArahanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Feedback::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'penerima_id' => [
                'required',
                'integer',
                Rule::exists('personel', 'id')->where('status_aktif', true),
            ],
            'jenis' => ['required', 'string', Rule::in(['arahan', 'komentar', 'masukan', 'koreksi'])],
            'isi' => ['required', 'string', 'max:5000'],
            'butuh_tindakan' => ['nullable', 'boolean'],
            'tenggat' => ['nullable', 'date', 'after_or_equal:'.now()->toDateString()],
            'lampiran_dokumen_id' => ['nullable', 'integer', Rule::exists('documents', 'id')],
            'subjek_type' => ['nullable', 'string', 'max:100'],
            'subjek_id' => ['nullable', 'integer'],
            'alasan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'penerima_id' => 'penerima arahan',
            'jenis' => 'jenis arahan',
            'isi' => 'isi arahan',
            'butuh_tindakan' => 'butuh tindakan',
            'tenggat' => 'tenggat',
            'lampiran_dokumen_id' => 'lampiran',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'penerima_id.exists' => 'Penerima arahan harus personel aktif.',
        ];
    }
}
