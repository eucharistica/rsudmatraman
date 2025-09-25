<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app.php';
$CFG = require __DIR__ . '/../_private/website.php';

app_boot();

/** Helper: validasi path internal aman untuk ?next */
function is_safe_next(string $n): bool {
  if ($n === '' || $n[0] !== '/') return false;             
  if (str_contains($n, "\r") || str_contains($n, "\n")) return false;
  if (str_starts_with($n, '//')) return false;              
  if (preg_match('/^[a-z][a-z0-9+\-.]*:/i', $n)) return false; 
  return true;
}

/** Helper HTTP POST sederhana */
function http_post(string $url, array $form): ?array {
  $body = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
  $ctx = stream_context_create([
    'http' => [
      'method'  => 'POST',
      'header'  => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json",
      'content' => $body,
      'timeout' => 15,
    ]
  ]);
  $raw = @file_get_contents($url, false, $ctx);
  if ($raw === false) return null;
  $json = json_decode($raw, true);
  return is_array($json) ? $json : null;
}

/** Helper parse ID token (tanpa verifikasi signature) */
function parse_jwt_unverified(string $jwt): ?array {
  $parts = explode('.', $jwt);
  if (count($parts) !== 3) return null;
  $payload = $parts[1] . str_repeat('=', (4 - strlen($parts[1]) % 4) % 4);
  $json = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
  return is_array($json) ? $json : null;
}

/** Helper PDO (fallback jika tidak ada dbx()) */
function get_pdo(array $CFG): PDO {
  if (function_exists('dbx')) {
    $pdo = dbx();
    if ($pdo instanceof PDO) return $pdo;
  }
  $dsn  = $CFG['DB_DSN'] ?? ('mysql:host=' . ($CFG['DB_HOST'] ?? '127.0.0.1') . ';dbname=' . ($CFG['DB_NAME'] ?? 'app') . ';charset=utf8mb4');
  $user = $CFG['DB_USER'] ?? 'root';
  $pass = $CFG['DB_PASS'] ?? '';
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ]);
  return $pdo;
}

// ==== Hanya GET ====
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); echo 'Method Not Allowed'; exit; }

if (isset($_GET['error'])) {
  $state = $_GET['state'] ?? '';
  $next  = '/pages/portal';
  if ($state) {
    $stateJson = json_decode(base64_decode(strtr((string)$state, '-_', '+/')), true);
    if (is_array($stateJson)) {
      $cand = (string)($stateJson['next'] ?? '');
      if (is_safe_next($cand)) $next = $cand;
    }
  }

/* access_denied */
if ((string)$_GET['error'] === 'access_denied') {
    audit_log('auth','oauth_denied', ['message'=>'Google access_denied']);
    header('Location: /auth/?e=google_denied&next=' . urlencode($next), true, 302); exit;
}
  header('Location: /auth/?e=google&next=' . urlencode($next), true, 302);
  exit;
}

// ==== Ambil code/state & validasi ====
$code  = $_GET['code']  ?? null;
$state = $_GET['state'] ?? null;
if (!$code || !$state) { http_response_code(400); echo 'Missing code/state'; exit; }

$stash = $_SESSION['oauth2_google'] ?? null;
unset($_SESSION['oauth2_google']); // sekali pakai
if (!$stash || !is_array($stash)) { http_response_code(400); echo 'Invalid session'; exit; }

$stateJson = json_decode(base64_decode(strtr((string)$state, '-_', '+/')), true);
if (!$stateJson || !hash_equals((string)$stash['state']['csrf'], (string)($stateJson['csrf'] ?? ''))) {
  http_response_code(400); echo 'Bad state'; exit;
}

// ==== Tukar code -> token ====
$tokenRes = http_post('https://oauth2.googleapis.com/token', [
  'grant_type'    => 'authorization_code',
  'code'          => $code,
  'client_id'     => $CFG['GOOGLE_CLIENT_ID'],
  'client_secret' => $CFG['GOOGLE_CLIENT_SECRET'],
  'redirect_uri'  => $CFG['GOOGLE_REDIRECT_URI'],
  'code_verifier' => $stash['verifier'] ?? '',
]);
if (!$tokenRes || empty($tokenRes['id_token'])) { http_response_code(400); echo 'Token exchange failed'; exit; }

// ==== Parse & cek ID token dasar ====
$claims = parse_jwt_unverified($tokenRes['id_token']);
if (!$claims) { http_response_code(400); echo 'ID token invalid'; exit; }

