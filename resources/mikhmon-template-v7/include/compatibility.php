<?php
/**
 * Universal PHP Compatibility Layer (PHP 7.0 - PHP 8.4)
 * by NODERA (nodera.id)
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        if ($needle === '' || $needle === null) return true;
        return substr((string)$haystack, 0, strlen((string)$needle)) === (string)$needle;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        if ($needle === '' || $needle === null) return true;
        $len = strlen((string)$needle);
        if ($len === 0) return true;
        return substr((string)$haystack, -$len) === (string)$needle;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        if ($needle === '' || $needle === null) return true;
        return strpos((string)$haystack, (string)$needle) !== false;
    }
}
