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
                likes INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS quack_rate_limits (
                ip VARCHAR(45) PRIMARY KEY,
                last_post_at INT UNSIGNED NOT NULL
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
                likes INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS quack_rate_limits (
                ip TEXT PRIMARY KEY,
                last_post_at INTEGER NOT NULL
            );
        ");
    }

    // Auto-migration: ensure 'likes' column exists if table was created previously
    try {
        if ($isCaesar) {
            $pdo->exec("ALTER TABLE quack_messages ADD COLUMN likes INT NOT NULL DEFAULT 0;");
        } else {
            $pdo->exec("ALTER TABLE quack_messages ADD COLUMN likes INTEGER NOT NULL DEFAULT 0;");
        }
    } catch (Throwable $e) {
        // Column already exists
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

            // Retrieve last 10 messages with likes
            $msgStmt = $pdo->prepare("SELECT id, message, likes, created_at FROM quack_messages ORDER BY id DESC LIMIT 10");
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

    case 'get_wall':
        try {
            // Retrieve last 50 messages for the full wall modal
            $msgStmt = $pdo->prepare("SELECT id, message, likes, created_at FROM quack_messages ORDER BY id DESC LIMIT 50");
            $msgStmt->execute();
            $messages = $msgStmt->fetchAll();

            echo json_encode([
                'status' => 'success',
                'messages' => $messages
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to fetch wall messages']);
        }
        break;

    case 'like_message':
        try {
            $msgId = (int)($_POST['id'] ?? $json['id'] ?? $_GET['id'] ?? 0);
            if ($msgId <= 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid message ID']);
                exit;
            }

            $likeStmt = $pdo->prepare("UPDATE quack_messages SET likes = likes + 1 WHERE id = :id");
            $likeStmt->execute([':id' => $msgId]);

            // Fetch updated like count
            $fetchStmt = $pdo->prepare("SELECT likes FROM quack_messages WHERE id = :id LIMIT 1");
            $fetchStmt->execute([':id' => $msgId]);
            $row = $fetchStmt->fetch();

            echo json_encode([
                'status' => 'success',
                'id' => $msgId,
                'likes' => $row ? (int)$row['likes'] : 0
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to like message']);
        }
        break;

    case 'quack':
        try {
            // Batched quack support (clamps between 1 and 250)
            $count = (int)($_POST['count'] ?? $json['count'] ?? $_GET['count'] ?? 1);
            if ($count < 1) $count = 1;
            if ($count > 250) $count = 250;

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
            // Rate limiting: 10s cooldown per IP
            $userIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userIp = substr(trim(explode(',', $userIp)[0]), 0, 45);
            $nowTime = time();
            $cooldown = 10;

            $rateStmt = $pdo->prepare("SELECT last_post_at FROM quack_rate_limits WHERE ip = :ip LIMIT 1");
            $rateStmt->execute([':ip' => $userIp]);
            $rateRow = $rateStmt->fetch();

            if ($rateRow && ($nowTime - (int)$rateRow['last_post_at']) < $cooldown) {
                $rem = $cooldown - ($nowTime - (int)$rateRow['last_post_at']);
                http_response_code(429);
                echo json_encode([
                    'status' => 'error',
                    'message' => "Too fast! Wait {$rem}s before posting again.",
                    'retry_after' => $rem
                ]);
                exit;
            }

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

            $insertStmt = $pdo->prepare("INSERT INTO quack_messages (message, likes, created_at) VALUES (:message, 0, :created_at)");
            $now = date('Y-m-d H:i:s');
            $insertStmt->execute([
                ':message' => $messageClean,
                ':created_at' => $now
            ]);

            $newId = (int)$pdo->lastInsertId();

            // Record rate limit timestamp
            if ($isCaesar) {
                $insRate = $pdo->prepare("INSERT INTO quack_rate_limits (ip, last_post_at) VALUES (:ip, :now) ON DUPLICATE KEY UPDATE last_post_at = :now2");
                $insRate->execute([':ip' => $userIp, ':now' => $nowTime, ':now2' => $nowTime]);
            } else {
                $insRate = $pdo->prepare("INSERT OR REPLACE INTO quack_rate_limits (ip, last_post_at) VALUES (:ip, :now)");
                $insRate->execute([':ip' => $userIp, ':now' => $nowTime]);
            }

            echo json_encode([
                'status' => 'success',
                'message' => [
                    'id' => $newId,
                    'message' => $messageClean,
                    'likes' => 0,
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
            'message' => 'Invalid action. Supported: get_stats, get_wall, quack, add_message, like_message'
        ]);
        break;
}
