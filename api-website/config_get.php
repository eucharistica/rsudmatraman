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
  $BASE = find_base_with_lib(__DIR__); // <— auto-discover
  if (!$BASE) throw new RuntimeException('Library path not found');
  require_once $BASE . '/lib/app.php';
  app_boot();
  $db = db();
  rbac_require_roles($db, ['admin']);

  $keys = $_GET['keys'] ?? '';
  $list = array_filter(array_map('trim', explode(',', (string)$keys)));
  if (!$list) {
    $list = [
      'site.name','site.tagline',
      'contact.phone','contact.whatsapp','contact.email','contact.address',
      'links.website',
      'social.facebook','social.x','social.instagram','social.tiktok','social.youtube',
      'smtp.enabled','smtp.host','smtp.port','smtp.username','smtp.password','smtp.secure','smtp.from_email','smtp.from_name',
    ];
  }
  $data = [];
  foreach ($list as $k) $data[$k] = config_get($k, null);

  echo json_encode(['ok'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(500);
  while (ob_get_level()) ob_end_clean();
  echo json_encode(['ok'=>false,'error'=>'internal','message'=>$e->getMessage()]);
}
