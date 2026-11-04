<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

class AcademicCalendarScope
{
    public static function excludeShortSemesters(Builder $query, string $yearColumn = 'tahun'): Builder
    {
        return $query->whereRaw(
            "NOT (LENGTH(TRIM(COALESCE({$yearColumn}, ''))) = 5 AND SUBSTR(TRIM({$yearColumn}), -1) = '3')"
        );
    }
}
