<?php
// ── Environment ─────────────────────────────────────────────
// Set to false on localhost/XAMPP, true on production server
define('IS_PRODUCTION', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');

// ── Security Headers ────────────────────────────────────────
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (IS_PRODUCTION) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
setSecurityHeaders();

// ── HTTPS Redirect (production only) ────────────────────────
function enforceHttps() {
    if (IS_PRODUCTION) return; // Already HTTPS
    // On production hosting, redirect HTTP to HTTPS
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'http') {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
        exit();
    }
}
enforceHttps();

// ── Session Security ────────────────────────────────────────
function secureSessionStart() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Lax');
        if (IS_PRODUCTION) {
            ini_set('session.cookie_secure', 1);
        }
        ini_set('session.gc_maxlifetime', 3600); // 1 hour
        session_start();

        // Session timeout: 1 hour of inactivity
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 3600) {
            session_unset();
            session_destroy();
            session_start();
        }
        $_SESSION['last_activity'] = time();
    }
}

// ── Error Handler ───────────────────────────────────────────
function setupErrorHandler() {
    $logDir = defined('LOG_DIR') ? LOG_DIR : __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', $logDir . '/error_' . date('Y-m') . '.log');
    set_error_handler(function($severity, $message, $file, $line) {
        error_log("[" . date('Y-m-d H:i:s') . "] [$severity] $message in $file:$line");
        return true;
    });
    set_exception_handler(function($e) {
        error_log("[" . date('Y-m-d H:i:s') . "] EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        http_response_code(500);
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'json') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'An unexpected error occurred']);
        } else {
            echo '<h3>Something went wrong</h3><p>Please try again later.</p>';
        }
        exit();
    });
}
setupErrorHandler();

// ── Role Normalization ───────────────────────────────────────
function normalizeRole($role) {
    $role = trim((string) $role);
    if ($role === '') return $role;

    $normalized = strtolower($role);
    return match ($normalized) {
        'super admin', 'super_admin', 'superadmin' => 'Super Admin',
        'admin' => 'Admin',
        'employee', 'staff', 'adviser' => 'Employee',
        'parent', 'parent/guardian', 'parent_guardian' => 'Parent/Guardian',
        default => $role,
    };
}

function isRole($role) { return normalizeRole($_SESSION['role'] ?? '') === normalizeRole($role); }
function isStaff() { return in_array(normalizeRole($_SESSION['role'] ?? ''), ['Employee', 'Admin', 'Super Admin']); }
function isAdmin() { return in_array(normalizeRole($_SESSION['role'] ?? ''), ['Admin', 'Super Admin']); }
function isSuperAdmin() { return normalizeRole($_SESSION['role'] ?? '') === 'Super Admin'; }
function isAdviser() { return isRole('Employee'); }
function isParent() { return normalizeRole($_SESSION['role'] ?? '') === 'Parent/Guardian'; }

function getAdviserScope($conn) {
    if (!isAdviser()) return null;

    $email = $_SESSION['user_id'] ?? '';
    $stmt = $conn->prepare("SELECT adviser_grade_level, adviser_sections FROM accounts WHERE LOWER(email) = LOWER(?) LIMIT 1");
    if (!$stmt) return ['grade_level' => '', 'sections' => []];
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $sections = array_values(array_filter(array_map('trim', explode(',', (string)($assignment['adviser_sections'] ?? ''))), fn($section) => $section !== ''));
    return ['grade_level' => trim((string)($assignment['adviser_grade_level'] ?? '')), 'sections' => $sections];
}

function appendAdviserScope($scope, $alias, &$params, &$types) {
    if ($scope === null) return '';
    if (empty($scope['grade_level']) || empty($scope['sections'])) return ' AND 1=0';

    $prefix = $alias === '' ? '' : $alias . '.';
    $placeholders = implode(',', array_fill(0, count($scope['sections']), '?'));
    $params[] = $scope['grade_level'];
    $types .= 's';
    foreach ($scope['sections'] as $section) {
        $params[] = $section;
        $types .= 's';
    }
    return " AND {$prefix}grade_level = ? AND {$prefix}section IN ($placeholders)";
}