$audOk = hash_equals($CFG['GOOGLE_CLIENT_ID'], (string)($claims['aud'] ?? ''));
$iss   = (string)($claims['iss'] ?? '');
$issOk = ($iss === 'https://accounts.google.com' || $iss === 'accounts.google.com');
$expOk = (int)($claims['exp'] ?? 0) > time();
if (!$audOk || !$issOk || !$expOk) { http_response_code(400); echo 'ID token check failed'; exit; }

// ==== Field penting ====
$email = strtolower((string)($claims['email'] ?? ''));
$name  = (string)($claims['name'] ?? '');
$sub   = (string)($claims['sub'] ?? '');
$pic   = (string)($claims['picture'] ?? '');
$hd    = (string)($claims['hd'] ?? '');

// ==== Allowlist (opsional) ====
$allow = true;
if (!empty($CFG['GOOGLE_ALLOWED_DOMAINS'])) {
  $domain = strtolower($hd ?: substr(strrchr($email, '@') ?: '', 1));
  $allow = in_array($domain, array_map('strtolower', $CFG['GOOGLE_ALLOWED_DOMAINS']), true);
}
if ($allow && !empty($CFG['GOOGLE_ALLOWED_EMAILS'])) {
  $allow = in_array(strtolower($email), array_map('strtolower', $CFG['GOOGLE_ALLOWED_EMAILS']), true);
}
if (!$allow) { http_response_code(403); echo 'Email not allowed'; exit; }

// ==== DB ====
$pdo = get_pdo($CFG);

// ==== Cari user ====
$sel = $pdo->prepare('SELECT * FROM users WHERE google_sub = :sub OR email = :email LIMIT 1');
$sel->execute([':sub'=>$sub, ':email'=>$email]);
$u = $sel->fetch(PDO::FETCH_ASSOC);

// ==== Upsert & flow lengkapi profil ====
if ($u) {
  $pdo->prepare('UPDATE users
      SET google_sub = COALESCE(google_sub, :sub),
          picture    = :pic,
          provider   = "google",
          email_verified_at = IFNULL(email_verified_at, NOW()),
          updated_at = NOW()
      WHERE id = :id')->execute([':sub'=>$sub, ':pic'=>$pic, ':id'=>$u['id']]);

  if (empty($u['dob']) || empty($u['phone_e164'])) {
    $_SESSION['complete_profile'] = [
      'user_id' => (int)$u['id'],
      'email'   => $u['email'],
      'name'    => $u['name'] ?: $name,
      'next'    => (string)($stateJson['next'] ?? '/pages/portal'),
    ];
    audit_log('profile','complete_required', [
        'message'=>'Wajib lengkapi profil (dob/phone)'
    ]);
    header('Location: /auth/complete_profile.php'); exit;
  }

  $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')->execute([':id'=>$u['id']]);

  $role = strtolower((string)($u['role'] ?? ($CFG['DEFAULT_ROLE'] ?? 'user')));
  $user = [
    'id'     => (string)$u['id'],
    'email'  => $u['email'],
    'name'   => $u['name'] ?: $name,
    'avatar' => $pic ?: ($u['picture'] ?? ''),
    'role'   => $role,
    'auth'   => 'google',
  ];
  session_set_user($user);
  audit_log('auth','login_success', [
    'message' => 'Login Google berhasil',
    'meta'    => ['email'=>$email, 'sub'=>$sub]
  ]);

} else {
  $role = strtolower((string)($CFG['DEFAULT_ROLE'] ?? 'user'));
  $pdo->prepare('INSERT INTO users (google_sub, email, name, picture, role, provider, email_verified_at, created_at, updated_at)
                 VALUES (:sub, :email, :name, :pic, :role, "google", NOW(), NOW(), NOW())')
      ->execute([':sub'=>$sub, ':email'=>$email, ':name'=>$name, ':pic'=>$pic, ':role'=>$role]);
    audit_log('auth','register_google', [
    'message'=>'Akun baru via Google',
    'meta'=>['email'=>$email, 'sub'=>$sub]
    ]);
  $newId = (int)$pdo->lastInsertId();

  $_SESSION['complete_profile'] = [
    'user_id' => $newId,
    'email'   => $email,
    'name'    => $name,
    'next'    => (string)($stateJson['next'] ?? '/pages/portal'),
  ];
  header('Location: /auth/complete_profile.php'); exit;
}

// ==== Redirect akhir ====
$requested = '';
if (!empty($stateJson) && is_array($stateJson)) {
  $cand = (string)($stateJson['next'] ?? '');
  if (is_safe_next($cand)) $requested = $cand;
}
$defaultByRole = in_array($role, ['admin','editor'], true) ? '/pages/dashboard' : '/pages/portal';
$dest = $requested && $requested !== '/pages/portal' ? $requested : $defaultByRole;

header('Location: ' . $dest, true, 302);
exit;
