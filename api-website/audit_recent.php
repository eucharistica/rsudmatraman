<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
error_reporting(E_ALL);

ob_start();
register_shutdown_function(function(){
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR])) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'fatal','message'=>$e['message']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  } else {
    $buf = ob_get_contents();
    if ($buf !== false) ob_end_flush();
  }
});

/** Cari BASE yang punya folder /lib berisi session.php, db.php, auth.php */
function find_base_with_lib(string $startDir, int $maxUp = 8): ?string {
  $dir = $startDir;
  for ($i=0; $i<=$maxUp; $i++) {
    $lib = $dir . DIRECTORY_SEPARATOR . 'lib';
    if (is_dir($lib)
        && is_file($lib . DIRECTORY_SEPARATOR . 'session.php')
        && is_file($lib . DIRECTORY_SEPARATOR . 'db.php')
        && is_file($lib . DIRECTORY_SEPARATOR . 'auth.php')) {
      return $dir;
    }
    $parent = dirname($dir);
    if ($parent === $dir) break; // sampai root disk
    $dir = $parent;
  }
  return null;
}

try {
  $start = __DIR__; // lokasi: .../public/api-website
  $BASE  = find_base_with_lib($start);
  if ($BASE === null) {
    throw new RuntimeException('Library path not found from: '.$start);
  }
  require_once $BASE . '/lib/session.php';
  require_once $BASE . '/lib/db.php';
  require_once $BASE . '/lib/auth.php';
  session_boot();

  // Hanya admin/editor yang boleh akses data audit
  $role = $_SESSION['user']['role'] ?? 'user';
  if (!in_array($role, ['admin','editor'], true)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
  }

  $db = db(); // pastikan lib/db.php pakai Opsi 1: function db(): PDO

  // === Filters ===
  $limit = max(1, min(100, (int)($_GET['limit'] ?? 30)));
  $page  = max(1, (int)($_GET['page'] ?? 1));
  $offset = ($page - 1) * $limit;
  $event = trim((string)($_GET['event'] ?? ''));
  $action= trim((string)($_GET['action'] ?? ''));
  $rolef = trim((string)($_GET['role'] ?? ''));
  $email = trim((string)($_GET['email'] ?? ''));
  $q     = trim((string)($_GET['q'] ?? ''));
  $from  = trim((string)($_GET['from'] ?? ''));
  $to    = trim((string)($_GET['to'] ?? ''));
  $countSql = "SELECT COUNT(*) FROM audit_logs";
  if ($where) $countSql .= ' WHERE '.implode(' AND ', $where);
  $stc = $db->prepare($countSql);
  foreach ($bind as $k=>$v) $stc->bindValue($k, $v);
  $stc->execute();
  $total = (int)$stc->fetchColumn();

  $where=[]; $bind=[];
  if ($event!==''){ $where[]='event=:event';       $bind[':event']=$event; }
  if ($action!==''){ $where[]='action=:action';    $bind[':action']=$action; }
  if ($rolef!==''){ $where[]='user_role=:urole';   $bind[':urole']=$rolef; }
  if ($email!==''){ $where[]='user_email LIKE :email'; $bind[':email']='%'.$email.'%'; }
  if ($q!==''){ $where[]='(message LIKE :q OR target_type LIKE :q OR target_id LIKE :q)'; $bind[':q']='%'.$q.'%'; }
  if ($from!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){ $where[]='created_at >= :from'; $bind[':from']=$from.' 00:00:00'; }
  if ($to!==''   && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){   $where[]='created_at <= :to';   $bind[':to']=$to.' 23:59:59'; }

  $sql = "SELECT id,user_email,user_name,user_role,event,action,target_type,target_id,message,ip,user_agent,created_at
        FROM audit_logs";
  if ($where) $sql .= ' WHERE '.implode(' AND ',$where);
  $sql .= " ORDER BY id DESC LIMIT :lim OFFSET :off";

  $st = $db->prepare($sql);
  foreach ($bind as $k=>$v) $st->bindValue($k, $v);
  $st->bindValue(':lim', $limit, PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll();

  if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="audit_logs.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array_keys($rows[0] ?? [
      'id','user_email','user_name','user_role','event','action','target_type',
      'target_id','message','ip','user_agent','created_at'
    ]));
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out); exit;
  }

  echo json_encode(['ok'=>true,'items'=>$rows], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  while (ob_get_level()) ob_end_clean();
  echo json_encode([
    'ok'=>false,'error'=>'internal','message'=>$e->getMessage()
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
