<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: DENY');
    header('Cache-Control: private, no-store');
}
session_name('mercopan_comercial');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>(bool)($config['session_secure']??false),'httponly'=>true,'samesite'=>'Strict']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/core.php';
require_once __DIR__.'/inventory.php';
require_once __DIR__.'/ocr.php';
