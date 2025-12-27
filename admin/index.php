<?php
require_once '../functions/auth.php';

// Redirect ke dashboard admin
if (is_logged_in() && get_user_role() == 'admin') {
    header('Location: /clean-tech/admin/dashboard.php');
} else {
    header('Location: /clean-tech/login.php');
}
exit();
?>