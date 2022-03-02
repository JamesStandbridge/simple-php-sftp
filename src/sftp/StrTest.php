<?php

/**
 * @author James Standbridge <james.standbridge.git@gmail.com>
 */

namespace JamesStandbridge\SimpleSFTP\sftp;


class StrTest
{
    public static function str_endwith(string $needle, string $haystack): bool
    {
        return substr_compare($haystack, $needle, -strlen($needle)) === 0;
    }

    public static function str_startwith(string $needle, string $haystack): bool
    {
        return substr_compare($haystack, $needle, 0, strlen($needle)) === 0;
    }

    public static function str_contains(string $needle, string $haystack): bool
    {
        return str_contains($haystack, $needle);
    }
}