<?php

namespace App\Http\Requests;

use App\Enums\KategoriPersonel;
use App\Models\Personel;
use App\Support\ServerClock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi Master Personel (fitur.pdf Tahap 3).
 *
 * Kolom identitas resmi (`nama_lengkap`, `jabatan_id`) hanya boleh diubah
 * pemegang izin ubah Master Personel; Staf ditolak Policy sebelum validasi
 * berjalan. `status_aktif` tidak diterima dari formulir ubah — perubahan status
 * hanya lewat aksi nonaktifkan agar tercatat sebagai peristiwa tersendiri.
 */
class SimpanPersonelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $personel = $this->route('personel');

        return $personel instanceof Personel
            ? $this->user()->can('update', $personel)
            : $this->user()->can('create', Personel::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $personel = $this->route('personel');
        $id = $personel instanceof Personel ? $personel->getKey() : null;

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('personel', 'user_id')->ignore($id),
            ],
            'nip' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('personel', 'nip')->ignore($id),
            ],
            'nama_lengkap' => ['required', 'string', 'max:150'],
            'jabatan_id' => ['required', 'integer', Rule::exists('jabatans', 'id')],
            'bidang_id' => ['nullable', 'integer', Rule::exists('bidangs', 'id')],
            'kategori' => ['required', Rule::enum(KategoriPersonel::class)],
            'tanggal_masuk' => ['nullable', 'date', 'before_or_equal:'.ServerClock::now()->toDateString()],
            'telepon' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'akun pengguna',
            'nip' => 'ID personel',
            'nama_lengkap' => 'nama resmi',
            'jabatan_id' => 'jabatan',
            'bidang_id' => 'bidang kerja',
            'kategori' => 'kategori personel',
            'tanggal_masuk' => 'tanggal masuk',
            'telepon' => 'telepon',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.unique' => 'Akun ini sudah tertaut ke personel lain.',
            'nip.unique' => 'ID personel sudah dipakai.',
            'telepon.regex' => 'Telepon hanya boleh berisi angka, spasi, tanda hubung, dan tanda kurung.',
        ];
    }
}
