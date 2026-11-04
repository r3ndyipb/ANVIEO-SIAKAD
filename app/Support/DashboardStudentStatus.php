<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

final class DashboardStudentStatus
{
    public const INCLUDED = ['A', 'N', 'C', 'M'];

    public static function applyStudentDataRequirements(Builder $query, string $alias = ''): Builder
    {
        $nim = $alias === '' ? 'nim' : $alias.'.nim';
        $name = $alias === '' ? 'nama_mahasiswa' : $alias.'.nama_mahasiswa';

        return $query
            ->whereNotNull($nim)
            ->whereRaw("TRIM({$nim}) <> ''")
            ->whereNotNull($name)
            ->whereRaw("TRIM({$name}) <> ''");
    }
}
