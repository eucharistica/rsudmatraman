<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app.php';
app_boot(['skip_maintenance_for' => ['/auth/']]);

// --- Load config ---
$CFG = require __DIR__ . '/../_private/website.php';
$CLIENT_ID     = (string)($CFG['GOOGLE_CLIENT_ID']     ?? '');
$REDIRECT_URI  = (string)($CFG['GOOGLE_REDIRECT_URI']  ?? '');

if ($CLIENT_ID === '' || $REDIRECT_URI === '') {
  render_error(500, 'Konfigurasi Google OAuth belum diisi.'); exit;
}

// --- Helpers ---
function b64url(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }

function is_safe_next(string $n): bool {
    if ($n === '' || $n[0] !== '/') return false;
    if (str_contains($n, "\r") || str_contains($n, "\n")) return false;
    if (str_starts_with($n, '//')) return false;
    if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $n)) return false;
  
    return (bool)preg_match("~^/[A-Za-z0-9._\\~!$&()*,;=:@%/\\-]*$~", $n);
}

// --- Build safe next ---
$next = '/pages/portal';
if (!empty($_GET['next']) && is_string($_GET['next']) && is_safe_next($_GET['next'])) {
  $next = (string)$_GET['next'];
}

// --- PKCE ---
$verifier  = b64url(random_bytes(32));
$challenge = b64url(hash('sha256', $verifier, true));

// --- State (CSRF + embed next) ---
$statePayload = [
  'csrf' => b64url(random_bytes(16)),
  't'    => time(),
  'next' => $next,
];
$state = b64url(json_encode($statePayload, JSON_UNESCAPED_SLASHES));

// --- Persist ke session ---
$_SESSION['oauth2_google'] = [
  'verifier' => $verifier,
  'state'    => $statePayload,
  'started'  => time(),
];

// --- Redirect ke Google ---
$authorize = 'https://accounts.google.com/o/oauth2/v2/auth';

$q = http_build_query([
  'response_type'         => 'code',
  'client_id'             => $CLIENT_ID,
  'redirect_uri'          => $REDIRECT_URI,
  'scope'                 => 'openid email profile',
  'prompt'                => 'select_account',
  'access_type'           => 'offline',
  'include_granted_scopes'=> 'true',
  'state'                 => $state,
  'code_challenge'        => $challenge,
  'code_challenge_method' => 'S256',
], '', '&', PHP_QUERY_RFC3986);

header('Location: ' . $authorize . '?' . $q, true, 302);
exit;
