<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app.php';
app_boot();

// --- CSRF ---
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
  http_response_code(400);
  exit('CSRF invalid');
}

// --- Helpers ---
function valid_name(string $s): bool {
  $s = trim($s);
  if (strlen($s) < 3) return false;
  if (preg_match('/\d/', $s)) return false; // tanpa angka
  return (bool)preg_match("/^[A-Za-zÀ-ÖØ-öø-ÿ'\.\-\s]+$/u", $s);
}
function is_indo_phone(string $p): bool {
  $p = trim($p);
  return (bool)preg_match('/^(\+62|62|0)8\d{8,11}$/', $p);
}
function normalize_e164(string $p): string {
  $raw = preg_replace('/\D+/', '', $p);
  if (str_starts_with($p, '+62')) return '+'.$raw;
  if (str_starts_with($raw, '62')) return '+'.$raw;
  if (str_starts_with($raw, '0'))  return '+62'.substr($raw, 1);
  return '+'.$raw;
}
function strong_pw(string $pw): bool {
  return (bool)preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,}$/', $pw);
}
function parse_dob(array $post): ?string {
  $dob = trim((string)($post['dob'] ?? ''));
  if ($dob !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
    [$y,$m,$d] = array_map('intval', explode('-', $dob));
    if (checkdate($m, $d, $y)) return sprintf('%04d-%02d-%02d', $y,$m,$d);
  }
  $d = (int)($post['dob_d'] ?? 0);
  $m = (int)($post['dob_m'] ?? 0);
  $y = (int)($post['dob_y'] ?? 0);
  if ($d && $m && $y && checkdate($m,$d,$y)) {
    return sprintf('%04d-%02d-%02d', $y,$m,$d);
  }
  return null;
}
function password_hash_safe(string $pw): string {
  return password_hash($pw, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
}

// --- Input ---
$next  = (string)($_POST['next'] ?? '/pages/portal');
$name  = (string)($_POST['name'] ?? '');
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$phone = (string)($_POST['phone'] ?? '');
$pw    = (string)($_POST['password'] ?? '');
$pw2   = (string)($_POST['password_confirm'] ?? '');
$dob   = parse_dob($_POST);

// === reCAPTCHA (server-side) ===
$CFG    = function_exists('cfg') ? cfg() : [];
$secret = (string)($CFG['RECAPTCHA_SECRET_KEY'] ?? '');

if ($secret !== '') {
  $token    = (string)($_POST['recaptcha_token'] ?? $_POST['g-recaptcha-response'] ?? '');
  $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
  $ok = false;
  if ($token !== '') {
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => http_build_query(['secret'=>$secret,'response'=>$token,'remoteip'=>$remoteIp]),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 10,
    ]);
    $res  = curl_exec($ch);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($res !== false) {
      $json = json_decode($res, true);
      $ok   = (bool)($json['success'] ?? false);
      if (!$ok) error_log('reCAPTCHA fail: '.json_encode($json));
    } else {
      error_log('reCAPTCHA curl error: '.$cerr);
    }
  }
  if (!$ok) { header('Location: /auth/?mode=register&e=captcha', true, 302); exit; }
}

// --- Validasi server-side ---
if (!valid_name($name)
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || $dob === null
    || !is_indo_phone($phone)
    || !strong_pw($pw)
    || $pw !== $pw2) {
  header('Location: /auth/?mode=register&e=invalid', true, 302); exit;
}

$phone_e164 = normalize_e164($phone);

// --- DB ops ---
try {
  $db = db();

  // Cek eksistensi email
  $st = $db->prepare("SELECT id FROM users WHERE email=:e LIMIT 1");
  $st->execute([':e'=>$email]);
  if ($st->fetchColumn()) {
    header('Location: /auth/?mode=register&e=exists', true, 302); exit;
  }

  // Simpan
  $pw_hash = password_hash_safe($pw);
  $ins = $db->prepare("
    INSERT INTO users
      (email, name, password_hash, dob, phone, phone_e164, role, last_login, created_at, updated_at, provider, status)
    VALUES
      (:email,:name,:ph,:dob,:phone,:e164,'user',NULL,NOW(),NOW(),'password','active')
  ");
  $ins->execute([
    ':email' => $email,
    ':name'  => $name,
    ':ph'    => $pw_hash,
    ':dob'   => $dob,
    ':phone' => $phone,
    ':e164'  => $phone_e164,
  ]);

  $uid = (int)$db->lastInsertId();

  audit_log('auth','register', [
    'message' => 'Registrasi akun password',
    'meta'    => ['email'=>$email]
  ]);

  // Set session & redirect
  $_SESSION['user'] = [
    'id'    => $uid,
    'email' => $email,
    'name'  => $name,
    'role'  => 'user',
    'auth'  => 'password',
  ];

  // Tentukan tujuan akhir (next aman > dashboard untuk admin/editor > portal)
  $dest = auth_default_destination($_SESSION['user'], $next);
  header('Location: '.$dest, true, 302);
  exit;

} catch (Throwable $e) {
  error_log('register error: '.$e->getMessage());
  render_error(500, 'Terjadi kesalahan saat registrasi.');
  exit;
}
