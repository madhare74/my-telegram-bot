<?php
// Диагностический режим — всё пишем в логи
error_log("=== БОТ ЗАПУЩЕН ===");

$token = "7818118293:AAENIdj7bbmYZuqfC_nQTjS-p_GFIWjJKn4";
$admin = "13448282";
$db_file = 'db.json';

// Функция отправки с логированием
function bot_send($method, $datas = []) {
    global $token;
    error_log("Отправка: $method с параметрами: " . json_encode($datas));
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.telegram.org/bot' . $token . '/' . $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $datas,
        CURLOPT_TIMEOUT => 10
    ]);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("ОШИБКА CURL: " . $error);
    } else {
        error_log("Результат: " . substr($result, 0, 200));
    }
    
    return json_decode($result, true);
}

// Получаем ВХОДНЫЕ ДАННЫЕ от Telegram
$input = file_get_contents('php://input');
error_log("Входные данные: " . ($input ? $input : "ПУСТО"));

if (!$input) {
    error_log("Нет входных данных — выходим");
    exit;
}

$update = json_decode($input, true);
if (!$update) {
    error_log("Не удалось декодировать JSON");
    exit;
}

error_log("Декодированный update: " . json_encode($update));

// ========== ОБРАБОТКА БИЗНЕС-СООБЩЕНИЙ ==========
if (isset($update['business_message'])) {
    error_log("=== НАЙДЕНО BUSINESS_MESSAGE ===");
    $msg = $update['business_message'];
    $b_id = $msg['business_connection_id'];
    $text = trim($msg['text'] ?? '');
    $chat_id = $msg['chat']['id'];
    $message_id = $msg['message_id'];
    
    error_log("business_message: chat_id=$chat_id, text=$text, admin=$admin");
    
    if ($chat_id != $admin && !empty($text)) {
        error_log("Отвечаем на бизнес-сообщение");
        
        // Читаем базу данных
        $db = [];
        if (file_exists($db_file)) {
            $db = json_decode(file_get_contents($db_file), true);
            error_log("База данных загружена, правил: " . count($db['data'] ?? []));
        } else {
            error_log("Файл db.json НЕ СУЩЕСТВУЕТ");
        }
        
        if (!is_array($db)) $db = ["data" => []];
        
        $matched = false;
        foreach ($db['data'] as $item) {
            $trigger = trim($item['text']);
            error_log("Проверяем триггер: '$trigger' против '$text'");
            if (!empty($trigger) && stripos($text, $trigger) !== false) {
                error_log("СОВПАДЕНИЕ найдено: $trigger");
                foreach ($item['answers'] as $answer) {
                    error_log("Отправляем ответ: " . json_encode($answer));
                    bot_send('sendMessage', [
                        'business_connection_id' => $b_id,
                        'chat_id' => $chat_id,
                        'text' => $answer['content'],
                        'reply_parameters' => json_encode(['message_id' => $message_id])
                    ]);
                }
                $matched = true;
                break;
            }
        }
        
        if (!$matched) {
            error_log("Совпадений не найдено");
        }
    } else {
        error_log("Пропускаем: chat_id==admin или текст пуст");
    }
} else {
    error_log("business_message НЕ НАЙДЕН в update");
}

// ========== ОБРАБОТКА ОБЫЧНЫХ СООБЩЕНИЙ (для теста) ==========
if (isset($update['message'])) {
    error_log("=== НАЙДЕНО ОБЫЧНОЕ СООБЩЕНИЕ ===");
    $chat_id = $update['message']['chat']['id'];
    $text = trim($update['message']['text'] ?? '');
    error_log("message: chat_id=$chat_id, text=$text");
    
    // Обязательно отвечаем на /start для проверки
    if ($text == '/start') {
        error_log("Отправляем приветствие на /start");
        bot_send('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "🤖 Бот работает! Получен /start\n\nВаш chat_id: $chat_id\nAdmin ID: $admin"
        ]);
    }
}

error_log("=== ОБРАБОТКА ЗАВЕРШЕНА ===");
?>
