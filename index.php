<?php
// ==================== КОНФИГУРАЦИЯ ====================
$token = "7818118293:AAENIdj7bbmYZuqfC_nQTjS-p_GFIWjJKn4";
$admin = "13448282";
$db_file = 'db.json';
$last_id_file = 'last_id.txt';

// ==================== ФУНКЦИЯ ОТПРАВКИ ====================
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

// ==================== ИНИЦИАЛИЗАЦИЯ ====================
if (!file_exists($db_file)) {
    file_put_contents($db_file, json_encode(["data" => []]));
}
$db = json_decode(file_get_contents($db_file), true);
if (!is_array($db)) $db = ["data" => []];

// Получаем последний обработанный update_id
$last_update_id = 0;
if (file_exists($last_id_file)) {
    $last_update_id = (int)file_get_contents($last_id_file);
}

// Запрашиваем ТОЛЬКО новые обновления (offset = last_update_id + 1)
$url = "https://api.telegram.org/bot$token/getUpdates?offset=" . ($last_update_id + 1) . "&timeout=30";
$response = file_get_contents($url);
if ($response === false) {
    exit;
}

$updates = json_decode($response, true);
if (!$updates || !isset($updates['result']) || empty($updates['result'])) {
    exit;
}

// ==================== ОБРАБОТКА ОБНОВЛЕНИЙ ====================
foreach ($updates['result'] as $update) {
    $update_id = $update['update_id'];
    
    // ========== 1. ОБРАБОТКА НАЖАТИЙ НА КНОПКИ (callback_query) ==========
    if (isset($update['callback_query'])) {
        $callback = $update['callback_query'];
        $callback_id = $callback['id'];
        $chat_id = $callback['message']['chat']['id'];
        $message_id = $callback['message']['message_id'];
        $data = $callback['data'];
        
        // Отвечаем на callback (убираем "часики")
        bot_send('answerCallbackQuery', ['callback_query_id' => $callback_id]);
        
        if ($data == 'add_reply') {
            bot_send('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "📝 Введите ключевое слово или фразу, на которую будет реагировать бот.\n\nПример: `привет`, `цена`, `доставка`",
                'parse_mode' => 'Markdown'
            ]);
            // Сохраняем состояние
            $db['step'] = ['action' => 'waiting_trigger', 'chat_id' => $chat_id];
            file_put_contents($db_file, json_encode($db));
        } elseif ($data == 'remove_reply') {
            if (count($db['data']) == 0) {
                bot_send('sendMessage', ['chat_id' => $chat_id, 'text' => "📭 Список автоответов пуст."]);
            } else {
                $buttons = [];
                foreach ($db['data'] as $index => $item) {
                    $buttons[] = [['text' => $item['text'], 'callback_data' => 'remove_' . $index]];
                }
                bot_send('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "🗑 Выберите правило для удаления:",
                    'reply_markup' => json_encode(['inline_keyboard' => $buttons])
                ]);
            }
        } elseif ($data == 'list_reply') {
            if (count($db['data']) == 0) {
                bot_send('sendMessage', ['chat_id' => $chat_id, 'text' => "📭 Список автоответов пуст."]);
            } else {
                $list = "📋 **Ваши автоответы:**\n\n";
                foreach ($db['data'] as $item) {
                    $list .= "• *" . $item['text'] . "* → " . count($item['answers']) . " ответ(ов)\n";
                }
                bot_send('sendMessage', ['chat_id' => $chat_id, 'text' => $list, 'parse_mode' => 'Markdown']);
            }
        } elseif (strpos($data, 'remove_') === 0) {
            $index = (int)str_replace('remove_', '', $data);
            if (isset($db['data'][$index])) {
                $removed_text = $db['data'][$index]['text'];
                unset($db['data'][$index]);
                $db['data'] = array_values($db['data']);
                file_put_contents($db_file, json_encode($db));
                bot_send('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ Правило *{$removed_text}* удалено!", 'parse_mode' => 'Markdown']);
            }
        }
    }
    
    // ========== 2. ОБРАБОТКА БИЗНЕС-СООБЩЕНИЙ (ОТ КЛИЕНТОВ) ==========
    if (isset($update['business_message'])) {
        $msg = $update['business_message'];
        $b_id = $msg['business_connection_id'];
        $text = trim($msg['text'] ?? '');
        $chat_id = $msg['chat']['id'];
        $message_id = $msg['message_id'];
        
        // НЕ отвечаем админу
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
    
    // ========== 3. ОБРАБОТКА СООБЩЕНИЙ ОТ АДМИНИСТРАТОРА ==========
    if (isset($update['message']) && $update['message']['chat']['id'] == $admin) {
        $text = trim($update['message']['text'] ?? '');
        $chat_id = $update['message']['chat']['id'];
        
        // Команда /start
        if ($text == '/start') {
            bot_send('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "🤖 *Бизнес-бот для Telegram*\n\nНастройте автоответы через меню:",
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [['text' => '📝 Добавить автоответ', 'callback_data' => 'add_reply']],
                        [['text' => '🗑 Удалить автоответ', 'callback_data' => 'remove_reply']],
                        [['text' => '📋 Список правил', 'callback_data' => 'list_reply']]
                    ]
                ])
            ]);
        }
        
        // Обработка шага "ожидание ключевой фразы"
        if (isset($db['step']) && $db['step']['action'] == 'waiting_trigger' && $db['step']['chat_id'] == $chat_id) {
            if (empty($text)) {
                bot_send('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ Отправьте текстовую фразу."]);
            } else {
                $db['data'][] = ['text' => $text, 'answers' => []];
                $db['step'] = ['action' => 'waiting_answer', 'chat_id' => $chat_id, 'last_key' => array_key_last($db['data'])];
                file_put_contents($db_file, json_encode($db));
                bot_send('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "✍️ Отправьте ответ (текст, фото, видео, стикер).\n\nКогда закончите — отправьте /done",
                    'parse_mode' => 'Markdown'
                ]);
            }
        }
        
        // Обработка шага "ожидание ответа"
        elseif (isset($db['step']['action']) && $db['step']['action'] == 'waiting_answer' && $db['step']['chat_id'] == $chat_id) {
            if ($text == '/done') {
                unset($db['step']);
                file_put_contents($db_file, json_encode($db));
                bot_send('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "🎉 Настройка завершена! Автоответы сохранены.",
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [['text' => '📝 Добавить ещё', 'callback_data' => 'add_reply']],
                            [['text' => '📋 Список правил', 'callback_data' => 'list_reply']]
                        ]
                    ])
                ]);
            } else {
                $type = null;
                $content = null;
                
                if (isset($update['message']['text'])) {
                    $type = 'text';
                    $content = $text;
                } elseif (isset($update['message']['sticker'])) {
                    $type = 'sticker';
                    $content = $update['message']['sticker']['file_id'];
                } elseif (isset($update['message']['photo'])) {
                    $type = 'photo';
                    $content = $update['message']['photo'][0]['file_id'];
                } elseif (isset($update['message']['video'])) {
                    $type = 'video';
                    $content = $update['message']['video']['file_id'];
                }
                
                if ($type && $content) {
                    $last_key = $db['step']['last_key'];
                    $db['data'][$last_key]['answers'][] = [
                        'type' => $type,
                        'content' => $content,
                        'caption' => $update['message']['caption'] ?? ''
                    ];
                    file_put_contents($db_file, json_encode($db));
                    bot_send('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ Ответ добавлен! Отправьте ещё или /done"]);
                } else {
                    bot_send('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ Неподдерживаемый тип. Отправьте текст, фото или стикер."]);
                }
            }
        }
    }
    
    // Сохраняем ID обработанного обновления
    file_put_contents($last_id_file, $update_id);
}
?>
