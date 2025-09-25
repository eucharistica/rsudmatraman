<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/app.php';
app_boot();

if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
  header('Location: /auth/complete_profile.php?e=csrf'); exit;
}

// ===== Guard: wajib datang dari sesi prefill Google =====
$prefill = $_SESSION['complete_profile'] ?? [];
$prefUserId = (int)($prefill['user_id'] ?? 0);
$prefEmail  = trim((string)($prefill['email'] ?? ''));
if ($prefUserId <= 0 || $prefEmail === '') {
  header('Location: /auth/?e=google_denied'); exit;
}

// Input
$uid   = (int)($_POST['user_id'] ?? 0);
$next  = (string)($_POST['next'] ?? '/pages/portal');
$name  = trim((string)($_POST['name'] ?? ''));
$d     = (int)($_POST['dob_d'] ?? 0);
$m     = (int)($_POST['dob_m'] ?? 0);
$y     = (int)($_POST['dob_y'] ?? 0);
$phoneRaw = trim((string)($_POST['phone'] ?? ''));

// Cocokkan user_id dengan prefill
if ($uid !== $prefUserId) {
  header('Location: /auth/?e=google_denied'); exit;
}

// Validasi dasar
if ($name === '' || strlen($name) < 3 || preg_match('/\d/', $name)) {
  header('Location: /auth/complete_profile.php?e=invalid'); exit;
}
if (!$d || !$m || !$y || !checkdate($m, $d, $y)) {
  header('Location: /auth/complete_profile.php?e=invalid'); exit;
}

// Validasi/normalisasi phone
$clean = preg_replace('/[.\s\-()]/', '', $phoneRaw);
if ($clean === null) $clean = '';
if ($clean === '' || preg_match('/[A-Za-z]/', $clean)) {
  header('Location: /auth/complete_profile.php?e=phone'); exit;
}
if (preg_match('/^\+?62/', $clean)) {
  $e164 = '+' . ltrim($clean, '+');
} elseif (preg_match('/^0/', $clean)) {
  $e164 = '+62' . substr($clean, 1);
} else {
  header('Location: /auth/complete_profile.php?e=phone'); exit;
}
if (!preg_match('/^\+628\d{8,11}$/', $e164)) {
  header('Location: /auth/complete_profile.php?e=phone'); exit;
}

$dob = sprintf('%04d-%02d-%02d', $y, $m, $d);

// Simpan
try {
  $db = db();

  // Pastikan user id/email sinkron (keamanan ekstra)
  $chk = $db->prepare("SELECT id, email, role FROM users WHERE id=:id LIMIT 1");
  $chk->execute([':id'=>$uid]);
  $row = $chk->fetch(PDO::FETCH_ASSOC);
  if (!$row || strcasecmp((string)$row['email'], $prefEmail) !== 0) {
    header('Location: /auth/?e=google_denied'); exit;
  }

  $stmt = $db->prepare("UPDATE users
    SET name=:n, dob=:dob, phone=:ph, phone_e164=:e164, provider='google', status=COALESCE(status,'active'), updated_at=NOW()
    WHERE id=:id");
  $stmt->execute([':n'=>$name, ':dob'=>$dob, ':ph'=>$phoneRaw, ':e164'=>$e164, ':id'=>$uid]);

  audit_log('profile','complete_success', [
    'message'=>'Lengkapi profil sukses',
    'meta'=>['user_id'=>$uid, 'dob'=>$dob, 'phone_e164'=>$e164]
  ]);

  // Set session login
  $_SESSION['user'] = [
    'id'    => (int)$row['id'],
    'email' => (string)$prefEmail,
    'name'  => $name,
    'role'  => strtolower((string)$row['role'] ?? 'user'),
    'auth'  => 'google',
  ];

  // Bersihkan prefill agar halaman tidak bisa diulang tanpa alur
  unset($_SESSION['complete_profile']);

} catch (Throwable $e) {
  error_log('complete_profile_post: '.$e->getMessage());
  header('Location: /auth/complete_profile.php?e=server'); exit;
}

// Tentukan tujuan akhir (hormati next jika aman; admin/editor → dashboard)
header('Location: ' . auth_default_destination($_SESSION['user'], $next), true, 302);
exit;