// ── Access Control ──────────────────────────────────────────
function checkAccess($allowedRoles = []) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../pages/signin.php");
        exit();
    }
    if (!empty($allowedRoles)) {
        $currentRole = normalizeRole($_SESSION['role'] ?? '');
        if (!in_array($currentRole, $allowedRoles)) {
            if ($currentRole === 'Parent/Guardian') {
                header("Location: parent_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        }
    }
}

// ── CSRF Protection ─────────────────────────────────────────
function generateCsrf() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrf() . '">';
}

function verifyCsrf() {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        $isJsonExpected = (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'json') !== false)
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'json') !== false)
            || in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['update_profile.php', 'update_record.php', 'delete_record.php', 'get_profile.php']);

        if ($isJsonExpected) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid request token. Please refresh the page.']);
        } else {
            header("Location: ../pages/signin.php");
        }
        exit();
    }
}

// ── Rate Limiting ───────────────────────────────────────────
function checkRateLimit($key, $maxAttempts = 5, $windowSeconds = 300) {
    $now = time();
    $sessionKey = 'rate_' . $key;
    if (!isset($_SESSION[$sessionKey])) $_SESSION[$sessionKey] = [];
    $_SESSION[$sessionKey] = array_filter($_SESSION[$sessionKey], fn($t) => ($now - $t) < $windowSeconds);
    if (count($_SESSION[$sessionKey]) >= $maxAttempts) return false;
    $_SESSION[$sessionKey][] = $now;
    return true;
}

// ── Input Validation ────────────────────────────────────────
function validateHeight($height, $unit) {
    $h = floatval($height);
    if ($h <= 0) return false;
    return match($unit) {
        'cm' => $h >= 30 && $h <= 300, 'm' => $h >= 0.3 && $h <= 3.0,
        'inch' => $h >= 12 && $h <= 120, 'feet' => $h >= 1 && $h <= 10,
        default => false
    };
}

function validateWeight($weight, $unit) {
    $w = floatval($weight);
    if ($w <= 0) return false;
    return match($unit) {
        'kg' => $w >= 2 && $w <= 300, 'lbs' => $w >= 4 && $w <= 660,
        default => false
    };
}

function isValidSchoolYear($schoolYear) {
    if (!preg_match('/^(\d{4})-(\d{4})$/', trim((string)$schoolYear), $matches)) return false;
    return (int)$matches[2] === (int)$matches[1] + 1;
}

