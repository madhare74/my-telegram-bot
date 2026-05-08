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

// ==================== КЛАВИАТУРЫ ====================
$home_keyboard = json_encode([
    'keyboard' => [
        [['text' => '📝 Добавить автоответ'], ['text' => '🗑 Удалить автоответ']],
        [['text' => '📋 Список правил']]
    ],
    'resize_keyboard' => true,
    'one_time_keyboard' => false
]);

$back_keyboard = json_encode([
    'keyboard' => [[['text' => '🔙 Назад']]],
    'resize_keyboard' => true
]);

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
        
        // ==================== ОБРАБОТКА НАЖАТИЙ НА КНОПКИ ====================
        if (isset($update['callback_query'])) {
            $callback = $update['callback_query'];
            $callback_id = $callback['id'];
            $chat_id = $callback['message']['chat']['id'];
            $message_id = $callback['message']['message_id'];
            $data = $callback['data'];
            
            // Отвечаем на callback (убираем "часики" на кнопке)
            bot_poll('answerCallbackQuery', ['callback_query_id' => $callback_id]);
            
            // Обработка действий от кнопок
            if ($data == 'add_reply') {
                $db['step'] = 'add-1';
                file_put_contents($db_file, json_encode($db));
                bot_poll('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "📝 Введите ключевое слово или фразу, на которую будет реагировать бот.\n\nПример: `привет`, `цена`, `доставка`",
                    'parse_mode' => 'Markdown',
                    'reply_markup' => $back_keyboard
                ]);
            } elseif ($data == 'remove_reply') {
                if (count($db['data']) == 0) {
                    bot_poll('sendMessage', [
                        'chat_id' => $chat_id,
                        'text' => "📭 Список автоответов пуст. Добавьте новый через 'Добавить автоответ'."
                    ]);
                } else {
                    $list = "🗑 **Выберите правило для удаления:**\n\n";
                    $buttons = [];
                    foreach ($db['data'] as $index => $item) {
                        $list .= "• `" . $item['text'] . "`\n";
                        $buttons[] = [['text' => $item['text'], 'callback_data' => 'remove_' . $index]];
                    }
                    bot_poll('sendMessage', [
                        'chat_id' => $chat_id,
                        'text' => $list,
                        'parse_mode' => 'Markdown',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => $buttons,
                            'resize_keyboard' => true
                        ])
                    ]);
                }
            } elseif ($data == 'list_reply') {
                if (count($db['data']) == 0) {
                    bot_poll('sendMessage', [
                        'chat_id' => $chat_id,
                        'text' => "📭 Список автоответов пуст. Добавьте новый через 'Добавить автоответ'."
                    ]);
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
                    $db['step'] = '';
                    file_put_contents($db_file, json_encode($db));
                    bot_poll('sendMessage', [
                        'chat_id' => $chat_id,
                        'text' => "✅ Правило *{$removed_text}* удалено!",
                        'parse_mode' => 'Markdown'
                    ]);
                } else {
                    bot_poll('sendMessage', [
                        'chat_id' => $chat_id,
                        'text' => "❌ Правило не найдено."
                    ]);
                }
            }
        }
        
        // ==================== ОБРАБОТКА БИЗНЕС-СООБЩЕНИЙ ====================
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
        
        // ==================== ОБРАБОТКА СООБЩЕНИЙ АДМИНИСТРАТОРА ====================
        if (isset($update['message']) && $update['message']['chat']['id'] == $admin) {
            $text = $update['message']['text'] ?? '';
            $chat_id = $update['message']['chat']['id'];
            $message_id = $update['message']['message_id'];
            $step = $db['step'] ?? '';
            
            // Команда /start
            if ($text == '/start') {
                bot_poll('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "🤖 *Бизнес-бот для Telegram*\n\nЯ умею автоматически отвечать на сообщения в вашем бизнес-аккаунте.\n\n📌 *Как настроить:*\n1. Добавьте правило через кнопку ниже\n2. Введите ключевую фразу\n3. Отправьте ответ (текст, фото, видео)\n4. Нажмите Готово\n\n✨ Бот работает в фоне и отвечает от вашего имени!",
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
            // Шаг 1: получение ключевой фразы
            elseif ($step == 'add-1') {
                bot_poll('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "✍️ Отправьте текст, который будет содержать ваш ответ.\n\nЭто может быть текст, фото, видео, стикер или другой файл.\n\n*Нажмите 'Готово', когда закончите добавлять ответы*",
                    'parse_mode' => 'Markdown',
                    'reply_markup' => $back_keyboard
                ]);
                $db['data'][] = ['text' => $text, 'answers' => []];
                $db['step'] = 'add-2';
                file_put_contents($db_file, json_encode($db));
            }
            // Шаг 2: получение ответов (можно несколько)
            elseif ($step == 'add-2') {
                end($db['data']);
                $last_key = key($db['data']);
                
                // Определяем тип контента
                $type = null;
                $content = null;
                
                if (isset($update['message']['text'])) {
                    $type = 'text';
                    $content = $update['message']['text'];
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
                } elseif (isset($update['message']['animation'])) {
                    $type = 'animation';
                    $content = $update['message']['animation']['file_id'];
                }
                
                if ($type && $content) {
                    $db['data'][$last_key]['answers'][] = [
                        'type' => $type,
                        'content' => $content,
                        'caption' => $update['message']['caption'] ?? ''
                    ];
                    file_put_contents($db_file, json_encode($db));
                    
                    bot_poll('sendMessage', [
                        'chat_id' => $chat_id,
                        'text' => "✅ Ответ добавлен!\n\nМожете добавить ещё один ответ или нажмите 'Готово'",
                        'reply_markup' => json_encode([
                            'keyboard' => [[['text' => '✅ Готово'], ['text' => '🔙 Назад']]],
                            'resize_keyboard' => true
                        ])
                    ]);
                }
            }
            // Кнопка "Готово" — завершение добавления
            elseif ($text == '✅ Готово') {
                $db['step'] = '';
                file_put_contents($db_file, json_encode($db));
                bot_poll('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "🎉 Настройка завершена! Ваши автоответы сохранены.\n\nТеперь бот будет отвечать клиентам автоматически.",
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [['text' => '📝 Добавить ещё', 'callback_data' => 'add_reply']],
                            [['text' => '📋 Список правил', 'callback_data' => 'list_reply']]
                        ]
                    ])
                ]);
            }
            // Кнопка "Назад" — отмена
            elseif ($text == '🔙 Назад') {
                $db['step'] = '';
                file_put_contents($db_file, json_encode($db));
                bot_poll('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "⏹ Действие отменено.",
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [['text' => '📝 Добавить автоответ', 'callback_data' => 'add_reply']],
                            [['text' => '🗑 Удалить автоответ', 'callback_data' => 'remove_reply']],
                            [['text' => '📋 Список правил', 'callback_data' => 'list_reply']]
                        ]
                    ])
                ]);
            }
        }
    }
    sleep(1);
}
?>
