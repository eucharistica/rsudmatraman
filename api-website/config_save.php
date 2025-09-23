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
  session_boot();
  $db = db();
  rbac_require_roles($db, ['admin']);

  if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit;
  }
  $uid = (int)($_SESSION['user']['id'] ?? 0);

  $map = [
    'site.name' => ['type'=>'string','group'=>'general'],
    'site.tagline'=>['type'=>'string','group'=>'general'],
    'contact.phone'=>['type'=>'string','group'=>'contact'],
    'contact.whatsapp'=>['type'=>'string','group'=>'contact'],
    'contact.email'=>['type'=>'string','group'=>'contact'],
    'contact.address'=>['type'=>'string','group'=>'contact'],
    'links.website'=>['type'=>'string','group'=>'links'],
    'social.facebook'=>['type'=>'string','group'=>'social'],
    'social.x'=>['type'=>'string','group'=>'social'],
    'social.instagram'=>['type'=>'string','group'=>'social'],
    'social.tiktok'=>['type'=>'string','group'=>'social'],
    'social.youtube'=>['type'=>'string','group'=>'social'],
    'smtp.enabled'=>['type'=>'boolean','group'=>'smtp'],
    'smtp.host'=>['type'=>'string','group'=>'smtp'],
    'smtp.port'=>['type'=>'number','group'=>'smtp'],
    'smtp.username'=>['type'=>'string','group'=>'smtp'],
    'smtp.password'=>['type'=>'string','group'=>'smtp'],
    'smtp.secure'=>['type'=>'string','group'=>'smtp'],
    'smtp.from_email'=>['type'=>'string','group'=>'smtp'],
    'smtp.from_name'=>['type'=>'string','group'=>'smtp'],
  ];

  foreach ($map as $key=>$meta) {
    $type = $meta['type']; $group=$meta['group'];
    $formKey = str_replace('.','_', $key);

    if ($type === 'boolean') {
      $val = isset($_POST[$formKey]) ? true : false;
      config_set($key, $val, 'boolean', $group, $uid);
      continue;
    }
    if (!array_key_exists($formKey, $_POST)) continue;

    $val = trim((string)$_POST[$formKey]);
    if (($key === 'contact.email' || $key === 'smtp.from_email') && $val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
      throw new RuntimeException("Email tidak valid: $key");
    }
    if ($key === 'links.website' && $val !== '' && !preg_match('~^https?://~i', $val)) {
      throw new RuntimeException("URL website harus diawali http(s)://");
    }
    if ($key === 'smtp.port' && $val !== '' && !ctype_digit($val)) {
      throw new RuntimeException("Port SMTP harus angka");
    }
    config_set($key, $val, $type==='number'?'number':'string', $group, $uid);
  }

  if (function_exists('audit_log')) {
    audit_log('config','update',['message'=>'Update konfigurasi','meta'=>['by'=>$uid]]);
  }

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(500);
  while (ob_get_level()) ob_end_clean();
  echo json_encode(['ok'=>false,'error'=>'internal','message'=>$e->getMessage()]);
}
