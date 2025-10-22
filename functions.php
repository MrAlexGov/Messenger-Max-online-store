<?php
/**
 * Основные функции для работы с интернет-магазином
 */

require_once 'config.php';
require_once 'Database.php';
require_once 'MaxBotAPI.php';

class ShopBot {
    public $config;
    public $db;
    public $bot_api;
    private $user_states = [];

    public function __construct() {
        global $config;
        $this->config = $config;
        $this->db = new Database($config);
        $this->db->connect();
        $this->bot_api = new MaxBotAPI($config['bot']['token']);
    }

    /**
     * Обработка входящих обновлений
     */
    public function handleUpdate($update) {
        try {
            $update_type = $update['update_type'];

            switch ($update_type) {
                case 'message_created':
                    $this->handleMessage($update['message']);
                    break;

                case 'message_callback':
                    $this->handleCallback($update['callback'], $update['message']);
                    break;

                case 'bot_started':
                    $this->handleBotStarted($update);
                    break;

                default:
                    // Игнорируем другие типы обновлений
                    break;
            }
        } catch (Exception $e) {
            error_log("Ошибка обработки обновления: " . $e->getMessage());
        }
    }

    /**
     * Обработка нового сообщения
     */
    private function handleMessage($message) {
        $chat_id = $message['recipient']['chat_id'];
        $user = $message['sender'];
        $text = $message['body']['text'] ?? '';

        // Сохраняем/обновляем информацию о пользователе
        $this->db->upsertUser([
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name']
        ]);

        // Обрабатываем команды
        if (strpos($text, '/') === 0) {
            $this->handleCommand($chat_id, $user, $text);
        } else {
            $this->handleText($chat_id, $user, $text);
        }
    }

    /**
     * Обработка команд
     */
    private function handleCommand($chat_id, $user, $command) {
        switch ($command) {
            case '/start':
                $this->sendMainMenu($chat_id);
                break;

            case '/catalog':
            case '/каталог':
                $this->sendCategories($chat_id);
                break;

            case '/cart':
            case '/корзина':
                $this->sendCart($chat_id, $user['user_id']);
                break;

            case '/search':
                $this->sendSearchPrompt($chat_id);
                break;

            case '/admin':
                $this->handleAdminCommand($chat_id, $user);
                break;

            default:
                $this->bot_api->sendMessage($chat_id, "Неизвестная команда. Используйте /start для начала работы.");
        }
    }

    /**
     * Обработка текстовых сообщений
     */
    private function handleText($chat_id, $user, $text) {
        // Проверяем состояние пользователя
        $state = $this->getUserState($user['user_id']);

        switch ($state) {
            case 'waiting_for_search':
                $this->handleSearch($chat_id, $user['user_id'], $text);
                $this->clearUserState($user['user_id']);
                break;

            case 'waiting_for_address':
                $this->handleAddressInput($chat_id, $user['user_id'], $text);
                break;

            case 'waiting_for_phone':
                $this->handlePhoneInput($chat_id, $user['user_id'], $text);
                break;

            default:
                $this->bot_api->sendMessage($chat_id, "Используйте кнопки меню или команды для навигации.");
        }
    }

    /**
     * Обработка callback кнопок
     */
    private function handleCallback($callback, $message) {
        $chat_id = $message['recipient']['chat_id'];
        $user_id = $callback['user']['user_id'];
        $callback_id = $callback['callback_id'];
        $payload = $callback['payload'];

        // Отвечаем на callback
        $this->bot_api->answerCallback($callback_id);

        // Обрабатываем payload
        $this->handleCallbackPayload($chat_id, $user_id, $payload);
    }

    /**
     * Обработка payload от callback кнопок
     */
    private function handleCallbackPayload($chat_id, $user_id, $payload) {
        // Парсим payload
        $data = json_decode($payload, true);

        if (!$data || !isset($data['action'])) {
            return;
        }

        $action = $data['action'];

        switch ($action) {
            case 'category_selected':
                $this->sendProductsInCategory($chat_id, $data['category_id'], 0);
                break;

            case 'product_add_to_cart':
                $this->addProductToCart($chat_id, $user_id, $data['product_id']);
                break;

            case 'view_cart':
                $this->sendCart($chat_id, $user_id);
                break;

            case 'cart_item_plus':
                $this->updateCartItem($chat_id, $user_id, $data['product_id'], 1);
                break;

            case 'cart_item_minus':
                $this->updateCartItem($chat_id, $user_id, $data['product_id'], -1);
                break;

            case 'cart_item_remove':
                $this->removeFromCart($chat_id, $user_id, $data['product_id']);
                break;

            case 'clear_cart':
                $this->clearCart($chat_id, $user_id);
                break;

            case 'checkout':
                $this->startCheckout($chat_id, $user_id);
                break;

            case 'payment_method_selected':
                $this->handlePaymentMethod($chat_id, $user_id, $data['method']);
                break;

            case 'pagination':
                $this->handlePagination($chat_id, $data);
                break;

            case 'back_to_main':
                $this->sendMainMenu($chat_id);
                break;

            case 'favorites':
                $this->sendFavorites($chat_id, $user_id);
                break;

            case 'add_to_favorites':
                $this->addToFavorites($chat_id, $user_id, $data['product_id']);
                break;

            case 'remove_from_favorites':
                $this->removeFromFavorites($chat_id, $user_id, $data['product_id']);
                break;
        }
    }

