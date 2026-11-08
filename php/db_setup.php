<?php
require_once 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
$conn->select_db(DB_NAME);
$conn->set_charset('utf8mb4');

// ── accounts ────────────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS accounts (
        account_id        INT AUTO_INCREMENT PRIMARY KEY,
        firstName         VARCHAR(50)  NOT NULL,
        lastName          VARCHAR(50)  NOT NULL,
        role              ENUM('Employee','Admin','Super Admin','Parent/Guardian') NOT NULL DEFAULT 'Employee',
        email             VARCHAR(100) NOT NULL UNIQUE,
        password          VARCHAR(255) NOT NULL,
        Created           DATE         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        is_verified       TINYINT(1)   DEFAULT 0,
        verification_code VARCHAR(6)   DEFAULT NULL,
        code_expires      DATETIME     DEFAULT NULL,
        consent_agreed    TINYINT(1)   DEFAULT 0,
        consent_date      DATETIME     DEFAULT NULL,
        status            VARCHAR(20)  DEFAULT 'pending',
        adviser_grade_level VARCHAR(20) DEFAULT NULL,
        adviser_sections  VARCHAR(255) DEFAULT NULL,
        dark_mode         TINYINT(1)   DEFAULT 0,
        lang              VARCHAR(5)   DEFAULT 'en'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

// ── stdRecord ───────────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS stdRecord (
        student_id       INT AUTO_INCREMENT PRIMARY KEY,
        std_last_name    VARCHAR(50)  NOT NULL,
        std_first_name   VARCHAR(50)  NOT NULL,
        std_mid_initial  VARCHAR(50)  NOT NULL,
        gender           ENUM('male','female') NOT NULL,
        grade_level      VARCHAR(20)  DEFAULT NULL,
        section          VARCHAR(50)  DEFAULT NULL,
        school_year      VARCHAR(20)  DEFAULT NULL,
        date_of_birth    DATE         DEFAULT NULL,
        photo            VARCHAR(255) DEFAULT NULL,
        height           INT(10)      NOT NULL,
        height_unit      ENUM('cm','m','inch','feet') NOT NULL DEFAULT 'cm',
        weight           INT(10)      NOT NULL,
        weight_unit      ENUM('kg','lbs') NOT NULL DEFAULT 'kg',
        classification   VARCHAR(50)  NOT NULL,
        bmi              DECIMAL(5,2) NOT NULL,
        bmi_zscore       DECIMAL(6,3) DEFAULT NULL,
        height_zscore    DECIMAL(6,3) DEFAULT NULL,
        height_classification VARCHAR(50) DEFAULT NULL,
        guardian_name    VARCHAR(50)  NOT NULL,
        guardian_number  VARCHAR(12)  NOT NULL,
        guardian_email   VARCHAR(50)  NOT NULL,
        created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

// ── feedingRecord ───────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS feedingRecord (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        student_id      INT NOT NULL,
        feed_date       DATE NOT NULL,
        feed_time       TIME NOT NULL DEFAULT '00:00:00',
        meal_type       VARCHAR(30) DEFAULT NULL,
        notes           TEXT DEFAULT NULL,
        recorded_by     VARCHAR(100) DEFAULT NULL,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

