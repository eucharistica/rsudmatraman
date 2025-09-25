<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/app.php';
app_boot();
$db = db();

if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
  header('Location: /auth/forgot.php?e=csrf'); exit;
}

$email = strtolower(trim((string)($_POST['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header('Location: /auth/forgot.php?e=email'); exit;
}

// cari user aktif
$st = $db->prepare("SELECT id, name, status FROM users WHERE email=:e LIMIT 1");
$st->execute([':e'=>$email]);
$u = $st->fetch();
if (!$u || ($u['status'] ?? 'active') !== 'active') {
  // jangan bocorkan status: selalu sukses
  header('Location: /auth/forgot.php?sent=1'); exit;
}

// buat token
$token = bin2hex(random_bytes(32)); // 64 hex chars
$hash  = hash('sha256', $token);
$exp   = date('Y-m-d H:i:s', time()+3600); // 1 jam

// simpan
$ins = $db->prepare("INSERT INTO password_resets (user_id,email,token_hash,expires_at) VALUES (:u,:e,:h,:x)");
$ins->execute([':u'=>$u['id'], ':e'=>$email, ':h'=>$hash, ':x'=>$exp]);

// buat link reset absolut
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$link   = $scheme . '://' . $host . '/auth/reset.php?token=' . urlencode($token);

// kirim email
$sent = false; $err = null;
try {
  $html = '<p>Halo,</p><p>Silakan klik tautan berikut untuk mengatur ulang kata sandi:</p>'.
          '<p><a href="'.$link.'">'.$link.'</a></p><p>Tautan berlaku 1 jam.</p>';
  $sent = mailer_send($email, (string)($u['name'] ?? ''), 'Atur Ulang Kata Sandi', $html);
} catch (Throwable $e) {
  $err = $e->getMessage();
}

if (function_exists('audit_log')) {
  audit_log('auth', $sent ? 'forgot_mail_sent' : 'forgot_mail_error', [
    'message' => $sent ? 'Kirim link reset' : 'Gagal kirim',
    'meta'    => ['email'=>$email, 'error'=>$err]
  ]);
}

// Selalu redirect ke halaman sukses (jangan bocor valid/invalid)
header('Location: /auth/forgot.php?sent=1'); exit;
