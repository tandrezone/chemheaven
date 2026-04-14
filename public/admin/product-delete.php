<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-auth.php';

send_security_headers();
session_secure_start();
admin_require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { safe_redirect('/admin/products.php'); }
csrf_verify();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) { safe_redirect('/admin/products.php'); }

db()->prepare('DELETE FROM products WHERE id = :id')->execute([':id' => $id]);

$_SESSION['_flash'] = ['type' => 'success', 'msg' => 'Product deleted.'];
safe_redirect('/admin/products.php');