// ── feedingSchedule ─────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS feedingSchedule (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        schedule_date    DATE NOT NULL,
        meal_type        VARCHAR(30) NOT NULL,
        food_description TEXT NOT NULL,
        created_by       VARCHAR(100) DEFAULT NULL,
        created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_feeding_schedule (schedule_date, meal_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

// ── audit_log ───────────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS audit_log (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_email  VARCHAR(100),
        user_name   VARCHAR(100),
        action      VARCHAR(50) NOT NULL,
        target_type VARCHAR(30),
        target_id   INT DEFAULT NULL,
        details     TEXT,
        ip_address  VARCHAR(45),
        created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

// ── notifications ───────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS notifications (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_email  VARCHAR(100),
        title       VARCHAR(100) NOT NULL,
        message     TEXT,
        type        VARCHAR(20) DEFAULT 'info',
        is_read     TINYINT(1) DEFAULT 0,
        link        VARCHAR(255) DEFAULT NULL,
        created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

// ── data_corrections ────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS data_corrections (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        parent_email    VARCHAR(100) DEFAULT NULL,
        student_id      INT DEFAULT NULL,
        field_name      VARCHAR(50) DEFAULT NULL,
        current_value   VARCHAR(255) DEFAULT NULL,
        requested_value VARCHAR(255) DEFAULT NULL,
        reason          TEXT DEFAULT NULL,
        status          VARCHAR(20) DEFAULT 'pending',
        reviewed_by     VARCHAR(100) DEFAULT NULL,
        reviewed_at     DATETIME DEFAULT NULL,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

// ── meal_plans ──────────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS meal_plans (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        plan_date   DATE NOT NULL,
        meal_type   VARCHAR(30) NOT NULL,
        menu        TEXT NOT NULL,
        calories    INT DEFAULT NULL,
        protein_g   DECIMAL(5,1) DEFAULT NULL,
        notes       TEXT DEFAULT NULL,
        created_by  VARCHAR(100) DEFAULT NULL,
        created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

// ── scheduled_reports ───────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS scheduled_reports (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        report_type     VARCHAR(50) NOT NULL,
        frequency       VARCHAR(20) NOT NULL,
        recipient_email VARCHAR(100) NOT NULL,
        last_sent       DATETIME DEFAULT NULL,
        is_active       TINYINT(1) DEFAULT 1,
        created_by      VARCHAR(100) DEFAULT NULL,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

// ── Migrations for existing tables ──────────────────────────
$mig = [
    // accounts columns
    ['accounts', 'created_at', "ALTER TABLE accounts ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"],
    ['accounts', 'Created', "ALTER TABLE accounts ADD COLUMN Created DATE NOT NULL DEFAULT CURRENT_TIMESTAMP"],
    ['accounts', 'is_verified', "ALTER TABLE accounts ADD COLUMN is_verified TINYINT(1) DEFAULT 0"],
    ['accounts', 'verification_code', "ALTER TABLE accounts ADD COLUMN verification_code VARCHAR(6) DEFAULT NULL"],
    ['accounts', 'code_expires', "ALTER TABLE accounts ADD COLUMN code_expires DATETIME DEFAULT NULL"],
    ['accounts', 'consent_agreed', "ALTER TABLE accounts ADD COLUMN consent_agreed TINYINT(1) DEFAULT 0"],
    ['accounts', 'consent_date', "ALTER TABLE accounts ADD COLUMN consent_date DATETIME DEFAULT NULL"],
    ['accounts', 'status', "ALTER TABLE accounts ADD COLUMN status VARCHAR(20) DEFAULT 'pending'"],
    ['accounts', 'adviser_grade_level', "ALTER TABLE accounts ADD COLUMN adviser_grade_level VARCHAR(20) DEFAULT NULL"],
    ['accounts', 'adviser_sections', "ALTER TABLE accounts ADD COLUMN adviser_sections VARCHAR(255) DEFAULT NULL"],
    ['accounts', 'dark_mode', "ALTER TABLE accounts ADD COLUMN dark_mode TINYINT(1) DEFAULT 0"],
    ['accounts', 'lang', "ALTER TABLE accounts ADD COLUMN lang VARCHAR(5) DEFAULT 'en'"],
    // stdRecord columns
    ['stdRecord', 'grade_level', "ALTER TABLE stdRecord ADD COLUMN grade_level VARCHAR(20) DEFAULT NULL AFTER gender"],
    ['stdRecord', 'section', "ALTER TABLE stdRecord ADD COLUMN section VARCHAR(50) DEFAULT NULL AFTER grade_level"],
    ['stdRecord', 'school_year', "ALTER TABLE stdRecord ADD COLUMN school_year VARCHAR(20) DEFAULT NULL AFTER section"],
    ['stdRecord', 'date_of_birth', "ALTER TABLE stdRecord ADD COLUMN date_of_birth DATE DEFAULT NULL AFTER school_year"],
    ['stdRecord', 'photo', "ALTER TABLE stdRecord ADD COLUMN photo VARCHAR(255) DEFAULT NULL AFTER school_year"],
    ['stdRecord', 'bmi_zscore', "ALTER TABLE stdRecord ADD COLUMN bmi_zscore DECIMAL(6,3) DEFAULT NULL AFTER bmi"],
    ['stdRecord', 'height_zscore', "ALTER TABLE stdRecord ADD COLUMN height_zscore DECIMAL(6,3) DEFAULT NULL AFTER bmi_zscore"],
    ['stdRecord', 'height_classification', "ALTER TABLE stdRecord ADD COLUMN height_classification VARCHAR(50) DEFAULT NULL AFTER height_zscore"],
    // feedingRecord columns
    ['feedingRecord', 'feed_time', "ALTER TABLE feedingRecord ADD COLUMN feed_time TIME NOT NULL DEFAULT '00:00:00' AFTER feed_date"],
];
foreach ($mig as $m) {
    $check = $conn->query("SHOW COLUMNS FROM {$m[0]} LIKE '{$m[1]}'");
    if ($check && $check->num_rows === 0) {
        $conn->query($m[2]);
    }
}

$bmiColumn = $conn->query("SHOW COLUMNS FROM stdRecord LIKE 'bmi'");
$bmiDefinition = $bmiColumn ? $bmiColumn->fetch_assoc() : null;
if ($bmiDefinition && stripos($bmiDefinition['Type'], 'decimal') !== 0 && stripos($bmiDefinition['Type'], 'float') !== 0 && stripos($bmiDefinition['Type'], 'double') !== 0) {
    $conn->query("ALTER TABLE stdRecord MODIFY bmi DECIMAL(5,2) NOT NULL");
}

// Ensure accounts has both 'account_id' and 'id'
$hasAccountId = $conn->query("SHOW COLUMNS FROM accounts LIKE 'account_id'")->num_rows > 0;
$hasAccId = $conn->query("SHOW COLUMNS FROM accounts LIKE 'id'")->num_rows > 0;
if ($hasAccountId && !$hasAccId) {
    $conn->query("ALTER TABLE accounts ADD COLUMN id INT GENERATED ALWAYS AS (account_id) VIRTUAL");
} elseif (!$hasAccountId && $hasAccId) {
    $conn->query("ALTER TABLE accounts ADD COLUMN account_id INT GENERATED ALWAYS AS (id) VIRTUAL");
}

// Ensure stdRecord has both 'student_id' and 'id'
$hasStudentId = $conn->query("SHOW COLUMNS FROM stdRecord LIKE 'student_id'")->num_rows > 0;
$hasStdId = $conn->query("SHOW COLUMNS FROM stdRecord LIKE 'id'")->num_rows > 0;
if ($hasStudentId && !$hasStdId) {
    $conn->query("ALTER TABLE stdRecord ADD COLUMN id INT GENERATED ALWAYS AS (student_id) VIRTUAL");
} elseif (!$hasStudentId && $hasStdId) {
    $conn->query("ALTER TABLE stdRecord ADD COLUMN student_id INT GENERATED ALWAYS AS (id) VIRTUAL");
}

$idColumnResult = $conn->query("SHOW COLUMNS FROM stdRecord LIKE 'id'");
$studentIdColumnResult = $conn->query("SHOW COLUMNS FROM stdRecord LIKE 'student_id'");
$idColumn = $idColumnResult ? $idColumnResult->fetch_assoc() : null;
$studentIdColumn = $studentIdColumnResult ? $studentIdColumnResult->fetch_assoc() : null;
if ($idColumn && $studentIdColumn
    && stripos($idColumn['Extra'], 'generated') === false
    && stripos($studentIdColumn['Extra'], 'generated') === false) {
    if (stripos($idColumn['Extra'], 'auto_increment') !== false) {
        $identifierSync = $conn->query("UPDATE stdRecord SET student_id = id WHERE student_id IS NULL");
    } elseif (stripos($studentIdColumn['Extra'], 'auto_increment') !== false) {
        $identifierSync = $conn->query("UPDATE stdRecord SET id = student_id WHERE id IS NULL");
    } else {
        $identifierSync = $conn->query("UPDATE stdRecord SET id = COALESCE(id, student_id), student_id = COALESCE(student_id, id) WHERE id IS NULL OR student_id IS NULL");
    }
    if (!$identifierSync) error_log('Student record identifier migration failed: ' . $conn->error);
} elseif (!$idColumn || !$studentIdColumn) {
    error_log('Student record identifier migration failed: ID columns could not be inspected.');
}

// Sync created_at from Created if empty
$conn->query("UPDATE accounts SET created_at = Created WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'");

// ── Create uploads directory ────────────────────────────────
$uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'photos';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

echo "<h2>Database setup complete!</h2>";
echo "<p>✔ <b>" . DB_NAME . "</b> — accounts, stdRecord, feedingRecord, feedingSchedule, audit_log, notifications, data_corrections, meal_plans, scheduled_reports</p>";

// Default Super Admin
$count = $conn->query("SELECT COUNT(*) as c FROM accounts")->fetch_assoc()['c'];
if ($count == 0 && strlen(INITIAL_ADMIN_PASSWORD) >= 16) {
    $initialPasswordHash = password_hash(INITIAL_ADMIN_PASSWORD, PASSWORD_DEFAULT);
    $initialAdmin = $conn->prepare("INSERT INTO accounts (firstName, lastName, role, email, password, is_verified, status) VALUES ('System', 'Admin', 'Super Admin', 'admin@nutriph.com', ?, 1, 'active')");
    if ($initialAdmin) {
        $initialAdmin->bind_param('s', $initialPasswordHash);
        if ($initialAdmin->execute()) {
            echo "<p>✔ Initial Super Admin created for admin@nutriph.com.</p>";
        } else {
            error_log('Initial admin account creation failed: ' . $initialAdmin->error);
        }
        $initialAdmin->close();
    } else {
        error_log('Initial admin account creation failed: could not prepare the insert.');
    }
} elseif ($count == 0) {
    echo "<p>Set NUTRIGUIDE_INITIAL_ADMIN_PASSWORD to a unique password with at least 16 characters, then reload to create the initial admin.</p>";
}

echo "<br><a href='../pages/signin.php'>Go to Sign In</a>";
$conn->close();
?>
