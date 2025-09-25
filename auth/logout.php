<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app.php';
app_boot();

audit_log('auth','logout', ['message'=>'User logout']);
$_SESSION = [];
session_destroy();
header('Location: /'); exit;

// Hapus cookie sesi dengan parameter yang sama persis
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  // PHP 7.3+ bisa pakai array options
  setcookie(session_name(), '', [
    'expires'  => time() - 3600,
    'path'     => $p['path']     ?? '/',
    'domain'   => $p['domain']   ?? '',
    'secure'   => !empty($p['secure']),
    'httponly' => !empty($p['httponly']),
    'samesite' => (isset($p['samesite']) && $p['samesite']) ? $p['samesite'] : 'Lax',
  ]);
}

// Destroy session di server
if (session_status() === PHP_SESSION_ACTIVE) {
  session_destroy();
}

// Hindari cache halaman pasca-logout
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Tentukan tujuan pasca logout
$dest = '/'; // atau '/login'
if (!empty($_GET['next']) && preg_match('~^/[A-Za-z0-9._~!$&\'()*+,;=:@%/\-]*$~', (string)$_GET['next'])) {
  $dest = (string)$_GET['next'];
}

// Redirect
header('Location: ' . $dest, true, 302);
exit;
