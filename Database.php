<?php
/**
 * Класс для работы с базой данных MySQL
 */

class Database {
    private $connection;
    private $config;

    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * Подключение к базе данных
     */
    public function connect() {
        try {
            $dsn = "mysql:host={$this->config['database']['host']};dbname={$this->config['database']['database']};charset={$this->config['database']['charset']}";

            $this->connection = new PDO($dsn, $this->config['database']['username'], $this->config['database']['password']);

            // Устанавливаем режим ошибок
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Устанавливаем режим выборки по умолчанию
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Устанавливаем кодировку
            $this->connection->exec("SET NAMES {$this->config['database']['charset']}");

        } catch(PDOException $e) {
            throw new Exception("Ошибка подключения к базе данных: " . $e->getMessage());
        }
    }

    /**
     * Выполнение запроса
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            throw new Exception("Ошибка выполнения запроса: " . $e->getMessage());
        }
    }

    /**
     * Получение одной записи
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Получение всех записей
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Вставка данных
     */
    public function insert($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }

    /**
     * Обновление данных
     */
    public function update($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Удаление данных
     */
    public function delete($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Получение пользователя по ID
     */
    public function getUser($user_id) {
        $sql = "SELECT * FROM users WHERE user_id = ?";
        return $this->fetchOne($sql, [$user_id]);
    }

    /**
     * Создание или обновление пользователя
     */
    public function upsertUser($user_data) {
        $existing_user = $this->getUser($user_data['user_id']);

        if ($existing_user) {
            // Обновляем данные пользователя
            $sql = "UPDATE users SET
                    username = ?,
                    first_name = ?,
                    last_name = ?,
                    phone = ?,
                    address = ?,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE user_id = ?";

            $params = [
                $user_data['username'] ?? null,
                $user_data['first_name'],
                $user_data['last_name'] ?? null,
                $user_data['phone'] ?? null,
                $user_data['address'] ?? null,
                $user_data['user_id']
            ];

            $this->update($sql, $params);
        } else {
            // Создаем нового пользователя
            $sql = "INSERT INTO users (user_id, username, first_name, last_name, phone, address)
                    VALUES (?, ?, ?, ?, ?, ?)";

            $params = [
                $user_data['user_id'],
                $user_data['username'] ?? null,
                $user_data['first_name'],
                $user_data['last_name'] ?? null,
                $user_data['phone'] ?? null,
                $user_data['address'] ?? null
            ];

            $this->insert($sql, $params);
        }

        return $this->getUser($user_data['user_id']);
    }

    /**
     * Получение всех категорий
     */
    public function getCategories() {
        $sql = "SELECT * FROM categories ORDER BY sort_order, name";
        return $this->fetchAll($sql);
    }

    /**
     * Получение товаров по категории с пагинацией
     */
    public function getProductsByCategory($category_id, $limit = 10, $offset = 0) {
        $sql = "SELECT p.*, c.name as category_name
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE p.category_id = ? AND p.is_active = 1
                ORDER BY p.name
                LIMIT ? OFFSET ?";

        return $this->fetchAll($sql, [$category_id, $limit, $offset]);
    }

    /**
     * Поиск товаров
     */
    public function searchProducts($query, $limit = 20, $offset = 0) {
        $sql = "SELECT p.*, c.name as category_name
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE p.is_active = 1
                AND (p.name LIKE ? OR p.description LIKE ?)
                ORDER BY p.name
                LIMIT ? OFFSET ?";

        $search_term = "%{$query}%";
        return $this->fetchAll($sql, [$search_term, $search_term, $limit, $offset]);
    }

    /**
     * Получение товара по ID
     */
    public function getProduct($product_id) {
        $sql = "SELECT p.*, c.name as category_name
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE p.id = ? AND p.is_active = 1";

        return $this->fetchOne($sql, [$product_id]);
    }

    /**
     * Добавление товара в корзину
     */
    public function addToCart($user_id, $product_id, $quantity = 1) {
        // Проверяем, есть ли товар уже в корзине
        $existing = $this->fetchOne(
            "SELECT * FROM cart WHERE user_id = ? AND product_id = ?",
            [$user_id, $product_id]
        );

        if ($existing) {
            // Обновляем количество
            $sql = "UPDATE cart SET quantity = quantity + ?, updated_at = CURRENT_TIMESTAMP
                    WHERE user_id = ? AND product_id = ?";
            $this->update($sql, [$quantity, $user_id, $product_id]);
        } else {
            // Добавляем новый товар в корзину
            $sql = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)";
            $this->insert($sql, [$user_id, $product_id, $quantity]);
        }

        return $this->getCart($user_id);
    }

    /**
     * Получение корзины пользователя
     */
    public function getCart($user_id) {
        $sql = "SELECT c.*, p.name, p.price, p.image_url, (c.quantity * p.price) as total_price
                FROM cart c
                JOIN products p ON c.product_id = p.id
                WHERE c.user_id = ?
                ORDER BY c.created_at";

        return $this->fetchAll($sql, [$user_id]);
    }

    /**
     * Обновление количества товара в корзине
     */
    public function updateCartItem($user_id, $product_id, $quantity) {
        if ($quantity <= 0) {
            // Удаляем товар из корзины
            $sql = "DELETE FROM cart WHERE user_id = ? AND product_id = ?";
            $this->delete($sql, [$user_id, $product_id]);
        } else {
            // Обновляем количество
            $sql = "UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE user_id = ? AND product_id = ?";
            $this->update($sql, [$quantity, $user_id, $product_id]);
        }

        return $this->getCart($user_id);
    }

    /**
     * Очистка корзины пользователя
     */
    public function clearCart($user_id) {
        $sql = "DELETE FROM cart WHERE user_id = ?";
        return $this->delete($sql, [$user_id]);
    }

    /**
     * Создание заказа
     */
    public function createOrder($user_id, $order_data) {
        // Начинаем транзакцию
        $this->connection->beginTransaction();

        try {
            // Создаем заказ
            $sql = "INSERT INTO orders (user_id, total_amount, payment_method, delivery_address, contact_phone, notes)
                    VALUES (?, ?, ?, ?, ?, ?)";

            $params = [
                $user_id,
                $order_data['total_amount'],
                $order_data['payment_method'],
                $order_data['delivery_address'],
                $order_data['contact_phone'],
                $order_data['notes'] ?? null
            ];

            $order_id = $this->insert($sql, $params);

            // Добавляем товары из корзины в заказ
            $cart_items = $this->getCart($user_id);

            foreach ($cart_items as $item) {
                $sql = "INSERT INTO order_items (order_id, product_id, quantity, price)
                        VALUES (?, ?, ?, ?)";

                $this->insert($sql, [
                    $order_id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price']
                ]);

                // Уменьшаем количество товара на складе
                $this->query(
                    "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?",
                    [$item['quantity'], $item['product_id']]
                );
            }

            // Очищаем корзину
            $this->clearCart($user_id);

            // Подтверждаем транзакцию
            $this->connection->commit();

            return $this->getOrder($order_id);

        } catch(Exception $e) {
            // Откатываем транзакцию в случае ошибки
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * Получение заказа по ID
     */
    public function getOrder($order_id) {
        $sql = "SELECT o.*, u.first_name, u.last_name, u.username
                FROM orders o
                JOIN users u ON o.user_id = u.user_id
                WHERE o.id = ?";

        return $this->fetchOne($sql, [$order_id]);
    }

    /**
     * Получение заказов пользователя
     */
    public function getUserOrders($user_id, $limit = 20, $offset = 0) {
        $sql = "SELECT o.*, COUNT(oi.id) as items_count
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.user_id = ?
                GROUP BY o.id
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?";

        return $this->fetchAll($sql, [$user_id, $limit, $offset]);
    }

    /**
     * Получение всех заказов для администратора
     */
    public function getAllOrders($status = null, $limit = 50, $offset = 0) {
        $where_clause = "";
        $params = [];

        if ($status) {
            $where_clause = "WHERE o.status = ?";
            $params[] = $status;
        }

        $sql = "SELECT o.*, u.first_name, u.last_name, u.username, COUNT(oi.id) as items_count
                FROM orders o
                JOIN users u ON o.user_id = u.user_id
                LEFT JOIN order_items oi ON o.id = oi.order_id
                $where_clause
                GROUP BY o.id
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        return $this->fetchAll($sql, $params);
    }

    /**
     * Обновление статуса заказа
     */
    public function updateOrderStatus($order_id, $status) {
        $sql = "UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        return $this->update($sql, [$status, $order_id]);
    }

    /**
     * Добавление товара в избранное
     */
    public function addToFavorites($user_id, $product_id) {
        $sql = "INSERT IGNORE INTO favorites (user_id, product_id) VALUES (?, ?)";
        return $this->insert($sql, [$user_id, $product_id]);
    }

    /**
     * Удаление товара из избранного
     */
    public function removeFromFavorites($user_id, $product_id) {
        $sql = "DELETE FROM favorites WHERE user_id = ? AND product_id = ?";
        return $this->delete($sql, [$user_id, $product_id]);
    }

    /**
     * Получение избранных товаров пользователя
     */
    public function getFavorites($user_id) {
        $sql = "SELECT p.*, c.name as category_name
                FROM favorites f
                JOIN products p ON f.product_id = p.id
                JOIN categories c ON p.category_id = c.id
                WHERE f.user_id = ? AND p.is_active = 1
                ORDER BY f.created_at DESC";

        return $this->fetchAll($sql, [$user_id]);
    }

    /**
     * Проверка промокода
     */
    public function validatePromoCode($code) {
        $sql = "SELECT * FROM promo_codes
                WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())";

        return $this->fetchOne($sql, [$code]);
    }

    /**
     * Использование промокода
     */
    public function usePromoCode($promo_code_id) {
        $sql = "UPDATE promo_codes
                SET used_count = used_count + 1
                WHERE id = ? AND (max_uses IS NULL OR used_count < max_uses)";

        return $this->update($sql, [$promo_code_id]);
    }

    /**
     * Получение статистики для администратора
     */
    public function getStats() {
        $stats = [];

        // Количество заказов сегодня
        $stats['orders_today'] = $this->fetchOne(
            "SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURDATE()"
        )['count'];

        // Общая выручка сегодня
        $stats['revenue_today'] = $this->fetchOne(
            "SELECT COALESCE(SUM(total_amount), 0) as amount FROM orders
             WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'"
        )['amount'];

        // Количество заказов за неделю
        $stats['orders_week'] = $this->fetchOne(
            "SELECT COUNT(*) as count FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )['count'];

        // Общая выручка за неделю
        $stats['revenue_week'] = $this->fetchOne(
            "SELECT COALESCE(SUM(total_amount), 0) as amount FROM orders
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status != 'cancelled'"
        )['amount'];

        // Количество активных товаров
        $stats['active_products'] = $this->fetchOne(
            "SELECT COUNT(*) as count FROM products WHERE is_active = 1"
        )['count'];

        // Количество пользователей
        $stats['total_users'] = $this->fetchOne(
            "SELECT COUNT(*) as count FROM users"
        )['count'];

        return $stats;
    }

    /**
     * Закрытие соединения
     */
    public function close() {
        $this->connection = null;
    }
}