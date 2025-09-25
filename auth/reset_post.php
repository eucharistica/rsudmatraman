<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/app.php';
app_boot();

// CSRF
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
  header('Location: /auth/forgot.php'); exit;
}

$selector = (string)($_POST['selector'] ?? '');
$token    = (string)($_POST['token'] ?? '');
$pw       = (string)($_POST['password'] ?? '');
$pw2      = (string)($_POST['password_confirm'] ?? '');

function strong_pw(string $pw): bool {
  return (bool)preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,}$/', $pw);
}
function password_hash_safe(string $pw): string {
  return password_hash($pw, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
}

if ($pw === '' || $pw !== $pw2 || !strong_pw($pw)) {
  header('Location: /auth/forgot.php'); exit;
}

// Validasi token
if (!ctype_xdigit($selector) || !ctype_xdigit($token) || strlen($selector)!==16 || strlen($token)!==64) {
  header('Location: /auth/forgot.php'); exit;
}

$db = db();
$st = $db->prepare("SELECT id, user_id, verifier_hash, expires_at, used FROM password_resets WHERE selector=:s LIMIT 1");
$st->execute([':s'=>$selector]);
$row = $st->fetch(PDO::FETCH_ASSOC);

if (!$row || (int)$row['used'] === 1 || strtotime((string)$row['expires_at']) <= time()) {
  header('Location: /auth/forgot.php'); exit;
}
if (!hash_equals((string)$row['verifier_hash'], hash('sha256',$token))) {
  header('Location: /auth/forgot.php'); exit;
}

$userId = (int)$row['user_id'];

// Update password & tandai token terpakai
try {
  $db->beginTransaction();

  $up = $db->prepare("UPDATE users SET password_hash=:ph, updated_at=NOW() WHERE id=:id");
  $up->execute([':ph'=>password_hash_safe($pw), ':id'=>$userId]);

  $use = $db->prepare("UPDATE password_resets SET used=1 WHERE id=:id");
  $use->execute([':id'=>(int)$row['id']]);

  // (opsional) hapus token lain milik user
  $del = $db->prepare("DELETE FROM password_resets WHERE user_id=:uid AND used=0");
  $del->execute([':uid'=>$userId]);

  $db->commit();

  if (function_exists('audit_log')) {
    audit_log('auth','reset_password', ['message'=>'Reset password sukses', 'meta'=>['user_id'=>$userId]]);
  }
} catch (Throwable $e) {
  $db->rollBack();
  error_log('reset_post error: '.$e->getMessage());
  render_error(500, 'Terjadi kesalahan.'); exit;
}

// Auto-login user
$st = $db->prepare("SELECT id, email, name, role FROM users WHERE id=:id LIMIT 1");
$st->execute([':id'=>$userId]);
$u = $st->fetch(PDO::FETCH_ASSOC);

if ($u) {
  $_SESSION['user'] = [
    'id'    => (int)$u['id'],
    'email' => (string)$u['email'],
    'name'  => (string)$u['name'],
    'role'  => strtolower((string)$u['role'] ?? 'user'),
    'auth'  => 'password',
  ];
}

header('Location: ' . auth_default_destination($_SESSION['user'] ?? null, null), true, 302);
exit;
