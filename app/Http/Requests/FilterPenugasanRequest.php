<?php

namespace App\Http\Requests;

use App\Enums\KategoriTugas;
use App\Enums\PrioritasTugas;
use App\Enums\StatusPenugasan;
use App\Enums\SumberPerintah;
use App\Models\Assignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi filter daftar penugasan (fitur.pdf Tahap 5).
 *
 * Semua nilai yang membentuk kueri berasal dari daftar tertutup (enum dan
 * allowlist kolom urut), sehingga tidak ada potongan masukan pengguna yang
 * menjadi bagian SQL.
 */
class FilterPenugasanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Assignment::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::enum(StatusPenugasan::class)],
            'kategori' => ['nullable', 'string', Rule::enum(KategoriTugas::class)],
            'prioritas' => ['nullable', 'string', Rule::enum(PrioritasTugas::class)],
            'sumber' => ['nullable', 'string', Rule::enum(SumberPerintah::class)],
            'urut' => ['nullable', 'string', Rule::in(['tenggat', 'dibuat', 'prioritas'])],
            'arah' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'q' => 'kata kunci',
            'status' => 'status',
            'kategori' => 'kategori',
            'prioritas' => 'prioritas',
            'sumber' => 'sumber perintah',
            'urut' => 'urutan',
            'arah' => 'arah urutan',
        ];
    }
}