// WHO Anthro 2006 (0-5 years) and WHO Growth Reference 2007 (5-19 years) LMS tables.
function calculatePediatricBmi($height, $heightUnit, $weight, $weightUnit, $dateOfBirth, $gender, $measurementDate = null) {
    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$dateOfBirth);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (!$birthDate || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count'])) || $birthDate->format('Y-m-d') !== $dateOfBirth) {
        return ['error' => 'Enter a valid date of birth.'];
    }

    $asOfDate = $measurementDate === null ? new DateTimeImmutable('today') : DateTimeImmutable::createFromFormat('!Y-m-d', (string)$measurementDate);
    if (!$asOfDate) return ['error' => 'The measurement date is invalid.'];
    if ($birthDate > $asOfDate) return ['error' => 'Date of birth must be on or before the measurement date.'];
    $age = $birthDate->diff($asOfDate);
    if ($age->y >= 19) return ['error' => 'WHO BMI-for-age references are available through age 18 years, 11 months.'];
    $ageDays = (int)$age->days;
    $ageMonths = $ageDays / 30.4375;

    $heightCm = (float)$height;
    if ($heightUnit === 'm') $heightCm *= 100;
    elseif ($heightUnit === 'inch') $heightCm *= 2.54;
    elseif ($heightUnit === 'feet') $heightCm *= 30.48;
    $weightKg = (float)$weight;
    if ($weightUnit === 'lbs') $weightKg *= 0.45359237;
    if ($heightCm <= 0 || $weightKg <= 0) return ['error' => 'Height and weight must be greater than zero.'];

    $reference = loadPediatricBmiReference();
    if (!$reference) return ['error' => 'WHO growth reference data could not be loaded.'];
    $sex = strtolower((string)$gender);
    if (!isset($reference['who2006'][$sex])) return ['error' => 'Select a valid sex for the WHO growth reference.'];

    if ($ageMonths < 60) {
        $rows = $reference['who2006'][$sex];
        $referenceAge = min($ageDays, 1826);
        $lms = $rows[$referenceAge] ?? null;
        $standard = 'who2006';
    } else {
        $rows = $reference['who2007'][$sex];
        $monthIndex = max(61, min(228, $ageMonths + 1));
        $lowerMonth = (int)floor($monthIndex);
        $upperMonth = (int)ceil($monthIndex);
        $lower = $rows[$lowerMonth] ?? null;
        $upper = $rows[$upperMonth] ?? $lower;
        if ($lower && $upper) {
            $fraction = $upperMonth === $lowerMonth ? 0 : ($monthIndex - $lowerMonth) / ($upperMonth - $lowerMonth);
            $lms = [
                $monthIndex,
                $lower[1] + ($upper[1] - $lower[1]) * $fraction,
                $lower[2] + ($upper[2] - $lower[2]) * $fraction,
                $lower[3] + ($upper[3] - $lower[3]) * $fraction,
            ];
        } else {
            $lms = null;
        }
        $standard = 'who2007';
    }

    if (!$lms || (float)$lms[2] <= 0 || (float)$lms[3] <= 0) return ['error' => 'No WHO reference value exists for this age.'];
    $bmi = $weightKg / (($heightCm / 100) ** 2);
    $l = (float)$lms[1];
    $m = (float)$lms[2];
    $s = (float)$lms[3];
    $zScore = abs($l) < 1e-8 ? log($bmi / $m) / $s : (((($bmi / $m) ** $l) - 1) / ($s * $l));

    if ($zScore < -3) $classification = 'Severe Underweight';
    elseif ($zScore < -2) $classification = 'Underweight';
    elseif ($standard === 'who2006' && $zScore > 3) $classification = 'Obese';
    elseif ($standard === 'who2006' && $zScore > 2) $classification = 'Overweight';
    elseif ($standard === 'who2007' && $zScore > 2) $classification = 'Obese';
    elseif ($standard === 'who2007' && $zScore > 1) $classification = 'Overweight';
    else $classification = 'Normal Weight';

    $heightAssessment = calculatePediatricHeightForAge($heightCm, 'cm', $birthDate->format('Y-m-d'), $sex, $asOfDate->format('Y-m-d'));
    if (isset($heightAssessment['error'])) return $heightAssessment;

    return [
        'bmi' => round($bmi, 1),
        'bmi_zscore' => round($zScore, 3),
        'classification' => $classification,
        'height_zscore' => $heightAssessment['height_zscore'],
        'height_classification' => $heightAssessment['height_classification'],
        'age_months' => round($ageMonths, 1),
        'standard' => $standard,
    ];
}

