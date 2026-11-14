<?php

namespace App\Http\Requests;

use App\Models\AuditEvent;
use App\Support\LabelAudit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi filter layar penelusuran audit (fitur.pdf Tahap 4).
 *
 * Semua nilai yang membentuk kueri berasal dari daftar tertutup: tindakan dan
 * objek memakai allowlist, aktor harus benar-benar ada, tanggal berformat tetap,
 * dan pengurutan dipetakan lewat `match` di controller. Dengan begitu tidak ada
 * potongan masukan pengguna yang menjadi bagian SQL.
 */
class FilterAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AuditEvent::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'tindakan' => ['nullable', 'string', Rule::in(LabelAudit::pilihanTindakan())],
            'objek' => ['nullable', 'string', Rule::in($this->pilihanObjek())],
            'aktor' => ['nullable', 'integer', 'exists:users,id'],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'urut' => ['nullable', 'string', Rule::in(['waktu', 'tindakan'])],
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
            'tindakan' => 'tindakan',
            'objek' => 'objek',
            'aktor' => 'pelaku',
            'dari' => 'tanggal awal',
            'sampai' => 'tanggal akhir',
            'urut' => 'urutan',
            'arah' => 'arah urutan',
        ];
    }

    /**
     * @return list<string>
     */
    private function pilihanObjek(): array
    {
        return [...array_keys(LabelAudit::KELAS), 'akses'];
    }
}
