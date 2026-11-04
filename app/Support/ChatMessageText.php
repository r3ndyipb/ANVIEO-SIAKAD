<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

class ChatMessageText
{
    public static function encodeForStorage(string $value): string
    {
        $value = preg_replace_callback('/&(#(?:x[0-9a-f]{1,6}|[0-9]{1,7});)/i', static function (array $match): string {
            return '&amp;'.$match[1];
        }, $value) ?? $value;

        return preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', static function (array $match): string {
            return '&#'.mb_ord($match[0], 'UTF-8').';';
        }, $value) ?? $value;
    }

    public static function decodeFromStorage(string $value): string
    {
        $decoded = preg_replace_callback('/&#(?:x([0-9a-f]{1,6})|([0-9]{1,7}));/i', static function (array $match): string {
            $codepoint = isset($match[1]) && $match[1] !== ''
                ? hexdec($match[1])
                : (int) ($match[2] ?? 0);

            return $codepoint > 0xFFFF && $codepoint <= 0x10FFFF
                ? mb_chr($codepoint, 'UTF-8')
                : $match[0];
        }, $value) ?? $value;

        return str_replace('&amp;#', '&#', $decoded);
    }

    public static function toSafeHtml(string $value): HtmlString
    {
        $parts = preg_split('/(<\/?b>|<br\s*\/?>)/i', self::decodeFromStorage($value), -1, PREG_SPLIT_DELIM_CAPTURE);
        $html = '';
        $boldDepth = 0;

        foreach ($parts ?: [$value] as $part) {
            if (preg_match('/^<b>$/i', $part) === 1) {
                $html .= '<strong>';
                $boldDepth++;
            } elseif (preg_match('/^<\/b>$/i', $part) === 1) {
                if ($boldDepth > 0) {
                    $html .= '</strong>';
                    $boldDepth--;
                } else {
                    $html .= e($part);
                }
            } elseif (preg_match('/^<br\s*\/?>$/i', $part) === 1) {
                $html .= '<br>';
            } else {
                $html .= nl2br(e($part), false);
            }
        }

        while ($boldDepth-- > 0) {
            $html .= '</strong>';
        }

        return new HtmlString($html);
    }
}
