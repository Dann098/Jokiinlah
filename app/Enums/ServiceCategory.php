<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ServiceCategory: string
{
    use HasOptions;

    case Academic = 'academic';
    case DataAnalysis = 'data_analysis';
    case Web = 'web';
    case Mobile = 'mobile';
    case Desktop = 'desktop';
    case InformationSystem = 'information_system';

    public function label(): string
    {
        return match ($this) {
            self::Academic => 'Akademik', self::DataAnalysis => 'Analisis Data', self::Web => 'Web Development',
            self::Mobile => 'Mobile Development', self::Desktop => 'Desktop Development',
            self::InformationSystem => 'Sistem Informasi',
        };
    }

    public function color(): string
    {
        return 'primary';
    }
}