function calculatePediatricHeightForAge($height, $heightUnit, $dateOfBirth, $gender, $measurementDate = null) {
    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$dateOfBirth);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (!$birthDate || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count'])) || $birthDate->format('Y-m-d') !== $dateOfBirth) {
        return ['error' => 'Enter a valid date of birth for height-for-age assessment.'];
    }

    $asOfDate = $measurementDate === null ? new DateTimeImmutable('today') : DateTimeImmutable::createFromFormat('!Y-m-d', (string)$measurementDate);
    if (!$asOfDate) return ['error' => 'The height measurement date is invalid.'];
    if ($birthDate > $asOfDate) return ['error' => 'Date of birth must be on or before the height measurement date.'];
    $age = $birthDate->diff($asOfDate);
    if ($age->y >= 19) return ['error' => 'WHO height-for-age references are available through age 18 years, 11 months.'];

    $ageMonths = (int)$age->days / 30.4375;
    if ($ageMonths < 24) {
        return ['height_zscore' => null, 'height_classification' => 'Not assessed', 'age_months' => round($ageMonths, 1)];
    }

    $heightCm = (float)$height;
    $heightCm *= match(strtolower((string)$heightUnit)) {
        'm' => 100,
        'inch' => 2.54,
        'feet' => 30.48,
        'cm' => 1,
        default => 0,
    };
    if ($heightCm <= 0) return ['error' => 'Enter a valid height for height-for-age assessment.'];

    $sex = strtolower((string)$gender);
    if (!in_array($sex, ['male', 'female'], true)) return ['error' => 'Select a valid sex for the WHO height-for-age reference.'];
    $reference = loadPediatricHeightReference();
    if (!$reference || empty($reference[$sex])) return ['error' => 'WHO height-for-age reference data could not be loaded.'];

    $referenceMonth = $ageMonths < 60 ? $ageMonths : min(228, $ageMonths + 1);
    $lowerMonth = (int)floor($referenceMonth);
    $upperMonth = (int)ceil($referenceMonth);
    $lower = $reference[$sex][$lowerMonth] ?? null;
    $upper = $reference[$sex][$upperMonth] ?? $lower;
    if (!$lower || !$upper) return ['error' => 'No WHO height-for-age reference value exists for this age.'];

    $fraction = $upperMonth === $lowerMonth ? 0 : ($referenceMonth - $lowerMonth) / ($upperMonth - $lowerMonth);
    $l = $lower[1] + ($upper[1] - $lower[1]) * $fraction;
    $m = $lower[2] + ($upper[2] - $lower[2]) * $fraction;
    $s = $lower[3] + ($upper[3] - $lower[3]) * $fraction;
    if ($m <= 0 || $s <= 0) return ['error' => 'WHO height-for-age reference data is invalid for this age.'];

    $zScore = abs($l) < 1e-8 ? log($heightCm / $m) / $s : (((($heightCm / $m) ** $l) - 1) / ($s * $l));
    if ($zScore < -3) $classification = 'Severely Stunted';
    elseif ($zScore < -2) $classification = 'Stunted';
    elseif ($zScore > 2) $classification = 'Tall';
    else $classification = 'Normal Height-for-Age';

    return [
        'height_zscore' => round($zScore, 3),
        'height_classification' => $classification,
        'age_months' => round($ageMonths, 1),
    ];
}

function loadPediatricHeightReference() {
    static $reference = null;
    if ($reference !== null) return $reference;

    $path = __DIR__ . '/who_hfa_reference.json';
    if (!is_file($path)) return null;
    $raw = file_get_contents($path);
    $data = $raw === false ? null : json_decode($raw, true);
    if (!is_array($data)) return null;

    foreach (['male', 'female'] as $sex) {
        if (empty($data[$sex]) || !is_array($data[$sex])) return null;
        $keyed = [];
        foreach ($data[$sex] as $row) {
            if (!is_array($row) || count($row) < 4) return null;
            $keyed[(int)$row[0]] = $row;
        }
        if (!isset($keyed[24], $keyed[228])) return null;
        $data[$sex] = $keyed;
    }
    $reference = $data;
    return $reference;
}

function getRecordHeightForAge($record) {
    if (!empty($record['height_classification'])) {
        return [
            'height_zscore' => $record['height_zscore'] ?? null,
            'height_classification' => $record['height_classification'],
        ];
    }
    if (empty($record['date_of_birth']) || empty($record['created_at'])) {
        return ['height_zscore' => null, 'height_classification' => 'Not assessed'];
    }

    return calculatePediatricHeightForAge(
        $record['height'] ?? 0,
        $record['height_unit'] ?? 'cm',
        $record['date_of_birth'],
        $record['gender'] ?? '',
        date('Y-m-d', strtotime($record['created_at']))
    );
}

function loadPediatricBmiReference() {
    static $reference = null;
    if ($reference !== null) return $reference;

    $path = __DIR__ . '/who_bmi_reference.json';
    if (!is_file($path)) return null;
    $raw = file_get_contents($path);
    $data = $raw === false ? null : json_decode($raw, true);
    if (!is_array($data)) return null;

    foreach (['who2006', 'who2007'] as $standard) {
        foreach (['male', 'female'] as $sex) {
            if (empty($data[$standard][$sex])) return null;
            $keyed = [];
            foreach ($data[$standard][$sex] as $row) $keyed[(int)$row[0]] = $row;
            $data[$standard][$sex] = $keyed;
        }
    }
    $reference = $data;
    return $reference;
}

