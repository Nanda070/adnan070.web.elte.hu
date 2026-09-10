<?php
/**
 * Quack Application API Backend
 * ELTE Caesar PHP 8.2 + MySQL Architecture
 * dev: Nanda (https://nanda.is-a.dev)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database Connection
$pdo = null;
$isCaesar = class_exists('_CS');

try {
    if ($isCaesar) {
        $cs = _CS::load_caesar_settings();
        $pdo = new PDO($cs->getMysqlDsn(), $cs->getMysqlUser(), $cs->getMysqlPswd(), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        $pdo->exec("SET NAMES utf8mb4");
    } else {
        // Fallback for local testing / non-Caesar environment
        $dbFile = __DIR__ . '/quack_data.sqlite';
        $pdo = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB Connection failed']);
    exit;
}

// Ensure database tables exist with initial rows
try {
    if ($isCaesar) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS quack_stats (
                id INT PRIMARY KEY,
                total_quacks BIGINT UNSIGNED NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $pdo->exec("INSERT IGNORE INTO quack_stats (id, total_quacks) VALUES (1, 0);");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS quack_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                message VARCHAR(191) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } else {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS quack_stats (
                id INTEGER PRIMARY KEY,
                total_quacks INTEGER NOT NULL DEFAULT 0
            );
        ");
        $pdo->exec("INSERT OR IGNORE INTO quack_stats (id, total_quacks) VALUES (1, 0);");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS quack_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                message TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }
} catch (Throwable $e) {
    // Fail silently if table already exists
}

// Parse request payload
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Support JSON input if Content-Type is application/json
$rawInput = file_get_contents('php://input');
if ($rawInput) {
    $json = json_decode($rawInput, true);
    if (is_array($json)) {
        if (empty($action) && isset($json['action'])) {
            $action = (string)$json['action'];
        }
    }
} else {
    $json = [];
}

switch ($action) {
    case 'get_stats':
        try {
            // Retrieve total quacks with self-healing if row missing
            $stmt = $pdo->prepare("SELECT total_quacks FROM quack_stats WHERE id = 1 LIMIT 1");
            $stmt->execute();
            $statRow = $stmt->fetch();

            if (!$statRow) {
                if ($isCaesar) {
                    $pdo->exec("INSERT IGNORE INTO quack_stats (id, total_quacks) VALUES (1, 0)");
                } else {
                    $pdo->exec("INSERT OR IGNORE INTO quack_stats (id, total_quacks) VALUES (1, 0)");
                }
                $totalQuacks = 0;
            } else {
                $totalQuacks = (int)$statRow['total_quacks'];
            }

            // Retrieve last 10 messages
            $msgStmt = $pdo->prepare("SELECT id, message, created_at FROM quack_messages ORDER BY id DESC LIMIT 10");
            $msgStmt->execute();
            $messages = $msgStmt->fetchAll();

            echo json_encode([
                'status' => 'success',
                'total_quacks' => $totalQuacks,
                'messages' => $messages
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to fetch stats']);
        }
        break;

    case 'quack':
        try {
            // Batched quack support (clamps between 1 and 100)
            $count = (int)($_POST['count'] ?? $json['count'] ?? $_GET['count'] ?? 1);
            if ($count < 1) $count = 1;
            if ($count > 100) $count = 100;

            $updateStmt = $pdo->prepare("UPDATE quack_stats SET total_quacks = total_quacks + :count WHERE id = 1");
            $updateStmt->execute([':count' => $count]);

            if ($updateStmt->rowCount() === 0) {
                if ($isCaesar) {
                    $insStmt = $pdo->prepare("INSERT INTO quack_stats (id, total_quacks) VALUES (1, :count) ON DUPLICATE KEY UPDATE total_quacks = total_quacks + :count2");
                    $insStmt->execute([':count' => $count, ':count2' => $count]);
                } else {
                    $insStmt = $pdo->prepare("INSERT OR REPLACE INTO quack_stats (id, total_quacks) VALUES (1, :count)");
                    $insStmt->execute([':count' => $count]);
                }
            }

            $stmt = $pdo->prepare("SELECT total_quacks FROM quack_stats WHERE id = 1 LIMIT 1");
            $stmt->execute();
            $statRow = $stmt->fetch();
            $totalQuacks = $statRow ? (int)$statRow['total_quacks'] : $count;

            echo json_encode([
                'status' => 'success',
                'total_quacks' => $totalQuacks,
                'increment' => $count
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to increment quack']);
        }
        break;

    case 'add_message':
        try {
            $messageRaw = $_POST['message'] ?? $json['message'] ?? $_GET['message'] ?? '';
            // Sanitize: strip tags, trim, strip control chars, limit to 60 characters
            $messageClean = trim(strip_tags((string)$messageRaw));
            $messageClean = preg_replace('/[\x00-\x1F\x7F]/u', '', $messageClean);
            $messageClean = mb_substr($messageClean, 0, 60, 'UTF-8');

            if ($messageClean === '') {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
                exit;
            }

            $insertStmt = $pdo->prepare("INSERT INTO quack_messages (message, created_at) VALUES (:message, :created_at)");
            $now = date('Y-m-d H:i:s');
            $insertStmt->execute([
                ':message' => $messageClean,
                ':created_at' => $now
            ]);

            $newId = (int)$pdo->lastInsertId();

            echo json_encode([
                'status' => 'success',
                'message' => [
                    'id' => $newId,
                    'message' => $messageClean,
                    'created_at' => $now
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to save message']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid action. Supported: get_stats, quack, add_message'
        ]);
        break;
}
