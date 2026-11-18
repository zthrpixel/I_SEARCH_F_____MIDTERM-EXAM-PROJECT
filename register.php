<?php
require_once __DIR__ . '/main/dbconfig.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $ageText = trim($_POST['age'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $age = $ageText === '' ? null : filter_var($ageText, FILTER_VALIDATE_INT);

    if ($firstName === '' || $lastName === '' || $username === '' || $email === '' || $password === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($ageText !== '' && ($age === false || $age < 1 || $age > 120)) {
        $error = 'Please enter a valid age between 1 and 120.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must contain at least 8 characters.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (first_name, last_name, username, email, age, address, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$firstName, $lastName, $username, $email, $age, $address ?: null, password_hash($password, PASSWORD_DEFAULT)]);
            header('Location: login.php?registered=1');
            exit;
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $error = 'That username or email is already registered.';
            } else {
                error_log($ex->getMessage());
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Register | Digital Press</title><link rel="stylesheet" href="style.css"></head>
<body><main class="auth-container"><section class="card"><h1>Create an Account</h1><p>Register to manage publishers and articles.</p>
<?php if ($error): ?><div class="message error"><?= h($error) ?></div><?php endif; ?>
<form method="post" action="register.php">
<label>First name *</label><input name="first_name" maxlength="80" required value="<?= h($_POST['first_name'] ?? '') ?>">
<label>Last name *</label><input name="last_name" maxlength="80" required value="<?= h($_POST['last_name'] ?? '') ?>">
<label>Username *</label><input name="username" maxlength="50" required autocomplete="username" value="<?= h($_POST['username'] ?? '') ?>">
<label>Email *</label><input type="email" name="email" maxlength="150" required autocomplete="email" value="<?= h($_POST['email'] ?? '') ?>">
<label>Age</label><input type="number" name="age" min="1" max="120" value="<?= h($_POST['age'] ?? '') ?>">
<label>Address</label><textarea name="address" rows="3"><?= h($_POST['address'] ?? '') ?></textarea>
<label>Password * (at least 8 characters)</label><input type="password" name="password" minlength="8" required autocomplete="new-password">
<button type="submit">Register</button>
</form><p>Already have an account? <a href="login.php">Log in</a></p></section></main></body></html>
