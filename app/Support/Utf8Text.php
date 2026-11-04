<?php

namespace App\Support;

class Utf8Text
{
    private const WINDOWS_1252 = [
        0x20AC => 0x80,
        0x201A => 0x82,
        0x0192 => 0x83,
        0x201E => 0x84,
        0x2026 => 0x85,
        0x2020 => 0x86,
        0x2021 => 0x87,
        0x02C6 => 0x88,
        0x2030 => 0x89,
        0x0160 => 0x8A,
        0x2039 => 0x8B,
        0x0152 => 0x8C,
        0x017D => 0x8E,
        0x2018 => 0x91,
        0x2019 => 0x92,
        0x201C => 0x93,
        0x201D => 0x94,
        0x2022 => 0x95,
        0x2013 => 0x96,
        0x2014 => 0x97,
        0x02DC => 0x98,
        0x2122 => 0x99,
        0x0161 => 0x9A,
        0x203A => 0x9B,
        0x0153 => 0x9C,
        0x017E => 0x9E,
        0x0178 => 0x9F,
    ];

    public static function repairMojibake(string $value): string
    {
        if (! str_contains($value, "\u{00F0}\u{0178}") || ! mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $bytes = '';
        foreach (preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) as $character) {
            $codepoint = mb_ord($character, 'UTF-8');
            if ($codepoint <= 0xFF) {
                $bytes .= chr($codepoint);
            } elseif (isset(self::WINDOWS_1252[$codepoint])) {
                $bytes .= chr(self::WINDOWS_1252[$codepoint]);
            } else {
                return $value;
            }
        }

        if (! mb_check_encoding($bytes, 'UTF-8')
            || preg_match('/[\x{1F000}-\x{1FAFF}]/u', $bytes) !== 1) {
            return $value;
        }

        return $bytes;
    }
}
