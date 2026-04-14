<?php
/**
 * ChemHeaven — Admin: Category Management
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

$cats = $db->query(
    'SELECT c.*, COUNT(p.id) AS product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id ORDER BY c.sort_order ASC, c.name ASC'
)->fetchAll();

$errors  = [];
$editCat = null;
$editId  = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

if ($editId) {
    $s = $db->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
    $s->execute([':id' => $editId]);
    $editCat = $s->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            $db->prepare('DELETE FROM categories WHERE id = :id')->execute([':id' => $id]);
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => 'Category deleted.'];
        }
        safe_redirect('/admin/categories.php');
    }

    // Save (create or update)
    $id        = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;
    $name      = mb_substr(trim(strip_tags($_POST['name'] ?? '')), 0, 100);
    $slug      = mb_substr(trim(strip_tags($_POST['slug'] ?? '')), 0, 100);
    $desc      = mb_substr(trim(strip_tags($_POST['description'] ?? '')), 0, 2000);
    $imageUrl  = mb_substr(trim($_POST['image_url'] ?? ''), 0, 500);
    $sortOrder = filter_input(INPUT_POST, 'sort_order', FILTER_VALIDATE_INT) ?? 0;
    $isActive  = !empty($_POST['is_active']) ? 1 : 0;

    if ($name === '') $errors[] = 'Name is required.';
    if ($slug === '' || !preg_match('/^[a-z0-9\-]+$/', $slug)) $errors[] = 'Valid slug required (lowercase, hyphens).';

    if (empty($errors)) {
        if ($id) {
            $db->prepare(
                'UPDATE categories SET name=:n,slug=:s,description=:d,image_url=:img,sort_order=:so,is_active=:a WHERE id=:id'
            )->execute([':n'=>$name,':s'=>$slug,':d'=>$desc,':img'=>$imageUrl,':so'=>$sortOrder,':a'=>$isActive,':id'=>$id]);
        } else {
            $db->prepare(
                'INSERT INTO categories (uuid,name,slug,description,image_url,sort_order,is_active) VALUES (:uuid,:n,:s,:d,:img,:so,:a)'
            )->execute([':uuid'=>generate_uuid(),':n'=>$name,':s'=>$slug,':d'=>$desc,':img'=>$imageUrl,':so'=>$sortOrder,':a'=>$isActive]);
        }
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => 'Category saved.'];
        safe_redirect('/admin/categories.php');
    }
}

$adminPageTitle = 'Categories';
$adminActiveNav = 'categories';
require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="admin-page-header">
    <h1>Categories</h1>
    <a href="/admin/categories.php" class="btn-secondary btn-sm">+ New category</a>
</div>

<?php if ($flash): ?>
    <div class="admin-alert admin-alert--<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
<?php endif; ?>

<div class="admin-form-grid">
    <!-- List -->
    <div class="admin-form-main">
        <div class="admin-card">
            <div class="table-wrap">
                <table class="admin-table">
                    <thead><tr><th>ID</th><th>Name</th><th>Slug</th><th>Products</th><th>Active</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($cats as $c): ?>
                        <tr>
                            <td><?= $c['id'] ?></td>
                            <td><?= h($c['name']) ?></td>
                            <td><code><?= h($c['slug']) ?></code></td>
                            <td><?= $c['product_count'] ?></td>
                            <td><?= $c['is_active'] ? '<span class="badge-active">Yes</span>' : '<span class="badge-inactive">No</span>' ?></td>
                            <td class="actions-cell">
                                <a href="/admin/categories.php?edit=<?= $c['id'] ?>" class="btn-sm">Edit</a>
                                <form method="post" action="/admin/categories.php" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn-sm btn-danger" onclick="return confirm('Delete category?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Form -->
    <div class="admin-form-sidebar">
        <div class="admin-card">
            <h2><?= $editCat ? 'Edit category' : 'New category' ?></h2>
            <?php if (!empty($errors)): ?>
                <ul class="admin-alert admin-alert--error"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <form method="post" action="/admin/categories.php" class="admin-form">
                <?= csrf_field() ?>
                <?php if ($editCat): ?>
                    <input type="hidden" name="id" value="<?= $editCat['id'] ?>">
                <?php endif; ?>
                <label>Name * <input type="text" name="name" value="<?= h($editCat['name'] ?? '') ?>" required maxlength="100"></label>
                <label>Slug * <input type="text" name="slug" value="<?= h($editCat['slug'] ?? '') ?>" required maxlength="100" pattern="[a-z0-9\-]+"></label>
                <label>Description <textarea name="description" rows="2"><?= h($editCat['description'] ?? '') ?></textarea></label>
                <label>Image URL <input type="url" name="image_url" value="<?= h($editCat['image_url'] ?? '') ?>" maxlength="500"></label>
                <label>Sort order <input type="number" name="sort_order" value="<?= (int)($editCat['sort_order'] ?? 0) ?>" min="0"></label>
                <label class="label-inline"><input type="checkbox" name="is_active" value="1"<?= ($editCat['is_active'] ?? 1) ? ' checked' : '' ?>> Active</label>
                <button type="submit" class="btn-primary">Save category</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
