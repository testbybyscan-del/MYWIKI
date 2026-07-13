#!/usr/bin/env php
<?php
// Скрипт импорта MD-файлов в PostgreSQL
// Использование: php import.php [--force] [--incremental]

// В продакшене ошибки не показываем, но логируем
error_reporting(0);
ini_set('display_errors', 0);

define('ROOT_PATH', __DIR__);
define('LOG_FILE', '/tmp/wiki_debug.log');

function writeLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents(LOG_FILE, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog("=== START IMPORT.PHP EXECUTION ===");

// Разбор аргументов командной строки
$options = getopt('', ['force', 'incremental']);
$force = isset($options['force']);
$incremental = isset($options['incremental']);

// Подключение к БД
$dbHost = getenv('DB_HOST') ?: 'db';
$dbName = getenv('DB_NAME') ?: 'wiki';
$dbUser = getenv('DB_USER') ?: 'wiki_user';
$dbPass = getenv('DB_PASSWORD') ?: 'secure_password_change_me';

try {
    $pdo = new PDO("pgsql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Подключено к БД\n";
    writeLog("Database connected");
} catch (PDOException $e) {
    $msg = "Ошибка подключения: " . $e->getMessage();
    echo $msg . "\n";
    writeLog($msg);
    exit(1);
}

// Проверяем, существует ли таблица pages
try {
    $pdo->query("SELECT 1 FROM pages LIMIT 1");
} catch (PDOException $e) {
    $msg = "Таблица pages не существует. Выполните init-db.sql.";
    echo $msg . "\n";
    writeLog($msg);
    exit(1);
}

// Проверяем, есть ли уже данные
$count = $pdo->query("SELECT COUNT(*) FROM pages")->fetchColumn();
if ($count > 0) {
    if ($force) {
        echo "Удаление всех существующих записей (--force)...\n";
        $pdo->exec("TRUNCATE TABLE pages RESTART IDENTITY");
        writeLog("Table truncated");
    } elseif ($incremental) {
        echo "Инкрементальный режим: будут добавлены только новые файлы.\n";
    } else {
        echo "В таблице уже есть $count записей. Используйте --force для перезаписи или --incremental для добавления.\n";
        exit(0);
    }
}

$directory = ROOT_PATH . '/pages';
if (!is_dir($directory)) {
    $msg = "Папка pages не найдена.";
    echo $msg . "\n";
    writeLog($msg);
    exit(1);
}

$files = glob($directory . '/*.md');
if (empty($files)) {
    echo "Нет .md файлов в папке pages.\n";
    exit(0);
}

echo "Найдено файлов: " . count($files) . "\n";
writeLog("Found " . count($files) . " .md files");

// Подготовка запросов
$insertStmt = $pdo->prepare("
    INSERT INTO pages (filename, title, num, lang, topic, content)
    VALUES (?, ?, ?, ?, ?, ?)
");
$updateStmt = $pdo->prepare("
    UPDATE pages SET title = ?, num = ?, lang = ?, topic = ?, content = ?
    WHERE filename = ?
");
$checkStmt = $pdo->prepare("SELECT 1 FROM pages WHERE filename = ?");

$success = 0;
$skipped = 0;
$errors = 0;

// Начинаем транзакцию для ускорения
$pdo->beginTransaction();

foreach ($files as $filePath) {
    $filename = basename($filePath);
    echo "Обработка $filename ... ";

    $content = @file_get_contents($filePath);
    if ($content === false) {
        echo "ОШИБКА чтения\n";
        writeLog("ERROR: Cannot read $filename");
        $errors++;
        continue;
    }

    // Извлекаем заголовок (первая строка)
    $lines = explode("\n", $content);
    $title = isset($lines[0]) ? trim($lines[0]) : $filename;
    $title = ltrim($title, '# ');

    // Парсим номер, язык, тему из имени файла
    $base = str_replace('.md', '', $filename);
    $parts = explode('._', $base);
    $num = isset($parts[0]) ? $parts[0] : '';
    $lang = isset($parts[1]) ? $parts[1] : '';
    $topic = isset($parts[2]) ? implode('._', array_slice($parts, 2)) : '';

    try {
        if ($incremental) {
            // Проверяем, существует ли файл
            $checkStmt->execute([$filename]);
            if ($checkStmt->fetchColumn()) {
                // Обновляем
                $updateStmt->execute([$title, $num, $lang, $topic, $content, $filename]);
                echo "ОБНОВЛЕНО\n";
                $success++;
                continue;
            }
        }
        // Вставка
        $insertStmt->execute([$filename, $title, $num, $lang, $topic, $content]);
        echo "OK (ID: " . $pdo->lastInsertId() . ")\n";
        $success++;
    } catch (PDOException $e) {
        // Если дубликат, можно обновить, но для простоты пропускаем
        if ($e->getCode() == 23505) { // unique violation
            echo "ПРОПУЩЕН (дубликат)\n";
            $skipped++;
        } else {
            echo "ОШИБКА: " . $e->getMessage() . "\n";
            writeLog("ERROR inserting $filename: " . $e->getMessage());
            $errors++;
        }
    }
}

// Фиксируем транзакцию
$pdo->commit();

echo "\nИмпорт завершён.\n";
echo "Успешно: $success\n";
echo "Пропущено (дубликаты): $skipped\n";
echo "Ошибок: $errors\n";
writeLog("Import finished. Success: $success, Skipped: $skipped, Errors: $errors");
