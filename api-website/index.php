<?php
declare(strict_types=1);

// ===== Bootstrap =====
require_once __DIR__ . '/bootstrap.php';

// Poli yang tidak boleh tampil
const EXCLUDED_POLI = ['U0035','U0015','U0016','U0039','U0033','U0037','U0041','U0046','IGDK','U0012'];
$excludedList = "'" . implode("','", EXCLUDED_POLI) . "'";

// ===== Toggle produksi vs debug =====
// Set dari env/server: putenv('APP_DEBUG=0'); // di produksi
$APP_DEBUG = (bool) (getenv('APP_DEBUG') ?: false);

// Matikan display errors ke klien
ini_set('display_errors', '0');
error_reporting(E_ALL);

// ===== Security & CORS =====
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowed_patterns = [
  '~^https://([a-z0-9-]+\.)?rsudmatraman\.jakarta\.go\.id$~i',
  '~^https://([a-z0-9-]+\.)?rsudmatraman\.my\.id$~i',
];

$allowOrigin = null;
if ($origin) {
  foreach ($allowed_patterns as $pat) {
    if (preg_match($pat, $origin)) { $allowOrigin = $origin; break; }
  }
}
if ($allowOrigin) {
  header('Access-Control-Allow-Origin: ' . $allowOrigin);
  header('Vary: Origin');
  // Hidupkan credentials hanya jika kamu PASTI butuh cookie/sesi
  // header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, If-None-Match, X-Requested-With, Authorization');
header('Access-Control-Expose-Headers: X-Total-Count, ETag');
header('Access-Control-Max-Age: 86400');

// --- Security headers (API-friendly) ---
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
// HSTS aktifkan hanya jika SEMUA subdomain pakai HTTPS
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload'); 

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

// Selalu set output type
header('Content-Type: application/json; charset=utf-8');


// Helper respons JSON singkat 
function jsonOut($data, int $status = 200, array $extraHeaders = []): void {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) { $json = '{"ok":false,"error":"JSON encode failed"}'; $status = 500; }
    $etag = '"' . sha1($json) . '"';
    header('ETag: ' . $etag);
    foreach ($extraHeaders as $k => $v) header("$k: $v");
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) { http_response_code(304); exit; }
    http_response_code($status); echo $json; exit;
}

function bad(string $msg, int $status = 400): void {
    jsonOut(['ok' => false, 'error' => $msg], $status);
}

// ===== Error → JSON (tanpa detail ke klien di PROD) =====
set_exception_handler(function (Throwable $e) use ($APP_DEBUG) {
    // Log internal
    error_log('[API-EXCEPTION] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $payload = ['ok' => false, 'error' => 'Server error'];
    if ($APP_DEBUG) $payload['detail'] = $e->getMessage();
    jsonOut($payload, 500);
});
set_error_handler(function ($sev, $msg, $file, $line) use ($APP_DEBUG) {
    error_log("[API-ERROR] $msg @ $file:$line");
    $payload = ['ok' => false, 'error' => 'Server error'];
    if ($APP_DEBUG) $payload['detail'] = "$msg @ $file:$line";
    jsonOut($payload, 500);
});
register_shutdown_function(function () use ($APP_DEBUG) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log("[API-FATAL] {$e['message']} @ {$e['file']}:{$e['line']}");
        $payload = ['ok' => false, 'error' => 'Fatal error'];
        if ($APP_DEBUG) $payload['detail'] = $e['message'];
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }
});

// ===== Router: normalisasi base path agar /api-jadwal/jadwal match =====
$reqPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// contoh: SCRIPT_NAME = /api-jadwal/index.php  ⇒ base = /api-jadwal
$base = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');

if ($base !== '' && strpos($reqPath, $base) === 0) {
    // hasil: /jadwal
    $reqPath = substr($reqPath, strlen($base));
}
$path = '/' . ltrim($reqPath, '/');

