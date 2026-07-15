#!/usr/bin/env php
<?php

/**
 * @Bybyscan
 * Санитайзер для .md-файлов:
 * - заменяет публичные IPv4 (кроме локальных) на X.X.X.X
 * - принудительно заменяет три указанных IP даже внутри URL
 * - заменяет порты >12000 на <ВАШ ПОРТ>
 *
 * Использование:
 *   php sanitizer.php [директория]
 *   укажите ВАШИ FORCED_IPS !!!
 * Если директория не указана, используется константа PAGES_DIR.
 */

const PAGES_DIR = 'D:\PHP\wiki\pages';   // путь по умолчанию
const FORCED_IPS = ['1.2.3.4', '5.6.7.8', '9.10.11.12'];
const PORT_PLACEHOLDER = '<ВАШ ПОРТ>';
const IP_PLACEHOLDER = 'X.X.X.X';

// Регулярные выражения
const IP_REGEX = '/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/';
const URL_REGEX = '/https?:\/\/[^\s<>"\'()]+/';
const PORT_REGEX = '/(?<=:)(\d{1,5})/';

/**
 * Проверяет, является ли IP локальным (частный, loopback, link-local, multicast и т.п.)
 */
function is_local_ip(string $ip): bool
{
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }

    // проверка на частные и зарезервированные диапазоны через filter_var с флагами
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return true;
    }

    // дополнительно проверяем link-local (169.254.0.0/16) и multicast (224.0.0.0/4)
    $long = ip2long($ip);
    if ($long === false) {
        return false;
    }

    // link-local: 169.254.0.0/16
    if (($long & 0xFFFF0000) === 0xA9FE0000) {
        return true;
    }

    // multicast: 224.0.0.0/4
    if (($long & 0xF0000000) === 0xE0000000) {
        return true;
    }

    return false;
}

/**
 * Находит все URL в тексте и возвращает массив диапазонов [start, end]
 */
function find_url_ranges(string $text): array
{
    $ranges = [];
    preg_match_all(URL_REGEX, $text, $matches, PREG_OFFSET_CAPTURE);
    foreach ($matches[0] as $match) {
        $ranges[] = [$match[1], $match[1] + strlen($match[0])];
    }
    return $ranges;
}

/**
 * Проверяет, находится ли позиция внутри хотя бы одного URL
 */
function is_position_in_url(int $pos, array $ranges): bool
{
    foreach ($ranges as [$start, $end]) {
        if ($start <= $pos && $pos < $end) {
            return true;
        }
    }
    return false;
}

/**
 * Обрабатывает один файл: заменяет IP и порты, сохраняет изменения
 */
function process_file(string $filepath): void
{
    $content = file_get_contents($filepath);
    if ($content === false) {
        fprintf(STDERR, "Не удалось прочитать файл: %s\n", $filepath);
        return;
    }

    // Находим все URL-диапазоны (для защиты IP внутри ссылок)
    $urlRanges = find_url_ranges($content);

    // Список замен: [start, end, replacement]
    $replacements = [];

    // 1. Поиск IP-адресов
    preg_match_all(IP_REGEX, $content, $ipMatches, PREG_OFFSET_CAPTURE);
    foreach ($ipMatches[0] as $match) {
        $ip = $match[0];
        $start = $match[1];
        $end = $start + strlen($ip);

        // Пропускаем локальные адреса
        if (is_local_ip($ip)) {
            continue;
        }

        // Принудительная замена для указанных IP
        if (in_array($ip, FORCED_IPS, true)) {
            $replacements[] = [$start, $end, IP_PLACEHOLDER];
            continue;
        }

        // Если IP не внутри URL — заменяем
        if (!is_position_in_url($start, $urlRanges)) {
            $replacements[] = [$start, $end, IP_PLACEHOLDER];
        }
    }

    // 2. Поиск портов (чисел после двоеточия, >12000 и <=65535)
    preg_match_all(PORT_REGEX, $content, $portMatches, PREG_OFFSET_CAPTURE);
    foreach ($portMatches[1] as $match) {
        $portStr = $match[0];
        $start = $match[1];
        $end = $start + strlen($portStr);

        $port = (int)$portStr;
        if ($port > 12000 && $port <= 65535) {
            $replacements[] = [$start, $end, PORT_PLACEHOLDER];
        }
    }

    if (empty($replacements)) {
        return;
    }

    // Сортируем замены по убыванию start (чтобы не сбивать индексы)
    usort($replacements, function ($a, $b) {
        return $b[0] <=> $a[0];
    });

    // Применяем замены
    foreach ($replacements as [$start, $end, $repl]) {
        $content = substr_replace($content, $repl, $start, $end - $start);
    }

    // Сохраняем файл
    file_put_contents($filepath, $content);

    printf("Обработан файл: %s (заменено %d фрагментов)\n", $filepath, count($replacements));
}

/**
 * Рекурсивный обход директории и сбор всех .md-файлов
 */
function collect_md_files(string $dir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

// ----------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------

// Определяем директорию (аргумент командной строки или константа)
$pagesDir = $argv[1] ?? PAGES_DIR;

if (!is_dir($pagesDir)) {
    fprintf(STDERR, "Директория %s не найдена.\n", $pagesDir);
    exit(1);
}

$mdFiles = collect_md_files($pagesDir);
if (empty($mdFiles)) {
    echo "Файлы .md не найдены.\n";
    exit(0);
}

printf("Найдено %d .md-файлов.\n", count($mdFiles));
foreach ($mdFiles as $file) {
    try {
        process_file($file);
    } catch (Exception $e) {
        fprintf(STDERR, "Ошибка при обработке %s: %s\n", $file, $e->getMessage());
    }
}