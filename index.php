<?php
// TOKEN
$token = "7818118293:AAENIdj7bbmYZuqfC_nQTjS-p_GFIWjJKn4"; // bot token
$admin = "13448282"; // userID of your account

// BOT - ИСПРАВЛЕННАЯ ФУНКЦИЯ С ОБРАБОТКОЙ ОШИБОК
function bot($method, $datas = [])
{
    global $token;
    // Инициализируем curl
    $ch = curl_init();
    
    // Задаем параметры
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.telegram.org/bot' . $token . '/' . $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $datas,
        CURLOPT_HEADER => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    
    // Выполняем запрос
    $result = curl_exec($ch);
    
    // Проверяем на ошибки curl
    if (curl_errno($ch)) {
        error_log("CURL Error in bot() method '" . $method . "': " . curl_error($ch));
        curl_close($ch);
        return (object)['ok' => false, 'error_code' => 500, 'description' => curl_error($ch)];
    }
    
    // Закрываем соединение
    curl_close($ch);
    
    // Декодируем результат
    $decoded = json_decode($result);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Decode Error in bot() method: " . json_last_error_msg());
        return (object)['ok' => false, 'error_code' => 500, 'description' => 'Invalid JSON response from Telegram'];
    }
    
    return $decoded;
}
// ================================================ \\

//UPDATE
$update = json_decode(file_get_contents('php://input'));
if (isset($update)) {
    @$message = $update->message;
    if (isset($message)) {
        @$text = $message->text;
        @$chat_id = $message->chat->id;
        @$caption = $message->caption;
        //file id
        @$sticker_id = $message->sticker->file_id;
        @$video_id = $message->video->file_id;
        @$voice_id = $message->voice->file_id;
        @$file_id = $message->document->file_id;
        @$music_id = $message->audio->file_id;
        @$animation_id = $message->animation->file_id;
        @$video_note_id = $message->video_note->file_id;
        @$photo_id = isset($message->photo) && count($message->photo) > 0 ? $message->photo[0]->file_id : null;
    }

    // business updates
    if (isset($update->business_message)) {
        @$b_message = $update->business_message;
        @$b_id = $b_message->business_connection_id;
        @$b_text = $b_message->text;
        @$b_caption = $b_message->caption;
        @$b_message_id = $b_message->message_id;
        @$b_chat_id = $b_message->chat->id;
    }
    
    // также обрабатываем edited_business_message для редактирования
    if (isset($update->edited_business_message)) {
        @$b_message = $update->edited_business_message;
        @$b_id = $b_message->business_connection_id;
        @$b_text = $b_message->text;
        @$b_message_id = $b_message->message_id;
        @$b_chat_id = $b_message->chat->id;
    }
}
// db
$db_file = 'db.json';
if (!file_exists($db_file)) {
    file_put_contents($db_file, json_encode(["data" => [], "step" => ""]));
}
$db = json_decode(file_get_contents($db_file), true);
if ($db === null) {
    $db = ["data" => [], "step" => ""];
}
$step = isset($db['step']) ? $db['step'] : '';

// keyboards
$home = json_encode(['resize_keyboard' => true, 'keyboard' => [[['text' => "Add auto reply ✉️"]], [['text' => "remove auto reply 🚫"]]]]);
$back = json_encode(['resize_keyboard' => true, 'keyboard' => [[['text' => "Back 🔙"]]]]);
// ================================================ \\

