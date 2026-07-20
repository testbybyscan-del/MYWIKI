<?php
// webhook.php — обновляет БД при push в репозиторий
$secret = 'your_secret_token'; // установите свой секрет

// Проверка подписи (для GitHub)
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
if ($signature) {
    $hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    if (!hash_equals($hash, $signature)) {
        http_response_code(403);
        die('Invalid signature');
    }
}

// Выполняем git pull и импорт
$output = [];
$return = 0;
chdir(__DIR__);
exec('git pull origin main 2>&1', $output, $return);
if ($return !== 0) {
    http_response_code(500);
    die("Git pull failed: " . implode("\n", $output));
}

// Запускаем инкрементальный импорт (только новые/изменённые файлы)
exec('php import.php --incremental 2>&1', $output, $return);

echo "OK\n";
