<?php
require_once __DIR__ . '/main/auth.php';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_article'])) {
    $id = (int)($_POST['article_id'] ?? 0);
    $publisherId = (int)($_POST['publisher_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $date = trim($_POST['published_date'] ?? '');
    if ($publisherId <= 0 || $title === '' || $content === '') {
        $error = 'Select a publisher and enter an article title and content.';
    } else {
        $check = $pdo->prepare('SELECT publisher_id FROM publishers WHERE publisher_id = ?'); $check->execute([$publisherId]);
        if (!$check->fetch()) { $error = 'Selected publisher does not exist.'; }
        else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE articles SET publisher_id = ?, title = ?, content = ?, published_date = ?, last_updated_by = ?, last_updated = CURRENT_TIMESTAMP WHERE article_id = ?');
                    $stmt->execute([$publisherId, $title, $content, $date ?: null, $_SESSION['user_id'], $id]);
                    log_activity($pdo, (int)$_SESSION['user_id'], 'UPDATE', 'articles', $id, 'Updated article: ' . $title);
                    $message = 'Article updated successfully.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO articles (publisher_id, title, content, published_date, added_by, last_updated_by) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$publisherId, $title, $content, $date ?: null, $_SESSION['user_id'], $_SESSION['user_id']]);
                    $newId = (int)$pdo->lastInsertId();
                    log_activity($pdo, (int)$_SESSION['user_id'], 'INSERT', 'articles', $newId, 'Created article: ' . $title);
                    $message = 'Article added successfully.';
                }
            } catch (PDOException $ex) { error_log($ex->getMessage()); $error = 'Unable to save article. Please check the entered information.'; }
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_article'])) {
    $id = (int)($_POST['article_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT title FROM articles WHERE article_id = ?'); $stmt->execute([$id]); $row = $stmt->fetch();
    if ($row) {
        $pdo->beginTransaction();
        try {
            $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, 'DELETE', 'articles', ?, ?)");
            $log->execute([$_SESSION['user_id'], $id, 'Deleted article: ' . $row['title']]);
            $stmt = $pdo->prepare('DELETE FROM articles WHERE article_id = ?'); $stmt->execute([$id]);
            $pdo->commit(); $message = 'Article deleted successfully.';
        } catch (PDOException $ex) { $pdo->rollBack(); error_log($ex->getMessage()); $error = 'Unable to delete article.'; }
    }
}
$editArticle = null;
if (isset($_GET['edit'])) { $stmt = $pdo->prepare('SELECT * FROM articles WHERE article_id = ?'); $stmt->execute([(int)$_GET['edit']]); $editArticle = $stmt->fetch(); }
$publishers = $pdo->query('SELECT publisher_id, publisher_name FROM publishers ORDER BY publisher_name')->fetchAll();
$search = trim($_GET['search'] ?? '');
$sql = 'SELECT a.*, p.publisher_name, ua.username AS added_username, uu.username AS updated_username FROM articles a INNER JOIN publishers p ON p.publisher_id = a.publisher_id LEFT JOIN users ua ON ua.user_id = a.added_by LEFT JOIN users uu ON uu.user_id = a.last_updated_by';
if ($search !== '') { $sql .= ' WHERE a.title LIKE :search1 OR a.content LIKE :search2 OR p.publisher_name LIKE :search3 OR CAST(a.article_id AS CHAR) LIKE :search4'; }
$sql .= ' ORDER BY a.article_id DESC';
$stmt = $pdo->prepare($sql);
if ($search !== '') { for ($i = 1; $i <= 4; $i++) $stmt->bindValue(':search' . $i, '%' . $search . '%'); }
$stmt->execute();
$articles = $stmt->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Articles | Digital Press</title><link rel="stylesheet" href="style.css"></head><body><nav class="navbar"><strong>Digital Press</strong><div><a href="index.php">Dashboard</a><a href="publishers.php">Publishers</a><a href="articles.php">Articles</a><span class="nav-user">Signed in: <?= e($_SESSION['user_name'] ?? $_SESSION['username']) ?></span><a href="logout.php">Logout</a></div></nav><main class="container">
<section class="card"><h1>Article Management</h1><?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?><?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?><?php if (!$publishers): ?><p>Add a publisher before creating an article.</p><a class="btn" href="publishers.php">Add Publisher</a><?php else: ?><h2><?= $editArticle ? 'Edit Article' : 'Add Article' ?></h2><form method="post" action="articles.php"><input type="hidden" name="article_id" value="<?= e($editArticle['article_id'] ?? 0) ?>"><label>Publisher</label><select name="publisher_id" required><option value="">-- Select Publisher --</option><?php foreach ($publishers as $p): ?><option value="<?= e($p['publisher_id']) ?>" <?= (int)($editArticle['publisher_id'] ?? 0) === (int)$p['publisher_id'] ? 'selected' : '' ?>><?= e($p['publisher_name']) ?></option><?php endforeach; ?></select><label>Article Title</label><input name="title" maxlength="200" required value="<?= e($editArticle['title'] ?? '') ?>"><label>Article Content</label><textarea name="content" required><?= e($editArticle['content'] ?? '') ?></textarea><label>Publication Date (optional)</label><input type="date" name="published_date" value="<?= e($editArticle['published_date'] ?? '') ?>"><button name="save_article" type="submit"><?= $editArticle ? 'Update Article' : 'Add Article' ?></button><?php if ($editArticle): ?> <a class="btn btn-secondary" href="articles.php">Cancel</a><?php endif; ?></form><?php endif; ?></section>
<section class="card"><h2>Article Records</h2><form method="get" action="articles.php" class="search-form"><input name="search" placeholder="Search by ID, title, content, or publisher" value="<?= e($search) ?>"><button type="submit">Search</button> <a class="btn btn-secondary" href="articles.php">Clear</a></form><p><small><?= count($articles) ?> matching article(s)</small></p><div class="table-wrap"><table><thead><tr><th>ID</th><th>Publisher</th><th>Title</th><th>Content</th><th>Publication Date</th><th>Date Added</th><th>Added By</th><th>Last Updated By (ID)</th><th>Last Updated</th><th>Actions</th></tr></thead><tbody><?php foreach ($articles as $a): ?><tr><td><?= e($a['article_id']) ?></td><td><?= e($a['publisher_name']) ?></td><td><?= e($a['title']) ?></td><td><?= nl2br(e($a['content'])) ?></td><td><?= e($a['published_date'] ?? 'Not set') ?></td><td><?= e($a['date_added'] ?? $a['created_at']) ?></td><td><?= e($a['added_username'] ?? 'Legacy record') ?></td><td><?= e($a['last_updated_by'] ?? 'Not updated') ?></td><td><?= e($a['last_updated'] ?? 'Not updated') ?></td><td><div class="actions"><a class="btn" href="articles.php?edit=<?= e($a['article_id']) ?>">Edit</a><form method="post" action="articles.php" onsubmit="return confirm('Delete this article?');"><input type="hidden" name="article_id" value="<?= e($a['article_id']) ?>"><button class="btn-danger" name="delete_article" type="submit">Delete</button></form></div></td></tr><?php endforeach; ?></tbody></table></div></section></main></body></html>
