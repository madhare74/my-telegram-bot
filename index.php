<?php
$token = "7818118293:AAENIdj7bbmYZuqfC_nQTjS-p_GFIWjJKn4";
$admin = "13448282";
$db_file = 'db.json';

// Функция отправки
function bot_send($method, $datas = []) {
    global $token;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.telegram.org/bot' . $token . '/' . $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $datas,
        CURLOPT_TIMEOUT => 10
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

// Инициализация базы данных
if (!file_exists($db_file)) {
    file_put_contents($db_file, json_encode(["data" => []]));
}
$db = json_decode(file_get_contents($db_file), true);
if (!is_array($db)) $db = ["data" => []];

// Получаем обновление от Telegram (вебхук)
$input = file_get_contents('php://input');
if (!$input) exit;

$update = json_decode($input, true);
if (!$update) exit;

// ========== ОБРАБОТКА БИЗНЕС-СООБЩЕНИЙ (ОТ КЛИЕНТОВ) ==========
if (isset($update['business_message'])) {
    $msg = $update['business_message'];
    $b_id = $msg['business_connection_id'];
    $text = trim($msg['text'] ?? '');
    $chat_id = $msg['chat']['id'];
    $message_id = $msg['message_id'];
    
    // Не отвечаем админу (самому себе)
    if ($chat_id != $admin && !empty($text)) {
        foreach ($db['data'] as $item) {
            $trigger = trim($item['text']);
            if (!empty($trigger) && stripos($text, $trigger) !== false) {
                foreach ($item['answers'] as $answer) {
                    bot_send('sendMessage', [
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

// ========== ОБРАБОТКА СООБЩЕНИЙ ОТ АДМИНА ==========
if (isset($update['message']) && $update['message']['chat']['id'] == $admin) {
    $text = trim($update['message']['text'] ?? '');
    $chat_id = $update['message']['chat']['id'];
    
    if ($text == '/start') {
        bot_send('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "🤖 Бот запущен и работает через вебхук!\n\nДля добавления автоответов используйте команду:\n/add привет\n\nГде 'привет' — это ключевое слово, а в следующем сообщении отправьте ответ."
        ]);
    } elseif (strpos($text, '/add ') === 0) {
        // Команда /add [ключевое слово]
        $trigger = trim(substr($text, 5));
        if (!empty($trigger)) {
            // Сохраняем состояние — ожидаем ответ
            $db['step'] = ['action' => 'waiting_answer', 'trigger' => $trigger, 'chat_id' => $chat_id];
            file_put_contents($db_file, json_encode($db));
            bot_send('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "✅ Ключевое слово '{$trigger}' сохранено!\n\nТеперь отправьте ответ (текст, фото, видео или стикер)."
            ]);
        } else {
            bot_send('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ Используйте: /add ключевое_слово"]);
        }
    } elseif (isset($db['step']) && $db['step']['action'] == 'waiting_answer' && $db['step']['chat_id'] == $chat_id) {
        // Сохраняем ответ
        $trigger = $db['step']['trigger'];
        $type = 'text';
        $content = $text;
        
        if (isset($update['message']['sticker'])) {
            $type = 'sticker';
            $content = $update['message']['sticker']['file_id'];
        } elseif (isset($update['message']['photo'])) {
            $type = 'photo';
            $content = $update['message']['photo'][0]['file_id'];
        } elseif (isset($update['message']['video'])) {
            $type = 'video';
            $content = $update['message']['video']['file_id'];
        }
        
        // Добавляем правило
        $found = false;
        foreach ($db['data'] as &$item) {
            if ($item['text'] == $trigger) {
                $item['answers'][] = ['type' => $type, 'content' => $content];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $db['data'][] = ['text' => $trigger, 'answers' => [['type' => $type, 'content' => $content]]];
        }
        
        unset($db['step']);
        file_put_contents($db_file, json_encode($db));
        
        bot_send('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ Правило добавлено!\n\nКогда кто-то напишет '{$trigger}', бот ответит этим сообщением.\n\nДобавить ещё? Используйте /add [слово]"
        ]);
    } elseif ($text == '/list') {
        // Список всех правил
        if (count($db['data']) == 0) {
            bot_send('sendMessage', ['chat_id' => $chat_id, 'text' => "📭 Нет добавленных правил. Используйте /add [слово]"]);
        } else {
            $list = "📋 **Ваши автоответы:**\n\n";
            foreach ($db['data'] as $item) {
                $list .= "• *{$item['text']}* → " . count($item['answers']) . " ответ(ов)\n";
            }
            bot_send('sendMessage', ['chat_id' => $chat_id, 'text' => $list, 'parse_mode' => 'Markdown']);
        }
    } elseif ($text == '/delete') {
        // Удаление — показывает список для удаления
        if (count($db['data']) == 0) {
            bot_send('sendMessage', ['chat_id' => $chat_id, 'text' => "📭 Нет правил для удаления."]);
        } else {
            $buttons = [];
            foreach ($db['data'] as $index => $item) {
                $buttons[] = [['text' => $item['text'], 'callback_data' => 'del_' . $index]];
            }
            bot_send('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "🗑 Выберите правило для удаления:",
                'reply_markup' => json_encode(['inline_keyboard' => $buttons])
            ]);
        }
    }
}

// ========== ОБРАБОТКА НАЖАТИЙ НА INLINE-КНОПКИ ==========
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $callback_id = $callback['id'];
    $chat_id = $callback['message']['chat']['id'];
    $data = $callback['data'];
    
    // Отвечаем на callback
    bot_send('answerCallbackQuery', ['callback_query_id' => $callback_id]);
    
    if (strpos($data, 'del_') === 0) {
        $index = (int)str_replace('del_', '', $data);
        if (isset($db['data'][$index])) {
            $removed = $db['data'][$index]['text'];
            unset($db['data'][$index]);
            $db['data'] = array_values($db['data']);
            file_put_contents($db_file, json_encode($db));
            bot_send('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ Правило '{$removed}' удалено!"]);
        } else {
            bot_send('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ Правило не найдено."]);
        }
    }
}
?>
