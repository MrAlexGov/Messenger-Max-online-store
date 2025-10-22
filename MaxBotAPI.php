<?php
/**
 * Класс для работы с Max Bot API
 * Реализует основные методы для взаимодействия с мессенджером Макс
 */

class MaxBotAPI {
    private $token;
    private $base_url = 'https://botapi.max.ru';
    private $timeout = 30;

    public function __construct($token) {
        $this->token = $token;
    }

    /**
     * Выполнение HTTP запроса к API
     */
    private function request($method, $endpoint, $data = null, $type = 'GET') {
        $url = $this->base_url . $endpoint;

        // Добавляем токен к параметрам
        if ($type === 'GET' && $data) {
            $url .= '?' . http_build_query(array_merge($data, ['access_token' => $this->token]));
        } else {
            $url .= '?access_token=' . $this->token;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: QuickShopBot/1.0'
            ]
        ]);

        if ($type === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($type === 'PUT' && $data) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($type === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('CURL Error: ' . $error);
        }

        $result = json_decode($response, true);

        if ($http_code >= 400) {
            throw new Exception('API Error: ' . ($result['message'] ?? 'Unknown error'), $http_code);
        }

        return $result;
    }

    /**
     * Получение информации о боте
     */
    public function getMe() {
        return $this->request('getMe', '/me');
    }

    /**
     * Отправка сообщения
     */
    public function sendMessage($chat_id, $text, $options = []) {
        $data = array_merge([
            'text' => $text
        ], $options);

        return $this->request('sendMessage', '/messages', $data, 'POST');
    }

    /**
     * Редактирование сообщения
     */
    public function editMessage($message_id, $text, $options = []) {
        $data = array_merge([
            'text' => $text
        ], $options);

        return $this->request('editMessage', '/messages?message_id=' . $message_id, $data, 'PUT');
    }

    /**
     * Удаление сообщения
     */
    public function deleteMessage($message_id) {
        return $this->request('deleteMessage', '/messages?message_id=' . $message_id, null, 'DELETE');
    }

    /**
     * Получение обновлений через long polling
     */
    public function getUpdates($options = []) {
        $params = array_merge([
            'limit' => 100,
            'timeout' => 30
        ], $options);

        return $this->request('getUpdates', '/updates', $params, 'GET');
    }

    /**
     * Получение списка чатов
     */
    public function getChats($options = []) {
        $params = array_merge([
            'count' => 50
        ], $options);

        return $this->request('getChats', '/chats', $params, 'GET');
    }

    /**
     * Получение информации о чате
     */
    public function getChat($chat_id) {
        return $this->request('getChat', '/chats/' . $chat_id, null, 'GET');
    }

    /**
     * Отправка действия в чат
     */
    public function sendAction($chat_id, $action) {
        $data = [
            'action' => $action
        ];

        return $this->request('sendAction', '/chats/' . $chat_id . '/actions', $data, 'POST');
    }

    /**
     * Загрузка файла
     */
    public function getUploadUrl($type) {
        $params = [
            'type' => $type
        ];

        return $this->request('getUploadUrl', '/uploads', $params, 'POST');
    }

    /**
     * Создание клавиатуры
     */
    public function createKeyboard($buttons, $type = 'inline_keyboard') {
        if ($type === 'reply_keyboard') {
            return [
                'buttons' => $buttons
            ];
        } else {
            return [
                'buttons' => $buttons
            ];
        }
    }

    /**
     * Создание кнопки
     */
    public function createButton($text, $type = 'callback', $payload = '') {
        $button = [
            'type' => $type,
            'text' => $text
        ];

        if ($type === 'callback' && !empty($payload)) {
            $button['payload'] = $payload;
        }

        return $button;
    }

    /**
     * Создание ссылочной кнопки
     */
    public function createLinkButton($text, $url) {
        return [
            'type' => 'link',
            'text' => $text,
            'url' => $url
        ];
    }

    /**
     * Создание кнопки запроса контакта
     */
    public function createContactButton($text) {
        return [
            'type' => 'request_contact',
            'text' => $text
        ];
    }

    /**
     * Создание кнопки геолокации
     */
    public function createLocationButton($text) {
        return [
            'type' => 'request_geo_location',
            'text' => $text
        ];
    }

    /**
     * Ответ на callback запрос
     */
    public function answerCallback($callback_id, $options = []) {
        $data = $options;
        $params = [
            'callback_id' => $callback_id
        ];

        return $this->request('answerOnCallback', '/answers?' . http_build_query($params), $data, 'POST');
    }

    /**
     * Получение списка сообщений в чате
     */
    public function getMessages($chat_id, $options = []) {
        $params = array_merge([
            'chat_id' => $chat_id,
            'count' => 50
        ], $options);

        return $this->request('getMessages', '/messages', $params, 'GET');
    }
}