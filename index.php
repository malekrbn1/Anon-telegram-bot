<?php

$BOT_TOKEN     = "8402908611:AAFduJ2ho-RkNd6mztDvLb5O9FBk7ED7bxM";
$ADMIN_ID      = 5986250975;
$BOT_USERNAME  = "malekeshambot";

$DATA_DIR          = __DIR__;
$USERS_FILE        = $DATA_DIR . "/users.json";
$SESSIONS_FILE     = $DATA_DIR . "/sessions.json";
$REQUESTS_FILE     = $DATA_DIR . "/requests.json";
$SPAM_FILE         = $DATA_DIR . "/spam.json";
$STATS_FILE        = $DATA_DIR . "/stats.json";
$CUSTOM_LINKS_FILE = $DATA_DIR . "/custom_links.json";

function bot($m, $d = []) {
    global $BOT_TOKEN;
    $u = "https://api.telegram.org/bot{$BOT_TOKEN}/{$m}";
    $ch = curl_init($u);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($d, JSON_UNESCAPED_UNICODE),
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode($r, true);
}

function sendMessage($cid, $t, $reply_to = null, $kb = null) {
    $d = [
        'chat_id'    => $cid,
        'text'       => $t,
        'parse_mode' => 'HTML',
    ];
    if ($reply_to) $d['reply_to_message_id'] = $reply_to;
    if ($kb) $d['reply_markup'] = $kb;
    return bot("sendMessage", $d);
}

function load_json($f) {
    if (!file_exists($f)) return [];
    $j = file_get_contents($f);
    $d = json_decode($j, true);
    return is_array($d) ? $d : [];
}

