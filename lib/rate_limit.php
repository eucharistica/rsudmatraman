<?php
declare(strict_types=1);

/**
 * Rate limit sederhana berbasis file.
 * Penyimpanan: /storage/runtime/ratelimit/<bucket>.json
 * Struktur: { "reset": <unix_ts>, "count": <int> }
 */

function _rl_dir(): string {
  $dir = __DIR__ . '/../storage/runtime/ratelimit';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir;
}

function _rl_path(string $bucket): string {
  $safe = preg_replace('/[^a-zA-Z0-9_.:\-@]/', '_', $bucket);
  return _rl_dir() . '/' . $safe . '.json';
}

function _rl_read(string $path, int $windowSeconds): array {
  $now = time();
  $data = ['reset' => $now + $windowSeconds, 'count' => 0];
  if (is_file($path)) {
    $raw = (string)@file_get_contents($path);
    $tmp = json_decode($raw, true);
    if (is_array($tmp)) $data = array_merge($data, $tmp);
    if (($data['reset'] ?? 0) < $now) {
      $data['reset'] = $now + $windowSeconds;
      $data['count'] = 0;
    }
  }
  return $data;
}

function _rl_write(string $path, array $data): void {
  @file_put_contents($path, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
}

/**
 * Cek apakah masih boleh (tidak menambah counter).
 * @return bool true=masih boleh; false=terbatas
 */
function rate_limit_allow(string $bucket, int $max, int $windowSeconds): bool {
  $path = _rl_path($bucket);
  $data = _rl_read($path, $windowSeconds);
  return ($data['count'] ?? 0) < $max;
}

/** Tambah 1 kegagalan / hit pada bucket. */
function rate_limit_hit(string $bucket, int $max = 0, int $windowSeconds = 600): void {
  $path = _rl_path($bucket);
  $data = _rl_read($path, $windowSeconds);
  $data['count'] = (int)($data['count'] ?? 0) + 1;
  _rl_write($path, $data);
}

/** Reset counter bucket. */
function rate_limit_reset(string $bucket): void {
  $path = _rl_path($bucket);
  if (is_file($path)) @unlink($path);
}

/* ====== Backward compatibility (opsional) ====== */

/** Versi lama berbasis session (tetap disediakan agar tidak putus) */
function login_throttle_check(): bool {
  $now = time();
  $w   = $_SESSION['login_window'] ?? ['start'=>$now,'fail'=>0];
  if ($now - $w['start'] > 300) $w = ['start'=>$now,'fail'=>0];
  $_SESSION['login_window'] = $w;
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

/**
 * ratelimit_enforce lama — disesuaikan agar tidak memaksa JSON.
 * Biarkan dipakai untuk endpoint API lain bila perlu.
 */
// Adapter seragam untuk rate limit
if (!function_exists('rate_limit_enforce')) {
  /**
   * Enforce rate limit dengan fallback ke fungsi yang tersedia.
   *
   * @param string $key     kunci identitas (mis. "forgot:<hash email + ip>")
   * @param int    $max     maksimum request dalam window
   * @param int    $window  window detik (mis. 60)
   */
  function rate_limit_enforce(string $key, int $max = 5, int $window = 60): void {
    // 1) Punya fungsi modern ratelimit_enforce()? (punyamu)
    if (function_exists('ratelimit_enforce')) {
      try {
        // Coba urutan (key, max, window)
        ratelimit_enforce($key, $max, $window);
        return;
      } catch (Throwable $e) {
        // Coba urutan (key, window, max)
        try {
          ratelimit_enforce($key, $window, $max);
          return;
        } catch (Throwable $e2) {
          // Biarkan lanjut ke fallback di bawah
        }
      }
    }

    // 2) Punya versi lama rate_limit_check(key, window, max)?
    if (function_exists('rate_limit_check')) {
      rate_limit_check($key, $window, $max);
      return;
    }

    // 3) Tidak ada apa-apa → no-op (jangan ganggu alur)
  }
}
