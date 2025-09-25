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

$exts = [
  'brid-vclaim' => 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/',
  'brid-antreanrs' => 'https://apijkn.bpjs-kesehatan.go.id/antreanrs',
  'fingerprint-bpjs'  => 'https://fp.bpjs-kesehatan.go.id/finger-rest/',
  'sipp-bpjs'  => 'https://sipp.bpjs-kesehatan.go.id/sipp/#/home/dashboard',
  'apotek-bpjs'  => 'https://apotek.bpjs-kesehatan.go.id/apotek/',
  'hfis-bpjs'  => 'https://hfis.bpjs-kesehatan.go.id/hfis/login',
  
];
foreach ($exts as $k=>$url) {
  $t0 = microtime(true);
  $ok = false;
  try {
    $ctx = stream_context_create(['http'=>['timeout'=>2]]);
    $raw = @file_get_contents($url, false, $ctx);
    $ok  = ($raw !== false);
  } catch (\Throwable $e) { $ok=false; }
  $out['ext'][$k] = ['ok'=>$ok,'ms'=> (int)((microtime(true)-$t0)*1000)];
}

function find_base_with_lib(string $startDir, int $maxUp = 8): ?string {
  $dir = $startDir;
  for ($i=0; $i<=$maxUp; $i++) {
    $lib = $dir . DIRECTORY_SEPARATOR . 'lib';
    if (is_dir($lib)
        && is_file($lib . DIRECTORY_SEPARATOR . 'session.php')
        && is_file($lib . DIRECTORY_SEPARATOR . 'db.php')) {
      return $dir;
    }
    $parent = dirname($dir);
    if ($parent === $dir) break;
    $dir = $parent;
  }
  return null;
}

try {
  $BASE = find_base_with_lib(__DIR__);
  if ($BASE === null) throw new RuntimeException('Library path not found from: '.__DIR__);
  require_once $BASE . '/lib/session.php';
  require_once $BASE . '/lib/db.php';
  app_boot();

  $out = ['ok'=>true, 'db'=>null, 'ext'=>[]];

  if (isset($_GET['db'])) {
    $db = db();
    $counts = [];
    foreach (['poliklinik','dokter','jadwal'] as $tbl) {
      try {
        $stmt = $db->query("SELECT COUNT(*) FROM `$tbl`");
        $counts[$tbl] = (int)$stmt->fetchColumn();
      } catch (Throwable $e) {
        $counts[$tbl] = null;
      }
    }
    $out['db'] = ['ok'=>true, 'counts'=>$counts];
  }

  echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  while (ob_get_level()) ob_end_clean();
  echo json_encode(['ok'=>false,'error'=>'internal','message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
