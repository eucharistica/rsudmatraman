<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/app.php';
app_boot();

//
// CSRF
//
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
  // Audit: CSRF gagal
  audit_log('auth', 'forgot_csrf_fail', [
    'message' => 'CSRF tidak valid pada forgot_post',
  ]);
  header('Location: /auth/forgot_done.php'); exit;
}

$email = strtolower(trim((string)($_POST['email'] ?? '')));
$ip    = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$ua    = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

//
// Audit: request diterima (tidak bocorkan status user)
//
audit_log('auth', 'forgot_request_received', [
  'message' => 'Permintaan reset password diterima',
  'meta'    => [
    'email_hash' => hash('sha256', $email ?: '-'),
    'ip'         => $ip,
    'ua'         => substr($ua, 0, 255),
  ],
]);

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  // Audit: email tidak valid (format)
  audit_log('auth', 'forgot_invalid_email', [
    'message' => 'Format email tidak valid',
    'meta'    => [
      'email_hash' => hash('sha256', $email ?: '-'),
      'ip'         => $ip,
    ],
  ]);
  header('Location: /auth/forgot_done.php'); exit;
}

$db = db();

//
// Rate limit (opsional; aman jika adapter sudah dibuat)
//
try {
  $key = 'forgot:' . hash('sha256', $email . '|' . $ip);
  if (function_exists('rate_limit_enforce')) {
    rate_limit_enforce($key, 5, 60); // max 5 req / 60s untuk kombinasi email+IP
  }
} catch (Throwable $e) {
  // Audit: rate limit error (tidak memblok alur, hanya catat)
  audit_log('auth', 'forgot_rate_limit_error', [
    'message' => 'Rate limit error (diabaikan)',
    'meta'    => ['err' => $e->getMessage()],
  ]);
}

try {
  // Cari user
  $st = $db->prepare("SELECT id, email, name, role FROM users WHERE email=:e and provider='password' LIMIT 1");
  $st->execute([':e'=>$email]);
  $u = $st->fetch(PDO::FETCH_ASSOC);

  // Selalu arahkan ke halaman "cek email", apapun hasilnya
  if ($u) {
    $userId = (int)$u['id'];

    // Buat selector + verifier
    $selector = bin2hex(random_bytes(8));    // 16 chars
    $verifier = bin2hex(random_bytes(32));   // 64 chars
    $hash     = hash('sha256', $verifier);   // simpan hash, JANGAN simpan verifier
    $expires  = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');

    // Simpan token
    $ins = $db->prepare("INSERT INTO password_resets (user_id, selector, verifier_hash, expires_at, used) VALUES (:uid,:sel,:vh,:exp,0)");
    $ins->execute([':uid'=>$userId, ':sel'=>$selector, ':vh'=>$hash, ':exp'=>$expires]);

    // Compose URL absolut
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = 'https://' . $host;
    $link = $base . '/auth/reset.php?s=' . urlencode($selector) . '&t=' . urlencode($verifier);

    // Kirim email (audit sukses/gagal)
    $site = (string)config_get('site.name','Website');
    $html = '<p>Anda meminta reset kata sandi untuk akun di <b>'.htmlspecialchars($site).'</b>.</p>'
          . '<p>Klik tautan berikut untuk mengatur ulang kata sandi (berlaku 30 menit):</p>'
          . '<p><a href="'.htmlspecialchars($link).'">'.htmlspecialchars($link).'</a></p>'
          . '<p>Jika bukan Anda, abaikan email ini.</p>';

    $sent = false; $sendErr = null;
    try {
      $sent = mailer_send($u['email'], (string)$u['name'], 'Reset Password — '.$site, $html);
    } catch (Throwable $e) {
      $sendErr = $e->getMessage();
    }

    audit_log('auth', $sent ? 'forgot_mail_sent' : 'forgot_mail_fail', [
      'message' => $sent ? 'Email reset terkirim' : 'Gagal mengirim email reset',
      'target_type' => 'user',
      'target_id'   => (string)$userId,
      'meta' => [
        'email_hash' => hash('sha256', $u['email']),
        'selector'   => $selector,  // aman, bukan verifier
        'expires_at' => $expires,
        'ip'         => $ip,
        'ua'         => substr($ua, 0, 255),
        'error'      => $sendErr,
      ]
    ]);
  } else {
    // User tidak ditemukan → tetap respon sama, tapi audit dicatat
    audit_log('auth', 'forgot_user_not_found', [
      'message' => 'Email tidak ditemukan (disamarkan)',
      'meta'    => [
        'email_hash' => hash('sha256', $email),
        'ip'         => $ip,
      ],
    ]);
  }
} catch (Throwable $e) {
  // Audit: error internal
  audit_log('auth', 'forgot_internal_error', [
    'message' => 'Kesalahan internal saat memproses forgot_post',
    'meta'    => ['err' => $e->getMessage()],
  ]);
  // Tetap arahkan ke done untuk tidak bocorkan info
  header('Location: /auth/forgot_done.php'); exit;
}

// Selesai: selalu tampilkan halaman “cek email”
header('Location: /auth/forgot_done.php'); exit;
