<?php
// TOKEN
$token = "7818118293:AAENIdj7bbmYZuqfC_nQTjS-p_GFIWjJKn4";
$admin = "13448282";

// BOT function
function bot($method, $datas = [])
{
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
    if (curl_errno($ch)) {
        error_log("CURL Error: " . curl_error($ch));
        curl_close($ch);
        return (object)['ok' => false];
    }
    curl_close($ch);
    return json_decode($result);
}

// Get update
$update = json_decode(file_get_contents('php://input'));
$b_message = null;
$b_id = null;
$b_text = null;
$b_caption = null;
$b_message_id = null;
$b_chat_id = null;

if (isset($update)) {
    if (isset($update->message)) {
        $message = $update->message;
        $text = $message->text ?? null;
        $chat_id = $message->chat->id ?? null;
        $caption = $message->caption ?? null;
        $sticker_id = $message->sticker->file_id ?? null;
        $video_id = $message->video->file_id ?? null;
        $voice_id = $message->voice->file_id ?? null;
        $file_id = $message->document->file_id ?? null;
        $music_id = $message->audio->file_id ?? null;
        $animation_id = $message->animation->file_id ?? null;
        $video_note_id = $message->video_note->file_id ?? null;
        $photo_id = isset($message->photo[0]) ? $message->photo[0]->file_id : null;
    }
    
    if (isset($update->business_message)) {
        $b_message = $update->business_message;
        $b_id = $b_message->business_connection_id ?? null;
        $b_text = $b_message->text ?? null;
        $b_caption = $b_message->caption ?? null;
        $b_message_id = $b_message->message_id ?? null;
        $b_chat_id = $b_message->chat->id ?? null;
    }
}

// Database
$db_file = 'db.json';
if (!file_exists($db_file)) {
    file_put_contents($db_file, json_encode(["data" => [], "step" => ""]));
}
$db = json_decode(file_get_contents($db_file), true);
if (!is_array($db)) $db = ["data" => [], "step" => ""];
$step = $db['step'] ?? '';

// Keyboards
$home = json_encode(['resize_keyboard' => true, 'keyboard' => [[['text' => "Add auto reply ✉️"]], [['text' => "remove auto reply 🚫"]]]]);
$back = json_encode(['resize_keyboard' => true, 'keyboard' => [[['text' => "Back 🔙"]]]]);

// Commands for admin
if (isset($message, $chat_id) && $chat_id == $admin) {
    if ($text == '/start') {
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "Welcome to Business Account Manager Bot!\n\nAdd your auto-replies using the buttons below.", 'reply_markup' => $home]);
    } elseif ($text == 'Back 🔙' || $text == "Done!") {
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "Main menu.", 'reply_markup' => $home]);
        $db['step'] = "";
        file_put_contents($db_file, json_encode($db));
    } elseif ($text == 'Add auto reply ✉️') {
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "Send me the trigger word/phrase.", 'reply_markup' => $back]);
        $db['step'] = "add-1";
        file_put_contents($db_file, json_encode($db));
    } elseif ($text == 'remove auto reply 🚫') {
        if (count($db['data']) > 0) {
            $list = "Your auto-replies:\n\n";
            foreach ($db['data'] as $item) {
                $list .= "• {$item['text']}\n";
            }
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => $list]);
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "Send the exact phrase you want to remove.", 'reply_markup' => $back]);
            $db['step'] = "remove";
            file_put_contents($db_file, json_encode($db));
        } else {
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "No auto-replies found.", 'reply_markup' => $home]);
        }
    } elseif ($step == 'add-1') {
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "Now send your answer (text, photo, video, etc.).", 'reply_markup' => $back]);
        $db['data'][] = ['text' => $text, 'answers' => []];
        $db['step'] = "add-2";
        file_put_contents($db_file, json_encode($db));
    } elseif ($step == 'add-2') {
        end($db['data']);
        $last_key = key($db['data']);
        $type = null;
        $content = null;
        
        if (isset($text)) {
            $type = "text";
            $content = $text;
        } elseif (isset($sticker_id)) {
            $type = "sticker";
            $content = $sticker_id;
        } elseif (isset($photo_id)) {
            $type = "photo";
            $content = $photo_id;
        } elseif (isset($video_id)) {
            $type = "video";
            $content = $video_id;
        } elseif (isset($voice_id)) {
            $type = "voice";
            $content = $voice_id;
        } elseif (isset($file_id)) {
            $type = "file";
            $content = $file_id;
        } elseif (isset($music_id)) {
            $type = "music";
            $content = $music_id;
        } elseif (isset($animation_id)) {
            $type = "animation";
            $content = $animation_id;
        } elseif (isset($video_note_id)) {
            $type = "video_note";
            $content = $video_note_id;
        }
        
        if ($type && $content) {
            $db['data'][$last_key]["answers"][] = ['type' => $type, 'content' => $content, 'caption' => $caption ?? ''];
            file_put_contents($db_file, json_encode($db));
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ Answer saved! Send another or press Done.", 'reply_markup' => json_encode(['resize_keyboard' => true, 'keyboard' => [[['text' => "Done!"]]]])]);
        }
    } elseif ($step == 'remove') {
        $found = false;
        foreach ($db['data'] as $key => $item) {
            if ($item['text'] == $text) {
                unset($db['data'][$key]);
                $found = true;
            }
        }
        if ($found) {
            $db['data'] = array_values($db['data']);
            $db['step'] = "";
            file_put_contents($db_file, json_encode($db));
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ Removed successfully.", 'reply_markup' => $home]);
        } else {
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ Not found.", 'reply_markup' => $home]);
        }
    }
}

// Handle business messages (auto-reply)
if ($b_message && $b_id && $b_chat_id != $admin) {
    $message_content = trim($b_text ?? $b_caption ?? '');
    if ($message_content === '') exit;
    
    $matched = false;
    foreach ($db['data'] as $item) {
        $trigger = trim($item['text']);
        if (stripos($message_content, $trigger) !== false) {
            foreach ($item['answers'] as $index => $answer) {
                $reply_params = ($index === 0) ? ['message_id' => $b_message_id] : null;
                $send_data = [
                    'business_connection_id' => $b_id,
                    'chat_id' => $b_chat_id,
                    'reply_parameters' => $reply_params
                ];
                
                switch ($answer['type']) {
                    case 'text':
                        $send_data['text'] = $answer['content'];
                        bot('sendMessage', $send_data);
                        break;
                    case 'sticker':
                        $send_data['sticker'] = $answer['content'];
                        bot('sendSticker', $send_data);
                        break;
                    case 'photo':
                        $send_data['photo'] = $answer['content'];
                        if (!empty($answer['caption'])) $send_data['caption'] = $answer['caption'];
                        bot('sendPhoto', $send_data);
                        break;
                    case 'video':
                        $send_data['video'] = $answer['content'];
                        if (!empty($answer['caption'])) $send_data['caption'] = $answer['caption'];
                        bot('sendVideo', $send_data);
                        break;
                }
            }
            $matched = true;
            break;
        }
    }
}
?>
