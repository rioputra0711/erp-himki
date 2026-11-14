<?php

namespace App\Http\Requests;

use App\Enums\FrekuensiTugas;
use App\Enums\KategoriTugas;
use App\Models\JobDescription;
use App\Support\ServerClock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi Job Description (fitur.pdf Tahap 3).
 *
 * `ditetapkan_pada` dan `ditetapkan_oleh_id` tidak pernah diterima dari formulir:
 * keduanya diisi server pada aksi "tetapkan" agar jejak waktu dan pelaku tidak
 * dapat dipalsukan.
 */
class SimpanJobDescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $jd = $this->route('job_description');

        return $jd instanceof JobDescription
            ? $this->user()->can('update', $jd)
            : $this->user()->can('create', JobDescription::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $jd = $this->route('job_description');
        $id = $jd instanceof JobDescription ? $jd->getKey() : null;

        return [
            'jabatan_id' => ['required', 'integer', Rule::exists('jabatans', 'id')],
            'kode' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('job_descriptions', 'kode')
                    ->where(fn ($q) => $q->where('jabatan_id', $this->integer('jabatan_id')))
                    ->where(fn ($q) => $q->where('versi', $jd instanceof JobDescription ? $jd->versi : 1))
                    ->ignore($id),
            ],
            'kategori' => ['required', Rule::enum(KategoriTugas::class)],
            'uraian' => ['required', 'string', 'max:2000'],
            'indikator' => ['nullable', 'string', 'max:2000'],
            'target' => ['nullable', 'string', 'max:2000'],
            'frekuensi' => ['nullable', Rule::enum(FrekuensiTugas::class)],
            'berlaku_mulai' => ['nullable', 'date', 'after_or_equal:'.ServerClock::now()->toDateString()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'jabatan_id' => 'jabatan',
            'kode' => 'kode job description',
            'kategori' => 'kategori tugas',
            'uraian' => 'uraian tugas',
            'indikator' => 'indikator hasil kerja',
            'target' => 'target dasar',
            'frekuensi' => 'frekuensi pelaporan',
            'berlaku_mulai' => 'berlaku mulai',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode.regex' => 'Kode hanya boleh huruf kapital, angka, dan tanda hubung.',
            'berlaku_mulai.after_or_equal' => 'Versi baru harus berlaku mulai hari ini atau setelahnya.',
        ];
    }
}
