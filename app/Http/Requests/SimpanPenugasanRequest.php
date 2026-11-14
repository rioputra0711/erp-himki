<?php

namespace App\Http\Requests;

use App\Enums\KategoriTugas;
use App\Enums\PrioritasTugas;
use App\Models\Assignment;
use App\Support\ServerClock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi formulir penugasan (fitur.pdf Tahap 5).
 *
 * Sumber perintah, pemberi tugas, dan penerus arahan tidak pernah diterima dari
 * formulir: model mengisinya dari sesi. Yang divalidasi di sini hanya data
 * pekerjaan, dan setiap rujukan (PIC, JD, dependensi, arahan asal) harus benar-
 * benar ada. PIC dibatasi ke personel aktif agar tugas tidak jatuh ke akun mati.
 */
class SimpanPenugasanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tugas = $this->route('tugas');

        return $tugas instanceof Assignment
            ? $this->user()->can('sunting', $tugas)
            : $this->user()->can('create', Assignment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:200'],
            'deskripsi' => ['required', 'string', 'max:5000'],
            'job_description_id' => ['nullable', 'integer', Rule::exists('job_descriptions', 'id')],
            'pic_id' => [
                'required',
                'integer',
                Rule::exists('personel', 'id')->where('status_aktif', true),
            ],
            'kategori' => ['required', Rule::enum(KategoriTugas::class)],
            'target_keluaran' => ['required', 'string', 'max:2000'],
            'prioritas' => ['required', Rule::enum(PrioritasTugas::class)],
            'tanggal_mulai' => [
                'required',
                'date',
                'after_or_equal:'.ServerClock::now()->subDays(30)->toDateString(),
            ],
            'tenggat' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'dependensi_id' => ['nullable', 'integer', Rule::exists('assignments', 'id')],
            'sumber_arahan_id' => ['nullable', 'integer', Rule::exists('feedback', 'id')],
            'alasan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'judul' => 'judul tugas',
            'deskripsi' => 'uraian tugas',
            'job_description_id' => 'job description',
            'pic_id' => 'penanggung jawab',
            'kategori' => 'kategori tugas',
            'target_keluaran' => 'target keluaran',
            'prioritas' => 'prioritas',
            'tanggal_mulai' => 'tanggal mulai',
            'tenggat' => 'tenggat',
            'dependensi_id' => 'tugas dependensi',
            'sumber_arahan_id' => 'arahan asal',
            'alasan' => 'alasan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pic_id.exists' => 'PIC harus personel aktif.',
            'tenggat.after_or_equal' => 'Tenggat tidak boleh lebih awal dari tanggal mulai.',
        ];
    }
}
