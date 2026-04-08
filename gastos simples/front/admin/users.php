<?php
declare(strict_types=1);

require_once __DIR__ . '/../../back/inc/conexion_bd.php';
require_once __DIR__ . '/../../back/inc/auth.php';
require_once __DIR__ . '/../../back/inc/csrf.php';
require_once __DIR__ . '/../../back/inc/flash.php';

require_login();
require_admin();

$pdo = get_pdo();

$q = trim((string)($_GET['q'] ?? ''));
$where = '';
$args = [];

if ($q !== '') {
  $where = "WHERE email LIKE :q";
  $args[':q'] = '%' . $q . '%';
}

$sql = "
SELECT
  u.id, u.email, u.role, u.created_at,
  COALESCE(SUM(CASE WHEN e.amount > 0 THEN e.amount ELSE 0 END),0) AS total_gastos,
  COALESCE(SUM(CASE WHEN e.amount < 0 THEN -e.amount ELSE 0 END),0) AS total_ingresos,
  COALESCE(SUM(e.amount),0) AS neto_contable,
  COALESCE(SUM(CASE WHEN e.amount < 0 THEN -e.amount ELSE 0 END),0)
    - COALESCE(SUM(CASE WHEN e.amount > 0 THEN e.amount ELSE 0 END),0) AS saldo,
  COUNT(e.id) AS n_mov
FROM users u
LEFT JOIN expenses e ON e.user_id = u.id
{$where}
GROUP BY u.id
ORDER BY u.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Para proteger “último admin”
$adminsCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$currentId = (int)(current_user_id() ?? 0);

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function eur(float $n): string { return '€ ' . number_format($n, 2, ',', '.'); }

$flashes = flash_consume_all();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Admin · Usuarios</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= h(app_url('/assets/css/style.css')) ?>">
</head>
<body>

<header class="app-header">
  <div class="container">
    <h1>Gestión de usuarios</h1>
    <nav aria-label="Acciones">
      <a class="btn" href="<?= h(app_url('/front/admin/index.php')) ?>">Panel admin</a>
      <a class="btn" href="<?= h(app_url('/front/index.php')) ?>">Volver</a>
    </nav>
  </div>
</header>

