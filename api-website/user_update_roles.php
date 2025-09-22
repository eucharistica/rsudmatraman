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
    echo json_encode(['ok'=>false,'error'=>'fatal','message'=>$e['message']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  } else {
    $buf = ob_get_contents(); if ($buf !== false) ob_end_flush();
  }
});

/** auto-discover BASE (folder yang berisi /lib) */
function find_base_with_lib(string $start, int $maxUp=8): ?string {
  $d = $start;
  for ($i=0; $i<=$maxUp; $i++) {
    $lib = $d . DIRECTORY_SEPARATOR . 'lib';
    if (is_dir($lib)
        && is_file($lib.'/app.php')) return $d;
    $p = dirname($d); if ($p === $d) break; $d = $p;
  }
  return null;
}

try {
  $BASE = find_base_with_lib(__DIR__);
  if (!$BASE) throw new RuntimeException('Library path not found');
  require_once $BASE . '/lib/app.php';
  session_boot();

  // Guard admin
  $db = db();
  rbac_require_roles($db, ['admin']);

  // CSRF
  $csrf = (string)($_POST['csrf'] ?? '');
  if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'bad_csrf','message'=>'Token CSRF tidak valid']); exit;
  }

  $userId = (int)($_POST['user_id'] ?? 0);
  $status = strtolower(trim((string)($_POST['status'] ?? '')));
  $roles  = $_POST['roles'] ?? [];
  if ($userId <= 0 || !is_array($roles)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'bad_request','message'=>'Parameter tidak valid']); exit;
  }
  if ($status !== 'active' && $status !== 'inactive') $status = 'active';

  // Normalisasi peran
  $roles = array_values(array_filter(array_map(fn($s)=> strtolower(trim((string)$s)), $roles)));

  // Update role (replace all)
  rbac_set_user_roles($db, $userId, $roles);

  // Update status
  $st = $db->prepare("UPDATE users SET status=:s, updated_at=NOW() WHERE id=:u");
  $st->execute([':s'=>$status, ':u'=>$userId]);

  // Audit
  if (function_exists('audit_log')) {
    audit_log('permission','roles_update', [
      'message' => 'Update roles & status',
      'meta'    => ['user_id'=>$userId,'roles'=>$roles,'status'=>$status]
    ]);
  }

  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;

} catch (Throwable $e) {
  http_response_code(500);
  while (ob_get_level()) ob_end_clean();
  echo json_encode(['ok'=>false,'error'=>'internal','message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}
