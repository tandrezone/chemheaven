<?php
/**
 * ChemHeaven — Admin: Admin User Management
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-auth.php';

send_security_headers();
session_secure_start();
admin_require_auth();

$db      = db();
$flash   = $_SESSION['_flash'] ?? null;
$current = admin_current();
unset($_SESSION['_flash']);

$users = $db->query('SELECT id, username, created_at, last_login_at FROM admin_users ORDER BY id ASC')->fetchAll();

$errors  = [];
$editUser = null;
$editId  = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId) {
    $s = $db->prepare('SELECT id, username FROM admin_users WHERE id = :id LIMIT 1');
    $s->execute([':id' => $editId]);
    $editUser = $s->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id && $id !== (int)$current['user_id']) {
            $count = (int)$db->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
            if ($count <= 1) {
                $_SESSION['_flash'] = ['type'=>'error','msg'=>'Cannot delete the last admin user.'];
            } else {
                $db->prepare('DELETE FROM admin_users WHERE id = :id')->execute([':id' => $id]);
                $_SESSION['_flash'] = ['type'=>'success','msg'=>'Admin user deleted.'];
            }
        } else {
            $_SESSION['_flash'] = ['type'=>'error','msg'=>'Cannot delete your own account.'];
        }
        safe_redirect('/admin/users.php');
    }

    // Create or update
    $id       = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;
    $username = mb_substr(trim(strip_tags($_POST['username'] ?? '')), 0, 100);
    $password = $_POST['password'] ?? '';
    $passConf = $_POST['password_confirm'] ?? '';

    if ($username === '') $errors[] = 'Username is required.';
    if (!$id && $password === '') $errors[] = 'Password is required for new users.';
    if ($password !== '' && strlen($password) < 12) $errors[] = 'Password must be at least 12 characters.';
    if ($password !== $passConf) $errors[] = 'Passwords do not match.';

    // Check username uniqueness
    if (empty($errors)) {
        $stmt = $db->prepare('SELECT id FROM admin_users WHERE username = :u AND id != :id LIMIT 1');
        $stmt->execute([':u' => $username, ':id' => $id ?? 0]);
        if ($stmt->fetch()) $errors[] = 'Username already taken.';
    }

    if (empty($errors)) {
        if ($id) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare('UPDATE admin_users SET username=:u, password_hash=:h WHERE id=:id')
                   ->execute([':u'=>$username,':h'=>$hash,':id'=>$id]);
            } else {
                $db->prepare('UPDATE admin_users SET username=:u WHERE id=:id')
                   ->execute([':u'=>$username,':id'=>$id]);
            }
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:u, :h)')
               ->execute([':u'=>$username,':h'=>$hash]);
        }
        $_SESSION['_flash'] = ['type'=>'success','msg'=>'Admin user saved.'];
        safe_redirect('/admin/users.php');
    }
}

$adminPageTitle = 'Admin Users';
$adminActiveNav = 'users';
require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="admin-page-header"><h1>Admin Users</h1></div>

<?php if ($flash): ?>
    <div class="admin-alert admin-alert--<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
<?php endif; ?>

<div class="admin-form-grid">
    <div class="admin-form-main">
        <div class="admin-card">
            <div class="table-wrap">
                <table class="admin-table">
                    <thead><tr><th>ID</th><th>Username</th><th>Created</th><th>Last login</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td>
                                <strong><?= h($u['username']) ?></strong>
                                <?= (int)$u['id'] === (int)$current['user_id'] ? ' <span class="badge-active">You</span>' : '' ?>
                            </td>
                            <td><?= h(date('d M Y', strtotime($u['created_at']))) ?></td>
                            <td><?= $u['last_login_at'] ? h(date('d M Y H:i', strtotime($u['last_login_at']))) : '—' ?></td>
                            <td class="actions-cell">
                                <a href="/admin/users.php?edit=<?= $u['id'] ?>" class="btn-sm">Edit</a>
                                <?php if ((int)$u['id'] !== (int)$current['user_id']): ?>
                                <form method="post" action="/admin/users.php" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn-sm btn-danger" onclick="return confirm('Delete admin user?')">Delete</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="admin-form-sidebar">
        <div class="admin-card">
            <h2><?= $editUser ? 'Edit user' : 'New admin user' ?></h2>
            <?php if (!empty($errors)): ?>
                <ul class="admin-alert admin-alert--error"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <form method="post" action="/admin/users.php" class="admin-form">
                <?= csrf_field() ?>
                <?php if ($editUser): ?><input type="hidden" name="id" value="<?= $editUser['id'] ?>"><?php endif; ?>
                <label>Username *
                    <input type="text" name="username" value="<?= h($editUser['username'] ?? '') ?>" required maxlength="100" autocomplete="off">
                </label>
                <label>Password <?= $editUser ? '(leave blank to keep current)' : '*' ?>
                    <input type="password" name="password" autocomplete="new-password" minlength="12">
                    <small>Minimum 12 characters.</small>
                </label>
                <label>Confirm password
                    <input type="password" name="password_confirm" autocomplete="new-password">
                </label>
                <button type="submit" class="btn-primary">Save user</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
