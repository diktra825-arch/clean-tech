<?php
require_once '../functions/auth.php';

// Redirect ke dashboard user
if (is_logged_in() && get_user_role() == 'user') {
    header('Location: /clean-tech/user/dashboard.php');
} else {
    header('Location: /clean-tech/login.php');
}
exit();
?>