<?php

namespace App\Models;

use App\Support\CakupanPengguna;
use App\Support\ServerClock;
use Database\Factories\AuditEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AuditEvent extends Model
{
    /** @use HasFactory<AuditEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Objek yang termasuk lingkup operasional Direktur Eksekutif.
     *
     * DE boleh menelusuri jejak pekerjaan (tugas, laporan, dokumen, penilaian,
     * KPI, rapat), tetapi tidak jejak keamanan akun (`auth.*`), perubahan hak
     * akses (`akses.*`), maupun master personel dan akun.
     *
     * @var list<class-string>
     */
    public const OPERASIONAL = [
        Assignment::class,
        ActivityLog::class,
        Report::class,
        Document::class,
        Review::class,
        Feedback::class,
        ActionItem::class,
        KpiResult::class,
        Meeting::class,
    ];

    protected $fillable = [
        'actor_id',
        'actor_nama',
        'actor_peran',
        'event',
        'auditable_type',
        'auditable_id',
        'before',
        'after',
        'reason',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->created_at = ServerClock::now();
        });

        static::updating(function (): never {
            throw new RuntimeException('Audit event bersifat append-only dan tidak boleh diubah.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('Audit event bersifat append-only dan tidak boleh dihapus.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Batasi jejak yang boleh dibaca akun ini.
     *
     * Administrator dan Pengurus membaca seluruh jejak; Direktur Eksekutif
     * hanya peristiwa operasional. Staf tidak pernah sampai ke sini karena
     * tidak memegang `audit.view`.
     */
    public function scopeTerlihatOleh(Builder $query, User $user): Builder
    {
        if (CakupanPengguna::adalahDirekturEksekutif($user)) {
            return $query->whereIn('auditable_type', self::OPERASIONAL);
        }

        return $query;
    }

    /**
     * Apakah satu baris jejak berada dalam cakupan akun ini.
     */
    public function terlihatOleh(User $user): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->terlihatOleh($user)
            ->exists();
    }
}
