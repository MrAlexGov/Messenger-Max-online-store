<?php
/**
 * Вебхук для получения обновлений от Max Bot API
 */

require_once 'config.php';
require_once 'MaxBotAPI.php';
require_once 'Database.php';
require_once 'functions.php';

// Проверяем, что запрос пришел от Max
$secret_header = $_SERVER['HTTP_X_MAX_BOT_API_SECRET'] ?? '';
if ($secret_header !== $config['webhook']['secret']) {
    http_response_code(401);
    die('Unauthorized');
}

// Читаем JSON данные
$input = file_get_contents('php://input');
if (!$input) {
    http_response_code(400);
    die('No data');
}

$data = json_decode($input, true);
if (!$data) {
    http_response_code(400);
    die('Invalid JSON');
}

// Логируем входящие обновления
file_put_contents('webhook.log', date('Y-m-d H:i:s') . ' ' . $input . PHP_EOL, FILE_APPEND);

// Обрабатываем обновления
try {
    $shop_bot = new ShopBot();

    if (isset($data['updates']) && is_array($data['updates'])) {
        foreach ($data['updates'] as $update) {
            $shop_bot->handleUpdate($update);
        }
    } else {
        // Обрабатываем одиночное обновление
        $shop_bot->handleUpdate($data);
    }

    http_response_code(200);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Ошибка обработки вебхука: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}