// start message
if (isset($message) and $chat_id == $admin) {

    //handle text messages
    if ($text == '/start') {
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "Hi welcome to Business Account Manager Bot! 🤖\n\nTo use the bot, just go to the telegram business section in your profile, enter the chatbot section and enter the bot username. 💼\n\nNote: Only premium users can use this option. ℹ️", 'reply_markup' => $home]);
    } elseif ($text == 'Back 🔙' || $text == "Done!") {
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "Hi welcome to Business Account Manager Bot! 🤖\n\nTo use the bot, just go to the telegram business section in your profile, enter the chatbot section and enter the bot username. 💼\n\nNote: Only premium users can use this option. ℹ️", 'reply_markup' => $home]);
        $db['step'] = "";
        file_put_contents($db_file, json_encode($db));
    }
    // add AUTO-REPLY
    elseif ($text == 'Add auto reply ✉️') {
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "To set up an auto-reply, type the message you want the bot to reply to (you'll send a reply to this text in the next step)", 'reply_markup' => $back]);
        $db['step'] = "add-1";
        file_put_contents($db_file, json_encode($db));
    }
    // remove existing auto-reply 
    elseif ($text == 'remove auto reply 🚫') {
        if (count($db['data']) > 0) {
            $list = "";
            foreach ($db['data'] as $item) {
                $list .= "<code>{$item['text']}</code>\n---\n";
            }
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => $list, 'parse_mode' => 'html']);
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "To remove an item from the auto-reply, copy and paste one of the above", 'reply_markup' => $back]);
            $db['step'] = "remove";
            file_put_contents($db_file, json_encode($db));
        } else {
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "auto-reply list is empty!", 'reply_markup' => $home]);
        }
    }

    // handle steps
    elseif ($step == 'add-1') {
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ Successfully created.\n\nSend your content to answer this text (it can include any type of content such as: text, photo, video, gif, sticker, voice, etc.)", 'reply_markup' => $back]);
        $db['data'][] = [
            'text' => $text,
            'answers' => []
        ];
        $db['step'] = "add-2";
        file_put_contents($db_file, json_encode($db));
    } elseif ($step == 'add-2') {
        end($db['data']);
        $last_key = key($db['data']);

        // check message type
        if (isset($text)) {
            $type = "text";

            // convert premium emoji
            if (isset($message->entities)) {
                $i = 0;
                foreach ($message->entities as $entity) {
                    if ($entity->type == "custom_emoji") {
                        $offset = $i + $entity->offset;
                        $emoji = '<tg-emoji emoji-id="' . $entity->custom_emoji_id . '">' . mb_substr($text, $offset, 1, "UTF-8") . '</tg-emoji>';
                        $text = mb_substr($text, 0, $offset, "UTF-8")
                            . $emoji
                            . mb_substr($text, $offset + 1, null, "UTF-8");
                        $i = $i + mb_strlen($emoji) - $entity->length;
                    }
                }
            }
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
        
        if (isset($caption)) {
            // convert premium emoji in caption
            if (isset($message->caption_entities)) {
                $i = 0;
                foreach ($message->caption_entities as $entity) {
                    if ($entity->type == "custom_emoji") {
                        $offset = $i + $entity->offset;
                        $emoji = '<tg-emoji emoji-id="' . $entity->custom_emoji_id . '">' . mb_substr($caption, $offset, 1, "UTF-8") . '</tg-emoji>';
                        $caption = mb_substr($caption, 0, $offset, "UTF-8")
                            . $emoji
                            . mb_substr($caption, $offset + 1, null, "UTF-8");
                        $i = $i + mb_strlen($emoji) - $entity->length;
                    }
                }
            }
        }

        // save 
        if (isset($type) and isset($content)) {
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ The answer has been added to your desired text\n\nYou can submit more content or click on 'Done!' ", 'reply_markup' =>  json_encode(['resize_keyboard' => true, 'keyboard' => [[['text' => "Done!"]]]])]);
            $db['data'][$last_key]["answers"][] = [
                'type' => $type,
                'content' => $content,
                'caption' => $caption ?? ''
            ];
            file_put_contents($db_file, json_encode($db));
        } else {
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "There was a problem with the content you sent, please send another content", 'reply_markup' =>  json_encode(['resize_keyboard' => true, 'keyboard' => [[['text' => "Done!"]]]])]);
        }
    } elseif ($step == 'remove') {
        $removed = false;
        foreach ($db['data'] as $key => $item) {
            if ($item['text'] == $text) {
                unset($db['data'][$key]);
                $removed = true;
            }
        }
        if ($removed) {
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ Successfully removed", 'reply_markup' => $home]);
        } else {
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ Text not found in auto-reply list", 'reply_markup' => $home]);
        }
        $db['step'] = "";
        $db['data'] = array_values($db['data']); // reindex array
        file_put_contents($db_file, json_encode($db));
    }
}

