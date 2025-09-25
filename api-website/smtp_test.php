<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
@ini_set('display_errors','0'); @ini_set('log_errors','1'); error_reporting(E_ALL);

ob_start();
register_shutdown_function(function(){
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR])) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'fatal','message'=>$e['message']]);
  }
});

function find_base_with_lib(string $start, int $maxUp=8): ?string {
  $d = $start;
  for ($i=0; $i<=$maxUp; $i++) {
    if (is_file($d.'/lib/app.php')) return $d;
    $p = dirname($d); if ($p === $d) break; $d = $p;
  }
  return null;
}

try {
  $BASE = find_base_with_lib(__DIR__);
  if (!$BASE) throw new RuntimeException('Library path not found');
  require_once $BASE . '/lib/app.php';
  app_boot();
  $db = db();
  rbac_require_roles($db, ['admin']);

  $to = (string)($_POST['to'] ?? $_GET['to'] ?? config_get('contact.email', ''));
  if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException('Email tujuan tidak valid (parameter ?to=...)');
  }

  $ok = mailer_send(
    $to,
    '',
    'Tes SMTP — ' . (string)config_get('site.name','Website'),
    '<p>Ini adalah email tes dari sistem.</p><p>Waktu: '.date('Y-m-d H:i:s').'</p>'
  );

  if (function_exists('audit_log')) {
    audit_log('email', $ok ? 'smtp_test_ok' : 'smtp_test_fail', [
      'message' => 'Tes SMTP',
      'meta'    => ['to'=>$to]
    ]);
  }

  echo json_encode(['ok'=>$ok]);
} catch (Throwable $e) {
  http_response_code(500);
  while (ob_get_level()) ob_end_clean();
  echo json_encode(['ok'=>false,'error'=>'internal','message'=>$e->getMessage()]);
}