    /**
     * Отправка главного меню
     */
    private function sendMainMenu($chat_id) {
        $keyboard = [
            'buttons' => [
                [
                    $this->createCallbackButton('🛍️ Каталог', 'back_to_main'),
                    $this->createCallbackButton('🛒 Корзина', 'view_cart')
                ],
                [
                    $this->createCallbackButton('🔍 Поиск', 'search'),
                    $this->createCallbackButton('❤️ Избранное', 'favorites')
                ],
                [
                    $this->createCallbackButton('ℹ️ О нас', 'about'),
                    $this->createCallbackButton('📞 Поддержка', 'support')
                ]
            ]
        ];

        $text = "🏪 Добро пожаловать в QuickShop Bot!\n\nВыберите действие:";
        $this->bot_api->sendMessage($chat_id, $text, [
            'attachments' => [[
                'type' => 'inline_keyboard',
                'payload' => $keyboard
            ]]
        ]);
    }

    /**
     * Отправка списка категорий
     */
    private function sendCategories($chat_id) {
        $categories = $this->db->getCategories();

        if (empty($categories)) {
            $this->bot_api->sendMessage($chat_id, "Категории товаров не найдены.");
            return;
        }

        $buttons = [];
        foreach ($categories as $category) {
            $buttons[] = [$this->createCallbackButton(
                $category['name'],
                json_encode([
                    'action' => 'category_selected',
                    'category_id' => $category['id']
                ])
            )];
        }

        $buttons[] = [$this->createCallbackButton('⬅️ Назад', 'back_to_main')];

        $keyboard = ['buttons' => $buttons];

        $text = "🛍️ Выберите категорию товаров:";
        $this->bot_api->sendMessage($chat_id, $text, [
            'attachments' => [[
                'type' => 'inline_keyboard',
                'payload' => $keyboard
            ]]
        ]);
    }

    /**
     * Отправка товаров в категории
     */
    private function sendProductsInCategory($chat_id, $category_id, $page = 0) {
        $items_per_page = $this->config['shop']['items_per_page'];
        $offset = $page * $items_per_page;

        $products = $this->db->getProductsByCategory($category_id, $items_per_page, $offset);

        if (empty($products)) {
            $this->bot_api->sendMessage($chat_id, "В этой категории нет товаров.");
            return;
        }

        $category = $this->db->fetchOne("SELECT name FROM categories WHERE id = ?", [$category_id]);

        foreach ($products as $product) {
            $this->sendProductCard($chat_id, $product);
        }

        // Добавляем пагинацию, если есть больше товаров
        $total_products = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM products WHERE category_id = ? AND is_active = 1",
            [$category_id]
        )['count'];

