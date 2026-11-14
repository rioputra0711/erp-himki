<?php

namespace App\Http\Requests;

use App\Enums\FrekuensiTugas;
use App\Models\JobDescription;
use App\Support\ServerClock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Penetapan (freeze) versi Job Description.
 *
 * Target, indikator, frekuensi, dan tanggal berlaku wajib lengkap sebelum
 * ditetapkan; tanggal penetapan diambil dari jam server, bukan dari isian.
 */
class TetapkanJobDescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $jd = $this->route('job_description');

        return $jd instanceof JobDescription && $this->user()->can('configure', $jd);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target' => ['required', 'string', 'max:2000'],
            'indikator' => ['required', 'string', 'max:2000'],
            'frekuensi' => ['required', Rule::enum(FrekuensiTugas::class)],
            'berlaku_mulai' => ['required', 'date', 'after_or_equal:'.ServerClock::now()->toDateString()],
            'alasan' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'target' => 'target dasar',
            'indikator' => 'indikator hasil kerja',
            'frekuensi' => 'frekuensi pelaporan',
            'berlaku_mulai' => 'berlaku mulai',
            'alasan' => 'alasan penetapan',
        ];
    }
}
