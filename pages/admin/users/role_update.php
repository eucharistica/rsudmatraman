<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/app.php';
session_boot();
$db = db();
rbac_require_roles($db, ['admin']);

// CSRF
if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
  http_response_code(400); echo 'Bad CSRF'; exit;
}

$userId = (int)($_POST['id'] ?? 0);
$roles  = $_POST['roles'] ?? [];
if ($userId <= 0 || !is_array($roles)) { http_response_code(400); echo 'Bad request'; exit; }

// bersihkan slug
$roles = array_values(array_filter(array_map(fn($s)=> strtolower(trim((string)$s)), $roles)));

try {
  rbac_set_user_roles($db, $userId, $roles);

  // Audit
  if (function_exists('audit_log')) {
    audit_log('permission','roles_update', [
      'message' => 'Update roles',
      'meta'    => ['user_id'=>$userId,'roles'=>$roles]
    ]);
  }

  header('Location: /pages/admin/users/edit.php?id=' . $userId); exit;
} catch (\Throwable $e) {
  http_response_code(500); echo 'Gagal update: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES);
}
