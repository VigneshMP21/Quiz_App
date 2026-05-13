<?php
$host = 'localhost';
$dbname = 'quiz_app';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS certificates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            attempt_id INT NOT NULL UNIQUE,
            certificate_path VARCHAR(255) NOT NULL,
            downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (attempt_id) REFERENCES user_attempts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $ensureColumn = static function (PDO $pdo, string $table, string $column, string $definition): void {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    };

    $ensureColumn($pdo, 'users', 'address', 'TEXT NULL AFTER email');
    $ensureColumn($pdo, 'users', 'phone', 'VARCHAR(20) NULL AFTER address');
    $ensureColumn($pdo, 'users', 'profile_image', 'VARCHAR(255) NULL AFTER phone');
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
