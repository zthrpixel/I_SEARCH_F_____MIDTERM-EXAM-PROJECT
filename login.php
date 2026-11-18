<?php
require_once __DIR__ . '/main/dbconfig.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT user_id, first_name, last_name, username, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['username'] = $user['username'];
        header('Location: index.php');
        exit;
    }
    $error = 'Invalid username/email or password.';
}
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Login | Digital Press</title><link rel="stylesheet" href="style.css"></head>
<body><main class="auth-container"><section class="card"><h1>Digital Press</h1><p>Log in to continue.</p>
<?php if (isset($_GET['registered'])): ?><div class="message">Registration successful. You can now log in.</div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= h($error) ?></div><?php endif; ?>
<form method="post" action="login.php"><label>Username or email</label><input name="login" required autocomplete="username" value="<?= h($_POST['login'] ?? '') ?>"><label>Password</label><input type="password" name="password" required autocomplete="current-password"><button type="submit">Log In</button></form>
<p>Don't have an account? <a href="register.php">Register here</a></p></section></main></body></html>
