<?php
// НАСТРОЙКИ (замените на свои)
$token = "7818118293:AAENIdj7bbmYZuqfC_nQTjS-p_GFIWjJKn4";
$admin = "13448282";
$db_file = 'db.json';

// --- ФУНКЦИЯ ОТПРАВКИ СООБЩЕНИЙ ---
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

// --- ИНИЦИАЛИЗАЦИЯ БАЗЫ ДАННЫХ ---
if (!file_exists($db_file)) {
    file_put_contents($db_file, json_encode(["data" => []]));
}
$db = json_decode(file_get_contents($db_file), true);
if (!is_array($db)) $db = ["data" => []];

// --- ПОЛУЧАЕМ ДАННЫЕ ОТ TELEGRAM ---
$input = file_get_contents('php://input');
if (!$input) exit;

$update = json_decode($input, true);
if (!$update) exit;

// ============================================================
// ОСНОВНАЯ ЧАСТЬ: ОБРАБОТКА НОВОГО ТИПА СООБЩЕНИЙ
// ============================================================

// 1. АВТООТВЕТЧИК (обрабатывает сообщения из раздела "Автоматизация чатов")
//    Новое обновление: поле называется 'chat_automation' или подобное.
//    Пока оставим универсальную проверку на наличие chat_id, не равного admin.
if (isset($update['message']) && $update['message']['chat']['id'] != $admin) {
    $msg = $update['message'];
    $chat_id = $msg['chat']['id'];
    $text = trim($msg['text'] ?? '');
    $message_id = $msg['message_id'];
    
    if (!empty($text)) {
        foreach ($db['data'] as $item) {
            $trigger = trim($item['text']);
            if (!empty($trigger) && stripos($text, $trigger) !== false) {
                foreach ($item['answers'] as $answer) {
                    bot_send('sendMessage', [
                        'chat_id' => $chat_id,
                        'text' => $answer['content'],
                        'reply_parameters' => ['message_id' => $message_id]
                    ]);
                }
                break;
            }
        }
    }
}

// 2. ОБРАБОТКА КОМАНД АДМИНИСТРАТОРА (для настройки)
if (isset($update['message']) && $update['message']['chat']['id'] == $admin) {
    $text = trim($update['message']['text'] ?? '');
    $chat_id = $update['message']['chat']['id'];
    
    if ($text == '/start') {
        bot_send('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ Бот обновлён для Telegram 8.0!\n\nАвтоответчик активен. Ваш ID: $admin"
        ]);
    }
    elseif (strpos($text, '/add ') === 0) {
        $trigger = trim(substr($text, 5));
        if (!empty($trigger)) {
            $db['step'] = ['action' => 'waiting_answer', 'trigger' => $trigger, 'chat_id' => $chat_id];
            file_put_contents($db_file, json_encode($db));
            bot_send('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "📝 Ключевое слово '{$trigger}' сохранено! Отправьте ответ."
            ]);
        }
    }
    elseif (isset($db['step']) && $db['step']['action'] == 'waiting_answer' && $db['step']['chat_id'] == $chat_id) {
        $trigger = $db['step']['trigger'];
        $type = 'text';
        $content = $text;
        
        // Определяем тип контента (фото, стикер и т.д.)
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
        
        // Добавляем правило в базу
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
            'text' => "✅ Правило добавлено!\n\nКогда кто-то напишет '{$trigger}', бот ответит."
        ]);
    }
    elseif ($text == '/list') {
        if (count($db['data']) == 0) {
            bot_send('sendMessage', ['chat_id' => $chat_id, 'text' => "📭 Нет правил. Используйте /add [слово]"]);
        } else {
            $list = "📋 **Ваши правила:**\n\n";
            foreach ($db['data'] as $item) {
                $list .= "• *{$item['text']}* — " . count($item['answers']) . " ответ(ов)\n";
            }
            bot_send('sendMessage', ['chat_id' => $chat_id, 'text' => $list, 'parse_mode' => 'Markdown']);
        }
    }
}

// Если ничего не подошло — просто выходим
?>
