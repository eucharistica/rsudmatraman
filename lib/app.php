<?php
declare(strict_types=1);

/**
 * lib/app.php
 * Pondasi bootstrap aplikasi:
 * - Session + Security Headers (CSP yang cocok dgn Alpine CDN, reCAPTCHA, Gravatar)
 * - Error handler (DEV menampilkan detail; PROD menampilkan halaman error)
 * - Maintenance mode (file toggle / config DB, allow admin & whitelist IP)
 * - Tidak bergantung framework eksternal
 *
 * PAKAI di setiap halaman publik:
 *   require_once __DIR__ . '/app.php';
 *   app_boot();
 */

// === Dependensi dasar (jangan ada require ke file ini dari file-file di bawah) ===
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/config.php';

// -----------------------------------------------------------------------------
// Konfigurasi lingkungan sederhana
// -----------------------------------------------------------------------------
function app_env(): string {
  // Bisa di-set via webserver/env. Default "production".
  $env = getenv('APP_ENV') ?: ($_SERVER['APP_ENV'] ?? 'production');
  return is_string($env) ? strtolower($env) : 'production';
}
function app_is_dev(): bool {
  $dev = getenv('APP_DEBUG') ?: ($_SERVER['APP_DEBUG'] ?? null);
  if ($dev !== null) return in_array((string)$dev, ['1','true','on','yes','dev'], true);
  return app_env() !== 'production';
}

// -----------------------------------------------------------------------------
// BOOT utama — panggil di awal setiap halaman
// -----------------------------------------------------------------------------
/**
 * app_boot()
 * - Start session
 * - Kirim security headers (CSP, HSTS, dll)
 * - Pasang error/exception handler
 * - Maintenance guard (kecuali path yang di-skip)
 */
function app_boot(array $opts = []): void {
  static $done = false; if ($done) return; $done = true;

  session_boot();
  send_security_headers();
  init_error_handling();

  // Skip maintenance untuk path tertentu (auth callback / api test dsb)
  $skip = $opts['skip_maintenance_for'] ?? [
    '/auth/',                  // alur login
    '/api-website/smtp_test.php', // smtp test
  ];
  $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
  $path = (string)parse_url($uri, PHP_URL_PATH);

  foreach ($skip as $prefix) {
    if (str_starts_with($path, $prefix)) {
      return;
    }
  }

  maintenance_guard();
}

// -----------------------------------------------------------------------------
// Security Headers (CSP disesuaikan: Alpine CDN, reCAPTCHA, Gravatar)
// -----------------------------------------------------------------------------
function send_security_headers(): void {
    static $sent = false; if ($sent || headers_sent()) return; $sent = true;
  
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-site');
    header('X-XSS-Protection: 0');
  
    // Host sendiri (dipakai untuk self connect di beberapa server)
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $self = $host !== '' ? "https://{$host}" : "'self'";
  
    // NOTE:
    // - 'unsafe-inline' di script-src sementara diperlukan karena kamu masih pakai inline <script>.
    //   Nanti jika JS dipindah ke file eksternal atau diberi nonce, hapus 'unsafe-inline'.
    // - Tambahkan domain API eksternal (rsudmatraman.jakarta.go.id) ke connect-src.
    // - Tambahkan domain Google Maps ke frame-src.
    $csp = [
      "default-src 'self'",
      // Alpine CDN + inline script sementara
      "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/ https://static.cloudflareinsights.com",
      // Tailwind CSS lokal + inline style (class utilities, style attr)
      "style-src 'self' 'unsafe-inline'",
      // Gambar dari mana saja yang aman (https) + data: (SVG inline/gravatar)
      "img-src 'self' https: data: https://www.gravatar.com",
      // Font lokal/https/data
      "font-src 'self' https: data:",
      // AJAX/fetch ke diri sendiri + API eksternal + recaptcha
      "connect-src 'self' {$self} https://website.rsudmatraman.my.id https://rsudmatraman.my.id https://rsudmatraman.jakarta.go.id https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/ https://cloudflareinsights.com https://static.cloudflareinsights.com",
      // Izinkan Google Maps iframe
      "frame-src 'self' https://www.google.com/ https://www.google.com/recaptcha/ https://recaptcha.google.com/",
      "base-uri 'self'",
      "form-action 'self'",
      "object-src 'none'",
      "frame-ancestors 'self'",
    ];
  
    header('Content-Security-Policy: '.implode('; ', $csp));
  
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '80') === '443');
    if ($https) {
      header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
  }
  

