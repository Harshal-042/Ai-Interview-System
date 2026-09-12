<?php
/**
 * Database Connection & Schema Bootstrapper
 * Supports MySQL with graceful SQLite PDO fallback.
 */

function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
    $dbName = getenv('DB_NAME') ?: 'interviews';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPass = getenv('DB_PASS') ?: '';
    $dbPort = getenv('DB_PORT') ?: '3306';

    // Try MySQL first if credentials or host specified
    if (getenv('DB_CONNECTION') === 'mysql' || (!empty(getenv('DB_HOST')) && getenv('DB_HOST') !== '127.0.0.1')) {
        try {
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            initTables($pdo, 'mysql');
            return $pdo;
        } catch (PDOException $e) {
            // Log notice and fallback to SQLite
            error_log("MySQL connection failed, falling back to SQLite: " . $e->getMessage());
        }
    }

    // Default SQLite database
    $dbDir = __DIR__ . '/../database';
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0777, true);
    }
    $dbFile = $dbDir . '/interviews.db';

    try {
        $pdo = new PDO("sqlite:" . $dbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        initTables($pdo, 'sqlite');
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . htmlspecialchars($e->getMessage()));
    }
}

function initTables($pdo, $driver = 'sqlite') {
    $autoIncrement = ($driver === 'mysql') ? 'AUTO_INCREMENT' : 'AUTOINCREMENT';
    $currentTimestamp = ($driver === 'mysql') ? 'CURRENT_TIMESTAMP' : 'CURRENT_TIMESTAMP';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY $autoIncrement,
            username VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(150) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT $currentTimestamp
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS interviews (
            id INTEGER PRIMARY KEY $autoIncrement,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            status VARCHAR(50) DEFAULT 'active',
            created_by INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT $currentTimestamp
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS questions (
            id INTEGER PRIMARY KEY $autoIncrement,
            interview_id INTEGER NOT NULL,
            question TEXT NOT NULL,
            question_order INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT $currentTimestamp,
            FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS candidates (
            id INTEGER PRIMARY KEY $autoIncrement,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL,
            phone VARCHAR(50),
            created_at DATETIME DEFAULT $currentTimestamp
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS interview_sessions (
            id INTEGER PRIMARY KEY $autoIncrement,
            interview_id INTEGER NOT NULL,
            candidate_id INTEGER NOT NULL,
            token VARCHAR(100) NOT NULL UNIQUE,
            started_at DATETIME,
            completed_at DATETIME,
            status VARCHAR(50) DEFAULT 'Created',
            video_path TEXT,
            transcript TEXT,
            created_at DATETIME DEFAULT $currentTimestamp,
            FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE,
            FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS interview_analysis (
            id INTEGER PRIMARY KEY $autoIncrement,
            session_id INTEGER NOT NULL UNIQUE,
            technical_score INTEGER DEFAULT 0,
            relevance_score INTEGER DEFAULT 0,
            communication_score INTEGER DEFAULT 0,
            completeness_score INTEGER DEFAULT 0,
            overall_score INTEGER DEFAULT 0,
            rating VARCHAR(50) DEFAULT 'Pending',
            ai_feedback TEXT,
            video_metrics TEXT,
            created_at DATETIME DEFAULT $currentTimestamp,
            FOREIGN KEY (session_id) REFERENCES interview_sessions(id) ON DELETE CASCADE
        );
    ");

    // Seed default admin if missing
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM admins");
    $res = $stmt->fetch();
    if ($res['cnt'] == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO admins (username, email, password_hash) VALUES (?, ?, ?)");
        $insert->execute(['admin', 'admin@ai-interview.local', $hash]);
    }

    // Seed initial interview if empty
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM interviews");
    $res = $stmt->fetch();
    if ($res['cnt'] == 0) {
        $insert = $pdo->prepare("INSERT INTO interviews (title, description, status, created_by) VALUES (?, ?, 'active', 1)");
        $insert->execute([
            'PHP & Full-Stack Developer Assessment',
            'Technical screening interview for PHP, MySQL, OOP principles, and Web Architecture.'
        ]);
        $interviewId = $pdo->lastInsertId();

        $qInsert = $pdo->prepare("INSERT INTO questions (interview_id, question, question_order) VALUES (?, ?, ?)");
        $qInsert->execute([$interviewId, 'What is PHP and how does its server-side execution lifecycle work?', 1]);
        $qInsert->execute([$interviewId, 'What is MySQL and how do indexes improve relational query performance?', 2]);
        $qInsert->execute([$interviewId, 'Explain Object-Oriented Programming principles: Encapsulation, Inheritance, and Polymorphism.', 3]);
        $qInsert->execute([$interviewId, 'What is a REST API and what are the best practices for structuring HTTP endpoints?', 4]);

        // Seed candidates
        $cInsert = $pdo->prepare("INSERT INTO candidates (name, email, phone) VALUES (?, ?, ?)");
        $cInsert->execute(['Alex Morgan', 'alex.morgan@example.com', '+1 (555) 234-5678']);
        $candidateId1 = $pdo->lastInsertId();

        $cInsert->execute(['Jordan Lee', 'jordan.lee@example.com', '+1 (555) 876-5432']);
        $candidateId2 = $pdo->lastInsertId();

        // Sample completed session for Alex Morgan
        $sampleVideo = 'uploads/interviews/sample_interview.webm';
        $transcript = "PHP is a server-side scripting language designed for web development. When a request hits the web server, PHP parses the script, executes business logic, interacts with databases like MySQL via PDO, and generates HTML or JSON responses. In MySQL, B-tree indexes significantly accelerate SELECT queries by avoiding full table scans. Object-oriented programming relies on encapsulation to protect object state, inheritance to share code across hierarchies, and polymorphism to enable interchangeable behavior. REST APIs organize resources via clean URI paths, utilizing standard HTTP verbs like GET, POST, PUT, DELETE, and returning structured JSON payloads.";

        $sInsert = $pdo->prepare("INSERT INTO interview_sessions (interview_id, candidate_id, token, started_at, completed_at, status, video_path, transcript) VALUES (?, ?, ?, datetime('now', '-2 hours'), datetime('now', '-1 hours'), 'Completed', ?, ?)");
        $sInsert->execute([$interviewId, $candidateId1, 'demo-token-alex-123', $sampleVideo, $transcript]);
        $sessionId = $pdo->lastInsertId();

        $metricsJson = json_encode([
            'success' => true,
            'face_detected' => true,
            'face_visibility_percentage' => 96,
            'camera_status' => 'Good',
            'resolution' => '1280x720',
            'fps' => 30,
            'lighting_quality' => 'Balanced / Optimal',
            'observable_signals' => [
                'Candidate face continuously visible in camera frame',
                'Sufficient lighting contrast with stable frame rates',
                'Direct camera eye-level orientation maintained'
            ],
            'frames_analyzed' => 450,
            'duration_seconds' => 45
        ]);

        $feedback = "The candidate demonstrated strong foundational knowledge across PHP server lifecycles, relational database indexing concepts, OOP principles, and clean RESTful design patterns. Articulation was clear, confident, and well-structured.";

        $aInsert = $pdo->prepare("INSERT INTO interview_analysis (session_id, technical_score, relevance_score, communication_score, completeness_score, overall_score, rating, ai_feedback, video_metrics) VALUES (?, 88, 86, 84, 85, 86, 'Proficient', ?, ?)");
        $aInsert->execute([$sessionId, $feedback, $metricsJson]);

        // Pending session for Jordan Lee ready to test
        $sInsert2 = $pdo->prepare("INSERT INTO interview_sessions (interview_id, candidate_id, token, status) VALUES (?, ?, ?, 'Created')");
        $sInsert2->execute([$interviewId, $candidateId2, 'interview-jordan-' . substr(md5(uniqid()), 0, 10)]);
    }
}
