<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-auth.php';

send_security_headers();
session_secure_start();
admin_logout();
safe_redirect('/admin/login.php');
