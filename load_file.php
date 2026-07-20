<?php
// Загрузка содержимого страницы из PostgreSQL с поддержкой таблиц (ParsedownExtra)
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', __DIR__);
define('LOG_FILE', '/tmp/wiki_debug.log');

function writeLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents(LOG_FILE, "[$timestamp] load_file: $message\n", FILE_APPEND);
}

writeLog("=== START load_file.php ===");

// Проверка входного параметра
if (!isset($_GET['file']) || !preg_match('/^[a-zA-Z0-9_\-\.]+\.md$/', $_GET['file'])) {
    writeLog("Invalid file parameter: " . ($_GET['file'] ?? 'not set'));
    http_response_code(400);
    die('Некорректное имя файла');
}

$filename = $_GET['file'];
writeLog("Requested file: $filename");

// Подключение к БД
$dbHost = getenv('DB_HOST') ?: 'db';
$dbName = getenv('DB_NAME') ?: 'wiki';
$dbUser = getenv('DB_USER') ?: 'wiki_user';
$dbPass = getenv('DB_PASSWORD') ?: 'secure_password_change_me';

try {
    $pdo = new PDO("pgsql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    writeLog("DB connected successfully");
} catch (PDOException $e) {
    writeLog("DB connection error: " . $e->getMessage());
    http_response_code(500);
    die('Ошибка подключения к БД');
}

// Подготовка и выполнение запроса
$stmt = $pdo->prepare("SELECT content FROM pages WHERE filename = ?");
$stmt->execute([$filename]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    writeLog("Page not found: $filename");
    http_response_code(404);
    die('Страница не найдена');
}

$content = $row['content'];
writeLog("Content length: " . strlen($content) . " bytes");

// Загрузка парсеров Markdown
$parsedownPath = ROOT_PATH . '/Parsedown.php';
$parsedownExtraPath = ROOT_PATH . '/ParsedownExtra.php';

if (!file_exists($parsedownPath)) {
    writeLog("ERROR: Parsedown.php not found at $parsedownPath");
    http_response_code(500);
    die('Ошибка: библиотека Parsedown не найдена');
}
if (!file_exists($parsedownExtraPath)) {
    writeLog("ERROR: ParsedownExtra.php not found at $parsedownExtraPath");
    http_response_code(500);
    die('Ошибка: библиотека ParsedownExtra не найдена');
}

require_once $parsedownPath;
require_once $parsedownExtraPath;

try {
    $Parsedown = new ParsedownExtra();
    writeLog("ParsedownExtra initialized successfully");
} catch (Exception $e) {
    writeLog("Error creating ParsedownExtra: " . $e->getMessage());
    http_response_code(500);
    die('Ошибка инициализации парсера Markdown');
}

// Парсим Markdown в HTML
$html = $Parsedown->text($content);
writeLog("Parsed HTML length: " . strlen($html) . " bytes");

// Отправка результата
header('Content-Type: text/html; charset=utf-8');
echo $html;
writeLog("=== END load_file.php ===");
?>