// ===== Routes =====
switch (true) {
    case preg_match('~^/jadwal$~', $path):         handleJadwal(); break;
    case preg_match('~^/poliklinik$~', $path):     handlePoli();   break;
    case preg_match('~^/dokter$~', $path):         handleDokter(); break;
    case preg_match('~^/kamar$~', $path):          handleKamar(); break;
    case preg_match('~^/kamar/detail$~', $path):   handleKamarDetail(); break;
    case preg_match('~^/ping$~', $path):           handlePing(); break;  // <— ganti ini
    default: bad('Endpoint not found', 404);
}


function handlePing(): void {
    $withDb = isset($_GET['db']) && (int)$_GET['db'] === 1;
    if (!$withDb) {
        jsonOut(['ok'=>true,'pong'=>true]);
    }
    // Tes koneksi + sample count yang aman
    $debug = (bool) (getenv('APP_DEBUG') ?: false);
    try {
        $pdo = db();
        // cek minimal tabel ada dan bisa SELECT
        $cPoli = (int)$pdo->query("SELECT COUNT(*) FROM poliklinik")->fetchColumn();
        $cDok  = (int)$pdo->query("SELECT COUNT(*) FROM dokter")->fetchColumn();
        $cJad  = (int)$pdo->query("SELECT COUNT(*) FROM jadwal")->fetchColumn();
        jsonOut([
            'ok'=>true,
            'db'=>[
                'connected'=>true,
                'counts'=>['poliklinik'=>$cPoli,'dokter'=>$cDok,'jadwal'=>$cJad]
            ]
        ]);
    } catch (Throwable $e) {
        $payload = ['ok'=>false,'db'=>['connected'=>false],'error'=>'DB connect failed'];
        if ($debug) $payload['detail'] = $e->getMessage();
        jsonOut($payload, 500);
    }
}


