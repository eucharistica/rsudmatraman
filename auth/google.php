<?php
declare(strict_types=1);


require_once __DIR__ . '/../lib/session.php';


// === Load config as array ===
$CFG = require __DIR__ . '/../_private/website.php';


// Start session early (correct helper name)
session_boot();


// Build safe `next`
$next = '/pages/portal';
if (!empty($_GET['next']) && is_string($_GET['next']) && preg_match('~^/[A-Za-z0-9._~!$&\'()*+,;=:@%/\-]*$~', $_GET['next'])) {
$next = $_GET['next'];
}


// Helper
function b64url(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }


// PKCE values
$verifier = b64url(random_bytes(32));
$challenge = b64url(hash('sha256', $verifier, true));


// CSRF state (embed next inside state; do NOT trust query later)
$statePayload = [
'csrf' => b64url(random_bytes(16)),
't' => time(),
'next' => $next,
];
$state = b64url(json_encode($statePayload, JSON_UNESCAPED_SLASHES));


// Persist to session
$_SESSION['oauth2_google'] = [
'verifier' => $verifier,
'state' => $statePayload,
'started' => time(),
];


// Google auth endpoint
$authorize = 'https://accounts.google.com/o/oauth2/v2/auth';


$query = http_build_query([
'response_type' => 'code',
'client_id' => $CFG['GOOGLE_CLIENT_ID'],
'redirect_uri' => $CFG['GOOGLE_REDIRECT_URI'],
'scope' => 'openid email profile',
'prompt' => 'select_account',
'access_type' => 'offline',
'include_granted_scopes' => 'true',
'state' => $state,
'code_challenge' => $challenge,
'code_challenge_method' => 'S256',
], '', '&', PHP_QUERY_RFC3986);


header('Location: ' . $authorize . '?' . $query, true, 302);
exit;