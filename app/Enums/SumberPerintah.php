<?php

namespace App\Enums;

enum SumberPerintah: string
{
    case DeLangsung = 'de_langsung';
    case PengurusMelaluiDe = 'pengurus_melalui_de';
    case PengurusLangsung = 'pengurus_langsung';

    public function label(): string
    {
        return match ($this) {
            self::DeLangsung => 'DE Langsung',
            self::PengurusMelaluiDe => 'Pengurus melalui DE',
            self::PengurusLangsung => 'Pengurus Langsung',
        };
    }
}
