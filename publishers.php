<?php
require_once __DIR__ . '/main/auth.php';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_publisher'])) {
    $id = (int)($_POST['publisher_id'] ?? 0);
    $name = trim($_POST['publisher_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a publisher name and valid email address.';
    } else {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE publishers SET publisher_name = ?, email = ?, description = ?, last_updated_by = ?, last_updated = CURRENT_TIMESTAMP WHERE publisher_id = ?');
                $stmt->execute([$name, $email, $description ?: null, $_SESSION['user_id'], $id]);
                log_activity($pdo, (int)$_SESSION['user_id'], 'UPDATE', 'publishers', $id, 'Updated publisher: ' . $name);
                $message = 'Publisher updated successfully.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO publishers (publisher_name, email, description, added_by, last_updated_by) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$name, $email, $description ?: null, $_SESSION['user_id'], $_SESSION['user_id']]);
                $newId = (int)$pdo->lastInsertId();
                log_activity($pdo, (int)$_SESSION['user_id'], 'INSERT', 'publishers', $newId, 'Created publisher: ' . $name);
                $message = 'Publisher added successfully.';
            }
        } catch (PDOException $ex) { error_log($ex->getMessage()); $error = 'Unable to save publisher. Check that the email is not already in use.'; }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_publisher'])) {
    $id = (int)($_POST['publisher_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT publisher_name FROM publishers WHERE publisher_id = ?');
    $stmt->execute([$id]); $row = $stmt->fetch();
    if ($row) {
        $pdo->beginTransaction();
        try {
            $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, 'DELETE', 'publishers', ?, ?)");
            $log->execute([$_SESSION['user_id'], $id, 'Deleted publisher: ' . $row['publisher_name'] . ' (associated articles are deleted by cascade)']);
            $stmt = $pdo->prepare('DELETE FROM publishers WHERE publisher_id = ?'); $stmt->execute([$id]);
            $pdo->commit(); $message = 'Publisher deleted. Its associated articles were also deleted by cascade.';
        } catch (PDOException $ex) { $pdo->rollBack(); error_log($ex->getMessage()); $error = 'Unable to delete publisher.'; }
    }
}
$editPublisher = null;
if (isset($_GET['edit'])) { $stmt = $pdo->prepare('SELECT * FROM publishers WHERE publisher_id = ?'); $stmt->execute([(int)$_GET['edit']]); $editPublisher = $stmt->fetch(); }
$search = trim($_GET['search'] ?? '');
$sql = 'SELECT p.*, ua.username AS added_username, uu.username AS updated_username, COUNT(a.article_id) AS article_count FROM publishers p LEFT JOIN articles a ON a.publisher_id = p.publisher_id LEFT JOIN users ua ON ua.user_id = p.added_by LEFT JOIN users uu ON uu.user_id = p.last_updated_by';
if ($search !== '') { $sql .= ' WHERE p.publisher_name LIKE :search1 OR p.email LIKE :search2 OR p.description LIKE :search3 OR CAST(p.publisher_id AS CHAR) LIKE :search4'; }
$sql .= ' GROUP BY p.publisher_id ORDER BY p.publisher_id DESC';
$stmt = $pdo->prepare($sql);
if ($search !== '') { for ($i = 1; $i <= 4; $i++) $stmt->bindValue(':search' . $i, '%' . $search . '%'); }
$stmt->execute();
$publishers = $stmt->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Publishers | Digital Press</title><link rel="stylesheet" href="style.css"></head><body><nav class="navbar"><strong>Digital Press</strong><div><a href="index.php">Dashboard</a><a href="publishers.php">Publishers</a><a href="articles.php">Articles</a><span class="nav-user">Signed in: <?= e($_SESSION['user_name'] ?? $_SESSION['username']) ?></span><a href="logout.php">Logout</a></div></nav><main class="container">
<section class="card"><h1>Publisher Management</h1><?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?><?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?><h2><?= $editPublisher ? 'Edit Publisher' : 'Add Publisher' ?></h2><form method="post" action="publishers.php"><input type="hidden" name="publisher_id" value="<?= e($editPublisher['publisher_id'] ?? 0) ?>"><label>Publisher Name</label><input name="publisher_name" maxlength="100" required value="<?= e($editPublisher['publisher_name'] ?? '') ?>"><label>Email</label><input type="email" name="email" maxlength="150" required value="<?= e($editPublisher['email'] ?? '') ?>"><label>Description</label><textarea name="description"><?= e($editPublisher['description'] ?? '') ?></textarea><button name="save_publisher" type="submit"><?= $editPublisher ? 'Update Publisher' : 'Add Publisher' ?></button><?php if ($editPublisher): ?> <a class="btn btn-secondary" href="publishers.php">Cancel</a><?php endif; ?></form></section>
<section class="card"><h2>Publisher Records</h2><form method="get" action="publishers.php" class="search-form"><input name="search" placeholder="Search by ID, publisher, email, or description" value="<?= e($search) ?>"><button type="submit">Search</button> <a class="btn btn-secondary" href="publishers.php">Clear</a></form><p><small><?= count($publishers) ?> matching publisher(s)</small></p><div class="table-wrap"><table><thead><tr><th>ID</th><th>Publisher</th><th>Email</th><th>Articles</th><th>Date Added</th><th>Added By</th><th>Last Updated By (ID)</th><th>Last Updated</th><th>Actions</th></tr></thead><tbody><?php foreach ($publishers as $p): ?><tr><td><?= e($p['publisher_id']) ?></td><td><?= e($p['publisher_name']) ?></td><td><?= e($p['email']) ?></td><td><?= e($p['article_count']) ?></td><td><?= e($p['date_added'] ?? $p['created_at']) ?></td><td><?= e($p['added_username'] ?? 'Legacy record') ?></td><td><?= e($p['last_updated_by'] ?? 'Not updated') ?></td><td><?= e($p['last_updated'] ?? 'Not updated') ?></td><td><div class="actions"><a class="btn" href="publishers.php?edit=<?= e($p['publisher_id']) ?>">Edit</a><form method="post" action="publishers.php" onsubmit="return confirm('Delete this publisher and all of its articles?');"><input type="hidden" name="publisher_id" value="<?= e($p['publisher_id']) ?>"><button class="btn-danger" name="delete_publisher" type="submit">Delete</button></form></div></td></tr><?php endforeach; ?></tbody></table></div></section></main></body></html>
