<?php
declare(strict_types=1);

function rbac_user_roles_slugs(PDO $db, int $userId): array {
  $sql = "SELECT r.slug
          FROM user_roles ur
          JOIN roles r ON r.id = ur.role_id
          WHERE ur.user_id = :uid";
  $st = $db->prepare($sql);
  $st->execute([':uid'=>$userId]);
  return array_map('strval', array_column($st->fetchAll(), 'slug'));
}

function rbac_user_has_role(PDO $db, int $userId, string $roleSlug): bool {
  $sql = "SELECT 1
          FROM user_roles ur
          JOIN roles r ON r.id=ur.role_id
          WHERE ur.user_id=:uid AND r.slug=:slug LIMIT 1";
  $st=$db->prepare($sql);
  $st->execute([':uid'=>$userId, ':slug'=>strtolower($roleSlug)]);
  return (bool)$st->fetchColumn();
}

function rbac_user_can(PDO $db, int $userId, string $permSlug): bool {
  $sql = "SELECT 1
          FROM user_roles ur
          JOIN role_permissions rp ON rp.role_id=ur.role_id
          JOIN permissions p ON p.id=rp.permission_id
          WHERE ur.user_id=:uid AND p.slug=:p LIMIT 1";
  $st=$db->prepare($sql);
  $st->execute([':uid'=>$userId, ':p'=>strtolower($permSlug)]);
  return (bool)$st->fetchColumn();
}

function rbac_require_roles(PDO $db, array $allowedSlugs): void {
  if (empty($_SESSION['user']['id'])) {
    header('Location: /auth/?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/')); exit;
  }
  $uid = (int)$_SESSION['user']['id'];
  foreach ($allowedSlugs as $slug) {
    if (rbac_user_has_role($db, $uid, $slug)) return;
  }
  // audit jika ada
  if (function_exists('audit_log')) {
    audit_log('permission', 'access_denied', ['message'=>'Forbidden area', 'meta'=>['path'=>$_SERVER['REQUEST_URI'] ?? '', 'allowed'=>$allowedSlugs]]);
  }
  http_response_code(403); echo 'Forbidden'; exit;
}

/** Set roles user (replace semua) dengan daftar slug. */
function rbac_set_user_roles(PDO $db, int $userId, array $roleSlugs): void {
  $db->beginTransaction();
  try {
    $db->prepare("DELETE FROM user_roles WHERE user_id=:u")->execute([':u'=>$userId]);

    if ($roleSlugs) {
      // Ambil mapping slug->id
      $in = implode(',', array_fill(0, count($roleSlugs), '?'));
      $st = $db->prepare("SELECT id, slug FROM roles WHERE slug IN ($in)");
      $st->execute(array_map('strtolower', $roleSlugs));
      $map = [];
      foreach ($st->fetchAll() as $r) $map[strtolower($r['slug'])] = (int)$r['id'];

      $ins = $db->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:u, :r)");
      foreach (array_unique(array_map('strtolower', $roleSlugs)) as $slug) {
        if (!isset($map[$slug])) continue;
        $ins->execute([':u'=>$userId, ':r'=>$map[$slug]]);
      }

      // (opsional) sinkron ke users.role satu nilai utama (ambil prioritas: admin>editor>user)
      $priority = ['admin'=>3,'editor'=>2,'user'=>1];
      $main = 'user';
      foreach ($roleSlugs as $s) {
        $s = strtolower($s);
        if (($priority[$s] ?? 0) > ($priority[$main] ?? 0)) $main = $s;
      }
      $db->prepare("UPDATE users SET role=:r, updated_at=NOW() WHERE id=:u")->execute([':r'=>$main, ':u'=>$userId]);
    }

    $db->commit();
  } catch (\Throwable $e) {
    $db->rollBack(); throw $e;
  }
}
