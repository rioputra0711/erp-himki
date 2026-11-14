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
            'alasan' => [
                Rule::requiredIf(fn (): bool => $jd instanceof JobDescription && $this->adaPerubahanSensitif($jd)),
                'nullable',
                'string',
                'min:10',
                'max:500',
            ],
        ];
    }

    /**
     * Apakah formulir ini menyentuh kolom yang perubahannya wajib alasan.
     *
     * Hanya berlaku untuk draf: setelah ditetapkan, perubahan sudah ditolak
     * seluruhnya oleh model (harus lewat versi baru).
     */
    private function adaPerubahanSensitif(JobDescription $jd): bool
    {
        if ($jd->ditetapkan_pada !== null) {
            return false;
        }

        foreach (['target', 'indikator', 'frekuensi', 'kategori', 'uraian', 'berlaku_mulai'] as $kolom) {
            if ((string) $this->input($kolom) !== (string) $jd->getRawOriginal($kolom)) {
                return true;
            }
        }

        return false;
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
            'alasan' => 'alasan perubahan',
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
            'alasan.required' => 'Perubahan target, indikator, uraian, kategori, frekuensi, atau tanggal berlaku wajib menyertakan alasan.',
        ];
    }
}
