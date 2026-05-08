<?php
// ==================== КОНФИГУРАЦИЯ ====================
$token = "7818118293:AAENIdj7bbmYZuqfC_nQTjS-p_GFIWjJKn4";
$admin = "13448282";
$db_file = 'db.json';

// ==================== ФУНКЦИЯ ОТПРАВКИ ====================
function bot_poll($method, $datas = []) {
    global $token;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.telegram.org/bot' . $token . '/' . $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $datas,
        CURLOPT_TIMEOUT => 30
    ]);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) error_log("CURL Error: $error");
    return json_decode($result, true);
}

// ==================== ИНИЦИАЛИЗАЦИЯ БАЗЫ ДАННЫХ ====================
if (!file_exists($db_file)) {
    file_put_contents($db_file, json_encode(["data" => [], "step" => ""]));
}
$db = json_decode(file_get_contents($db_file), true);
if (!is_array($db)) $db = ["data" => [], "step" => ""];

// ==================== ОСНОВНОЙ ЦИКЛ ОПРОСА ====================
$last_update_id = 0;
if (file_exists('last_id.txt')) $last_update_id = (int)file_get_contents('last_id.txt');

while (true) {
    $url = "https://api.telegram.org/bot$token/getUpdates?offset=" . ($last_update_id + 1) . "&timeout=30";
    $response = file_get_contents($url);
    if ($response === false) {
        sleep(2);
        continue;
    }
    
    $updates = json_decode($response, true);
    if (!$updates || !isset($updates['result'])) {
        sleep(1);
        continue;
    }
    
    foreach ($updates['result'] as $update) {
        $last_update_id = $update['update_id'];
        file_put_contents('last_id.txt', $last_update_id);
        
        // Обработка БИЗНЕС-сообщений (те самые, от клиентов)
        if (isset($update['business_message'])) {
            $msg = $update['business_message'];
            $b_id = $msg['business_connection_id'];
            $text = $msg['text'] ?? '';
            $chat_id = $msg['chat']['id'];
            $message_id = $msg['message_id'];
            
            if ($chat_id != $admin && !empty($text)) {
                foreach ($db['data'] as $item) {
                    if (stripos($text, $item['text']) !== false) {
                        foreach ($item['answers'] as $answer) {
                            bot_poll('sendMessage', [
                                'business_connection_id' => $b_id,
                                'chat_id' => $chat_id,
                                'text' => $answer['content'],
                                'reply_parameters' => json_encode(['message_id' => $message_id])
                            ]);
                        }
                        break;
                    }
                }
            }
        }
        
        // Обработка команд администратора (для настройки)
        if (isset($update['message']) && $update['message']['chat']['id'] == $admin) {
            $text = $update['message']['text'] ?? '';
            $chat_id = $update['message']['chat']['id'];
            if ($text == '/start') {
                bot_poll('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ Бот активен в режиме опроса (Polling). Используйте кнопки меню для настройки."]);
            }
        }
    }
    sleep(1);
}
?>
