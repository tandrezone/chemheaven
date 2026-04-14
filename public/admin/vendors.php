<?php
/**
 * ChemHeaven — Admin: Vendor Management
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-auth.php';

send_security_headers();
session_secure_start();
admin_require_auth();

$db    = db();
$flash = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);

$vendors = $db->query(
    'SELECT v.*, COUNT(p.id) AS product_count
     FROM vendors v LEFT JOIN products p ON p.vendor_id = v.id
     GROUP BY v.id ORDER BY v.name ASC'
)->fetchAll();

$errors    = [];
$editVendor = null;
$editId    = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId) {
    $s = $db->prepare('SELECT * FROM vendors WHERE id = :id LIMIT 1');
    $s->execute([':id' => $editId]);
    $editVendor = $s->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) { $db->prepare('DELETE FROM vendors WHERE id = :id')->execute([':id' => $id]); }
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => 'Vendor deleted.'];
        safe_redirect('/admin/vendors.php');
    }

    $id       = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;
    $name     = mb_substr(trim(strip_tags($_POST['name'] ?? '')), 0, 150);
    $location = mb_substr(trim(strip_tags($_POST['location'] ?? '')), 0, 150);

    if ($name === '')     $errors[] = 'Vendor name is required.';
    if ($location === '') $errors[] = 'Location is required.';

    if (empty($errors)) {
        if ($id) {
            $db->prepare('UPDATE vendors SET name=:n,location=:l WHERE id=:id')
               ->execute([':n'=>$name,':l'=>$location,':id'=>$id]);
        } else {
            $db->prepare('INSERT INTO vendors (name,location) VALUES (:n,:l)')
               ->execute([':n'=>$name,':l'=>$location]);
        }
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => 'Vendor saved.'];
        safe_redirect('/admin/vendors.php');
    }
}

$adminPageTitle = 'Vendors';
$adminActiveNav = 'vendors';
require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="admin-page-header"><h1>Vendors</h1></div>

<?php if ($flash): ?>
    <div class="admin-alert admin-alert--<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
<?php endif; ?>

<div class="admin-form-grid">
    <div class="admin-form-main">
        <div class="admin-card">
            <div class="table-wrap">
                <table class="admin-table">
                    <thead><tr><th>ID</th><th>Name</th><th>Location</th><th>Products</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($vendors as $v): ?>
                        <tr>
                            <td><?= $v['id'] ?></td>
                            <td><?= h($v['name']) ?></td>
                            <td><?= h($v['location']) ?></td>
                            <td><?= $v['product_count'] ?></td>
                            <td class="actions-cell">
                                <a href="/admin/vendors.php?edit=<?= $v['id'] ?>" class="btn-sm">Edit</a>
                                <form method="post" action="/admin/vendors.php" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                    <button type="submit" class="btn-sm btn-danger" onclick="return confirm('Delete vendor?')">Delete</button>
                                </form>
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
            <h2><?= $editVendor ? 'Edit vendor' : 'New vendor' ?></h2>
            <?php if (!empty($errors)): ?>
                <ul class="admin-alert admin-alert--error"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <form method="post" action="/admin/vendors.php" class="admin-form">
                <?= csrf_field() ?>
                <?php if ($editVendor): ?><input type="hidden" name="id" value="<?= $editVendor['id'] ?>"><?php endif; ?>
                <label>Name * <input type="text" name="name" value="<?= h($editVendor['name'] ?? '') ?>" required maxlength="150"></label>
                <label>Location * <input type="text" name="location" value="<?= h($editVendor['location'] ?? '') ?>" required maxlength="150"></label>
                <button type="submit" class="btn-primary">Save vendor</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
