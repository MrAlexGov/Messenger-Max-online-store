<?php
/**
 * Основной файл бота для тестирования через long polling
 */

require_once 'config.php';
require_once 'MaxBotAPI.php';
require_once 'Database.php';
require_once 'functions.php';

echo "🚀 Запуск бота...\n";

try {
    $shop_bot = new ShopBot();

    // Проверяем подключение к базе данных
    $shop_bot->db->connect();
    echo "✅ Подключение к базе данных установлено\n";

    // Проверяем токен бота
    $me = $shop_bot->bot_api->getMe();
    echo "🤖 Бот активен: {$me['first_name']} (@{$me['username']})\n";

    echo "📡 Ожидание обновлений...\n";
    echo "Нажмите Ctrl+C для остановки\n\n";

    // Основной цикл получения обновлений
    while (true) {
        try {
            $updates = $shop_bot->getUpdates();

            if ($updates && isset($updates['marker'])) {
                // Можно сохранить marker для продолжения с нужного места
                // file_put_contents('last_marker.txt', $updates['marker']);
            }

            // Пауза между запросами
            sleep(1);

        } catch (Exception $e) {
            echo "⚠️ Ошибка получения обновлений: " . $e->getMessage() . "\n";
            sleep(5); // Ждем 5 секунд перед следующей попыткой
        }
    }

} catch (Exception $e) {
    echo "❌ Критическая ошибка: " . $e->getMessage() . "\n";
    exit(1);
}