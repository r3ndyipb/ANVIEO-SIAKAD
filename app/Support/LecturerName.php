<?php

namespace App\Support;

final class LecturerName
{
    public static function format(?string $name, ?string $prefix = null, ?string $suffix = null): string
    {
        $parts = array_values(array_filter(
            array_map(static fn (?string $part): string => trim((string) $part), [$prefix, $name]),
            static fn (string $part): bool => $part !== ''
        ));
        $formatted = implode(' ', $parts);
        $suffix = trim((string) $suffix);

        if ($suffix === '') {
            return $formatted;
        }

        $suffix = ltrim($suffix, ', ');

        return $formatted === '' ? $suffix : $formatted.', '.$suffix;
    }
}
