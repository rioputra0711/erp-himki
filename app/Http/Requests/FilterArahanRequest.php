<?php

namespace App\Http\Requests;

use App\Enums\AsalFeedback;
use App\Enums\JenisFeedback;
use App\Enums\StatusFeedback;
use App\Models\Feedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi filter daftar arahan (fitur.pdf Tahap 5).
 */
class FilterArahanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Feedback::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'jenis' => ['nullable', 'string', Rule::enum(JenisFeedback::class)],
            'asal' => ['nullable', 'string', Rule::enum(AsalFeedback::class)],
            'status' => ['nullable', 'string', Rule::enum(StatusFeedback::class)],
            'tindakan' => ['nullable', 'string', Rule::in(['butuh', 'selesai'])],
            'urut' => ['nullable', 'string', Rule::in(['tenggat', 'dibuat'])],
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
            'jenis' => 'jenis',
            'asal' => 'asal arahan',
            'status' => 'status',
            'tindakan' => 'tindak lanjut',
            'urut' => 'urutan',
            'arah' => 'arah urutan',
        ];
    }
}
