<?php
require_once __DIR__ . '/../includes/auth.php';

initSession();
$_SESSION = [];
@session_destroy();

@setcookie('admin_token', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None',
]);
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Logged Out</title></head>
<body>
    <script>
        try {
            localStorage.removeItem('admin_token');
            sessionStorage.removeItem('admin_token');
        } catch(e) {}
        window.location.replace('login.php?logged_out=1');
    </script>
    <p>Logged out. <a href="login.php?logged_out=1">Click here to return to login.</a></p>
</body>
</html>
