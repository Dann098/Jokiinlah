<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ArticleCategory: string
{
    use HasOptions;

    case Thesis = 'thesis';
    case Research = 'research';
    case DataAnalysis = 'data_analysis';
    case Programming = 'programming';
    case Website = 'website';
    case Mobile = 'mobile';
    case Database = 'database';
    case ItCareer = 'it_career';

    public function label(): string
    {
        return match ($this) {
            self::Thesis => 'Skripsi', self::Research => 'Penelitian', self::DataAnalysis => 'Analisis Data',
            self::Programming => 'Pemrograman', self::Website => 'Website', self::Mobile => 'Mobile',
            self::Database => 'Database', self::ItCareer => 'Karier IT',
        };
    }

    public function color(): string
    {
        return 'primary';
    }
}
