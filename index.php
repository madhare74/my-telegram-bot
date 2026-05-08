<?php
// Логируем ВСЁ, что приходит
$input = file_get_contents('php://input');
file_put_contents('telegram_log.txt', date('Y-m-d H:i:s') . ":\n" . $input . "\n---\n", FILE_APPEND);
// Простейший отладочный бот
$token = "7818118293:AAENIdj7bbmYZuqfC_nQTjS-p_GFIWjJKn4";
$admin = "13448282";

// Отправка сообщения
function send($chat_id, $text) {
    global $token;
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $text];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

// Получаем данные от Telegram
$input = file_get_contents('php://input');
if (!$input) {
    // Если нет данных — выводим простую страницу
    echo "Bot is running";
    exit;
}

// Декодируем
$update = json_decode($input, true);

// ПРОСТАЯ ПРОВЕРКА: на любое сообщение отвечаем
if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $text = $update['message']['text'] ?? '';
    
    // Если сообщение от администратора
    if ($chat_id == $admin) {
        if ($text == '/start') {
            send($chat_id, "✅ Бот работает! Ваш ID: $admin\n\nТеперь попросите друга написать вам любое сообщение.");
        }
    } 
    // Если сообщение от другого пользователя (клиента)
    else {
        send($chat_id, "Привет! Я автоответчик. Спасибо за сообщение!");
    }
}

// Отдаем успешный ответ серверу
http_response_code(200);
echo "ok";
?>
