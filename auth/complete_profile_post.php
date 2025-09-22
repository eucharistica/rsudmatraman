<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/audit.php';
session_boot();

if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
  header('Location: /auth/complete_profile.php?e=csrf'); exit;
}

$CFG  = require __DIR__ . '/../_private/website.php';
$db   = db();

$uid  = (int)($_POST['user_id'] ?? 0);
$next = (string)($_POST['next'] ?? '/pages/portal');
$name = trim((string)($_POST['name'] ?? ''));
$d    = (int)($_POST['dob_d'] ?? 0);
$m    = (int)($_POST['dob_m'] ?? 0);
$y    = (int)($_POST['dob_y'] ?? 0);
$phoneRaw = trim((string)($_POST['phone'] ?? ''));

// helper phone
$clean = preg_replace('/[.\s\-()]/', '', $phoneRaw ?? '');
if ($clean === null) $clean = '';
if ($clean === '' || preg_match('/[A-Za-z]/', $clean)) { // tolak jika ada huruf atau kosong
  header('Location: /auth/complete_profile.php?e=phone'); exit;
}

// normalisasi ke E.164 (+62...)
if (preg_match('/^\+?62/', $clean)) {
  $e164 = '+' . ltrim($clean, '+');
} elseif (preg_match('/^0/', $clean)) {
  $e164 = '+62' . substr($clean, 1);
} else {
  // tidak diawali 0 / 62 / +62 → bukan nomor Indonesia
  header('Location: /auth/complete_profile.php?e=phone'); exit;
}

// validasi akhir: +628 diikuti 8-11 digit (total panjang umum 10-13 digit lokal)
if (!preg_match('/^\+628\d{8,11}$/', $e164)) {
  header('Location: /auth/complete_profile.php?e=phone'); exit;
}

if (!$uid || !$name || !$d || !$m || !$y || !checkdate($m, $d, $y)) {
  header('Location: /auth/complete_profile.php?e=invalid'); exit;
}

$dob = sprintf('%04d-%02d-%02d', $y, $m, $d);

// simpan ke DB
try {
  $stmt = $db->prepare("UPDATE users
    SET name=:n, dob=:dob, phone=:ph, phone_e164=:e164, updated_at=NOW()
    WHERE id=:id");
  $stmt->execute([':n'=>$name, ':dob'=>$dob, ':ph'=>$phoneRaw, ':e164'=>$e164, ':id'=>$uid]);

  audit_log('profile','complete_success', [
    'message'=>'Lengkapi profil sukses',
    'meta'=>['user_id'=>$uid, 'dob'=>$dob, 'phone_e164'=>$e164]
  ]);
} catch (Throwable $e) {
  error_log('complete_profile_post: '.$e->getMessage());
  header('Location: /auth/complete_profile.php?e=server'); exit;
}

// ambil user untuk set session rapi (nama bisa baru)
$st = $db->prepare("SELECT id, email, name, role FROM users WHERE id=:id LIMIT 1");
$st->execute([':id'=>$uid]);
$u = $st->fetch(PDO::FETCH_ASSOC);

if ($u) {
  $_SESSION['user'] = [
    'id'    => (int)$u['id'],
    'email' => (string)$u['email'],
    'name'  => (string)$u['name'],
    'role'  => strtolower((string)$u['role'] ?? 'user'),
    'auth'  => $_SESSION['user']['auth'] ?? 'google',
  ];
}

$role = $_SESSION['user']['role'] ?? 'user';
if ($next === '/pages/dashboard' && !in_array($role, ['admin','editor'], true)) {
  $next = '/pages/portal';
}
header('Location: '. $next); exit;