function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// ── Audit Logging ───────────────────────────────────────────
function auditLog($action, $targetType = null, $targetId = null, $details = null) {
    try {
        require_once __DIR__ . '/config.php';
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) return;
        mysqli_report(MYSQLI_REPORT_OFF);
        $email = $_SESSION['user_id'] ?? 'system';
        $name  = $_SESSION['firstName'] ?? 'System';
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $stmt = $conn->prepare("INSERT INTO audit_log (user_email, user_name, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            error_log('Audit log failed: missing audit_log table or invalid SQL.');
            $conn->close();
            return;
        }

        $bind = $stmt->bind_param("ssssiss", $email, $name, $action, $targetType, $targetId, $details, $ip);
        if ($bind === false) {
            error_log('Audit log failed: could not bind parameters.');
            $stmt->close();
            $conn->close();
            return;
        }

        $stmt->execute();
        $stmt->close();
        $conn->close();
    } catch (Throwable $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}

// ── DB Helper ───────────────────────────────────────────────
function ensureDatabaseSchema() {
    static $schemaChecked = false;
    if ($schemaChecked) {
        return;
    }
    $schemaChecked = true;

    try {
        require_once __DIR__ . '/config.php';
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            return;
        }
        mysqli_report(MYSQLI_REPORT_OFF);

        $tables = [
            'accounts' => "CREATE TABLE IF NOT EXISTS accounts (
                id                INT AUTO_INCREMENT PRIMARY KEY,
                firstName         VARCHAR(50)  NOT NULL,
                lastName          VARCHAR(50)  NOT NULL,
                role              VARCHAR(30)  NOT NULL DEFAULT 'Employee',
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            'stdRecord' => "CREATE TABLE IF NOT EXISTS stdRecord (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            'feedingRecord' => "CREATE TABLE IF NOT EXISTS feedingRecord (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                student_id      INT NOT NULL,
                feed_date       DATE NOT NULL,
                feed_time       TIME NOT NULL DEFAULT '00:00:00',
                meal_type       VARCHAR(30) DEFAULT NULL,
                notes           TEXT DEFAULT NULL,
                recorded_by     VARCHAR(100) DEFAULT NULL,
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            'feedingSchedule' => "CREATE TABLE IF NOT EXISTS feedingSchedule (
                id               INT AUTO_INCREMENT PRIMARY KEY,
                schedule_date    DATE NOT NULL,
                meal_type        VARCHAR(30) NOT NULL,
                food_description TEXT NOT NULL,
                created_by       VARCHAR(100) DEFAULT NULL,
                created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_feeding_schedule (schedule_date, meal_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            'audit_log' => "CREATE TABLE IF NOT EXISTS audit_log (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                user_email  VARCHAR(100),
                user_name   VARCHAR(100),
                action      VARCHAR(50) NOT NULL,
                target_type VARCHAR(30),
                target_id   INT DEFAULT NULL,
                details     TEXT,
                ip_address  VARCHAR(45),
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            'notifications' => "CREATE TABLE IF NOT EXISTS notifications (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                user_email  VARCHAR(100),
                title       VARCHAR(100) NOT NULL,
                message     TEXT,
                type        VARCHAR(20) DEFAULT 'info',
                is_read     TINYINT(1) DEFAULT 0,
                link        VARCHAR(255) DEFAULT NULL,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            'data_corrections' => "CREATE TABLE IF NOT EXISTS data_corrections (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            'meal_plans' => "CREATE TABLE IF NOT EXISTS meal_plans (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                plan_date   DATE NOT NULL,
                meal_type   VARCHAR(30) NOT NULL,
                menu        TEXT NOT NULL,
                calories    INT DEFAULT NULL,
                protein_g   DECIMAL(5,1) DEFAULT NULL,
                notes       TEXT DEFAULT NULL,
                created_by  VARCHAR(100) DEFAULT NULL,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            'scheduled_reports' => "CREATE TABLE IF NOT EXISTS scheduled_reports (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                report_type     VARCHAR(50) NOT NULL,
                frequency       VARCHAR(20) NOT NULL,
                recipient_email VARCHAR(100) NOT NULL,
                last_sent       DATETIME DEFAULT NULL,
                is_active       TINYINT(1) DEFAULT 1,
                created_by      VARCHAR(100) DEFAULT NULL,
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        ];

        foreach ($tables as $table => $sql) {
            $conn->query($sql);
        }

        $requiredColumns = [
            'accounts' => [
                'Created' => "ALTER TABLE accounts ADD COLUMN Created DATE NULL DEFAULT NULL",
                'created_at' => "ALTER TABLE accounts ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
                'is_verified' => "ALTER TABLE accounts ADD COLUMN is_verified TINYINT(1) DEFAULT 0",
                'verification_code' => "ALTER TABLE accounts ADD COLUMN verification_code VARCHAR(6) DEFAULT NULL",
                'code_expires' => "ALTER TABLE accounts ADD COLUMN code_expires DATETIME DEFAULT NULL",
                'consent_agreed' => "ALTER TABLE accounts ADD COLUMN consent_agreed TINYINT(1) DEFAULT 0",
                'consent_date' => "ALTER TABLE accounts ADD COLUMN consent_date DATETIME DEFAULT NULL",
                'status' => "ALTER TABLE accounts ADD COLUMN status VARCHAR(20) DEFAULT 'pending'",
                'adviser_grade_level' => "ALTER TABLE accounts ADD COLUMN adviser_grade_level VARCHAR(20) DEFAULT NULL",
                'adviser_sections' => "ALTER TABLE accounts ADD COLUMN adviser_sections VARCHAR(255) DEFAULT NULL",
                'dark_mode' => "ALTER TABLE accounts ADD COLUMN dark_mode TINYINT(1) DEFAULT 0",
                'lang' => "ALTER TABLE accounts ADD COLUMN lang VARCHAR(5) DEFAULT 'en'"
            ],
            'stdRecord' => [
                'student_id' => "ALTER TABLE stdRecord ADD COLUMN student_id INT NULL",
                'grade_level' => "ALTER TABLE stdRecord ADD COLUMN grade_level VARCHAR(20) DEFAULT NULL",
                'section' => "ALTER TABLE stdRecord ADD COLUMN section VARCHAR(50) DEFAULT NULL",
                'school_year' => "ALTER TABLE stdRecord ADD COLUMN school_year VARCHAR(20) DEFAULT NULL",
                'date_of_birth' => "ALTER TABLE stdRecord ADD COLUMN date_of_birth DATE DEFAULT NULL",
                'photo' => "ALTER TABLE stdRecord ADD COLUMN photo VARCHAR(255) DEFAULT NULL",
                'bmi_zscore' => "ALTER TABLE stdRecord ADD COLUMN bmi_zscore DECIMAL(6,3) DEFAULT NULL",
                'height_zscore' => "ALTER TABLE stdRecord ADD COLUMN height_zscore DECIMAL(6,3) DEFAULT NULL",
                'height_classification' => "ALTER TABLE stdRecord ADD COLUMN height_classification VARCHAR(50) DEFAULT NULL"
            ],
            'feedingRecord' => [
                'feed_time' => "ALTER TABLE feedingRecord ADD COLUMN feed_time TIME NOT NULL DEFAULT '00:00:00'"
            ]
        ];

        foreach ($requiredColumns as $table => $columns) {
            $existing = $conn->query("SHOW COLUMNS FROM {$table}");
            $fields = [];
            if ($existing) {
                while ($row = $existing->fetch_assoc()) {
                    $fields[] = $row['Field'];
                }
            }

            foreach ($columns as $column => $sql) {
                if (!in_array($column, $fields, true)) {
                    $conn->query($sql);
                }
            }

            // Ensure accounts has both id and account_id
            if ($table === 'accounts') {
                if (!in_array('account_id', $fields, true) && in_array('id', $fields, true)) {
                    $conn->query("ALTER TABLE accounts ADD COLUMN account_id INT NULL");
                    $conn->query("UPDATE accounts SET account_id = id WHERE account_id IS NULL");
                } elseif (!in_array('id', $fields, true) && in_array('account_id', $fields, true)) {
                    $conn->query("ALTER TABLE accounts ADD COLUMN id INT NULL");
                    $conn->query("UPDATE accounts SET id = account_id WHERE id IS NULL");
                }
            }

            // Ensure stdRecord has both student_id and id
            if ($table === 'stdRecord') {
                if (!in_array('id', $fields, true) && in_array('student_id', $fields, true)) {
                    $conn->query("ALTER TABLE stdRecord ADD COLUMN id INT NULL");
                    $conn->query("UPDATE stdRecord SET id = student_id WHERE id IS NULL");
                } elseif (!in_array('student_id', $fields, true) && in_array('id', $fields, true)) {
                    $conn->query("ALTER TABLE stdRecord ADD COLUMN student_id INT NULL");
                    $conn->query("UPDATE stdRecord SET student_id = id WHERE student_id IS NULL");
                }
            }
        }

        $idColumn = $conn->query("SHOW COLUMNS FROM stdRecord LIKE 'id'");
        $studentIdColumn = $conn->query("SHOW COLUMNS FROM stdRecord LIKE 'student_id'");
        $idDefinition = $idColumn ? $idColumn->fetch_assoc() : null;
        $studentIdDefinition = $studentIdColumn ? $studentIdColumn->fetch_assoc() : null;
        if ($idDefinition && $studentIdDefinition
            && stripos($idDefinition['Extra'], 'generated') === false
            && stripos($studentIdDefinition['Extra'], 'generated') === false) {
            if (stripos($idDefinition['Extra'], 'auto_increment') !== false) {
                if (!$conn->query("UPDATE stdRecord SET student_id = id WHERE student_id IS NULL")) {
                    throw new RuntimeException('Could not backfill stdRecord.student_id: ' . $conn->error);
                }
            } elseif (stripos($studentIdDefinition['Extra'], 'auto_increment') !== false) {
                if (!$conn->query("UPDATE stdRecord SET id = student_id WHERE id IS NULL")) {
                    throw new RuntimeException('Could not backfill stdRecord.id: ' . $conn->error);
                }
            } elseif (!$conn->query("UPDATE stdRecord SET id = COALESCE(id, student_id), student_id = COALESCE(student_id, id) WHERE id IS NULL OR student_id IS NULL")) {
                throw new RuntimeException('Could not backfill stdRecord identifiers: ' . $conn->error);
            }
        }

        // Check columns before conditional updates
        $stdCols = [];
        $resCols = $conn->query("SHOW COLUMNS FROM stdRecord");
        if ($resCols) {
            while ($r = $resCols->fetch_assoc()) {
                $stdCols[] = $r['Field'];
            }
        }
        if (in_array('grade_level', $stdCols, true)) {
            $conn->query("UPDATE stdRecord SET grade_level = 'N/A' WHERE grade_level IS NULL");
        }
        if (in_array('section', $stdCols, true)) {
            $conn->query("UPDATE stdRecord SET section = 'N/A' WHERE section IS NULL");
        }
        if (in_array('school_year', $stdCols, true) && in_array('created_at', $stdCols, true)) {
            $conn->query("UPDATE stdRecord SET school_year = YEAR(created_at) WHERE school_year IS NULL OR school_year = ''");
        }
        $bmiColumn = $conn->query("SHOW COLUMNS FROM stdRecord LIKE 'bmi'");
        $bmiDefinition = $bmiColumn ? $bmiColumn->fetch_assoc() : null;
        if ($bmiDefinition && stripos($bmiDefinition['Type'], 'decimal') !== 0 && stripos($bmiDefinition['Type'], 'float') !== 0 && stripos($bmiDefinition['Type'], 'double') !== 0) {
            $conn->query("ALTER TABLE stdRecord MODIFY bmi DECIMAL(5,2) NOT NULL");
        }

        $countResult = $conn->query("SELECT COUNT(*) as c FROM accounts");
        $count = $countResult ? (int) ($countResult->fetch_assoc()['c'] ?? 0) : 0;
        if ($count === 0 && strlen(INITIAL_ADMIN_PASSWORD) >= 16) {
            $initialPasswordHash = password_hash(INITIAL_ADMIN_PASSWORD, PASSWORD_DEFAULT);
            $initialAdmin = $conn->prepare("INSERT INTO accounts (firstName, lastName, role, email, password, is_verified, status) VALUES ('System', 'Admin', 'Super Admin', 'admin@nutriph.com', ?, 1, 'active')");
            if ($initialAdmin) {
                $initialAdmin->bind_param('s', $initialPasswordHash);
                $initialAdmin->execute();
                $initialAdmin->close();
            } else {
                error_log('Initial admin account creation failed: could not prepare the insert.');
            }
        }

        $conn->close();
    } catch (Throwable $e) {
        error_log('Database schema repair failed: ' . $e->getMessage());
    }
}

function syncInsertedStudentRecordIdentifier($conn, $recordId) {
    $recordId = (int)$recordId;
    if ($recordId <= 0) {
        error_log('Student record identifier synchronization failed: invalid inserted ID.');
        return false;
    }

    $idResult = $conn->query("SHOW COLUMNS FROM stdRecord LIKE 'id'");
    $studentIdResult = $conn->query("SHOW COLUMNS FROM stdRecord LIKE 'student_id'");
    $idColumn = $idResult ? $idResult->fetch_assoc() : null;
    $studentIdColumn = $studentIdResult ? $studentIdResult->fetch_assoc() : null;
    if (!$idColumn || !$studentIdColumn) {
        error_log('Student record identifier synchronization failed: required ID columns are missing.');
        return false;
    }

    $idExtra = strtolower($idColumn['Extra'] ?? '');
    $studentIdExtra = strtolower($studentIdColumn['Extra'] ?? '');
    if (strpos($idExtra, 'generated') !== false || strpos($studentIdExtra, 'generated') !== false) {
        return true;
    }

    if (strpos($idExtra, 'auto_increment') !== false) {
        $stmt = $conn->prepare('UPDATE stdRecord SET student_id = id WHERE id = ?');
        if ($stmt) $stmt->bind_param('i', $recordId);
    } elseif (strpos($studentIdExtra, 'auto_increment') !== false) {
        $stmt = $conn->prepare('UPDATE stdRecord SET id = student_id WHERE student_id = ?');
        if ($stmt) $stmt->bind_param('i', $recordId);
    } else {
        $stmt = $conn->prepare('UPDATE stdRecord SET id = COALESCE(id, ?), student_id = COALESCE(student_id, ?) WHERE id = ? OR student_id = ?');
        if ($stmt) $stmt->bind_param('iiii', $recordId, $recordId, $recordId, $recordId);
    }

    if (!$stmt || !$stmt->execute()) {
        error_log('Student record identifier synchronization failed: ' . ($stmt ? $stmt->error : $conn->error));
        if ($stmt) $stmt->close();
        return false;
    }
    $stmt->close();

    $verify = $conn->prepare('SELECT id, student_id FROM stdRecord WHERE id = ? OR student_id = ? LIMIT 1');
    if (!$verify) {
        error_log('Student record identifier verification failed: ' . $conn->error);
        return false;
    }
    $verify->bind_param('ii', $recordId, $recordId);
    if (!$verify->execute()) {
        error_log('Student record identifier verification failed: ' . $verify->error);
        $verify->close();
        return false;
    }
    $row = $verify->get_result()->fetch_assoc();
    $verify->close();
    if (!$row || (int)$row['id'] !== $recordId || (int)$row['student_id'] !== $recordId) {
        error_log('Student record identifier verification failed: ID aliases do not match.');
        return false;
    }
    return true;
}

function getDB() {
    require_once __DIR__ . '/config.php';
    ensureDatabaseSchema();
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log("DB connection failed: " . $conn->connect_error);
        http_response_code(503);
        die("Service temporarily unavailable. Please try again later.");
    }
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn->set_charset('utf8mb4');
    return $conn;
}

// ── Name Normalization ───────────────────────────────────────
function normalizeNameCase($value) {
    $value = trim((string) $value);
    if ($value === '') return '';
    $value = preg_replace('/\s+/', ' ', $value);
    return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
}

function normalizeInitialCase($value) {
    $value = trim((string) $value);
    if ($value === '') return '';
    $value = preg_replace('/\s+/', '', $value);
    return strtoupper(mb_substr($value, 0, 2, 'UTF-8'));
}

function formatStudentFullName($first, $mid = '', $last = '') {
    $parts = [
        normalizeNameCase($first),
        normalizeInitialCase($mid),
        normalizeNameCase($last)
    ];
    $parts = array_filter($parts, fn($part) => $part !== '');
    return implode(' ', $parts);
}
?>
