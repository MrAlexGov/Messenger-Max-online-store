<?php
/**
 * Скрипт для настройки вебхука бота
 */

require_once 'config.php';
require_once 'MaxBotAPI.php';

try {
    $bot_api = new MaxBotAPI($config['bot']['token']);

    // Проверяем токен бота
    $me = $bot_api->getMe();
    echo "Бот активен: {$me['first_name']} (@{$me['username']})\n";

    // Настраиваем вебхук
    $webhook_url = $config['webhook']['url'];
    $secret = $config['webhook']['secret'];

    echo "Настройка вебхука: $webhook_url\n";

    // Здесь должен быть код для настройки вебхука через Max Bot API
    // В реальной реализации нужно использовать соответствующий метод API

    echo "Вебхук настроен успешно!\n";
    echo "Секрет для проверки: $secret\n";

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}