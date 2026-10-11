<?php declare(strict_types=1);

/**
 * Создание / обновление администратора.
 * Использование:
 *   php tools/create_admin.php
 *   php tools/create_admin.php email@site.ru Пароль123 username "Имя Фамилия"
 *
 * bcrypt-хеш нельзя вписать в SQL-дамп, поэтому админ создаётся этим скриптом.
 * PHP 7.4.
 */

if (PHP_SAPI !== 'cli') {
    die("Скрипт запускается только из командной строки.\n");
}

$config = require __DIR__ . '/../app/Config/config.php';

$email    = isset($argv[1]) ? trim((string)$argv[1]) : 'admin@example.com';
$password = isset($argv[2]) ? (string)$argv[2] : 'admin123';
$username = isset($argv[3]) ? trim((string)$argv[3]) : 'admin';
$fullname = isset($argv[4]) ? trim((string)$argv[4]) : 'Администратор';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Некорректный email: {$email}\n");
    exit(1);
}
if (mb_strlen($password) < 6) {
    fwrite(STDERR, "Пароль должен быть не короче 6 символов.\n");
    exit(1);
}
if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
    fwrite(STDERR, "Username: только латиница, цифры и _, 3-50 символов.\n");
    exit(1);
}

$db = $config['db'];
$dsn = 'mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['name'] . ';charset=' . $db['charset'];

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Ошибка подключения к БД: " . $e->getMessage() . "\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

// Ищем пользователя по email или username
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
$stmt->execute([$email, $username]);
$existing = $stmt->fetch();

try {
    $pdo->beginTransaction();

    if ($existing) {
        $id = (int)$existing['id'];
        $upd = $pdo->prepare(
            'UPDATE users SET role = ?, password_hash = ?, status = ?, email = ?, username = ? WHERE id = ?'
        );
        $upd->execute(['admin', $hash, 'active', $email, $username, $id]);
        $action = 'обновлён';
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO users (role, email, password_hash, username, status, balance, rating) VALUES (?, ?, ?, ?, ?, 0.00, 0.00)'
        );
        $ins->execute(['admin', $email, $hash, $username, 'active']);
        $id = (int)$pdo->lastInsertId();
        $action = 'создан';
    }

    // Профиль
    $parts = preg_split('/\s+/', $fullname, 2);
    $firstName = isset($parts[0]) ? $parts[0] : '';
    $lastName  = isset($parts[1]) ? $parts[1] : '';

    $profile = $pdo->prepare(
        'INSERT INTO user_profiles (user_id, first_name, last_name)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name)'
    );
    $profile->execute([$id, $firstName, $lastName]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Ошибка: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Администратор {$action}.\n";
echo "  ID:       {$id}\n";
echo "  Email:    {$email}\n";
echo "  Username: {$username}\n";
echo "  Пароль:   {$password}\n";
echo "  Панель:   /admin\n";
