<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Satu-satunya sumber waktu sistem.
 *
 * Seluruh timestamp kejadian (pengajuan laporan, review, pencatatan aktivitas,
 * penguncian KPI, audit) diambil dari jam server melalui kelas ini, bukan dari
 * input pengguna, agar jam perangkat tidak dapat memanipulasi SLA atau tenggat.
 */
final class ServerClock
{
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    public static function timezone(): string
    {
        return (string) config('app.timezone');
    }

    /**
     * SLA risalah rapat sesuai PRD: ringkas 1 jam, lengkap 16 jam setelah rapat selesai.
     */
    public const SLA_RISALAH_SINGKAT_JAM = 1;

    public const SLA_RISALAH_LENGKAP_JAM = 16;

    public static function tenggatRisalahSingkat(DateTimeInterface $waktuSelesai): CarbonImmutable
    {
        return CarbonImmutable::instance($waktuSelesai)
            ->setTimezone(self::timezone())
            ->addHours(self::SLA_RISALAH_SINGKAT_JAM);
    }

    public static function tenggatRisalahLengkap(DateTimeInterface $waktuSelesai): CarbonImmutable
    {
        return CarbonImmutable::instance($waktuSelesai)
            ->setTimezone(self::timezone())
            ->addHours(self::SLA_RISALAH_LENGKAP_JAM);
    }
}