// Handle messages to Business Account
if (isset($b_message) && isset($b_id) && $b_chat_id != $admin) {
    // Get message content for comparison
    $message_content = '';
    
    if (!empty($b_text)) {
        $message_content = $b_text;
    } elseif (!empty($b_caption)) {
        $message_content = $b_caption;
    }
    
    // If no text content, skip (can't match)
    if (empty($message_content)) {
        // Optional: send a default response or ignore
        exit;
    }
    
    $message_content_lower = mb_strtolower(trim($message_content));
    $matched = false;
    
    foreach ($db['data'] as $item) {
        $trigger_text_lower = mb_strtolower(trim($item['text']));
        
        // Check for exact match or partial match
        if ($trigger_text_lower == $message_content_lower || 
            strpos($message_content_lower, $trigger_text_lower) !== false) {
            
            foreach ($item['answers'] as $index => $answer) {
                // Правильный формат reply_parameters - массив, НЕ JSON строка
                $reply_params = ($index == 0 && isset($b_message_id)) ? ['message_id' => $b_message_id] : null;
                
                switch ($answer["type"]) {
                    case "text":
                        bot('sendMessage', [
                            'business_connection_id' => $b_id, 
                            'chat_id' => $b_chat_id, 
                            'text' => $answer['content'], 
                            'parse_mode' => "html", 
                            'disable_web_page_preview' => true, 
                            'reply_parameters' => $reply_params
                        ]);
                        break;
                    case "sticker":
                        $params = [
                            'business_connection_id' => $b_id, 
                            'chat_id' => $b_chat_id, 
                            'sticker' => $answer['content'], 
                            'reply_parameters' => $reply_params
                        ];
                        if (!empty($answer['caption'])) {
                            $params['caption'] = $answer['caption'];
                        }
                        bot('sendSticker', $params);
                        break;
                    case "photo":
                        $params = [
                            'business_connection_id' => $b_id, 
                            'chat_id' => $b_chat_id, 
                            'photo' => $answer['content'], 
                            'reply_parameters' => $reply_params
                        ];
                        if (!empty($answer['caption'])) {
                            $params['caption'] = $answer['caption'];
                            $params['parse_mode'] = "html";
                        }
                        bot('sendPhoto', $params);
                        break;
                    case "video":
                        $params = [
                            'business_connection_id' => $b_id, 
                            'chat_id' => $b_chat_id, 
                            'video' => $answer['content'], 
                            'reply_parameters' => $reply_params
                        ];
                        if (!empty($answer['caption'])) {
                            $params['caption'] = $answer['caption'];
                            $params['parse_mode'] = "html";
                        }
                        bot('sendVideo', $params);
                        break;
                    case "voice":
                        $params = [
                            'business_connection_id' => $b_id, 
                            'chat_id' => $b_chat_id, 
                            'voice' => $answer['content'], 
                            'reply_parameters' => $reply_params
                        ];
                        if (!empty($answer['caption'])) {
                            $params['caption'] = $answer['caption'];
                        }
                        bot('sendVoice', $params);
                        break;
                    case "file":
                        $params = [
                            'business_connection_id' => $b_id, 
                            'chat_id' => $b_chat_id, 
                            'document' => $answer['content'], 
                            'reply_parameters' => $reply_params
                        ];
                        if (!empty($answer['caption'])) {
                            $params['caption'] = $answer['caption'];
                            $params['parse_mode'] = "html";
                        }
                        bot('sendDocument', $params);
                        break;
                    case "music":
                        $params = [
                            'business_connection_id' => $b_id, 
                            'chat_id' => $b_chat_id, 
                            'audio' => $answer['content'], 
                            'reply_parameters' => $reply_params
                        ];
                        if (!empty($answer['caption'])) {
                            $params['caption'] = $answer['caption'];
                            $params['parse_mode'] = "html";
                        }
                        bot('sendAudio', $params);
                        break;
                    case "animation":
                        $params = [
                            'business_connection_id' => $b_id, 
                            'chat_id' => $b_chat_id, 
                            'animation' => $answer['content'], 
                            'reply_parameters' => $reply_params
                        ];
                        if (!empty($answer['caption'])) {
                            $params['caption'] = $answer['caption'];
                            $params['parse_mode'] = "html";
                        }
                        bot('sendAnimation', $params);
                        break;
                    case "video_note":
                        bot('sendVideoNote', [
                            'business_connection_id' => $b_id, 
                            'chat_id' => $b_chat_id, 
                            'video_note' => $answer['content'], 
                            'reply_parameters' => $reply_params
                        ]);
                        break;
                }
            }
            $matched = true;
            break;
        }
    }
    
    // Optional: send a default response if no match found
    if (!$matched) {
        // Uncomment the line below if you want a default response
        // bot('sendMessage', [
        //     'business_connection_id' => $b_id, 
        //     'chat_id' => $b_chat_id, 
        //     'text' => "Thank you for your message! I'll get back to you soon.",
        //     'reply_parameters' => ['message_id' => $b_message_id]
        // ]);
    }
}
?>