<main class="container">

  <?php foreach ($flashes as $f): ?>
    <div class="alert <?= h($f['type'] ?? 'info') ?>" role="status" aria-live="polite" aria-atomic="true">
      <strong><?= h(ucfirst($f['type'] ?? 'info')) ?>:</strong> <?= h($f['message'] ?? '') ?>
    </div>
  <?php endforeach; ?>

  <section class="card">
    <h2>Buscar</h2>
    <form method="get" action="users.php" class="filters-grid">
      <div class="field">
        <label for="q">Email contiene</label>
        <input id="q" name="q" type="search" value="<?= h($q) ?>" placeholder="ej: @gmail.com">
        <small class="help">&nbsp;</small>
      </div>
      <div class="field actions">
        <button class="btn primary" type="submit">Aplicar</button>
        <a class="btn" href="users.php">Limpiar</a>
        <small class="help">&nbsp;</small>
      </div>
    </form>
  </section>

  <section class="card">
    <h2>Usuarios</h2>

    <div class="table-wrapper">
      <table class="table" aria-label="Tabla de usuarios">
        <thead>
          <tr>
            <th>Email</th>
            <th>Rol</th>
            <th>Alta</th>
            <th class="num">Gastos</th>
            <th class="num">Ingresos</th>
            <th class="num">Saldo</th>
            <th class="num">Mov.</th>
            <th class="actions-col">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <?php
              $uid = (int)$u['id'];
              $role = (string)$u['role'];
              $saldo = (float)$u['saldo'];
              $isLastAdmin = ($role === 'admin' && $adminsCount <= 1);
              $isSelf = ($uid === $currentId);
            ?>
            <tr>
              <td><?= h($u['email']) ?></td>
              <td><?= h($role) ?></td>
              <td><?= h((string)$u['created_at']) ?></td>
              <td class="num"><?= eur((float)$u['total_gastos']) ?></td>
              <td class="num"><?= eur((float)$u['total_ingresos']) ?></td>
              <td class="num"><span class="<?= $saldo >= 0 ? 'neto-pos' : 'neto-neg' ?>"><?= eur($saldo) ?></span></td>
              <td class="num"><?= (int)$u['n_mov'] ?></td>

              <td>
                <!-- Cambiar rol -->
                <form class="inline-form" method="post" action="<?= h(app_url('/back/controllers/admin_update_role.php')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= $uid ?>">
                  <select name="role" aria-label="Nuevo rol para <?= h($u['email']) ?>">
                    <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>user</option>
                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>admin</option>
                  </select>
                  <button class="btn" type="submit"
                    <?= ($isLastAdmin && $role === 'admin') ? 'disabled title="No puedes degradar el último admin"' : '' ?>
                    <?= ($isSelf && $isLastAdmin) ? 'disabled title="No puedes degradarte siendo el último admin"' : '' ?>
                  >Guardar</button>
                </form>
                <button class="btn danger" type="button"
                        data-open-del-user="<?= $uid ?>"
                        data-email="<?= h($u['email']) ?>">
                  Borrar
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p class="muted">Admins actuales: <?= $adminsCount ?>. El sistema impide degradar al último admin.</p>
  </section>

  <!-- Modal borrar usuario (opcional) -->
  <div id="del-user-modal" class="modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="del-user-title">
    <div class="modal__backdrop" data-close-modal></div>
    <div class="modal__panel">
      <h3 id="del-user-title" class="modal__title">Borrar usuario</h3>
      <p class="muted">Vas a borrar al usuario <strong id="del-user-email"></strong> y todos sus gastos. Irreversible.</p>

      <div class="alert warning" role="alert">
        <strong>Escribe exactamente: BORRAR USUARIO</strong>
      </div>

      <form method="post" action="<?= h(app_url('/back/controllers/admin_delete_user.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="del-user-id" value="">
        <div class="field">
          <label for="del-confirm">Confirmación</label>
          <input id="del-confirm" name="confirm_text" type="text" placeholder="BORRAR USUARIO" autocomplete="off" required>
        </div>
        <div class="field actions" style="justify-content:flex-end;">
          <button type="button" class="btn" data-close-modal>Cancelar</button>
          <button type="submit" class="btn danger" id="del-user-submit" disabled aria-disabled="true">Borrar definitivamente</button>
        </div>
      </form>
    </div>
  </div>

</main>

<footer class="app-footer">
  <div class="container"><small>© <?= date('Y') ?> Gastos Simples</small></div>
</footer>

<script>
  // JS simple para el modal de borrar usuario (opcional)
  (function() {
    const modal = document.getElementById('del-user-modal');
    const emailEl = document.getElementById('del-user-email');
    const idEl = document.getElementById('del-user-id');
    const input = document.getElementById('del-confirm');
    const submit = document.getElementById('del-user-submit');

    if (!modal) return;

    const open = (id, email) => {
      modal.setAttribute('aria-hidden','false');
      emailEl.textContent = email;
      idEl.value = id;
      input.value = '';
      submit.disabled = true;
      submit.setAttribute('aria-disabled','true');
      setTimeout(() => input.focus(), 50);
    };

    const close = () => modal.setAttribute('aria-hidden','true');

    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-open-del-user]');
      if (btn) {
        open(btn.getAttribute('data-open-del-user'), btn.getAttribute('data-email'));
      }
      if (e.target.hasAttribute('data-close-modal')) close();
    });

    document.addEventListener('keydown', (e) => {
      if (modal.getAttribute('aria-hidden') === 'false' && e.key === 'Escape') close();
    });

    input.addEventListener('input', () => {
      const ok = input.value.trim() === 'BORRAR USUARIO';
      submit.disabled = !ok;
      submit.setAttribute('aria-disabled', ok ? 'false' : 'true');
    });
  })();
</script>

</body>
</html>