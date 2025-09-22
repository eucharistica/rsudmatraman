<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app.php';

session_boot();

/* Helper: validasi path internal aman untuk ?next */
function is_safe_next(string $n): bool {
  if ($n === '' || $n[0] !== '/') return false;                 // harus path absolut internal
  if (str_contains($n, "\r") || str_contains($n, "\n")) return false; // cegah header injection
  if (str_starts_with($n, '//')) return false;                  // protocol-relative
  if (preg_match('/^[a-z][a-z0-9+\-.]*:/i', $n)) return false;  // URL absolut (http:, js:, dll)
  return true;
}

/* ---- CSRF ---- */
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
  header('Location: /auth/?e=invalid'); exit;
}

/* ---- Input ---- */
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$pw    = (string)($_POST['password'] ?? '');
$next  = (string)($_POST['next'] ?? '/pages/portal');
if (!is_safe_next($next)) $next = '/pages/portal';

/* ---- reCAPTCHA (opsional) ---- */
$CFG = require __DIR__ . '/../_private/website.php';
$secret = (string)($CFG['RECAPTCHA_SECRET_KEY'] ?? '');
if ($secret !== '') {
  $resp = (string)($_POST['g-recaptcha-response'] ?? '');
  $ok = false;
  if ($resp !== '') {
    if (function_exists('curl_init')) {
      $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
      curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['secret'=>$secret,'response'=>$resp]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
      ]);
      $body = curl_exec($ch); curl_close($ch);
    } else {
      $ctx = stream_context_create([
        'http'=>[
          'method'=>'POST',
          'header'=>"Content-Type: application/x-www-form-urlencoded\r\n",
          'content'=>http_build_query(['secret'=>$secret,'response'=>$resp]),
          'timeout'=>10,
        ]
      ]);
      $body = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
    }
    $j = @json_decode((string)$body, true);
    $ok = (bool)($j['success'] ?? false);
  }
  if (!$ok) { header('Location: /auth/?e=captcha&next='.urlencode($next)); exit; }
}

/* ---- DB & query user ---- */
$db = db(); // Opsi 1: auto-load config di lib/db.php

$st = $db->prepare("SELECT id,email,name,password_hash,role,status FROM users WHERE email = :e LIMIT 1");
$st->execute([':e'=>$email]);
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!login_throttle_check()) {
    header('Location: /auth/?e=locked'); exit;
}

/* ---- Verifikasi kredensial ---- */
if (!$user || empty($user['password_hash']) || !password_verify($pw, (string)$user['password_hash'])) {
  audit_log('auth','login_failed', [
    'message' => 'Login password gagal',
    'meta'    => ['email'=>$email, 'reason'=>'invalid_credentials']
  ]);
  login_throttle_fail();
  header('Location: /auth/?e=login&next='.urlencode($next)); exit;
}
if (($user['status'] ?? 'active') !== 'active') {
  audit_log('auth','login_failed', [
    'message' => 'Login diblokir/ tidak aktif',
    'meta'    => ['email'=>$email, 'reason'=>'inactive']
  ]);
  header('Location: /auth/?e=inactive'); exit;
}

/* ---- Update meta login ---- */
try {
  $db->prepare("UPDATE users SET last_login = NOW(), provider='password', updated_at=NOW() WHERE id=:id")
     ->execute([':id'=>$user['id']]);
} catch (\Throwable $e) {
  error_log('login_password last_login update: '.$e->getMessage());
  // tidak memblokir login
}

/* ---- Set session ---- */
session_regenerate_id(true);
$_SESSION['user'] = [
  'id'    => (int)$user['id'],
  'email' => (string)$user['email'],
  'name'  => (string)$user['name'],
  'role'  => strtolower((string)$user['role'] ?? 'user'),
  'auth'  => 'password',
];

/* ---- Audit sukses ---- */
audit_log('auth','login_success', [
  'message' => 'Login password berhasil',
  'meta'    => ['email'=>$user['email']]
]);

/* ---- Tentukan tujuan ---- */
$role = $_SESSION['user']['role'];
$default = in_array($role, ['admin','editor'], true) ? '/pages/dashboard' : '/pages/portal';
if ($next === '/pages/dashboard' && !in_array($role, ['admin','editor'], true)) {
  $next = '/pages/portal';
}
$dest = $next ?: $default;

/* ---- Redirect & fallback ---- */
$didHeader = @header('Location: ' . $dest, true, 303);
if ($didHeader !== false) { exit; }
?>
<!doctype html>
<meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($dest, ENT_QUOTES) ?>">
<a href="<?= htmlspecialchars($dest, ENT_QUOTES) ?>">Lanjutkan…</a>
