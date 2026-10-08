<?php

$dbPath = __DIR__ . '/database/database.sqlite';
$pdo = new PDO("sqlite:" . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "=== ALL CHATBOTS ===\n";
$stmt = $pdo->query("SELECT id, workspace_id, name, enabled, status, ai_kb_id, strict_knowledge_mode, fallback_reply FROM ai_chatbots");
while ($row = $stmt->fetch()) {
    echo "ID: {$row['id']} | WS: {$row['workspace_id']} | Name: {$row['name']} | Enabled: {$row['enabled']} | Status: {$row['status']} | KB_ID: {$row['ai_kb_id']} | Fallback: " . json_encode($row['fallback_reply']) . "\n";
}

echo "\n=== CHANNEL ACCOUNTS ===\n";
$stmt = $pdo->query("SELECT id, workspace_id, channel, meta_json FROM channel_accounts");
while ($row = $stmt->fetch()) {
    echo "ID: {$row['id']} | WS: {$row['workspace_id']} | Channel: {$row['channel']} | Meta: {$row['meta_json']}\n";
}
