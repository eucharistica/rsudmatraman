<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/app.php';
app_boot();
$db = db();

if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
  header('Location: /auth/reset.php?e=csrf'); exit;
}

$token = (string)($_POST['token'] ?? '');
$pw1   = (string)($_POST['password'] ?? '');
$pw2   = (string)($_POST['password2'] ?? '');

if (!ctype_xdigit($token) || strlen($token)!==64) {
  header('Location: /auth/reset.php?e=token'); exit;
}
if ($pw1 === '' || $pw1 !== $pw2 || strlen($pw1) < 8) {
  header('Location: /auth/reset.php?token='.urlencode($token).'&e=pw'); exit;
}

$hash = hash('sha256', $token);

// cari token
$st = $db->prepare("SELECT * FROM password_resets WHERE token_hash=:h AND used_at IS NULL AND expires_at>NOW() ORDER BY id DESC LIMIT 1");
$st->execute([':h'=>$hash]);
$row = $st->fetch();
if (!$row) {
  header('Location: /auth/reset.php?e=expired'); exit;
}

// update password user
$db->beginTransaction();
try {
  $uid = (int)$row['user_id'];
  $ph  = password_hash($pw1, PASSWORD_DEFAULT);

  $db->prepare("UPDATE users SET password_hash=:p, updated_at=NOW() WHERE id=:u")->execute([':p'=>$ph, ':u'=>$uid]);
  $db->prepare("UPDATE password_resets SET used_at=NOW() WHERE id=:id")->execute([':id'=>$row['id']]);

  $db->commit();

  if (function_exists('audit_log')) {
    audit_log('auth','password_reset_ok',['message'=>'Reset password sukses','meta'=>['user_id'=>$uid,'email'=>$row['email']]]);
  }

  header('Location: /auth/?reset=ok'); exit;
} catch (Throwable $e) {
  $db->rollBack();
  if (function_exists('audit_log')) {
    audit_log('auth','password_reset_fail',['message'=>'Reset password gagal','meta'=>['err'=>$e->getMessage()]]);
  }
  header('Location: /auth/reset.php?token='.urlencode($token).'&e=internal'); exit;
}