function save_json($f, $d) {
    file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function generate_token($len = 16) {
    $c = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $r = '';
    for ($i = 0; $i < $len; $i++) $r .= $c[random_int(0, strlen($c) - 1)];
    return $r;
}

function add_user($u) {
    global $USERS_FILE;
    $users = load_json($USERS_FILE);
    $id = $u['id'];
    if (!isset($users[$id])) {
        $users[$id] = [
            'id'       => $id,
            'username' => $u['username'] ?? '',
            'first'    => $u['first_name'] ?? '',
            'approved' => false,
            'blocked'  => false,
            'stats'    => ['sent_anon' => 0, 'received_anon' => 0],
            'link'     => [
                'token'      => null,
                'expires_at' => null,
                'one_time'   => false,
                'used'       => false,
            ],
        ];
    } else {
        $users[$id]['username'] = $u['username'] ?? $users[$id]['username'];
        $users[$id]['first']    = $u['first_name'] ?? $users[$id]['first'];
        if (!isset($users[$id]['blocked'])) $users[$id]['blocked'] = false;
        if (!isset($users[$id]['stats'])) $users[$id]['stats'] = ['sent_anon' => 0, 'received_anon' => 0];
        if (!isset($users[$id]['link'])) {
            $users[$id]['link'] = [
                'token'      => null,
                'expires_at' => null,
                'one_time'   => false,
                'used'       => false,
            ];
        }
    }
    save_json($USERS_FILE, $users);
}

function set_session($uid, $tid, $type, $token = null) {
    global $SESSIONS_FILE;
    $s = load_json($SESSIONS_FILE);
    $s[$uid] = [
        'target_id'   => $tid,
        'target_type' => $type,
        'token'       => $token,
        'time'        => time(),
    ];
    save_json($SESSIONS_FILE, $s);
}

function get_session($uid) {
    global $SESSIONS_FILE;
    $s = load_json($SESSIONS_FILE);
    return $s[$uid] ?? null;
}

function clear_session($uid) {
    global $SESSIONS_FILE;
    $s = load_json($SESSIONS_FILE);
    if (isset($s[$uid])) {
        unset($s[$uid]);
        save_json($SESSIONS_FILE, $s);
    }
}

function add_request($uid, $type, $slug = null) {
    global $REQUESTS_FILE;
    $r = load_json($REQUESTS_FILE);
    $r[$uid] = [
        'user_id' => $uid,
        'type'    => $type,
        'slug'    => $slug,
        'time'    => time(),
    ];
    save_json($REQUESTS_FILE, $r);
}

function remove_request($uid) {
    global $REQUESTS_FILE;
    $r = load_json($REQUESTS_FILE);
    if (isset($r[$uid])) {
        unset($r[$uid]);
        save_json($REQUESTS_FILE, $r);
    }
}

function has_request($uid) {
    global $REQUESTS_FILE;
    $r = load_json($REQUESTS_FILE);
    return isset($r[$uid]);
}

function approve_user_link($uid) {
    global $USERS_FILE;
    $u = load_json($USERS_FILE);
    if (!isset($u[$uid])) {
        $u[$uid] = [
            'id'       => $uid,
            'username' => '',
            'first'    => '',
            'approved' => true,
            'blocked'  => false,
            'stats'    => ['sent_anon' => 0, 'received_anon' => 0],
            'link'     => [
                'token'      => null,
                'expires_at' => null,
                'one_time'   => false,
                'used'       => false,
            ],
        ];
    } else {
        $u[$uid]['approved'] = true;
        if (!isset($u[$uid]['link'])) {
            $u[$uid]['link'] = [
                'token'      => null,
                'expires_at' => null,
                'one_time'   => false,
                'used'       => false,
            ];
        }
    }
    save_json($USERS_FILE, $u);
}

function load_custom_links() {
    global $CUSTOM_LINKS_FILE;
    return load_json($CUSTOM_LINKS_FILE);
}

function save_custom_links($d) {
    global $CUSTOM_LINKS_FILE;
    save_json($CUSTOM_LINKS_FILE, $d);
}

function set_custom_link($uid, $slug) {
    $l = load_custom_links();
    $l[$slug] = $uid;
    save_custom_links($l);
}

function get_user_by_custom_slug($slug) {
    $l = load_custom_links();
    return $l[$slug] ?? null;
}

function get_user_by_token($token) {
    global $USERS_FILE;
    $u = load_json($USERS_FILE);
    foreach ($u as $x) {
        if (!empty($x['link']['token']) && $x['link']['token'] === $token) return $x['id'];
    }
    return null;
}

function check_spam($uid) {
    global $SPAM_FILE;
    $s = load_json($SPAM_FILE);
    $now = time();
    $limit = 5;
    $max = 20;
    if (!isset($s[$uid])) {
        $s[$uid] = ['last_time' => 0, 'count_min' => 0, 'window' => $now];
    }
    $d = $s[$uid];
    if ($now - $d['window'] >= 60) {
        $d['window'] = $now;
        $d['count_min'] = 0;
    }
    if ($now - $d['last_time'] < $limit) {
        $s[$uid] = $d;
        save_json($SPAM_FILE, $s);
        return false;
    }
    if ($d['count_min'] >= $max) {
        $s[$uid] = $d;
        save_json($SPAM_FILE, $s);
        return false;
    }
    $d['last_time'] = $now;
    $d['count_min']++;
    $s[$uid] = $d;
    save_json($SPAM_FILE, $s);
    return true;
}

function inc_stat($k) {
    global $STATS_FILE;
    $s = load_json($STATS_FILE);
    if (!isset($s[$k])) $s[$k] = 0;
    $s[$k]++;
    save_json($STATS_FILE, $s);
}

function get_stats() {
    global $STATS_FILE, $USERS_FILE;
    $s = load_json($STATS_FILE);
    $u = load_json($USERS_FILE);
    $s['total_users'] = count($u);
    return $s;
}

function get_user_profile_photo_id($uid) {
    $p = bot('getUserProfilePhotos', ['user_id' => $uid, 'limit' => 1]);
    if (!empty($p['ok']) && !empty($p['result']['photos'][0][0]['file_id'])) {
        return $p['result']['photos'][0][0]['file_id'];
    }
    return null;
}

$update = json_decode(file_get_contents("php://input"), true);
if (!$update) { echo "OK"; exit; }

$message = $update['message'] ?? null;
$callback = $update['callback_query'] ?? null;

if ($callback) {
    global $ADMIN_ID, $BOT_USERNAME, $USERS_FILE;
    $cb_id = $callback['id'];
    $data  = $callback['data'] ?? '';
    $from_id = $callback['from']['id'];
    $msg = $callback['message'] ?? null;

    if ($from_id != $ADMIN_ID) {
        bot('answerCallbackQuery', [
            'callback_query_id' => $cb_id,
            'text'              => 'فقط ادمین می‌تواند این دکمه را استفاده کند.',
            'show_alert'        => true,
        ]);
        exit;
    }

    if (strpos($data, 'approve_normal:') === 0) {
        $uid = (int)substr($data, 15);
        approve_user_link($uid);
        remove_request($uid);
        $users = load_json($USERS_FILE);
        if (empty($users[$uid]['link']['token'])) {
            $users[$uid]['link']['token'] = generate_token(16);
            save_json($USERS_FILE, $users);
        }
        $token = $users[$uid]['link']['token'];
        $link = "https://t.me/{$BOT_USERNAME}?start={$token}";
        bot('answerCallbackQuery', [
            'callback_query_id' => $cb_id,
            'text'              => 'لینک کاربر تأیید شد.',
            'show_alert'        => false,
        ]);
        if ($msg) {
            bot('editMessageText', [
                'chat_id'    => $msg['chat']['id'],
                'message_id' => $msg['message_id'],
                'text'       => "درخواست لینک ناشناس کاربر تأیید شد.\n\nUserID: <a href=\"tg://user?id={$uid}\">{$uid}</a>\nلینک:\n{$link}",
                'parse_mode' => 'HTML',
            ]);
        }
        sendMessage($uid, "درخواست لینک ناشناس شما توسط ادمین تأیید شد ✅\n\nلینک شما:\n{$link}");
        exit;
    }

    if (strpos($data, 'reject_normal:') === 0) {
        $uid = (int)substr($data, 15);
        remove_request($uid);
        bot('answerCallbackQuery', [
            'callback_query_id' => $cb_id,
            'text'              => 'درخواست رد شد.',
            'show_alert'        => false,
        ]);
        if ($msg) {
            bot('editMessageText', [
                'chat_id'    => $msg['chat']['id'],
                'message_id' => $msg['message_id'],
                'text'       => "درخواست لینک ناشناس کاربر رد شد.\n\nUserID: <a href=\"tg://user?id={$uid}\">{$uid}</a>",
                'parse_mode' => 'HTML',
            ]);
        }
        sendMessage($uid, "درخواست لینک ناشناس شما توسط ادمین رد شد ❌");
        exit;
    }

    if (strpos($data, 'approve_custom:') === 0) {
        $uid = (int)substr($data, 15);
        $req = load_json($REQUESTS_FILE);
        $slug = $req[$uid]['slug'] ?? null;
        if (!$slug) {
            bot('answerCallbackQuery', [
                'callback_query_id' => $cb_id,
                'text'              => 'اسلاگ یافت نشد.',
                'show_alert'        => true,
            ]);
            exit;
        }
        $links = load_custom_links();
        if (isset($links[$slug])) {
            bot('answerCallbackQuery', [
                'callback_query_id' => $cb_id,
                'text'              => 'این اسلاگ قبلاً استفاده شده.',
                'show_alert'        => true,
            ]);
            exit;
        }
        set_custom_link($uid, $slug);
        remove_request($uid);
        $link = "https://t.me/{$BOT_USERNAME}?start=custom_{$slug}";
        bot('answerCallbackQuery', [
            'callback_query_id' => $cb_id,
            'text'              => 'لینک اختصاصی تأیید شد.',
            'show_alert'        => false,
        ]);
        if ($msg) {
            bot('editMessageText', [
                'chat_id'    => $msg['chat']['id'],
                'message_id' => $msg['message_id'],
                'text'       => "درخواست لینک اختصاصی کاربر تأیید شد.\n\nUserID: <a href=\"tg://user?id={$uid}\">{$uid}</a>\nاسلاگ: {$slug}\nلینک:\n{$link}",
                'parse_mode' => 'HTML',
            ]);
        }
        sendMessage($uid, "درخواست لینک اختصاصی شما توسط ادمین تأیید شد ✅\n\nلینک اختصاصی شما:\n{$link}");
        exit;
    }

    if (strpos($data, 'reject_custom:') === 0) {
        $uid = (int)substr($data, 15);
        remove_request($uid);
        bot('answerCallbackQuery', [
            'callback_query_id' => $cb_id,
            'text'              => 'درخواست رد شد.',
            'show_alert'        => false,
        ]);
        if ($msg) {
            bot('editMessageText', [
                'chat_id'    => $msg['chat']['id'],
                'message_id' => $msg['message_id'],
                'text'       => "درخواست لینک اختصاصی کاربر رد شد.\n\nUserID: <a href=\"tg://user?id={$uid}\">{$uid}</a>",
                'parse_mode' => 'HTML',
            ]);
        }
        sendMessage($uid, "درخواست لینک اختصاصی شما توسط ادمین رد شد ❌");
        exit;
    }

    bot('answerCallbackQuery', [
        'callback_query_id' => $cb_id,
        'text'              => 'دکمه نامعتبر.',
        'show_alert'        => false,
    ]);
    exit;
}

if ($message) {
    global $ADMIN_ID, $USERS_FILE, $BOT_USERNAME;

    $chat_id  = $message['chat']['id'];
    $from     = $message['from'];
    $from_id  = $from['id'];
    $username = $from['username'] ?? '';
    $first    = $from['first_name'] ?? '';
    $text     = $message['text'] ?? '';
    $msg_id   = $message['message_id'];
    $reply_to = $message['reply_to_message'] ?? null;

    add_user($from);
    $users = load_json($USERS_FILE);

    if ($from_id != $ADMIN_ID) {
        if (!check_spam($from_id)) {
            sendMessage($chat_id, "⏱ لطفاً کمی صبر کنید و بعد دوباره پیام بفرستید.");
            exit;
        }
    }

    if ($from_id != $ADMIN_ID && isset($users[$from_id]) && !empty($users[$from_id]['blocked'])) {
        sendMessage($chat_id, "🚫 شما توسط ادمین بلاک شده‌اید.");
        exit;
    }

    $start_param = null;
    if (isset($text) && strpos($text, '/start') === 0) {
        $p = explode(' ', $text, 2);
        if (isset($p[1])) $start_param = trim($p[1]);
    }

    if ($from_id == $ADMIN_ID && $reply_to) {
        $rtxt = $reply_to['caption'] ?? $reply_to['text'] ?? '';
        if (preg_match('/SenderID:\s*([\d]+)/', $rtxt, $m)) {
            $target_id = (int)$m[1];
            if (!empty($text) && strpos($text, '/start') !== 0 && strpos($text, '/') !== 0) {
                sendMessage($target_id, "📬 <b>پیام از طرف ادمین:</b>\n\n{$text}");
                sendMessage($ADMIN_ID, "پیام برای کاربر <a href=\"tg://user?id={$target_id}\">{$target_id}</a> ارسال شد ✔️");
                exit;
            }
            if (!empty($message['photo']) ||
                !empty($message['document']) ||
                !empty($message['video']) ||
                !empty($message['voice']) ||
                !empty($message['audio']) ||
                !empty($message['animation']) ||
                !empty($message['sticker'])) {
                bot('copyMessage', [
                    'from_chat_id' => $from_id,
                    'chat_id'      => $target_id,
                    'message_id'   => $msg_id,
                ]);
                sendMessage($ADMIN_ID, "مدیا برای کاربر <a href=\"tg://user?id={$target_id}\">{$target_id}</a> ارسال شد ✔️");
                exit;
            }
        }
    }

    if ($from_id == $ADMIN_ID && isset($text)) {
        if ($text == '/panel') {
            $stats = get_stats();
            $total_users = $stats['total_users'] ?? 0;
            $total_sent  = $stats['sent_anon'] ?? 0;
            $total_recv  = $stats['received_anon'] ?? 0;
            $total_links = 0;
            foreach ($users as $u) if (!empty($u['approved'])) $total_links++;
            $msg = "📊 <b>پنل ادمین</b>\n\n"
                 . "👥 کاربران: <b>{$total_users}</b>\n"
                 . "🔗 لینک‌های فعال: <b>{$total_links}</b>\n"
                 . "📨 پیام‌های ناشناس ارسال‌شده: <b>{$total_sent}</b>\n"
                 . "📥 پیام‌های ناشناس دریافت‌شده: <b>{$total_recv}</b>\n\n"
                 . "/users - لیست کاربران\n"
                 . "/user ID - اطلاعات کاربر\n"
                 . "/broadcast متن - پیام همگانی\n"
                 . "/block ID - بلاک\n"
                 . "/unblock ID - آن‌بلاک\n"
                 . "/mylink - لینک ناشناس ادمین\n";
            sendMessage($chat_id, $msg);
            exit;
        }

        if ($text == '/users') {
            if (!$users) {
                sendMessage($ADMIN_ID, "هیچ کاربری ثبت نشده است.");
                exit;
            }
            $out = "👥 <b>لیست کاربران:</b>\n\n";
            $i = 1;
            foreach ($users as $u) {
                $u_un = $u['username'] ? '@' . $u['username'] : 'بدون یوزرنیم';
                $uid  = $u['id'];
                $out .= $i++ . ") {$u_un} — <a href=\"tg://user?id={$uid}\">{$uid}</a>"
                      . (!empty($u['approved']) ? " ✅" : "")
                      . (!empty($u['blocked']) ? " 🚫" : "")
                      . "\n";
            }
            sendMessage($ADMIN_ID, $out);
            exit;
        }

        if (strpos($text, '/broadcast ') === 0) {
            $msg_b = trim(substr($text, 11));
            if ($msg_b == '') {
                sendMessage($ADMIN_ID, "متن پیام همگانی خالی است.");
                exit;
            }
            $c = 0;
            foreach ($users as $uid => $u) {
                bot('sendMessage', [
                    'chat_id'    => $uid,
                    'text'       => "📢 <b>پیام از طرف ادمین:</b>\n\n{$msg_b}",
                    'parse_mode' => 'HTML',
                ]);
                $c++;
            }
            sendMessage($ADMIN_ID, "پیام همگانی برای {$c} کاربر ارسال شد.");
            exit;
        }

        if (strpos($text, '/block ') === 0) {
            $uid = (int)trim(substr($text, 7));
            if (!$uid || !isset($users[$uid])) {
                sendMessage($ADMIN_ID, "کاربر یافت نشد.");
                exit;
            }
            $users[$uid]['blocked'] = true;
            save_json($USERS_FILE, $users);
            sendMessage($ADMIN_ID, "کاربر <a href=\"tg://user?id={$uid}\">{$uid}</a> بلاک شد.");
            exit;
        }

        if (strpos($text, '/unblock ') === 0) {
            $uid = (int)trim(substr($text, 9));
            if (!$uid || !isset($users[$uid])) {
                sendMessage($ADMIN_ID, "کاربر یافت نشد.");
                exit;
            }
            $users[$uid]['blocked'] = false;
            save_json($USERS_FILE, $users);
            sendMessage($ADMIN_ID, "کاربر <a href=\"tg://user?id={$uid}\">{$uid}</a> آن‌بلاک شد.");
            exit;
        }

        if (strpos($text, '/user ') === 0) {
            $uid = (int)trim(substr($text, 6));
            if (!$uid || !isset($users[$uid])) {
                sendMessage($ADMIN_ID, "کاربر یافت نشد.");
                exit;
            }
            $u = $users[$uid];
            $u_un = $u['username'] ? '@' . $u['username'] : 'بدون یوزرنیم';
            $approved = !empty($u['approved']) ? '✅' : '❌';
            $blocked  = !empty($u['blocked']) ? '🚫' : '✅';
            $sent     = $u['stats']['sent_anon'] ?? 0;
            $recv     = $u['stats']['received_anon'] ?? 0;
            $token    = $u['link']['token'] ?? null;
            if ($token) $link = "https://t.me/{$BOT_USERNAME}?start={$token}";
            else        $link = "https://t.me/{$BOT_USERNAME}?start=user_{$uid}";
            $msg = "👤 <b>اطلاعات کاربر</b>\n\n"
                 . "ID: <a href=\"tg://user?id={$uid}\">{$uid}</a>\n"
                 . "Username: {$u_un}\n"
                 . "نام: {$u['first']}\n"
                 . "لینک فعال: {$approved}\n"
                 . "بلاک: {$blocked}\n\n"
                 . "📨 ارسال ناشناس: <b>{$sent}</b>\n"
                 . "📥 دریافت ناشناس: <b>{$recv}</b>\n\n"
                 . "🔗 لینک:\n<code>{$link}</code>";
            sendMessage($ADMIN_ID, $msg);
            exit;
        }

        if ($text == '/mylink') {
            $bot_username = $BOT_USERNAME;
            $admin_link = "https://t.me/{$bot_username}?start=anon";
            sendMessage($ADMIN_ID, "لینک ناشناس ادمین:\n<a href=\"{$admin_link}\">{$admin_link}</a>");
            exit;
        }

        if ($text == '/start') {
            $bot_username = $BOT_USERNAME;
            $admin_link = "https://t.me/{$bot_username}?start=anon";
            $msg = "👑 <b>خوش آمدی ادمین</b>\n\n"
                 . "🔗 لینک ناشناس شما:\n<a href=\"{$admin_link}\">{$admin_link}</a>\n\n"
                 . "برای مدیریت از /panel استفاده کنید.";
            sendMessage($chat_id, $msg);
            exit;
        }
    }

    if ($text == '/mylink' && $from_id != $ADMIN_ID) {
        if (has_request($from_id)) {
            sendMessage($chat_id, "درخواست شما قبلاً ثبت شده و در انتظار تأیید ادمین است.");
            exit;
        }
        add_request($from_id, 'normal');
        sendMessage($chat_id, "درخواست لینک ناشناس شما برای ادمین ارسال شد ✅");
        $kb = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ تأیید', 'callback_data' => 'approve_normal:' . $from_id],
                    ['text' => '❌ رد',   'callback_data' => 'reject_normal:' . $from_id],
                ]
            ]
        ];
        $txt = "درخواست لینک ناشناس جدید:\n\n"
             . "SenderID: <a href=\"tg://user?id={$from_id}\">{$from_id}</a>\n"
             . ($username ? "Username: @{$username}\n" : "")
             . "نام: {$first}";
        sendMessage($ADMIN_ID, $txt, null, $kb);
        exit;
    }

    if (strpos($text, '/custom_link') === 0 && $from_id != $ADMIN_ID) {
        if (has_request($from_id)) {
            sendMessage($chat_id, "درخواست شما قبلاً ثبت شده و در انتظار بررسی ادمین است.");
            exit;
        }
        $parts = explode(' ', $text, 2);
        if (count($parts) < 2 || trim($parts[1]) == '') {
            sendMessage($chat_id, "برای درخواست لینک اختصاصی، اسلاگ را هم بنویس:\n\n/custom_link myslug");
            exit;
        }
        $slug = trim($parts[1]);
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $slug)) {
            sendMessage($chat_id, "اسلاگ فقط می‌تواند شامل حروف، اعداد و زیرخط باشد.");
            exit;
        }
        $links = load_custom_links();
        if (isset($links[$slug])) {
            sendMessage($chat_id, "این اسلاگ قبلاً استفاده شده است.");
            exit;
        }
        add_request($from_id, 'custom', $slug);
        $kb = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ تأیید', 'callback_data' => 'approve_custom:' . $from_id],
                    ['text' => '❌ رد',   'callback_data' => 'reject_custom:' . $from_id],
                ]
            ]
        ];
        $txt = "درخواست لینک اختصاصی جدید:\n\n"
             . "SenderID: <a href=\"tg://user?id={$from_id}\">{$from_id}</a>\n"
             . ($username ? "Username: @{$username}\n" : "")
             . "نام: {$first}\n"
             . "اسلاگ پیشنهادی: <code>{$slug}</code>";
        sendMessage($ADMIN_ID, $txt, null, $kb);
        sendMessage($chat_id, "درخواست لینک اختصاصی شما برای ادمین ارسال شد ✅");
        exit;
    }

    if ($text == '/mypanel') {
        $u = $users[$from_id] ?? null;
        if (!$u) {
            sendMessage($chat_id, "شما هنوز در سیستم ثبت نشده‌اید. یک بار /start بزنید.");
            exit;
        }
        $approved = !empty($u['approved']);
        $blocked  = !empty($u['blocked']);
        $sent     = $u['stats']['sent_anon'] ?? 0;
        $recv     = $u['stats']['received_anon'] ?? 0;
        $uid      = $u['id'];
        $u_un     = $u['username'] ? '@' . $u['username'] : 'بدون یوزرنیم';
        $token    = $u['link']['token'] ?? null;
        if ($token) $link = "https://t.me/{$BOT_USERNAME}?start={$token}";
        else        $link = "https://t.me/{$BOT_USERNAME}?start=user_{$uid}";
        $msg = "🧾 <b>پنل کاربری شما</b>\n\n"
             . "👤 نام: {$u['first']}\n"
             . "🆔 آیدی عددی: <a href=\"tg://user?id={$uid}\">{$uid}</a>\n"
             . "🔹 یوزرنیم: {$u_un}\n"
             . "🚫 وضعیت بلاک: " . ($blocked ? 'بلاک شده' : 'آزاد') . "\n"
             . "🔗 وضعیت لینک: " . ($approved ? 'فعال ✅' : 'غیرفعال ❌') . "\n\n"
             . "📨 پیام‌های ناشناس ارسال‌شده: <b>{$sent}</b>\n"
             . "📥 پیام‌های ناشناس دریافت‌شده: <b>{$recv}</b>\n\n";
        if ($approved) {
            $msg .= "🔗 لینک ناشناس شما:\n<code>{$link}</code>\n\n"
                  . "برای لینک اختصاصی می‌توانید /custom_link slug را ارسال کنید.\n";
        } else {
            $msg .= "برای فعال شدن لینک ناشناس، ابتدا با /mylink درخواست بدهید.\n";
        }
        sendMessage($chat_id, $msg);
        exit;
    }

    if (isset($text) && strpos($text, '/start') === 0) {
        $users = load_json($USERS_FILE);
        if ($start_param === 'anon') {
            if ($from_id == $ADMIN_ID) {
                sendMessage($chat_id, "❌ نمی‌توانید به خودتان پیام ناشناس ارسال کنید.");
                exit;
            }
            set_session($from_id, $ADMIN_ID, 'admin', 'anon');
            sendMessage($chat_id, "شما در حالت ارسال پیام ناشناس قرار گرفتید.\nشناسه لینک: anon\nاولین پیام شما به صورت ناشناس ارسال خواهد شد.");
            exit;
        }

        if ($start_param && strpos($start_param, 'custom_') === 0) {
            $slug = substr($start_param, 7);
            $target_id = get_user_by_custom_slug($slug);
            if (!$target_id) {
                sendMessage($chat_id, "این لینک اختصاصی معتبر نیست.");
                exit;
            }
            if ($target_id == $from_id) {
                sendMessage($chat_id, "❌ نمی‌توانید به خودتان پیام ناشناس ارسال کنید.");
                exit;
            }
            if (empty($users[$target_id]['approved'])) {
                sendMessage($chat_id, "این لینک ناشناس فعال نیست.");
                exit;
            }
            $link_info = $users[$target_id]['link'] ?? ['expires_at' => null, 'one_time' => false, 'used' => false, 'token' => null];
            if ($link_info['expires_at'] && time() > $link_info['expires_at']) {
                sendMessage($chat_id, "⏱ این لینک ناشناس منقضی شده است.");
                exit;
            }
            if (!empty($link_info['one_time']) && !empty($link_info['used'])) {
                sendMessage($chat_id, "این لینک ناشناس یک‌بارمصرف بوده و قبلاً استفاده شده است.");
                exit;
            }
            set_session($from_id, $target_id, 'user', $slug);
            sendMessage($chat_id, "شما در حالت ارسال پیام ناشناس قرار گرفتید.\nشناسه لینک: {$slug}\nاولین پیام شما به صورت ناشناس ارسال خواهد شد.");
            exit;
        }

        if ($start_param && !in_array(substr($start_param, 0, 5), ['user_', 'cust_', 'anon_'])) {
            $target_id = get_user_by_token($start_param);
            if ($target_id) {
                if ($target_id == $from_id) {
                    sendMessage($chat_id, "❌ نمی‌توانید به خودتان پیام ناشناس ارسال کنید.");
                    exit;
                }
                if (empty($users[$target_id]['approved'])) {
                    sendMessage($chat_id, "این لینک ناشناس فعال نیست.");
                    exit;
                }
                $link_info = $users[$target_id]['link'] ?? ['expires_at' => null, 'one_time' => false, 'used' => false, 'token' => $start_param];
                if ($link_info['expires_at'] && time() > $link_info['expires_at']) {
                    sendMessage($chat_id, "⏱ این لینک ناشناس منقضی شده است.");
                    exit;
                }
                if (!empty($link_info['one_time']) && !empty($link_info['used'])) {
                    sendMessage($chat_id, "این لینک ناشناس یک‌بارمصرف بوده و قبلاً استفاده شده است.");
                    exit;
                }
                set_session($from_id, $target_id, 'user', $start_param);
                sendMessage($chat_id, "شما در حالت ارسال پیام ناشناس قرار گرفتید.\nشناسه لینک: {$start_param}\nاولین پیام شما به صورت ناشناس ارسال خواهد شد.");
                exit;
            }
        }

        if ($start_param && strpos($start_param, 'user_') === 0) {
            $target_id = (int)substr($start_param, 5);
            if ($target_id > 0) {
                if ($target_id == $from_id) {
                    sendMessage($chat_id, "❌ نمی‌توانید به خودتان پیام ناشناس ارسال کنید.");
                    exit;
                }
                if (empty($users[$target_id]['approved'])) {
                    sendMessage($chat_id, "این لینک ناشناس فعال نیست.");
                    exit;
                }
                $link_info = $users[$target_id]['link'] ?? ['expires_at' => null, 'one_time' => false, 'used' => false, 'token' => null];
                if ($link_info['expires_at'] && time() > $link_info['expires_at']) {
                    sendMessage($chat_id, "⏱ این لینک ناشناس منقضی شده است.");
                    exit;
                }
                if (!empty($link_info['one_time']) && !empty($link_info['used'])) {
                    sendMessage($chat_id, "این لینک ناشناس یک‌بارمصرف بوده و قبلاً استفاده شده است.");
                    exit;
                }
                set_session($from_id, $target_id, 'user', "user_{$target_id}");
                sendMessage($chat_id, "شما در حالت ارسال پیام ناشناس قرار گرفتید.\nشناسه لینک: user_{$target_id}\nاولین پیام شما به صورت ناشناس ارسال خواهد شد.");
                exit;
            }
        }

        $bot_username = $BOT_USERNAME;
        $admin_link = "https://t.me/{$bot_username}?start=anon";
        if ($from_id == $ADMIN_ID) {
            $msg = "👑 <b>خوش آمدی ادمین</b>\n\n"
                 . "🔗 لینک ناشناس شما:\n<a href=\"{$admin_link}\">{$admin_link}</a>\n\n"
                 . "برای مدیریت از /panel استفاده کنید.";
            sendMessage($chat_id, $msg);
            exit;
        } else {
            $uid = $from_id;
            $u   = $users[$uid] ?? null;
            $approved = $u['approved'] ?? false;
            $token = $u['link']['token'] ?? null;
            if ($token) $user_link = "https://t.me/{$bot_username}?start={$token}";
            else        $user_link = "https://t.me/{$bot_username}?start=user_{$uid}";
            $msg = "👋 <b>خوش آمدی {$first}!</b>\n\n"
                 . "این ربات برای ارسال و دریافت پیام‌های ناشناس است.\n\n"
                 . "🔗 لینک ناشناس ادمین:\n<a href=\"{$admin_link}\">{$admin_link}</a>\n\n"
                 . "برای دریافت لینک ناشناس خود:\n/mylink\n\n"
                 . "برای لینک اختصاصی:\n/custom_link slug\n\n"
                 . "برای دیدن وضعیت خود:\n/mypanel\n\n"
                 . "وضعیت لینک شما: " . ($approved ? "فعال ✅" : "غیرفعال ❌") . "\n"
                 . ($approved ? "لینک شما:\n<code>{$user_link}</code>" : "برای فعال شدن لینک، /mylink را ارسال کنید.");
            sendMessage($chat_id, $msg);
            exit;
        }
    }

    $session = get_session($from_id);
    $is_text = !empty($text);
    $has_media = !empty($message['photo']) ||
                 !empty($message['document']) ||
                 !empty($message['video']) ||
                 !empty($message['voice']) ||
                 !empty($message['audio']) ||
                 !empty($message['animation']) ||
                 !empty($message['sticker']);

    if ($session && $from_id != $ADMIN_ID) {
        if (!$is_text && !$has_media) {
            sendMessage($chat_id, "نوع این پیام پشتیبانی نمی‌شود. لطفاً متن یا مدیا ارسال کنید.");
            exit;
        }
        $target_id   = $session['target_id'];
        $target_type = $session['target_type'];
        $token       = $session['token'];

        $users = load_json($USERS_FILE);
        if (!isset($users[$from_id]['stats'])) $users[$from_id]['stats'] = ['sent_anon' => 0, 'received_anon' => 0];
        if (!isset($users[$target_id]['stats'])) $users[$target_id]['stats'] = ['sent_anon' => 0, 'received_anon' => 0];

        if ($target_type == 'user') {
            $link_info = $users[$target_id]['link'] ?? ['expires_at' => null, 'one_time' => false, 'used' => false, 'token' => $token];
            if ($link_info['expires_at'] && time() > $link_info['expires_at']) {
                sendMessage($chat_id, "⏱ این لینک ناشناس منقضی شده است.");
                clear_session($from_id);
                exit;
            }
            if (!empty($link_info['one_time']) && !empty($link_info['used'])) {
                sendMessage($chat_id, "این لینک ناشناس یک‌بارمصرف بوده و قبلاً استفاده شده است.");
                clear_session($from_id);
                exit;
            }
            if (!empty($link_info['one_time']) && empty($link_info['used'])) {
                $users[$target_id]['link']['used'] = true;
                save_json($USERS_FILE, $users);
            }
        }

        if ($is_text) {
            $txt_to_target = "📩 <b>یک پیام ناشناس برای شما ارسال شد:</b>\n\n{$text}";
            sendMessage($target_id, $txt_to_target);
        } else {
            bot('copyMessage', [
                'from_chat_id' => $from_id,
                'chat_id'      => $target_id,
                'message_id'   => $msg_id,
            ]);
        }

        inc_stat('sent_anon');
        inc_stat('received_anon');
        $users[$from_id]['stats']['sent_anon']++;
        $users[$target_id]['stats']['received_anon']++;
        save_json($USERS_FILE, $users);

        $target_label = ($target_type == 'admin') ? "ادمین" : "کاربر";
        $log = "📥 <b>پیام ناشناس جدید</b>\n\n"
             . "👤 <b>فرستنده:</b>\n"
             . "SenderID: <a href=\"tg://user?id={$from_id}\">{$from_id}</a>\n"
             . ($username ? "Username: @{$username}\n" : "")
             . "نام: {$first}\n\n"
             . "🎯 <b>گیرنده:</b>\n"
             . "Type: {$target_label}\n"
             . "UserID: <a href=\"tg://user?id={$target_id}\">{$target_id}</a>\n\n";
        if ($is_text) $log .= "📝 <b>متن پیام:</b>\n{$text}";
        else          $log .= "🖼 <b>پیام مدیا</b>";

        $sender_photo_id = get_user_profile_photo_id($from_id);
        if ($sender_photo_id) {
            bot('sendPhoto', [
                'chat_id'    => $ADMIN_ID,
                'photo'      => $sender_photo_id,
                'caption'    => $log,
                'parse_mode' => 'HTML',
            ]);
        } else {
            sendMessage($ADMIN_ID, $log);
        }
        if ($has_media) {
            bot('copyMessage', [
                'from_chat_id' => $from_id,
                'chat_id'      => $ADMIN_ID,
                'message_id'   => $msg_id,
            ]);
        }

        sendMessage($chat_id, "پیام ناشناس شما ارسال شد ✔️");
        clear_session($from_id);
        exit;
    }

    if ($from_id != $ADMIN_ID) {
        if (!$is_text && !$has_media) {
            sendMessage($chat_id, "نوع این پیام پشتیبانی نمی‌شود. لطفاً متن یا مدیا ارسال کنید.");
            exit;
        }
        $users = load_json($USERS_FILE);
        if (!isset($users[$from_id]['stats'])) $users[$from_id]['stats'] = ['sent_anon' => 0, 'received_anon' => 0];
        $users[$from_id]['stats']['sent_anon']++;
        save_json($USERS_FILE, $users);
        inc_stat('sent_anon');
        inc_stat('received_anon');

        $target_id = $ADMIN_ID;
        $log = "📥 <b>پیام ناشناس جدید (برای ادمین)</b>\n\n"
             . "👤 <b>فرستنده:</b>\n"
             . "SenderID: <a href=\"tg://user?id={$from_id}\">{$from_id}</a>\n"
             . ($username ? "Username: @{$username}\n" : "")
             . "نام: {$first}\n\n"
             . "🎯 <b>گیرنده:</b>\n"
             . "Type: ادمین\n"
             . "UserID: <a href=\"tg://user?id={$target_id}\">{$target_id}</a>\n\n";
        if ($is_text) $log .= "📝 <b>متن پیام:</b>\n{$text}";
        else          $log .= "🖼 <b>پیام مدیا</b>";

        $sender_photo_id = get_user_profile_photo_id($from_id);
        if ($sender_photo_id) {
            bot('sendPhoto', [
                'chat_id'    => $ADMIN_ID,
                'photo'      => $sender_photo_id,
                'caption'    => $log,
                'parse_mode' => 'HTML',
            ]);
        } else {
            sendMessage($ADMIN_ID, $log);
        }
        if ($has_media) {
            bot('copyMessage', [
                'from_chat_id' => $from_id,
                'chat_id'      => $ADMIN_ID,
                'message_id'   => $msg_id,
            ]);
        }
        sendMessage($chat_id, "پیام ناشناس شما برای ادمین ارسال شد ✔️");
        exit;
    }

    if ($from_id == $ADMIN_ID && !empty($text)) {
        sendMessage($ADMIN_ID, "پیام شما دریافت شد.\nبرای پاسخ به کاربران، روی گزارش آن‌ها Reply بزنید.\nبرای مدیریت از /panel استفاده کنید.");
        exit;
    }
}

echo "OK";