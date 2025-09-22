<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';

/**
 * Catat audit log (kompatibel MySQL tanpa tipe JSON).
 */
function audit_log(string $event, string $action, array $opts = []): void {
  try {
    $db = db(); // auto-load cfg (sesuai Opsi 1)
    $u  = $_SESSION['user'] ?? null;

    $user_id    = $u['id']    ?? null;
    $user_email = $u['email'] ?? null;
    $user_name  = $u['name']  ?? null;
    $user_role  = $u['role']  ?? null;

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (strlen($ip) > 45) $ip = substr($ip, 0, 45); // jaga-jaga

    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $target_type = isset($opts['target_type']) ? (string)$opts['target_type'] : null;
    $target_id   = isset($opts['target_id'])   ? (string)$opts['target_id']   : null;
    $message     = isset($opts['message'])     ? (string)$opts['message']     : null;

    // Simpan meta sebagai teks JSON (opsional/truncate untuk jaga ukuran)
    $meta = $opts['meta'] ?? null;
    if ($meta !== null && !is_string($meta)) {
      $meta = json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
    // batasi meta jika terlalu besar (optional – LONGTEXT besar, tapi mencegah bloat)
    if (is_string($meta) && strlen($meta) > 2000000) { // ~2MB
      $meta = substr($meta, 0, 2000000);
    }

    $sql = "INSERT INTO audit_logs
      (user_id, user_email, user_name, user_role, event, action, target_type, target_id, message, ip, user_agent, meta, created_at)
      VALUES (:uid, :uem, :unm, :urole, :event, :action, :tt, :tid, :msg, :ip, :ua, :meta, NOW())";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':uid',   $user_id,    PDO::PARAM_INT);
    $stmt->bindValue(':uem',   $user_email);
    $stmt->bindValue(':unm',   $user_name);
    $stmt->bindValue(':urole', $user_role);
    $stmt->bindValue(':event', $event);
    $stmt->bindValue(':action',$action);
    $stmt->bindValue(':tt',    $target_type);
    $stmt->bindValue(':tid',   $target_id);
    $stmt->bindValue(':msg',   $message);
    $stmt->bindValue(':ip',    $ip);
    $stmt->bindValue(':ua',    $ua);
    $stmt->bindValue(':meta',  $meta);
    $stmt->execute();
  } catch (Throwable $e) {
    error_log('[audit_log] '.$e->getMessage());
  }
}