        if ($total_products > $items_per_page) {
            $this->sendPagination($chat_id, $category_id, $page, $items_per_page, $total_products, 'category');
        }
    }

    /**
     * Отправка карточки товара
     */
    private function sendProductCard($chat_id, $product) {
        $text = "📦 *{$product['name']}*\n\n";
        $text .= "💰 Цена: {$product['price']} руб.\n";
        $text .= "📂 Категория: {$product['category_name']}\n\n";

        if ($product['description']) {
            $text .= "📝 {$product['description']}\n\n";
        }

        $keyboard = [
            'buttons' => [
                [
                    $this->createCallbackButton('🛒 В корзину', json_encode([
                        'action' => 'product_add_to_cart',
                        'product_id' => $product['id']
                    ])),
                    $this->createCallbackButton('❤️ В избранное', json_encode([
                        'action' => 'add_to_favorites',
                        'product_id' => $product['id']
                    ]))
                ]
            ]
        ];

        $this->bot_api->sendMessage($chat_id, $text, [
            'attachments' => [[
                'type' => 'inline_keyboard',
                'payload' => $keyboard
            ]]
        ]);
    }

    /**
     * Добавление товара в корзину
     */
    private function addProductToCart($chat_id, $user_id, $product_id) {
        $product = $this->db->getProduct($product_id);

        if (!$product) {
            $this->bot_api->sendMessage($chat_id, "Товар не найден.");
            return;
        }

        if ($product['stock_quantity'] <= 0) {
            $this->bot_api->sendMessage($chat_id, "Товар временно отсутствует.");
            return;
        }

        $this->db->addToCart($user_id, $product_id, 1);
        $this->bot_api->sendMessage($chat_id, "✅ Товар добавлен в корзину!");
    }

    /**
     * Отправка корзины
     */
    private function sendCart($chat_id, $user_id) {
        $cart_items = $this->db->getCart($user_id);

        if (empty($cart_items)) {
            $keyboard = [
                'buttons' => [
                    [$this->createCallbackButton('🛍️ Продолжить покупки', 'back_to_main')],
                    [$this->createCallbackButton('❤️ Избранное', 'favorites')]
                ]
            ];

            $this->bot_api->sendMessage($chat_id, "🛒 Ваша корзина пуста", [
                'attachments' => [[
                    'type' => 'inline_keyboard',
                    'payload' => $keyboard
                ]]
            ]);
            return;
        }

        $total = 0;
        $text = "🛒 Ваша корзина:\n\n";

        foreach ($cart_items as $item) {
            $text .= "📦 {$item['name']}\n";
            $text .= "💰 {$item['price']} руб. × {$item['quantity']} = {$item['total_price']} руб.\n";
            $text .= "➖➕\n\n";
            $total += $item['total_price'];
        }

        $text .= "💰 Итого: {$total} руб.\n\n";

        // Создаем кнопки для каждого товара в корзине
        $buttons = [];
        foreach ($cart_items as $item) {
            $buttons[] = [
                $this->createCallbackButton("➕ {$item['name']}", json_encode([
                    'action' => 'cart_item_plus',
                    'product_id' => $item['product_id']
                ])),
                $this->createCallbackButton("➖", json_encode([
                    'action' => 'cart_item_minus',
                    'product_id' => $item['product_id']
                ])),
                $this->createCallbackButton("❌", json_encode([
                    'action' => 'cart_item_remove',
                    'product_id' => $item['product_id']
                ]))
            ];
        }

        $buttons[] = [$this->createCallbackButton('🧹 Очистить корзину', 'clear_cart')];
        $buttons[] = [$this->createCallbackButton('✅ Оформить заказ', 'checkout')];
        $buttons[] = [$this->createCallbackButton('⬅️ Назад', 'back_to_main')];

        $keyboard = ['buttons' => $buttons];

        $this->bot_api->sendMessage($chat_id, $text, [
            'attachments' => [[
                'type' => 'inline_keyboard',
                'payload' => $keyboard
            ]]
        ]);
    }

    /**
     * Обновление количества товара в корзине
     */
    private function updateCartItem($chat_id, $user_id, $product_id, $delta) {
        $cart_items = $this->db->getCart($user_id);
        $current_item = null;

        foreach ($cart_items as $item) {
            if ($item['product_id'] == $product_id) {
                $current_item = $item;
                break;
            }
        }

        if (!$current_item) {
            return;
        }

        $new_quantity = $current_item['quantity'] + $delta;

        if ($new_quantity <= 0) {
            $this->removeFromCart($chat_id, $user_id, $product_id);
        } else {
            $this->db->updateCartItem($user_id, $product_id, $new_quantity);
            $this->sendCart($chat_id, $user_id);
        }
    }

    /**
     * Удаление товара из корзины
     */
    private function removeFromCart($chat_id, $user_id, $product_id) {
        $this->db->updateCartItem($user_id, $product_id, 0);
        $this->bot_api->sendMessage($chat_id, "✅ Товар удален из корзины");
    }

    /**
     * Очистка корзины
     */
    private function clearCart($chat_id, $user_id) {
        $this->db->clearCart($user_id);
        $this->bot_api->sendMessage($chat_id, "🧹 Корзина очищена");
    }

    /**
     * Начало оформления заказа
     */
    private function startCheckout($chat_id, $user_id) {
        $cart_items = $this->db->getCart($user_id);

        if (empty($cart_items)) {
            $this->bot_api->sendMessage($chat_id, "Корзина пуста. Добавьте товары для оформления заказа.");
            return;
        }

        $this->setUserState($user_id, 'waiting_for_phone');

        $keyboard = [
            'buttons' => [
                [$this->createContactButton('📱 Отправить номер телефона')]
            ]
        ];

        $this->bot_api->sendMessage($chat_id, "📞 Пожалуйста, укажите номер телефона для связи:", [
            'attachments' => [[
                'type' => 'inline_keyboard',
                'payload' => $keyboard
            ]]
        ]);
    }

    /**
     * Обработка введенного адреса
     */
    private function handleAddressInput($chat_id, $user_id, $address) {
        // Здесь можно сохранить адрес во временную переменную сессии
        // или сразу перейти к выбору способа оплаты

        $keyboard = ['buttons' => []];

        foreach ($this->config['payment']['methods'] as $key => $name) {
            $keyboard['buttons'][] = [$this->createCallbackButton($name, json_encode([
                'action' => 'payment_method_selected',
                'method' => $key
            ]))];
        }

        $keyboard['buttons'][] = [$this->createCallbackButton('⬅️ Назад', 'view_cart')];

        $this->bot_api->sendMessage($chat_id, "💳 Выберите способ оплаты:", [
            'attachments' => [[
                'type' => 'inline_keyboard',
                'payload' => $keyboard
            ]]
        ]);

        $this->setUserState($user_id, 'waiting_for_payment');
    }

    /**
     * Обработка введенного телефона
     */
    private function handlePhoneInput($chat_id, $user_id, $phone) {
        $this->setUserState($user_id, 'waiting_for_address');
        $this->bot_api->sendMessage($chat_id, "🏠 Теперь укажите адрес доставки:");
    }

    /**
     * Отправка промпта для поиска
     */
    private function sendSearchPrompt($chat_id) {
        $this->setUserState($this->getCurrentUserId(), 'waiting_for_search');
        $this->bot_api->sendMessage($chat_id, "🔍 Введите текст для поиска товаров:");
    }

    /**
     * Получение текущего user_id (временная реализация)
     */
    private function getCurrentUserId() {
        // В реальной реализации нужно получить user_id из контекста
        return 0;
    }

    /**
     * Обработка запуска бота
     */
    private function handleBotStarted($update) {
        $chat_id = $update['chat_id'];
        $this->sendMainMenu($chat_id);
    }

    /**
     * Обработка выбранного способа оплаты
     */
    private function handlePaymentMethod($chat_id, $user_id, $method) {
        $cart_items = $this->db->getCart($user_id);

        if (empty($cart_items)) {
            $this->bot_api->sendMessage($chat_id, "Корзина пуста.");
            return;
        }

        $total = array_sum(array_column($cart_items, 'total_price'));

        // Создаем заказ
        $order_data = [
            'total_amount' => $total,
            'payment_method' => $method,
            'delivery_address' => 'Адрес будет уточнен',
            'contact_phone' => 'Телефон будет уточнен'
        ];

        $order = $this->db->createOrder($user_id, $order_data);

        if ($order) {
            $text = "✅ Заказ #{$order['id']} успешно оформлен!\n\n";
            $text .= "💰 Сумма: {$total} руб.\n";
            $text .= "💳 Оплата: {$this->config['payment']['methods'][$method]}\n\n";
            $text .= "Мы свяжемся с вами в ближайшее время для подтверждения заказа.";

            $this->bot_api->sendMessage($chat_id, $text);

            // Отправляем уведомление администратору
            $this->notifyAdminNewOrder($order, $cart_items);
        } else {
            $this->bot_api->sendMessage($chat_id, "Произошла ошибка при оформлении заказа. Попробуйте еще раз.");
        }

        $this->clearUserState($user_id);
    }

    /**
     * Обработка поиска товаров
     */
    private function handleSearch($chat_id, $user_id, $query) {
        if (empty(trim($query))) {
            $this->bot_api->sendMessage($chat_id, "Поисковый запрос не может быть пустым.");
            return;
        }

        $products = $this->db->searchProducts($query, 20, 0);

        if (empty($products)) {
            $this->bot_api->sendMessage($chat_id, "По запросу '{$query}' ничего не найдено.");
            return;
        }

        $text = "🔍 Результаты поиска по '{$query}':\n\n";
        $this->bot_api->sendMessage($chat_id, $text);

        foreach ($products as $product) {
            $this->sendProductCard($chat_id, $product);
        }
    }

    /**
     * Отправка избранных товаров
     */
    private function sendFavorites($chat_id, $user_id) {
        $favorites = $this->db->getFavorites($user_id);

        if (empty($favorites)) {
            $this->bot_api->sendMessage($chat_id, "❤️ У вас нет избранных товаров.");
            return;
        }

        $text = "❤️ Избранные товары:\n\n";
        $this->bot_api->sendMessage($chat_id, $text);

        foreach ($favorites as $product) {
            $this->sendProductCard($chat_id, $product);
        }
    }

    /**
     * Добавление в избранное
     */
    private function addToFavorites($chat_id, $user_id, $product_id) {
        $this->db->addToFavorites($user_id, $product_id);
        $this->bot_api->sendMessage($chat_id, "❤️ Товар добавлен в избранное");
    }

    /**
     * Удаление из избранного
     */
    private function removeFromFavorites($chat_id, $user_id, $product_id) {
        $this->db->removeFromFavorites($user_id, $product_id);
        $this->bot_api->sendMessage($chat_id, "❤️ Товар удален из избранного");
    }

    /**
     * Обработка команды администратора
     */
    private function handleAdminCommand($chat_id, $user) {
        $this->setUserState($user['user_id'], 'waiting_for_admin_password');

        $this->bot_api->sendMessage($chat_id, "🔐 Введите пароль администратора:");
    }

    /**
     * Создание callback кнопки
     */
    private function createCallbackButton($text, $payload) {
        return [
            'type' => 'callback',
            'text' => $text,
            'payload' => $payload
        ];
    }

    /**
     * Создание кнопки контакта
     */
    private function createContactButton($text) {
        return [
            'type' => 'request_contact',
            'text' => $text
        ];
    }

    /**
     * Управление состоянием пользователя
     */
    private function setUserState($user_id, $state) {
        $this->user_states[$user_id] = $state;
    }

    private function getUserState($user_id) {
        return $this->user_states[$user_id] ?? null;
    }

    private function clearUserState($user_id) {
        unset($this->user_states[$user_id]);
    }

    /**
     * Пагинация
     */
    private function sendPagination($chat_id, $category_id, $current_page, $items_per_page, $total_items, $type) {
        $total_pages = ceil($total_items / $items_per_page);
        $buttons = [];

        if ($current_page > 0) {
            $buttons[] = $this->createCallbackButton('⬅️ Назад', json_encode([
                'action' => 'pagination',
                'category_id' => $category_id,
                'page' => $current_page - 1,
                'type' => $type
            ]));
        }

        $buttons[] = $this->createCallbackButton(($current_page + 1) . '/' . $total_pages, 'none');

        if ($current_page < $total_pages - 1) {
            $buttons[] = $this->createCallbackButton('Вперед ➡️', json_encode([
                'action' => 'pagination',
                'category_id' => $category_id,
                'page' => $current_page + 1,
                'type' => $type
            ]));
        }

        $keyboard = ['buttons' => [$buttons]];

        $this->bot_api->sendMessage($chat_id, "Страница " . ($current_page + 1) . " из $total_pages", [
            'attachments' => [[
                'type' => 'inline_keyboard',
                'payload' => $keyboard
            ]]
        ]);
    }

    private function handlePagination($chat_id, $data) {
        $this->sendProductsInCategory($chat_id, $data['category_id'], $data['page']);
    }

    /**
     * Уведомление администратора о новом заказе
     */
    private function notifyAdminNewOrder($order, $cart_items) {
        $admin_chat_id = $this->config['admin']['chat_id'];

        $text = "🆕 Новый заказ #{$order['id']}\n\n";
        $text .= "👤 Покупатель: {$order['first_name']} {$order['last_name']}\n";
        $text .= "💰 Сумма: {$order['total_amount']} руб.\n";
        $text .= "💳 Оплата: {$this->config['payment']['methods'][$order['payment_method']]}\n\n";

        $text .= "📦 Товары:\n";
        foreach ($cart_items as $item) {
            $text .= "• {$item['name']} × {$item['quantity']} = {$item['total_price']} руб.\n";
        }

        $this->bot_api->sendMessage($admin_chat_id, $text);
    }

    /**
     * Получение обновлений через long polling
     */
    public function getUpdates() {
        try {
            $updates = $this->bot_api->getUpdates(['limit' => 100, 'timeout' => 30]);

            if (isset($updates['updates']) && !empty($updates['updates'])) {
                foreach ($updates['updates'] as $update) {
                    $this->handleUpdate($update);
                }
            }

            return $updates;
        } catch (Exception $e) {
            error_log("Ошибка получения обновлений: " . $e->getMessage());
            return null;
        }
    }
}