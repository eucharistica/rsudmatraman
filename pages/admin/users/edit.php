<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/lib/app.php';
app_boot();
$db = db();
rbac_require_roles($db, ['admin']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo 'Bad request'; exit; }

$user = $db->prepare("SELECT id,name,email FROM users WHERE id=:id");
$user->execute([':id'=>$id]);
$u = $user->fetch();
if (!$u) { http_response_code(404); echo 'Not found'; exit; }

$roles = $db->query("SELECT id,slug,name FROM roles ORDER BY slug")->fetchAll();
$has   = rbac_user_roles_slugs($db, $id);

// csrf
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Kelola Role — Dashboard Admin</title>
  <link rel="stylesheet" href="/assets/components/css/tw.css"></script>
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
  <?php $TOPBAR_SUBTITLE='Dashboard • Pengguna • Kelola Role'; include dirname(__DIR__, 3) . '/partials/topbar-dashboard-lite.php'; ?>
  <main class="mx-auto max-w-3xl px-4 py-8">
    <h1 class="text-2xl font-bold">Kelola Role</h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
      <?= htmlspecialchars($u['name'] ?? '', ENT_QUOTES) ?> — <?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES) ?>
    </p>

    <form class="mt-6 rounded-xl border p-5 dark:border-gray-800" method="post" action="/pages/admin/users/role_update.php">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
      <fieldset class="space-y-2">
        <?php foreach ($roles as $r): $checked = in_array($r['slug'], $has, true); ?>
          <label class="flex items-center gap-2">
            <input type="checkbox" name="roles[]" value="<?= htmlspecialchars($r['slug'], ENT_QUOTES) ?>" <?= $checked?'checked':'' ?> class="h-4 w-4">
            <span><b><?= htmlspecialchars($r['name'], ENT_QUOTES) ?></b> <span class="text-xs text-gray-500"> (<?= htmlspecialchars($r['slug'], ENT_QUOTES) ?>)</span></span>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <div class="mt-6 flex gap-2">
        <button class="rounded-lg px-4 py-2 text-white bg-[#38bdf8] hover:brightness-110">Simpan</button>
        <a href="/pages/admin/users/index.php" class="rounded-lg border px-4 py-2 dark:border-gray-700">Batal</a>
      </div>
    </form>
  </main>
</body>
</html>
