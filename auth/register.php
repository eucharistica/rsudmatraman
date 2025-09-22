<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/audit.php';
session_boot();

if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
  http_response_code(400); exit('CSRF invalid');
}

$next = $_POST['next'] ?? '/pages/portal';

$name = trim((string)($_POST['name'] ?? ''));
$email= trim(strtolower((string)($_POST['email'] ?? '')));
$d    = (int)($_POST['dob_d'] ?? 0);
$m    = (int)($_POST['dob_m'] ?? 0);
$y    = (int)($_POST['dob_y'] ?? 0);
$phone= trim((string)($_POST['phone'] ?? ''));
$pw   = (string)($_POST['password'] ?? '');
$pw2  = (string)($_POST['password_confirm'] ?? '');

$CFG = $CFG ?? [];
$secret = $CFG['RECAPTCHA_SECRET_KEY'] ?? '';
if ($secret) {
  $resp = $_POST['g-recaptcha-response'] ?? '';
  $ok = false;
  if ($resp) {
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query(['secret'=>$secret,'response'=>$resp]), CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]);
    $res = curl_exec($ch); curl_close($ch);
    $json = @json_decode($res, true);
    $ok = (bool)($json['success'] ?? false);
  }
  if (!$ok) { header('Location: /auth/?mode=register&e=captcha'); exit; }
}

function is_indo_phone(string $p): bool { return (bool)preg_match('/^(\+62|62|0)8\d{8,11}$/', trim($p)); }
function normalize_e164(string $p): string { $p = preg_replace('/\D+/','', $p); if (strpos($p,'62')===0) return '+'.$p; if (strpos($p,'0')===0) return '+62'.substr($p,1); if (strpos($p,'+62')===0) return $p; return $p; }
function strong_pw(string $pw): bool { return (bool)preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,}$/', $pw); }

if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$d || !$m || !$y || !checkdate($m,$d,$y) || !is_indo_phone($phone) || !strong_pw($pw) || $pw!==$pw2) {
  header('Location: /auth/?mode=register&e=invalid'); exit;
}

$dob = sprintf('%04d-%02d-%02d', $y,$m,$d);
$phone_e164 = normalize_e164($phone);

// Simpan
$db = db();
$db->beginTransaction();
try {
  // cek email unik
  $exists = $db->prepare("SELECT id FROM users WHERE email=:e LIMIT 1");
  $exists->execute([':e'=>$email]);
  if ($exists->fetch()) { $db->rollBack(); header('Location: /auth/?mode=register&e=exists'); exit; }

  $pw_hash = password_hash($pw, PASSWORD_ARGON2ID);

  $stmt = $db->prepare("INSERT INTO users (email,name,password_hash,dob,phone,phone_e164,role,last_login,created_at,updated_at,provider,status)
                        VALUES (:email,:name,:ph,:dob,:phone,:e164,'user',NULL,NOW(),NOW(),'password','active')");
  $stmt->execute([
    ':email'=>$email, ':name'=>$name, ':ph'=>$pw_hash,
    ':dob'=>$dob, ':phone'=>$phone, ':e164'=>$phone_e164
  ]);

  $uid = (int)$db->lastInsertId();
  $db->commit();
  audit_log('auth','register', [
    'message' => 'Registrasi akun password',
    'meta'    => ['email'=>$email]
  ]);
  // Set session dan arahkan portal
  $_SESSION['user'] = ['id'=>$uid,'email'=>$email,'name'=>$name,'role'=>'user'];
  header('Location: '.$next); exit;

} catch (Throwable $e) {
  if ($db->inTransaction()) $db->rollBack();
  http_response_code(500); echo 'Error';
}
