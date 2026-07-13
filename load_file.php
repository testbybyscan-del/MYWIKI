<?php
// Загрузка содержимого страницы из PostgreSQL
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', __DIR__);

// Проверка входного параметра
if (!isset($_GET['file']) || !preg_match('/^[a-zA-Z0-9_\-\.]+\.md$/', $_GET['file'])) {
    http_response_code(400);
    die('Некорректное имя файла');
}

$filename = $_GET['file'];

// Подключение к БД
$dbHost = getenv('DB_HOST') ?: 'db';
$dbName = getenv('DB_NAME') ?: 'wiki';
$dbUser = getenv('DB_USER') ?: 'wiki_user';
$dbPass = getenv('DB_PASSWORD') ?: 'secure_password_change_me';

try {
    $pdo = new PDO("pgsql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die('Ошибка подключения к БД');
}

$stmt = $pdo->prepare("SELECT content FROM pages WHERE filename = ?");
$stmt->execute([$filename]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    die('Страница не найдена');
}

// Парсим Markdown
require_once ROOT_PATH . '/Parsedown.php';
$Parsedown = new Parsedown();
header('Content-Type: text/html; charset=utf-8');
echo $Parsedown->text($row['content']);
