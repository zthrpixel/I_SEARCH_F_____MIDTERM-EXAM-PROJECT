<?php
require_once __DIR__ . '/main/auth.php';

$publisherCount = (int)$pdo->query('SELECT COUNT(*) FROM publishers')->fetchColumn();
$articleCount = (int)$pdo->query('SELECT COUNT(*) FROM articles')->fetchColumn();
$recentArticles = $pdo->query(
    'SELECT a.article_id, a.title, a.published_date, p.publisher_name, u.first_name, u.last_name
     FROM articles a
     INNER JOIN publishers p ON p.publisher_id = a.publisher_id
     LEFT JOIN users u ON u.user_id = a.added_by
     ORDER BY a.article_id DESC LIMIT 10'
)->fetchAll();
$recentActivity = $pdo->query(
    'SELECT al.action, al.table_name, al.record_id, al.details, al.created_at, u.username
     FROM audit_logs al LEFT JOIN users u ON u.user_id = al.user_id
     ORDER BY al.audit_id DESC LIMIT 10'
)->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Dashboard | Digital Press</title><link rel="stylesheet" href="style.css"></head><body>
<nav class="navbar"><strong>Digital Press</strong><div><a href="index.php">Dashboard</a><a href="publishers.php">Publishers</a><a href="articles.php">Articles</a><span class="nav-user">Signed in: <?= e($_SESSION['user_name'] ?? $_SESSION['username']) ?></span><a href="logout.php">Logout</a></div></nav>
<main class="container"><section class="card"><h1>Dashboard</h1><p>Welcome, <?= e($_SESSION['user_name'] ?? $_SESSION['username']) ?>. You are logged in.</p><div class="stats"><div class="stat"><span>Publishers</span><strong><?= $publisherCount ?></strong></div><div class="stat"><span>Articles</span><strong><?= $articleCount ?></strong></div></div><p><a class="btn" href="publishers.php">Manage Publishers</a> <a class="btn" href="articles.php">Manage Articles</a></p></section>
<section class="card"><h2>Recent Articles</h2><?php if (!$recentArticles): ?><p>No articles yet.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Title</th><th>Publisher</th><th>Created by</th><th>Publication Date</th></tr></thead><tbody><?php foreach ($recentArticles as $a): ?><tr><td><?= e($a['title']) ?></td><td><?= e($a['publisher_name']) ?></td><td><?= e(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?: 'Legacy record') ?></td><td><?= e($a['published_date'] ?? 'Not set') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<section class="card"><h2>Recent Activity</h2><?php if (!$recentActivity): ?><p>No activity recorded yet. Changes made after this feature is installed will appear here.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>User</th><th>Action</th><th>Entity</th><th>Record ID</th><th>Details</th><th>Timestamp</th></tr></thead><tbody><?php foreach ($recentActivity as $a): ?><tr><td><?= e($a['username'] ?? 'Deleted account') ?></td><td><?= e($a['action']) ?></td><td><?= e($a['table_name']) ?></td><td><?= e($a['record_id']) ?></td><td><?= e($a['details']) ?></td><td><?= e($a['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section></main></body></html>