// -----------------------------------------------------------------------------
// Error Handling
// -----------------------------------------------------------------------------
function init_error_handling(): void {
  static $once = false; if ($once) return; $once = true;

  // Convert PHP notices/warnings menjadi ErrorException
  set_error_handler(function(int $severity, string $message, string $file, int $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
  });

  set_exception_handler(function(Throwable $e) {
    // Log detail
    error_log('[EXC] '.$e->getMessage().' @'.$e->getFile().':'.$e->getLine());
    if (app_is_dev()) {
      // Dev: tampilkan halaman debug sederhana
      if (!headers_sent()) http_response_code(500);
      $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES);
      $file = htmlspecialchars($e->getFile(), ENT_QUOTES);
      $line = (int)$e->getLine();
      echo "<!doctype html><meta charset='utf-8'><title>500 Debug</title>";
      echo "<div style='font-family:system-ui;padding:24px;max-width:900px;margin:auto'>";
      echo "<h1>500 — Debug</h1>";
      echo "<p><b>{$msg}</b><br><small>{$file}:{$line}</small></p>";
      echo "<pre style='white-space:pre-wrap;background:#f8f8f8;border:1px solid #eee;padding:12px;border-radius:8px;'>"
          .htmlspecialchars($e->getTraceAsString(), ENT_QUOTES)."</pre>";
      echo "</div>";
    } else {
      // Prod: render halaman error umum
      render_error(500, 'Terjadi kesalahan internal.');
    }
  });

  register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
      error_log('[FATAL] '.$err['message'].' @'.$err['file'].':'.$err['line']);
      if (app_is_dev()) {
        if (!headers_sent()) http_response_code(500);
        $msg  = htmlspecialchars($err['message'], ENT_QUOTES);
        $file = htmlspecialchars($err['file'], ENT_QUOTES);
        $line = (int)$err['line'];
        echo "<!doctype html><meta charset='utf-8'><title>500 Fatal</title>";
        echo "<div style='font-family:system-ui;padding:24px;max-width:900px;margin:auto'>";
        echo "<h1>500 — Fatal</h1>";
        echo "<p><b>{$msg}</b><br><small>{$file}:{$line}</small></p>";
        echo "</div>";
      } else {
        if (!headers_sent()) http_response_code(500);
        render_error(500, 'Terjadi kesalahan internal.');
      }
    }
  });
}

// -----------------------------------------------------------------------------
// Maintenance Guard
// -----------------------------------------------------------------------------
function maintenance_guard(): void {
  // 1) File toggle (_private/MAINTENANCE)
  $fileToggle = dirname(__DIR__) . '/_private/MAINTENANCE';
  $enabled = file_exists($fileToggle);

  // 2) Flag dari tabel config (opsional)
  try {
    $db = db();
    $stmt = $db->prepare("SELECT `value` FROM config WHERE `key` = 'site.maintenance' LIMIT 1");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    if ($val !== false) {
      $enabled = in_array((string)$val, ['1','true','on','yes'], true);
    }
  } catch (Throwable $e) {
    // Abaikan jika tabel belum ada
  }

  if (!$enabled) return;

  // Admin boleh lewat
  $u = auth_current_user();
  $role = strtolower((string)($u['role'] ?? ''));
  if ($role === 'admin') return;

  // Whitelist IP dari config
  $allowedIps = [];
  try {
    $db ??= db();
    $stmt = $db->prepare("SELECT `value` FROM config WHERE `key`='site.maintenance_allow_ips' LIMIT 1");
    $stmt->execute();
    $csv = (string)($stmt->fetchColumn() ?: '');
    $allowedIps = array_filter(array_map('trim', explode(',', $csv)));
  } catch (Throwable $e) {}

  $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
  if ($ip !== '' && in_array($ip, $allowedIps, true)) return;

  // Pesan kustom
  $msg = 'Situs sedang dalam perawatan. Silakan kembali beberapa saat lagi.';
  try {
    $db ??= db();
    $stmt = $db->prepare("SELECT `value` FROM config WHERE `key`='site.maintenance_message' LIMIT 1");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    if ($val) $msg = (string)$val;
  } catch (Throwable $e) {}

  if (!headers_sent()) {
    header('Retry-After: 600'); // 10 menit
    http_response_code(503);
  }
  render_error(503, $msg);
  exit;
}

// -----------------------------------------------------------------------------
// Renderer Error Umum
// -----------------------------------------------------------------------------
function render_error(int $code, string $message = ''): void {
  if (!headers_sent()) http_response_code($code);

  $title = match ($code) {
    403 => 'Akses ditolak',
    404 => 'Halaman tidak ditemukan',
    500 => 'Kesalahan internal',
    503 => 'Sedang perawatan',
    default => 'Terjadi kesalahan',
  };

  // Gunakan template jika tersedia: /errors/{code}.php
  $tpl = __DIR__ . '/../errors/' . $code . '.php';
  if (is_file($tpl)) {
    // Variabel yang tersedia: $code, $title, $message
    include $tpl;
    return;
  }

  // Fallback minimal
  $msg = htmlspecialchars($message, ENT_QUOTES);
  echo "<!doctype html><meta charset='utf-8'><title>{$code} {$title}</title>"
     . "<div style='font-family:system-ui;padding:40px;max-width:720px;margin:auto'>"
     . "<h1>{$code} — {$title}</h1><p>{$msg}</p></div>";
}
