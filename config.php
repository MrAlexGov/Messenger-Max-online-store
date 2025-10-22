<?php
// Конфигурация бота для магазина Макс

// Основные настройки бота
$config = [
    'bot' => [
        'token' => 'YOUR_BOT_TOKEN', // Получить от @MasterBot в Максе
        'username' => 'your_bot_username',
        'name' => 'QuickShop Bot'
    ],

    // Настройки базы данных
    'database' => [
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'max_shop',
        'charset' => 'utf8mb4'
    ],

    // Настройки вебхука
    'webhook' => [
        'url' => 'https://yourdomain.com/webhook.php',
        'secret' => 'your_webhook_secret'
    ],

    // Настройки администратора
    'admin' => [
        'password' => 'admin123',
        'chat_id' => 'YOUR_ADMIN_CHAT_ID'
    ],

    // Настройки магазина
    'shop' => [
        'currency' => 'RUB',
        'items_per_page' => 5,
        'order_timeout' => 3600, // 1 час на оформление заказа
        'max_cart_items' => 50
    ],

    // Способы оплаты
    'payment' => [
        'methods' => [
            'card' => 'Банковской картой',
            'cash' => 'Наличными при получении',
            'online' => 'Онлайн оплата'
        ],
        'providers' => [
            'yookassa' => [
                'enabled' => false,
                'shop_id' => '',
                'secret_key' => ''
            ]
        ]
    ],

    // Категории товаров
    'categories' => [
        'electronics' => 'Электроника',
        'clothing' => 'Одежда',
        'books' => 'Книги',
        'home' => 'Для дома',
        'sports' => 'Спорт и отдых'
    ]
];

// Проверка наличия токена бота
if ($config['bot']['token'] === 'YOUR_BOT_TOKEN') {
    die('Ошибка: Установите токен бота в файле config.php');
}

return $config;