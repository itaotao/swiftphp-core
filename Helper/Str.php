<?php

namespace SwiftPHP\Helper;

class Str
{
    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    public static function studly(string $value): string
    {
        $value = ucwords(str_replace(['-', '_'], ' ', $value));
        return str_replace(' ', '', $value);
    }

    public static function snake(string $value, string $delimiter = '_'): string
    {
        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', $value);
            $value = strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }
        return $value;
    }

    public static function kebab(string $value): string
    {
        return self::snake($value, '-');
    }

    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    public static function upper(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }

    public static function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }
        return mb_substr($value, 0, $limit, 'UTF-8') . $end;
    }

    public static function words(string $value, int $words = 100, string $end = '...'): string
    {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);
        if (!isset($matches[0]) || mb_strlen($value, 'UTF-8') === mb_strlen($matches[0], 'UTF-8')) {
            return $value;
        }
        return rtrim($matches[0]) . $end;
    }

    public static function random(int $length = 16): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public static function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    public static function contains(string $haystack, $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function containsAll(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (!self::contains($haystack, $needle)) {
                return false;
            }
        }
        return true;
    }

    public static function startsWith(string $haystack, $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle !== '' && mb_strpos($haystack, $needle, 0, 'UTF-8') === 0) {
                return true;
            }
        }
        return false;
    }

    public static function endsWith(string $haystack, $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle !== '' && mb_strrpos($haystack, $needle, 0, 'UTF-8') === mb_strlen($haystack, 'UTF-8') - mb_strlen($needle, 'UTF-8')) {
                return true;
            }
        }
        return false;
    }

    public static function replace(string $subject, string $search, string $replace): string
    {
        return str_replace($search, $replace, $subject);
    }

    public static function replaceArray(string $subject, array $search, array $replace): string
    {
        foreach ($search as $index => $searchItem) {
            $subject = self::replace($subject, $searchItem, $replace[$index] ?? '');
        }
        return $subject;
    }

    public static function after(string $subject, string $search): string
    {
        return $search === '' ? $subject : array_reverse(explode($search, $subject, 2))[0];
    }

    public static function before(string $subject, string $search): string
    {
        return $search === '' ? $subject : explode($search, $subject)[0];
    }

    public static function substr(string $subject, int $start, int $length = null): string
    {
        return mb_substr($subject, $start, $length, 'UTF-8');
    }

    public static function length(string $value): int
    {
        return mb_strlen($value, 'UTF-8');
    }

    public static function trim(string $value, string $characters = " \t\n\r\0\x0B"): string
    {
        return self::trim($value, $characters);
    }

    public static function slug(string $title, string $separator = '-'): string
    {
        $title = self::lower($title);
        $title = preg_replace('/[^\p{L}\p{Nd}]+/u', $separator, $title);
        $title = trim($title, $separator);
        return self::lower($title);
    }

    public static function finish(string $value, string $cap): string
    {
        return self::endsWith($value, $cap) ? $value : $value . $cap;
    }

    public static function start(string $value, string $start): string
    {
        return self::startsWith($value, $start) ? $value : $start . $value;
    }

    public static function classBasename(string $class): string
    {
        return basename(str_replace('\\', '/', $class));
    }

    public static function isAscii(string $value): bool
    {
        return preg_match('/[^\x00-\x7F]/', $value) === 0;
    }

    public static function ascii(string $value): string
    {
        $ascii = [
            'ä' => 'a', 'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'd', 'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'þ' => 'th',
            'ÿ' => 'y', 'ß' => 'ss', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A',
            'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
            'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O',
            'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ý' => 'Y', 'Þ' => 'TH', 'Ÿ' => 'Y', '€' => 'EUR', '£' => 'GBP', '¥' => 'JPY',
        ];
        return strtr($value, $ascii);
    }
}
