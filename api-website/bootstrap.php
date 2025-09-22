<?php
declare(strict_types=1);

@ini_set('display_errors', '0');
error_reporting(E_ALL);

// ---------- open_basedir helpers (SELALU aman) ----------
function ob_allowed(): array {
    $base = ini_get('open_basedir');
    if (!$base) return []; // tidak dibatasi
    // pecah by ":" (Linux) atau ";" (Windows)
    $parts = preg_split('/[:;]/', (string)$base) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $rp = @realpath($p);
        $out[] = $rp ?: rtrim(str_replace('\\','/',$p), '/');
    }
    return $out;
}
function path_allowed(string $path): bool {
    $allowed = ob_allowed();                 // array selalu
    if (empty($allowed)) return true;        // open_basedir tidak aktif
    $rp = @realpath($path);
    if ($rp === false) $rp = $path;
    $rp = rtrim(str_replace('\\','/',$rp), '/');
    foreach ($allowed as $a) {
        $a = rtrim(str_replace('\\','/',$a), '/');
        if ($rp === $a || strpos($rp, $a . '/') === 0) return true;
    }
    return false;
}

// ---------- load config file yang return array ----------
function try_load_config(string $path): array {
    if (!path_allowed($path)) return [];
    if (!@is_file($path) || !@is_readable($path)) return [];
    if (!defined('RSUD_API_CONTEXT')) define('RSUD_API_CONTEXT', true);
    $cfg = @require $path;
    return is_array($cfg) ? $cfg : [];
}

// ---------- kandidat path config ----------
$candidates = [];
$envPath = getenv('RSUD_API_CONFIG');
if (is_string($envPath) && $envPath !== '') $candidates[] = $envPath;

// 1) /public/_private/api-config.php
$candidates[] = __DIR__ . '/../_private/api-config.php';
// 2) /public/api-website/_private/api-config.php
$candidates[] = __DIR__ . '/_private/api-config.php';

$loaded = [];
$GLOBALS['RSUD_CFG_PATH'] = null;

$APP_DEBUG = (string)env('APP_DEBUG','0') === '1';
$IS_LOCAL  = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'])
          || preg_match('~^(100\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.)~', $_SERVER['REMOTE_ADDR'] ?? '');
if (!($APP_DEBUG && $IS_LOCAL) && isset($_GET['diag'])) {
  http_response_code(404); exit; // sembunyikan diag di produksi/akses publik
}

// (opsional) diagnosa path sebelum load
// if (isset($_GET['diag']) && $_GET['diag'] === 'paths') {
//     header('Content-Type: text/plain; charset=utf-8');
//     echo "open_basedir=".(ini_get('open_basedir')?:'(none)')."\n";
//     foreach ($candidates as $cand) {
//         $allowed = path_allowed($cand) ? 1 : 0;
//         $exists  = @is_file($cand) ? 1 : 0;
//         $read    = @is_readable($cand) ? 1 : 0;
//         $real    = @realpath($cand) ?: '(no-realpath)';
//         echo "cand={$cand}\n  realpath={$real}\n  allowed={$allowed} exists={$exists} readable={$read}\n";
//     }
//     exit;
// }

// load pertama yang valid
foreach ($candidates as $cand) {
    $cfg = try_load_config($cand);
    if (!empty($cfg)) {
        $loaded = $cfg;
        $GLOBALS['RSUD_CFG_PATH'] = @realpath($cand) ?: $cand;
        break;
    }
}

// simpan ke global & $_ENV (tanpa bergantung pada putenv)
$GLOBALS['RSUD_CFG'] = is_array($loaded) ? $loaded : [];
if (is_array($loaded)) {
    foreach ($loaded as $k => $v) {
        if (!array_key_exists($k, $_ENV) || $_ENV[$k] === '') $_ENV[$k] = $v;
        if (function_exists('putenv')) { @putenv($k.'='.$v); } // boleh gagal, ada fallback
    }
}

// helper ENV: urutan baca getenv() → $_ENV → $GLOBALS['RSUD_CFG'] → default
function env(string $key, $default = null) {
    $gv = getenv($key);
    if ($gv !== false && $gv !== '') return $gv;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($GLOBALS['RSUD_CFG'][$key]) && $GLOBALS['RSUD_CFG'][$key] !== '') return $GLOBALS['RSUD_CFG'][$key];
    return $default;
}

// ---------- diagnostik ENV aman ----------
// if (isset($_GET['diag']) && $_GET['diag'] === 'env') {
//     header('Content-Type: text/plain; charset=utf-8');
//     $mask = function($s){ $s = (string)$s; if ($s==='') return '(empty)'; return strlen($s)<=2 ? '**' : substr($s,0,2).'***'; };
//     $putenvUsable = (function_exists('putenv') && strpos(strtolower((string)ini_get('disable_functions')), 'putenv') === false) ? 'yes' : 'no';
//     echo "open_basedir=".(ini_get('open_basedir')?:'(none)')."\n";
//     echo "config_path=".($GLOBALS['RSUD_CFG_PATH'] ?? '(none)')."\n";
//     echo "putenv_usable=".$putenvUsable."\n";
//     echo "DB_HOST=".$mask(env('DB_HOST',''))."\n";
//     echo "DB_NAME=".$mask(env('DB_NAME',''))."\n";
//     echo "DB_USER=".$mask(env('DB_USER',''))."\n";
//     echo "DB_PASS=".$mask(env('DB_PASS',''))."\n";
//     echo "DB_CHARSET=".$mask(env('DB_CHARSET',''))."\n";
//     echo "APP_TZ=".$mask(env('APP_TZ',''))."\n";
//     exit;
// }

// ---------- timezone ----------
$tz = env('APP_TZ', 'Asia/Jakarta');
if ($tz) @date_default_timezone_set($tz);

// ---------- DB ----------
function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;

    $name = env('DB_NAME', '');
    $user = env('DB_USER', '');
    if ($name === '' || $user === '') {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'DB config missing','detail'=>'DB_NAME/DB_USER belum terisi']);
        exit;
    }

    $host = env('DB_HOST', '127.0.0.1');
    $pass = env('DB_PASS', '');
    $charset = env('DB_CHARSET', 'utf8mb4');
    $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $opts);
    try { $pdo->exec("SET time_zone = '+07:00'"); } catch (\Throwable $e) {}
    return $pdo;
}
