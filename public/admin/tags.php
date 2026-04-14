<?php
/**
 * ChemHeaven — Admin: Tag Management
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

$tags = $db->query(
    'SELECT t.*, tg.name AS group_name, COUNT(pt.product_id) AS usage_count
     FROM tags t
     LEFT JOIN tag_groups tg ON tg.id = t.tag_group_id
     LEFT JOIN product_tags pt ON pt.tag_id = t.id
     GROUP BY t.id ORDER BY t.sort_order ASC, t.name ASC'
)->fetchAll();

$groups = $db->query('SELECT * FROM tag_groups ORDER BY name ASC')->fetchAll();

$errors  = [];
$editTag = null;
$editId  = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId) {
    $s = $db->prepare('SELECT * FROM tags WHERE id = :id LIMIT 1');
    $s->execute([':id' => $editId]);
    $editTag = $s->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) { $db->prepare('DELETE FROM tags WHERE id = :id')->execute([':id' => $id]); }
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => 'Tag deleted.'];
        safe_redirect('/admin/tags.php');
    }

    if ($action === 'add-group') {
        $gname = mb_substr(trim(strip_tags($_POST['group_name'] ?? '')), 0, 100);
        if ($gname !== '') {
            $db->prepare('INSERT INTO tag_groups (uuid, name) VALUES (:uuid, :n)')
               ->execute([':uuid' => generate_uuid(), ':n' => $gname]);
        }
        safe_redirect('/admin/tags.php');
    }

    $id        = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;
    $name      = mb_substr(trim(strip_tags($_POST['name'] ?? '')), 0, 100);
    $color     = preg_match('/^#[0-9a-fA-F]{3,6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#ffffff';
    $bgColor   = preg_match('/^#[0-9a-fA-F]{3,6}$/', $_POST['bg_color'] ?? '') ? $_POST['bg_color'] : '#000000';
    $groupId   = filter_input(INPUT_POST, 'tag_group_id', FILTER_VALIDATE_INT) ?: null;
    $sortOrder = filter_input(INPUT_POST, 'sort_order', FILTER_VALIDATE_INT) ?? 0;

    if ($name === '') $errors[] = 'Tag name is required.';

    if (empty($errors)) {
        if ($id) {
            $db->prepare('UPDATE tags SET name=:n,color=:c,bg_color=:bg,tag_group_id=:gid,sort_order=:so WHERE id=:id')
               ->execute([':n'=>$name,':c'=>$color,':bg'=>$bgColor,':gid'=>$groupId,':so'=>$sortOrder,':id'=>$id]);
        } else {
            $db->prepare('INSERT INTO tags (uuid,name,color,bg_color,tag_group_id,sort_order) VALUES (:uuid,:n,:c,:bg,:gid,:so)')
               ->execute([':uuid'=>generate_uuid(),':n'=>$name,':c'=>$color,':bg'=>$bgColor,':gid'=>$groupId,':so'=>$sortOrder]);
        }
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => 'Tag saved.'];
        safe_redirect('/admin/tags.php');
    }
}

$adminPageTitle = 'Tags';
$adminActiveNav = 'tags';
require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="admin-page-header"><h1>Tags</h1></div>

<?php if ($flash): ?>
    <div class="admin-alert admin-alert--<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
<?php endif; ?>

<div class="admin-form-grid">
    <div class="admin-form-main">
        <!-- Tag groups -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2>Tag groups</h2>
                <form method="post" action="/admin/tags.php" style="display:flex;gap:8px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add-group">
                    <input type="text" name="group_name" placeholder="New group name" maxlength="100" class="input-sm">
                    <button type="submit" class="btn-secondary btn-sm">Add</button>
                </form>
            </div>
            <div class="tag-group-list">
                <?php foreach ($groups as $g): ?>
                    <span class="tag-group-chip"><?= h($g['name']) ?></span>
                <?php endforeach; ?>
                <?php if (empty($groups)): ?><span class="text-muted">No groups yet.</span><?php endif; ?>
            </div>
        </div>

        <!-- Tags list -->
        <div class="admin-card">
            <div class="table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Name</th><th>Colors</th><th>Group</th><th>Used</th><th>Sort</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($tags as $t): ?>
                        <tr>
                            <td><span class="tag-chip" style="color:<?= h($t['color']) ?>;background:<?= h($t['bg_color']) ?>"><?= h($t['name']) ?></span></td>
                            <td><code><?= h($t['color']) ?> / <?= h($t['bg_color']) ?></code></td>
                            <td><?= h($t['group_name'] ?? '—') ?></td>
                            <td><?= $t['usage_count'] ?></td>
                            <td><?= $t['sort_order'] ?></td>
                            <td class="actions-cell">
                                <a href="/admin/tags.php?edit=<?= $t['id'] ?>" class="btn-sm">Edit</a>
                                <form method="post" action="/admin/tags.php" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn-sm btn-danger" onclick="return confirm('Delete tag?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tags)): ?><tr><td colspan="6" class="text-muted text-center">No tags yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Form -->
    <div class="admin-form-sidebar">
        <div class="admin-card">
            <h2><?= $editTag ? 'Edit tag' : 'New tag' ?></h2>
            <?php if (!empty($errors)): ?>
                <ul class="admin-alert admin-alert--error"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <form method="post" action="/admin/tags.php" class="admin-form">
                <?= csrf_field() ?>
                <?php if ($editTag): ?><input type="hidden" name="id" value="<?= $editTag['id'] ?>"><?php endif; ?>
                <label>Name * <input type="text" name="name" value="<?= h($editTag['name'] ?? '') ?>" required maxlength="100"></label>
                <label>Text color
                    <input type="color" name="color" value="<?= h($editTag['color'] ?? '#ffffff') ?>">
                </label>
                <label>Background color
                    <input type="color" name="bg_color" value="<?= h($editTag['bg_color'] ?? '#000000') ?>">
                </label>
                <label>Group
                    <select name="tag_group_id">
                        <option value="">— none —</option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= $g['id'] ?>"<?= ($editTag['tag_group_id'] ?? null) == $g['id'] ? ' selected' : '' ?>><?= h($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Sort order <input type="number" name="sort_order" value="<?= (int)($editTag['sort_order'] ?? 0) ?>" min="0"></label>
                <button type="submit" class="btn-primary">Save tag</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
