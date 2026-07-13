<?php
//Версия 1.0 - продакшен-решение на PostgreSQL
error_reporting(0); // в продакшене ошибки не показываем
ini_set('display_errors', 0);

define('ROOT_PATH', __DIR__);
// Лог пишем в /tmp, чтобы не было проблем с правами
define('LOG_FILE', '/tmp/wiki_debug.log');

function writeLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents(LOG_FILE, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog("=== START INDEX.PHP EXECUTION ===");

// Подключение к БД
$dbHost = getenv('DB_HOST') ?: 'db';
$dbName = getenv('DB_NAME') ?: 'wiki';
$dbUser = getenv('DB_USER') ?: 'wiki_user';
$dbPass = getenv('DB_PASSWORD') ?: 'secure_password_change_me';

try {
    $pdo = new PDO("pgsql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    writeLog("Database connected successfully");
} catch (PDOException $e) {
    writeLog("DB connection error: " . $e->getMessage());
    // В продакшене показываем общее сообщение без деталей
    die("Ошибка подключения к базе данных. Обратитесь к администратору.");
}

// Получение списка страниц для меню
$menuStmt = $pdo->query("
    SELECT filename, title, num
    FROM pages
    ORDER BY NULLIF(regexp_replace(num, '^0+', ''), '')::integer NULLS LAST, filename
");
$menuItems = $menuStmt->fetchAll(PDO::FETCH_ASSOC);

// Обработка AJAX-поиска
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search') {
    // Отключаем все возможные выводы, кроме JSON
    error_reporting(0);
    ini_set('display_errors', 0);
    if (ob_get_level()) ob_end_clean();
    ob_start();

    $query = trim($_GET['q'] ?? '');
    $results = [];
    if (strlen($query) >= 2) {
        $searchStmt = $pdo->prepare("
            SELECT filename, title, num, lang, topic,
                   ts_rank(search_vector, plainto_tsquery('russian', ?)) AS relevance
            FROM pages
            WHERE search_vector @@ plainto_tsquery('russian', ?)
            ORDER BY relevance DESC, NULLIF(regexp_replace(num, '^0+', ''), '')::integer
            LIMIT 50
        ");
        $searchStmt->execute([$query, $query]);
        $results = $searchStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    header('Content-Type: application/json');
    echo json_encode($results);
    ob_end_flush();
    exit;
}

// Отладочная информация доступна только по параметру debug
if (isset($_GET['debug']) && $_GET['debug'] === 'true') {
    $countStmt = $pdo->query("SELECT COUNT(*) FROM pages");
    $totalPages = $countStmt->fetchColumn();
    echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc; font-family: monospace;'>";
    echo "<h3>Debug Information</h3>";
    echo "<strong>Total pages in DB:</strong> " . $totalPages . "<br>";
    echo "<strong>PHP version:</strong> " . phpversion() . "<br>";
    echo "</div>";
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library v2.0 @bybyscan</title>
    <link rel="stylesheet" href="styles.css?<?= filemtime(__DIR__.'/styles.css') ?>">
    <script src="script.js" defer></script>
</head>
<body>
<header>
    <h1>
        Library v2.0
        <a href="https://github.com/testbybyscan-del" target="_blank" class="github-link">GitHub</a>
        <span class="version">@bybyscan 04.06.2025</span>
    </h1>
    <div class="search-container">
        <input type="text" id="searchInput" placeholder="По номеру страницы, языку кода или теме...">
        <button onclick="performSearch()">Поиск</button>
        <div id="searchResults"></div>
    </div>
</header>
<div class="main-layout">
    <aside class="navigation-panel">
        <h2>Оглавление</h2>
        <div class="navigation-list">
            <?php if (empty($menuItems)): ?>
                <p style="color: #999; padding: 10px;">Нет доступных страниц. Импортируйте данные через import.php</p>
            <?php else: ?>
                <?php foreach ($menuItems as $item): ?>
                    <a href="javascript:void(0)" onclick="openFile('<?= htmlspecialchars($item['filename']) ?>')"
                       class="nav-link" data-file="<?= htmlspecialchars($item['filename']) ?>">
                        <?= htmlspecialchars($item['title']) ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>
    <main class="content-panel">
        <div class="content-wrapper" id="mainContent">
            <div class="welcome-message">
                <h3>Wiki-библиотека @bybyscan</h3>
                <p>Выберите страницу из списка слева или воспользуйтесь поиском</p>
                <div class="features">
                    <h4>Особенности реализации:</h4>
                    <ul>
                        <li>Поиск по номеру, языку и теме</li>
                        <li>Подсветка результатов</li>
                        <li>История просмотров</li>
                        <li>Копирование кода</li>
                        <li>Адаптивный дизайн</li>
                        <li>Работа на PostgreSQL</li>
                    </ul>
                </div>
                <p style="margin-top: 20px;"><a href="?debug=true">Показать отладку</a></p>
            </div>
        </div>
    </main>
</div>
</body>
</html>
