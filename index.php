<?php
// ==================== КОНФИГУРАЦИЯ ====================
$token = "7818118293:AAENIdj7bbmYZuqfC_nQTjS-p_GFIWjJKn4";
$admin = "13448282";
$db_file = 'db.json';
$processed_ids_file = 'processed_ids.txt'; // файл для хранения ID обработанных обновлений

// ==================== ФУНКЦИЯ ОТПРАВКИ ====================
function bot_poll($method, $datas = []) {
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

// ==================== ОСНОВНОЙ ЦИКЛ С ЗАЩИТОЙ ОТ ДУБЛЕЙ ====================
$last_update_id = 0;
if (file_exists('last_id.txt')) $last_update_id = (int)file_get_contents('last_id.txt');

// Загружаем ID уже обработанных обновлений
$processed_ids = [];
if (file_exists($processed_ids_file)) {
    $processed_ids = explode("\n", file_get_contents($processed_ids_file));
}

while (true) {
    $url = "https://api.telegram.org/bot$token/getUpdates?offset=" . ($last_update_id + 1) . "&timeout=30";
    $response = file_get_contents($url);
    if ($response === false) {
        sleep(1);
        continue;
    }
    
    $updates = json_decode($response, true);
    if (!$updates || !isset($updates['result'])) {
        sleep(1);
        continue;
    }
    
    foreach ($updates['result'] as $update) {
        $update_id = $update['update_id'];
        
        // Пропускаем, если уже обработано
        if (in_array($update_id, $processed_ids)) {
            continue;
        }
        
        $last_update_id = $update_id;
        file_put_contents('last_id.txt', $last_update_id);
        
        // ==================== ОБРАБОТКА НАЖАТИЙ НА КНОПКИ ====================
        if (isset($update['callback_query'])) {
            $callback = $update['callback_query'];
            $callback_id = $callback['id'];
            $chat_id = $callback['message']['chat']['id'];
            $data = $callback['data'];
            
            // Отвечаем на callback сразу
            bot_poll('answerCallbackQuery', ['callback_query_id' => $callback_id]);
            
            if ($data == 'add_reply') {
                $db['step'] = 'add-1';
                file_put_contents($db_file, json_encode($db));
                bot_poll('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "📝 Введите ключевое слово или фразу, на которую будет реагировать бот.\n\nПример: `привет`, `цена`, `доставка`",
                    'parse_mode' => 'Markdown',
                    'reply_markup' => json_encode(['remove_keyboard' => true])
                ]);
            } elseif ($data == 'remove_reply') {
                if (count($db['data']) == 0) {
                    bot_poll('sendMessage', ['chat_id' => $chat_id, 'text' => "📭 Список автоответов пуст."]);
                } else {
                    $buttons = [];
                    foreach ($db['data'] as $index => $item) {
                        $buttons[] = [['text' => $item['text'], 'callback_data' => 'remove_' . $index]];
                    }
                    bot_poll('sendMessage', [
                        'chat_id' => $chat_id,
                        'text' => "🗑 Выберите правило для удаления:",
                        'reply_markup' => json_encode(['inline_keyboard' => $buttons])
                    ]);
                }
            } elseif ($data == 'list_reply') {
                if (count($db['data']) == 0) {
                    bot_poll('sendMessage', ['chat_id' => $chat_id, 'text' => "📭 Список автоответов пуст."]);
                } else {
                    $list = "📋 **Ваши автоответы:**\n\n";
                    foreach ($db['data'] as $item) {
                        $list .= "• *" . $item['text'] . "* → " . count($item['answers']) . " ответ(ов)\n";
                    }
                    bot_poll('sendMessage', [
                        'chat_id' => $chat_id,
                        'text' => $list,
                        'parse_mode' => 'Markdown'
                    ]);
                }
            } elseif (strpos($data, 'remove_') === 0) {
                $index = (int)str_replace('remove_', '', $data);
                if (isset($db['data'][$index])) {
                    $removed_text = $db['data'][$index]['text'];
                    unset($db['data'][$index]);
                    $db['data'] = array_values($db['data']);
                    file_put_contents($db_file, json_encode($db));
                    bot_poll('sendMessage', [
                        'chat_id' => $chat_id,
                        'text' => "✅ Правило *{$removed_text}* удалено!",
                        'parse_mode' => 'Markdown'
                    ]);
                }
            }
        }
        
        // ==================== ОБРАБОТКА БИЗНЕС-СООБЩЕНИЙ (ОТ КЛИЕНТОВ) ====================
        if (isset($update['business_message'])) {
            $msg = $update['business_message'];
            $b_id = $msg['business_connection_id'];
            $text = trim($msg['text'] ?? '');
            $chat_id = $msg['chat']['id'];
            $message_id = $msg['message_id'];
            
            // НЕ отвечаем самому себе (админу)
            if ($chat_id != $admin && !empty($text)) {
                $replied = false;
                foreach ($db['data'] as $item) {
                    $trigger = trim($item['text']);
                    if (!empty($trigger) && stripos($text, $trigger) !== false) {
                        foreach ($item['answers'] as $answer) {
                            bot_poll('sendMessage', [
                                'business_connection_id' => $b_id,
                                'chat_id' => $chat_id,
                                'text' => $answer['content'],
                                'reply_parameters' => json_encode(['message_id' => $message_id])
                            ]);
                        }
                        $replied = true;
                        break;
                    }
                }
                // Опционально: если нет совпадения — стандартный ответ
                if (!$replied) {
                    // bot_poll('sendMessage', [
                    //     'business_connection_id' => $b_id,
                    //     'chat_id' => $chat_id,
                    //     'text' => "Спасибо за сообщение! Я свяжусь с вами.",
                    //     'reply_parameters' => json_encode(['message_id' => $message_id])
                    // ]);
                }
            }
        }
        
        // ==================== ОБРАБОТКА СООБЩЕНИЙ ОТ АДМИНИСТРАТОРА ====================
        if (isset($update['message']) && $update['message']['chat']['id'] == $admin) {
            $text = trim($update['message']['text'] ?? '');
            $chat_id = $update['message']['chat']['id'];
            $step = $db['step'] ?? '';
            
            if ($text == '/start') {
                bot_poll('sendMessage', [
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
            } elseif ($step == 'add-1') {
                if (empty($text)) {
                    bot_poll('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ Отправьте текстовую фразу."]);
                } else {
                    $db['data'][] = ['text' => $text, 'answers' => []];
                    $db['step'] = 'add-2';
                    file_put_contents($db_file, json_encode($db));
                    bot_poll('sendMessage', [
                        'chat_id' => $chat_id,
                        'text' => "✍️ Отправьте ответ (текст, фото, видео, стикер).\n\nМожно добавить несколько ответов. Когда закончите — нажмите /done",
                        'parse_mode' => 'Markdown'
                    ]);
                }
            } elseif ($step == 'add-2') {
                $type = null;
                $content = null;
                
                if (isset($update['message']['text']) && $text != '/done') {
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
                } elseif (isset($update['message']['document'])) {
                    $type = 'document';
                    $content = $update['message']['document']['file_id'];
                } elseif (isset($update['message']['voice'])) {
                    $type = 'voice';
                    $content = $update['message']['voice']['file_id'];
                } elseif (isset($update['message']['audio'])) {
                    $type = 'audio';
                    $content = $update['message']['audio']['file_id'];
                }
                
                if ($type && $content) {
                    end($db['data']);
                    $last_key = key($db['data']);
                    $db['data'][$last_key]['answers'][] = [
                        'type' => $type,
                        'content' => $content,
                        'caption' => $update['message']['caption'] ?? ''
                    ];
                    file_put_contents($db_file, json_encode($db));
                    bot_poll('sendMessage', [
                        'chat_id' => $chat_id,
                        'text' => "✅ Ответ добавлен! Отправьте ещё или /done для завершения."
                    ]);
                } elseif ($text == '/done') {
                    $db['step'] = '';
                    file_put_contents($db_file, json_encode($db));
                    bot_poll('sendMessage', [
                        'chat_id' => $chat_id,
                        'text' => "🎉 Настройка завершена! Ваши автоответы сохранены.",
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [['text' => '📝 Добавить ещё', 'callback_data' => 'add_reply']],
                                [['text' => '📋 Список правил', 'callback_data' => 'list_reply']]
                            ]
                        ])
                    ]);
                } else {
                    bot_poll('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ Неподдерживаемый тип содержимого. Отправьте текст, фото или стикер."]);
                }
            }
        }
        
        // Помечаем обновление как обработанное
        $processed_ids[] = $update_id;
        file_put_contents($processed_ids_file, implode("\n", $processed_ids));
    }
    sleep(1);
}
?>
