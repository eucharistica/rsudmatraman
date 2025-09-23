<?php
declare(strict_types=1);

function config_get(string $key, $default = null) {
  static $cache = [];
  if (array_key_exists($key, $cache)) return $cache[$key];

  $db = db();
  $st = $db->prepare("SELECT `value`,`type` FROM settings WHERE `key`=:k LIMIT 1");
  $st->execute([':k'=>$key]);
  $row = $st->fetch();
  if (!$row) return $cache[$key] = $default;

  $val = $row['value'];
  switch ($row['type'] ?? '') {
    case 'boolean': return $cache[$key] = ($val === '1' || strtolower((string)$val) === 'true');
    case 'number':  return $cache[$key] = is_numeric($val) ? 0 + $val : $default;
    case 'json':    $j = json_decode((string)$val, true); return $cache[$key] = (is_array($j)||is_object($j)) ? $j : $default;
    default:        return $cache[$key] = $val;
  }
}

function config_set(string $key, $value, string $type = 'string', ?string $group = null, ?int $userId = null): void {
  $db = db();
  if ($type === 'boolean') $value = $value ? '1' : '0';
  elseif ($type === 'json') $value = json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

  $st = $db->prepare("INSERT INTO settings (`key`,`value`,`type`,`group_key`,`updated_by`,`updated_at`)
                      VALUES (:k,:v,:t,:g,:u,NOW())
                      ON DUPLICATE KEY UPDATE `value`=VALUES(`value`),`type`=VALUES(`type`),`group_key`=VALUES(`group_key`),`updated_by`=VALUES(`updated_by`),`updated_at`=NOW()");
  $st->execute([
    ':k'=>$key, ':v'=>(string)$value, ':t'=>$type, ':g'=>$group, ':u'=>$userId
  ]);

  // refresh cache lokal
  static $cache = [];
  $cache[$key] = $value;
}

function config_group(array $keys): array {
  $out = [];
  foreach ($keys as $k => $def) {
    if (is_int($k)) { $out[$def] = config_get($def); }
    else { $out[$k] = config_get($k, $def); }
  }
  return $out;
}
