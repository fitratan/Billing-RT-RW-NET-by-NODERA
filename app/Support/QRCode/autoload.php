<?php

/**
 * Standalone Fallback Autoloader for chillerlan/php-qrcode & chillerlan/php-settings-container.
 * Ensures QR generation & scanning works even when `composer install` has not been run
 * on shared hosting / cPanel environments.
 */

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'chillerlan\\QRCode\\')) {
        $subPath = str_replace('\\', '/', substr($class, 17));
        $file = __DIR__ . '/php-qrcode/src/' . $subPath . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    } elseif (str_starts_with($class, 'chillerlan\\Settings\\')) {
        $subPath = str_replace('\\', '/', substr($class, 20));
        $file = __DIR__ . '/php-settings-container/src/' . $subPath . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});
