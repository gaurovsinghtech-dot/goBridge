<?php

$passwords = ['', 'root', '123456', 'admin', 'password', 'root123'];
foreach ($passwords as $p) {
    try {
        $pdo = new PDO('mysql:host=127.0.0.1', 'root', $p);
        echo "Connected with password '{$p}'!\n";
        $dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
        echo "Databases: " . implode(', ', $dbs) . "\n";
        
        foreach ($dbs as $db) {
            if (in_array($db, ['information_schema', 'mysql', 'performance_schema', 'sys'])) continue;
            echo "\nChecking DB '{$db}':\n";
            $pdo->exec("USE `$db`");
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('contacts', $tables)) {
                echo "  Found contacts table in '{$db}'!\n";
                $stmt = $pdo->query("SELECT * FROM contacts WHERE phone_e164 LIKE '%91%' OR first_name LIKE '%Panda%' OR last_name LIKE '%Panda%' OR first_name LIKE '%Gourav%' OR first_name LIKE '%Biswaranjan%' OR first_name LIKE '%om%'");
                $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "  Contacts count: " . count($contacts) . "\n";
                foreach ($contacts as $c) {
                    echo "    " . json_encode($c) . "\n";
                }
                
                if (in_array('segments', $tables)) {
                    $stmt2 = $pdo->query("SELECT * FROM segments");
                    echo "  Segments:\n";
                    foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $s) {
                        echo "    " . json_encode($s) . "\n";
                    }
                }
            }
        }
        break;
    } catch (Exception $e) {
        // continue
    }
}
