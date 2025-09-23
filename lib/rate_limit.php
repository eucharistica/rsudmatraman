<?php
declare(strict_types=1);

// sangat sederhana — simpan counter di session. Nanti bisa pindah ke Redis/DB.
function login_throttle_check(): bool {
  $now = time();
  $w   = $_SESSION['login_window'] ?? ['start'=>$now,'fail'=>0];
  if ($now - $w['start'] > 300) $w = ['start'=>$now,'fail'=>0]; // reset 5 menit
  $_SESSION['login_window'] = $w;
  // true artinya masih boleh mencoba
  return $w['fail'] < 5;
}
function login_throttle_fail(): void {
  $now = time();
  $w   = $_SESSION['login_window'] ?? ['start'=>$now,'fail'=>0];
  if ($now - $w['start'] > 300) $w = ['start'=>$now,'fail'=>0];
  $w['fail']++;
  $_SESSION['login_window'] = $w;
}
function login_throttle_reset(): void {
  unset($_SESSION['login_window']);
}
function ratelimit_enforce(string $bucket, int $max, int $windowSeconds, ?callable $onLimit = null): void {
  $dir = __DIR__ . '/../storage/runtime/ratelimit';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);

  $now = time();
  $file = $dir . '/' . preg_replace('/[^a-zA-Z0-9_\-:.]/', '_', $bucket) . '.json';
  $data = ['reset' => $now + $windowSeconds, 'count' => 0];

  if (is_file($file)) {
      $tmp = json_decode((string)@file_get_contents($file), true);
      if (is_array($tmp)) $data = array_merge($data, $tmp);
      if ($data['reset'] < $now) $data = ['reset' => $now + $windowSeconds, 'count' => 0];
  }

  $data['count']++;
  @file_put_contents($file, json_encode($data), LOCK_EX);

  header('X-RateLimit-Limit: '.$max);
  header('X-RateLimit-Remaining: '.max(0, $max - $data['count']));
  header('X-RateLimit-Reset: '.$data['reset']);

  if ($data['count'] > $max) {
      if ($onLimit) $onLimit();
      http_response_code(429);
      header('Content-Type: application/json');
      echo json_encode(['error' => 'Too Many Requests', 'retry_after' => $data['reset'] - $now]);
      exit;
  }
}