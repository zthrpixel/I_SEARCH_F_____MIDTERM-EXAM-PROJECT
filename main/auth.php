<?php
require_once __DIR__ . '/dbconfig.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function log_activity(PDO $pdo, int $userId, string $action, string $tableName, int $recordId, string $details = ''): void {
    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $action, $tableName, $recordId, $details]);
}