// ===== Handlers =====
function handleJadwal(): void {
    $pdo = db();

    $kd_poli = trim($_GET['kd_poli'] ?? '');
    $hari    = strtoupper(trim($_GET['hari'] ?? ''));
    $q       = trim($_GET['q'] ?? '');
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 200)));

    $validHari = ['SENIN','SELASA','RABU','KAMIS','JUMAT','SABTU','MINGGU'];
    if ($hari && !in_array($hari, $validHari, true)) {
        bad('Parameter hari tidak valid. Gunakan: '.implode(',', $validHari));
    }

    // excluded list (pakai konstanta dari jawaban sebelumnya)
    $excludedList = "'" . implode("','", EXCLUDED_POLI) . "'";

    // Hanya aktif + exclude poli
    $where = ['p.status = "1"', 'd.status = "1"', "p.kd_poli NOT IN ($excludedList)"];
    $bind  = [];

    if ($kd_poli !== '') { $where[] = 'j.kd_poli = :kd_poli'; $bind[':kd_poli'] = $kd_poli; }
    if ($hari    !== '') { $where[] = 'j.hari_kerja = :hari';  $bind[':hari']    = $hari;    }
    if ($q       !== '') {
        // PENTING: dua placeholder berbeda (qq1 & qq2)
        $where[] = '(d.nm_dokter LIKE :qq1 OR p.nm_poli LIKE :qq2)';
        $bind[':qq1'] = '%'.$q.'%';
        $bind[':qq2'] = '%'.$q.'%';
    }
    $whereSql = 'WHERE '.implode(' AND ', $where);

    // Count
    $stc = $pdo->prepare("
        SELECT COUNT(*) AS c
        FROM jadwal j
        JOIN dokter d     ON d.kd_dokter = j.kd_dokter
        JOIN poliklinik p ON p.kd_poli   = j.kd_poli
        $whereSql
    ");
    foreach ($bind as $k=>$v) $stc->bindValue($k, $v);
    $stc->execute();
    $total = (int)$stc->fetchColumn();

    $offset = ($page - 1) * $perPage;

    // Data
    $st = $pdo->prepare("
        SELECT
            j.kd_dokter, d.nm_dokter,
            j.hari_kerja,
            DATE_FORMAT(j.jam_mulai, '%H:%i')   AS jam_mulai,
            DATE_FORMAT(j.jam_selesai, '%H:%i') AS jam_selesai,
            j.kd_poli, p.nm_poli
        FROM jadwal j
        JOIN dokter d     ON d.kd_dokter = j.kd_dokter
        JOIN poliklinik p ON p.kd_poli   = j.kd_poli
        $whereSql
        ORDER BY FIELD(j.hari_kerja,'SENIN','SELASA','RABU','KAMIS','JUMAT','SABTU','MINGGU'),
                 p.nm_poli, d.nm_dokter, j.jam_mulai
        LIMIT :limit OFFSET :offset
    ");
    foreach ($bind as $k=>$v) $st->bindValue($k, $v);
    $st->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $st->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();

    jsonOut([
        'ok'=>true,
        'data'=>$rows,
        'meta'=>[
            'total'=>$total,'page'=>$page,'per_page'=>$perPage,
            'filters'=>[
                'kd_poli'=>$kd_poli ?: null,
                'hari'   =>$hari    ?: null,
                'q'      =>$q       ?: null,
            ],
        ],
    ], 200, ['X-Total-Count'=>(string)$total]);
}



function handlePoli(): void {
    $pdo = db();
    $excludedList = "'" . implode("','", EXCLUDED_POLI) . "'";
    $rows = $pdo->query("
        SELECT kd_poli, nm_poli
        FROM poliklinik
        WHERE status = '1'
          AND kd_poli NOT IN ($excludedList)
        ORDER BY nm_poli
    ")->fetchAll();
    jsonOut(['ok'=>true,'data'=>$rows]);
}



function handleDokter(): void {
    $pdo = db();
    $kd_poli = trim($_GET['kd_poli'] ?? '');
    $excludedList = "'" . implode("','", EXCLUDED_POLI) . "'";

    if ($kd_poli !== '') {
        // Dokter yang punya jadwal di poli tsb; poli & dokter aktif; poli tidak disembunyikan
        $st = $pdo->prepare("
            SELECT DISTINCT d.kd_dokter, d.nm_dokter
            FROM dokter d
            JOIN jadwal j     ON j.kd_dokter = d.kd_dokter
            JOIN poliklinik p ON p.kd_poli   = j.kd_poli
            WHERE j.kd_poli = :kd_poli
              AND d.status = '1'
              AND p.status = '1'
              AND p.kd_poli NOT IN ($excludedList)
            ORDER BY d.nm_dokter
        ");
        $st->execute([':kd_poli'=>$kd_poli]);
        $rows = $st->fetchAll();
    } else {
        // Seluruh dokter aktif (tanpa memaksa harus punya jadwal pada poli non-excluded)
        $rows = $pdo->query("
            SELECT kd_dokter, nm_dokter
            FROM dokter
            WHERE status = '1'
            ORDER BY nm_dokter
        ")->fetchAll();
    }

    jsonOut(['ok'=>true,'data'=>$rows]);
}

/**
 * GET /kamar
 * Ringkasan ketersediaan per kelompok (jenis) & kelas.
 * - jenis (kelompok): Dewasa, Anak, VK, Khusus, Isolasi
 * - kelas: 'Kelas 1','Kelas 2','Kelas 3'
 */
function handleKamar(): void {
    $pdo = db();

    // Aggregasi per jenis+kelas. statusdata=1 berarti aktif. Status ISI/DIBERSIHKAN/DIBOOKING dianggap ISI.
    $sql = "
        SELECT 
            bk.jenis,
            bk.kelas,
            SUM(CASE WHEN k.status = 'KOSONG' AND k.statusdata = '1' THEN 1 ELSE 0 END) AS kosong,
            SUM(CASE WHEN k.status IN ('ISI','DIBERSIHKAN','DIBOOKING') AND k.statusdata = '1' THEN 1 ELSE 0 END) AS isi,
            SUM(CASE WHEN k.statusdata = '1' THEN 1 ELSE 0 END) AS total
        FROM bangsal_kamar bk
        LEFT JOIN kamar k ON k.kd_bangsal = bk.kd_bangsal
        WHERE bk.status = '1'
        GROUP BY bk.jenis, bk.kelas
        ORDER BY 
            FIELD(bk.jenis,'Dewasa','Anak','VK','Khusus','Isolasi'),
            FIELD(bk.kelas,'Kelas 1','Kelas 2','Kelas 3')
    ";

    $rows = $pdo->query($sql)->fetchAll();

    // Normalisasi label kelompok → teks UI
    $labelKelompok = [
        'Dewasa'  => 'Rawat Inap Dewasa',
        'Anak'    => 'Rawat Inap Anak',
        'VK'      => 'Ruang Rawat VK RB',
        'Khusus'  => 'Ruang Rawat Khusus',
        'Isolasi' => 'Isolasi',
    ];

    // Susun nested: data[kelompok][kelas] = {...}
    $out = [];
    foreach ($rows as $r) {
        $jenis = $r['jenis'] ?? '';
        $kelas = $r['kelas'] ?? '';
        if ($jenis === '' || $kelas === '') continue;

        $kelompokKey  = $jenis;
        $kelompokName = $labelKelompok[$jenis] ?? $jenis;

        if (!isset($out[$kelompokKey])) {
            $out[$kelompokKey] = [
                'kelompok_key'  => $kelompokKey,
                'kelompok_name' => $kelompokName,
                'kelas' => []
            ];
        }

        $out[$kelompokKey]['kelas'][] = [
            'kelas'   => $kelas,
            'kosong'  => (int)$r['kosong'],
            'isi'     => (int)$r['isi'],
            'total'   => (int)$r['total'],
        ];
    }

    // Ubah ke array berurutan
    $data = array_values($out);

    jsonOut(['ok'=>true,'data'=>$data]);
}


/**
 * GET /kamar/detail?jenis=Dewasa&kelas=Kelas%201
 * Detail per bangsal (nm_bangsal) untuk modal.
 * Status ISI/DIBERSIHKAN/DIBOOKING digabung sebagai “isi”.
 */
function handleKamarDetail(): void {
    $pdo   = db();
    $jenis = trim($_GET['jenis'] ?? '');
    $kelas = trim($_GET['kelas'] ?? '');

    // Validasi sederhana
    $allowJenis = ['Dewasa','Anak','VK','Khusus','Isolasi'];
    $allowKelas = ['Kelas 1','Kelas 2','Kelas 3'];
    if (!in_array($jenis, $allowJenis, true))  bad('Parameter jenis tidak valid. Gunakan: '.implode(', ',$allowJenis));
    if (!in_array($kelas, $allowKelas, true))  bad('Parameter kelas tidak valid. Gunakan: '.implode(', ',$allowKelas));

    $st = $pdo->prepare("
        SELECT 
            bk.kd_bangsal,
            bk.nm_bangsal,
            SUM(CASE WHEN k.status = 'KOSONG' AND k.statusdata = '1' THEN 1 ELSE 0 END) AS kosong,
            SUM(CASE WHEN k.status IN ('ISI','DIBERSIHKAN','DIBOOKING') AND k.statusdata = '1' THEN 1 ELSE 0 END) AS isi,
            SUM(CASE WHEN k.statusdata = '1' THEN 1 ELSE 0 END) AS total
        FROM bangsal_kamar bk
        LEFT JOIN kamar k ON k.kd_bangsal = bk.kd_bangsal
        WHERE bk.status = '1'
          AND bk.jenis  = :jenis
          AND bk.kelas  = :kelas
        GROUP BY bk.kd_bangsal, bk.nm_bangsal
        ORDER BY bk.nm_bangsal
    ");
    $st->execute([':jenis'=>$jenis, ':kelas'=>$kelas]);
    $rows = $st->fetchAll();

    jsonOut(['ok'=>true,'data'=>$rows, 'meta'=>['jenis'=>$jenis,'kelas'=>$kelas]]);
}


