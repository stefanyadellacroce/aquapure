<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/presentation.php';
require_once __DIR__ . '/invitations.php';
require_once __DIR__ . '/paymongo.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_name('aquapure_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL === '' ? '/' : BASE_URL . '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Prevent raw SQL/PHP exception details from being displayed to normal users.
 * Full details remain in the PHP/Apache error log for development.
 */
set_exception_handler(static function (Throwable $exception): void {
    error_log(
        sprintf(
            'AquaPure uncaught %s: %s in %s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
        ),
    );

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    $schemaProblem = $exception instanceof RuntimeException &&
        str_starts_with($exception->getMessage(), 'AquaPure database schema');
    $message = $schemaProblem
        ? 'Your AquaPure database needs the included safe migration before this version can run.'
        : 'AquaPure encountered an unexpected error. Please try again or check the Apache/PHP error log.';

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>AquaPure error</title>';
    echo '<style>body{font-family:system-ui,-apple-system,Segoe UI,sans-serif;background:#f6fafc;color:#1f2937;margin:0;padding:24px}.box{max-width:680px;margin:8vh auto;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;box-shadow:0 8px 30px rgba(15,23,42,.06)}h1{font-size:1.4rem;margin:0 0 10px}p{line-height:1.6;color:#64748b}code{background:#eef6fb;padding:2px 6px;border-radius:6px;color:#0b6cae}</style>';
    echo '</head><body><main class="box"><h1>AquaPure could not load this page</h1><p>' .
        htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    if ($schemaProblem) {
        echo '<p>In phpMyAdmin, select your existing AquaPure database and run <code>database/migrations/20260818_upgrade_existing_aquapure.sql</code> once after making a backup.</p>';
    }
    echo '</main></body></html>';
});
