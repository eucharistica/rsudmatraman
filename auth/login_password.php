<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/app.php';
app_boot();

/** NEXT internal path validator */
function is_safe_next(string $n): bool {
  if ($n === '' || $n[0] !== '/') return false;
  if (str_contains($n, "\r") || str_contains($n, "\n")) return false;
  if (str_starts_with($n, '//')) return false;
  if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $n)) return false;
  return (bool)preg_match("#^/[A-Za-z0-9._~!$&()*,;=:@%/\-]*$#", $n);
}

/* ===== CSRF ===== */
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
  http_response_code(400); exit('CSRF invalid');
}

/* ===== Input ===== */
$next  = isset($_POST['next']) && is_safe_next((string)$_POST['next']) ? (string)$_POST['next'] : '/pages/portal';
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$pw    = (string)($_POST['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $pw === '') {
  header('Location: /auth/?e=invalid'); exit;
}

$db = db();

/* ===== Rate limit (IP + email) ===== */
$ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$bucket= 'login:pwd:' . $ip . ':' . $email;
$MAX   = 5;     // maksimum percobaan
$WIN   = 600;   // jendela 10 menit

if (!rate_limit_allow($bucket, $MAX, $WIN)) {
  header('Location: /auth/?e=rate'); exit;
}

/* ===== Lookup user ===== */
$stmt = $db->prepare("SELECT id, email, name, role, status, password_hash FROM users WHERE email=:e LIMIT 1");
$stmt->execute([':e' => $email]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$u || !in_array(($u['status'] ?? 'active'), ['active'], true)) {
  rate_limit_hit($bucket, $MAX, $WIN);
  audit_log('auth','login_failed', [
    'message' => 'Login diblokir/ tidak aktif',
    'meta'    => ['email'=>$email, 'reason'=>'inactive']
  ]);
  header('Location: /auth/?e=' . ($u ? 'inactive' : 'login')); exit;
}

/* ===== Verify password ===== */
$ok = password_verify($pw, (string)$u['password_hash']);
if (!$ok) {
  rate_limit_hit($bucket, $MAX, $WIN);
  audit_log('auth','login_failed', [
    'message' => 'Login password gagal',
    'meta'    => ['email'=>$email, 'reason'=>'invalid_credentials']
  ]);
  header('Location: /auth/?e=login'); exit;
}

/* Upgrade hash bila perlu */
try {
  if (password_needs_rehash((string)$u['password_hash'], PASSWORD_DEFAULT)) {
    $new = password_hash($pw, PASSWORD_DEFAULT);
    $upd = $db->prepare("UPDATE users SET password_hash=:h, updated_at=NOW() WHERE id=:id");
    $upd->execute([':h' => $new, ':id' => (int)$u['id']]);
  }
} catch (Throwable $e) { /* ignore */ }

/* Sukses → reset counter */
rate_limit_reset($bucket);

/* Set session */
$_SESSION['user'] = [
  'id'    => (int)$u['id'],
  'email' => (string)$u['email'],
  'name'  => (string)$u['name'],
  'role'  => strtolower((string)$u['role'] ?? 'user'),
  'auth'  => 'password',
];

/* ---- Audit sukses ---- */
audit_log('auth','login_success', [
  'message' => 'Login password berhasil',
  'meta'    => ['email'=>$u['email']]
]);

/* Update last_login info */
try {
  $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
  $ip = substr($ip, 0, 45);
  $upd = $db->prepare("UPDATE users SET last_login = NOW(), last_login_ip=:ip, last_login_ua=:ua WHERE id=:id");
  $upd->execute([':ip'=>$ip, ':ua'=>$ua, ':id'=>(int)$u['id']]);
} catch (Throwable $e) { /* ignore */ }

/* Redirect */
$next = $_POST['next'] ?? $_GET['next'] ?? null;
header('Location: '.auth_default_destination($_SESSION['user'], $next), true, 302);
exit;
