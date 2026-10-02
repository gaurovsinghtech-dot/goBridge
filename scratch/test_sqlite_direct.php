<?php

$pdo = new PDO('sqlite:' . __DIR__ . '/../database/database.sqlite');
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);

echo "TABLES IN DB:\n" . implode(', ', $tables) . "\n\n";

if (in_array('contacts', $tables)) {
    $contacts = $pdo->query("SELECT * FROM contacts")->fetchAll(PDO::FETCH_ASSOC);
    echo "Total Contacts in SQLite: " . count($contacts) . "\n";
    foreach ($contacts as $c) {
        if (str_contains(strtolower($c['first_name'] . ' ' . $c['last_name']), 'panda') ||
            str_contains(strtolower($c['first_name'] . ' ' . $c['last_name']), 'biswaranjan') ||
            str_contains(strtolower($c['first_name'] . ' ' . $c['last_name']), 'gourav') ||
            str_contains(strtolower($c['first_name'] . ' ' . $c['last_name']), 'gaurav') ||
            str_contains(strtolower($c['phone_e164']), '8763975388') ||
            str_contains(strtolower($c['phone_e164']), '9692358823') ||
            str_contains(strtolower($c['phone_e164']), '8338861017')) {
            echo "  MATCHED CONTACT: " . json_encode($c) . "\n";
        }
    }
}

if (in_array('segments', $tables)) {
    $segments = $pdo->query("SELECT * FROM segments")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nTotal Segments in SQLite: " . count($segments) . "\n";
    foreach ($segments as $s) {
        echo "  SEGMENT: " . json_encode($s) . "\n";
    }
}
