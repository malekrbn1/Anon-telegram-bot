<?php
/*************************************************
 * ربات پیام ناشناس حرفه‌ای (یک فایل PHP)
 * نسخه ارتقایافته با:
 * - حفظ کامل تمام امکانات قبلی
 * - لینک‌های ناشناس حرفه‌ای (انقضا، یک‌بارمصرف، وضعیت)
 * - ضد اسپم ساده و مؤثر
 * - مدیریت حرفه‌ای کاربران و پنل ادمین
 *************************************************/

// توکن ربات (همان مقدار قبلی را نگه دار)
$BOT_TOKEN = "8333037974:AAFqVPrxet-4lKhk7q0mDs1bKs7vKf5IDW0";

// آیدی عددی ادمین کل (همان مقدار قبلی را نگه دار)
$ADMIN_ID  = 5986250975;

// فایل‌های ذخیره‌سازی
$USERS_FILE    = __DIR__ . '/users.json';
$SESSIONS_FILE = __DIR__ . '/sessions.json';
$REQUESTS_FILE = __DIR__ . '/requests.json';
$SPAM_FILE     = __DIR__ . '/spam.json';
$STATS_FILE    = __DIR__ . '/stats.json';

/*************************************************
 * توابع کمکی
 *************************************************/

function bot($method, $data = [])
{
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/{$method}";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($data, JSON_UNESCAPED_UNICODE),
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function sendMessage($chat_id, $text, $reply_to = null, $keyboard = null)
{
    $data = [
        'chat_id'    => $chat_id,
        'text'       => $text,
        'parse_mode' => 'HTML',
    ];
    if ($reply_to) {
        $data['reply_to_message_id'] = $reply_to;
    }
    if ($keyboard) {
        $data['reply_markup'] = $keyboard;
    }
    return bot("sendMessage", $data);
}

function load_json($file)
{
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function save_json($file, $data)
{
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function add_user($user)
{
    global $USERS_FILE;
    $users = load_json($USERS_FILE);
    $id = $user['id'];
    if (!isset($users[$id])) {
        $users[$id] = [
            'id'        => $id,
            'username'  => $user['username'] ?? '',
            'first'     => $user['first_name'] ?? '',
            'approved'  => false,
            'blocked'   => false,
            'created_at'=> time(),
            'stats'     => [
                'sent_anon'     => 0,
                'received_anon' => 0,
            ],
            'link'      => [
                'expires_at' => null,
                'one_time'   => false,
                'used'       => false,
            ],
        ];
    } else {
        $users[$id]['username'] = $user['username'] ?? $users[$id]['username'];
        $users[$id]['first']    = $user['first_name'] ?? $users[$id]['first'];
        if (!isset($users[$id]['blocked'])) $users[$id]['blocked'] = false;
        if (!isset($users[$id]['stats'])) {
            $users[$id]['stats'] = ['sent_anon'=>0,'received_anon'=>0];
        }
        if (!isset($users[$id]['link'])) {
            $users[$id]['link'] = [
                'expires_at' => null,
                'one_time'   => false,
                'used'       => false,
            ];
        }
    }
    save_json($USERS_FILE, $users);
}

function set_session($user_id, $target_id, $target_type)
{
    global $SESSIONS_FILE;
    $sessions = load_json($SESSIONS_FILE);
    $sessions[$user_id] = [
        'target_id'   => $target_id,
        'target_type' => $target_type,
        'time'        => time(),
    ];
    save_json($SESSIONS_FILE, $sessions);
}

function get_session($user_id)
{
    global $SESSIONS_FILE;
    $sessions = load_json($SESSIONS_FILE);
    return $sessions[$user_id] ?? null;
}

function clear_session($user_id)
{
    global $SESSIONS_FILE;
    $sessions = load_json($SESSIONS_FILE);
    if (isset($sessions[$user_id])) {
        unset($sessions[$user_id]);
        save_json($SESSIONS_FILE, $sessions);
    }
}

function add_request($user_id)
{
    global $REQUESTS_FILE;
    $req = load_json($REQUESTS_FILE);
    $req[$user_id] = [
        'user_id' => $user_id,
        'time'    => time(),
    ];
    save_json($REQUESTS_FILE, $req);
}

function remove_request($user_id)
{
    global $REQUESTS_FILE;
    $req = load_json($REQUESTS_FILE);
    if (isset($req[$user_id])) {
        unset($req[$user_id]);
        save_json($REQUESTS_FILE, $req);
    }
}

function has_request($user_id)
{
    global $REQUESTS_FILE;
    $req = load_json($REQUESTS_FILE);
    return isset($req[$user_id]);
}

function approve_user_link($user_id)
{
    global $USERS_FILE;
    $users = load_json($USERS_FILE);
    if (!isset($users[$user_id])) {
        $users[$user_id] = [
            'id'       => $user_id,
            'username' => '',
            'first'    => '',
            'approved' => true,
            'blocked'  => false,
            'stats'    => ['sent_anon'=>0,'received_anon'=>0],
            'link'     => [
                'expires_at' => null,
                'one_time'   => false,
                'used'       => false,
            ],
        ];
    } else {
        $users[$user_id]['approved'] = true;
        if (!isset($users[$user_id]['link'])) {
            $users[$user_id]['link'] = [
                'expires_at' => null,
                'one_time'   => false,
                'used'       => false,
            ];
        }
    }
    save_json($USERS_FILE, $users);
}

/*************************************************
 * ضد اسپم ساده
 *************************************************/

function check_spam($user_id)
{
    global $SPAM_FILE;
    $spam = load_json($SPAM_FILE);
    $now  = time();
    $limit_seconds = 5; // حداقل فاصله بین دو پیام
    $max_per_min   = 20; // حداکثر پیام در ۶۰ ثانیه

    if (!isset($spam[$user_id])) {
        $spam[$user_id] = [
            'last_time' => 0,
            'count_min' => 0,
            'window'    => $now,
        ];
    }

    $data = $spam[$user_id];

    if ($now - $data['window'] >= 60) {
        $data['window']    = $now;
        $data['count_min'] = 0;
    }

    if ($now - $data['last_time'] < $limit_seconds) {
        $spam[$user_id] = $data;
        save_json($SPAM_FILE, $spam);
        return false;
    }

    if ($data['count_min'] >= $max_per_min) {
        $spam[$user_id] = $data;
        save_json($SPAM_FILE, $spam);
        return false;
    }

    $data['last_time'] = $now;
    $data['count_min']++;
    $spam[$user_id] = $data;
    save_json($SPAM_FILE, $spam);
    return true;
}

/*************************************************
 * آمار کلی
 *************************************************/

function inc_stat($key)
{
    global $STATS_FILE;
    $stats = load_json($STATS_FILE);
    if (!isset($stats[$key])) $stats[$key] = 0;
    $stats[$key]++;
    save_json($STATS_FILE, $stats);
}

function get_stats()
{
    global $STATS_FILE, $USERS_FILE;
    $stats = load_json($STATS_FILE);
    $users = load_json($USERS_FILE);
    $stats['total_users'] = count($users);
    return $stats;
}

/*************************************************
 * دریافت آپدیت
 *************************************************/

$update = json_decode(file_get_contents("php://input"), true);
if (!$update) {
    echo "No update";
    exit;
}

$message = $update["message"] ?? null;
$callback = $update["callback_query"] ?? null;

/*************************************************
 * پردازش کال‌بک‌ها (تأیید/رد لینک)
 *************************************************/

if ($callback) {
    $cb_id   = $callback['id'];
    $data    = $callback['data'] ?? '';
    $from_id = $callback['from']['id'];
    $msg     = $callback['message'] ?? null;

    if ($from_id != $ADMIN_ID) {
        bot('answerCallbackQuery', [
            'callback_query_id' => $cb_id,
            'text'              => 'فقط ادمین می‌تواند این دکمه را استفاده کند.',
            'show_alert'        => true,
        ]);
        exit;
    }

    if (strpos($data, 'approve:') === 0) {
        $uid = (int)substr($data, 8);
        approve_user_link($uid);
        remove_request($uid);

        $bot_username = $msg['chat']['username'] ?? 'YOUR_BOT_USERNAME';
        $link = "https://t.me/{$bot_username}?start=user_" . $uid;

        bot('answerCallbackQuery', [
            'callback_query_id' => $cb_id,
            'text'              => 'لینک کاربر تأیید شد.',
            'show_alert'        => false,
        ]);

        if ($msg) {
            bot('editMessageText', [
                'chat_id'    => $msg['chat']['id'],
                'message_id' => $msg['message_id'],
                'text'       => "درخواست لینک کاربر با موفقیت تأیید شد.\n\nUserID: <code>{$uid}</code>\nلینک:\n{$link}",
                'parse_mode' => 'HTML',
            ]);
        }

        sendMessage($uid, "درخواست لینک ناشناس شما توسط ادمین تأیید شد ✅\n\nلینک شما:\n{$link}");
        exit;
    }

    if (strpos($data, 'reject:') === 0) {
        $uid = (int)substr($data, 7);
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
                'text'       => "درخواست لینک کاربر رد شد.\n\nUserID: <code>{$uid}</code>",
                'parse_mode' => 'HTML',
            ]);
        }

        sendMessage($uid, "درخواست لینک ناشناس شما توسط ادمین رد شد ❌");
        exit;
    }

    bot('answerCallbackQuery', [
        'callback_query_id' => $cb_id,
        'text'              => 'دکمه نامعتبر.',
        'show_alert'        => false,
    ]);
    exit;
}

/*************************************************
 * پردازش پیام‌ها
 *************************************************/

if ($message) {
    global $ADMIN_ID, $USERS_FILE;

    $chat_id  = $message["chat"]["id"];
    $from     = $message["from"];
    $from_id  = $from["id"];
    $username = $from["username"] ?? "";
    $first    = $from["first_name"] ?? "";
    $text     = $message["text"] ?? "";
    $msg_id   = $message["message_id"];
    $reply_to = $message["reply_to_message"] ?? null;

    add_user($from);
    $users = load_json($USERS_FILE);

    // ضد اسپم برای غیر ادمین
    if ($from_id != $ADMIN_ID) {
        if (!check_spam($from_id)) {
            sendMessage($chat_id, "⏱ لطفاً کمی صبر کنید و بعد دوباره پیام بفرستید.");
            exit;
        }
    }

    // اگر کاربر بلاک شده
    if ($from_id != $ADMIN_ID && isset($users[$from_id]) && !empty($users[$from_id]['blocked'])) {
        sendMessage($chat_id, "🚫 شما توسط ادمین بلاک شده‌اید و نمی‌توانید پیام ارسال کنید.");
        exit;
    }

    // تشخیص پارامتر /start
    $start_param = null;
    if (isset($text) && strpos($text, '/start') === 0) {
        $parts = explode(' ', $text, 2);
        if (isset($parts[1])) {
            $start_param = trim($parts[1]);
        }
    }

    /***********************************************
     * پاسخ ادمین به کاربر (Reply روی گزارش)
     ***********************************************/
    if ($from_id == $ADMIN_ID && $reply_to) {
        $rtxt = $reply_to['text'] ?? '';

        if (preg_match('/SenderID:\s*(\d+)/', $rtxt, $m)) {
            $target_id = (int)$m[1];

            if (!empty($text) && strpos($text, '/start') !== 0 && strpos($text, '/users') !== 0 && strpos($text, '/mylink') !== 0 && strpos($text, '/panel') !== 0 && strpos($text, '/broadcast') !== 0 && strpos($text, '/block') !== 0 && strpos($text, '/unblock') !== 0 && strpos($text, '/user') !== 0) {
                sendMessage($target_id, "📬 <b>پیام از طرف ادمین:</b>\n\n{$text}");
                sendMessage($ADMIN_ID, "پیام متنی برای کاربر <code>{$target_id}</code> ارسال شد ✔️");
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

                sendMessage($ADMIN_ID, "مدیا برای کاربر <code>{$target_id}</code> ارسال شد ✔️");
                exit;
            }
        }
    }

    /***********************************************
     * دستورات ادمین: /users /panel /broadcast /block /unblock /user
     ***********************************************/
    if ($from_id == $ADMIN_ID && isset($text)) {

        if ($text == '/users') {
            if (!$users) {
                sendMessage($ADMIN_ID, "هیچ کاربری ثبت نشده است.");
                exit;
            }
            $out = "👥 <b>لیست کاربران ربات:</b>\n\n";
            $i = 1;
            foreach ($users as $u) {
                $u_un = $u['username'] ? '@' . $u['username'] : 'بدون یوزرنیم';
                $out .= $i++ . ") {$u_un} — <code>{$u['id']}</code>"
                      . ($u['approved'] ? " ✅" : "")
                      . (!empty($u['blocked']) ? " 🚫" : "")
                      . "\n";
            }
            sendMessage($ADMIN_ID, $out);
            exit;
        }

        if ($text == '/panel') {
            $stats = get_stats();
            $total_users   = $stats['total_users'] ?? 0;
            $total_sent    = $stats['sent_anon'] ?? 0;
            $total_recv    = $stats['received_anon'] ?? 0;
            $total_links   = 0;
            foreach ($users as $u) {
                if (!empty($u['approved'])) $total_links++;
            }

            $msg = "📊 <b>پنل ادمین</b>\n\n"
                 . "👥 کاربران: <b>{$total_users}</b>\n"
                 . "🔗 لینک‌های فعال: <b>{$total_links}</b>\n"
                 . "📨 پیام‌های ناشناس ارسال‌شده: <b>{$total_sent}</b>\n"
                 . "📥 پیام‌های ناشناس دریافت‌شده: <b>{$total_recv}</b>\n\n"
                 . "دستورات:\n"
                 . "/users - لیست کاربران\n"
                 . "/broadcast متن - ارسال پیام همگانی\n"
                 . "/block ID - بلاک کاربر\n"
                 . "/unblock ID - آن‌بلاک کاربر\n"
                 . "/user ID - اطلاعات کاربر\n"
                 . "/mylink - لینک ادمین\n";
            sendMessage($ADMIN_ID, $msg);
            exit;
        }

        if (strpos($text, '/broadcast ') === 0) {
            $msg_b = trim(substr($text, 11));
            if ($msg_b == '') {
                sendMessage($ADMIN_ID, "متن پیام همگانی خالی است.");
                exit;
            }
            $count = 0;
            foreach ($users as $uid => $u) {
                bot('sendMessage', [
                    'chat_id'    => $uid,
                    'text'       => "📢 <b>پیام از طرف ادمین:</b>\n\n{$msg_b}",
                    'parse_mode' => 'HTML',
                ]);
                $count++;
            }
            sendMessage($ADMIN_ID, "پیام همگانی برای {$count} کاربر ارسال شد.");
            exit;
        }

        if (strpos($text, '/block ') === 0) {
            $uid = (int)trim(substr($text, 7));
            if (!$uid || !isset($users[$uid])) {
                sendMessage($ADMIN_ID, "کاربر با این آیدی یافت نشد.");
                exit;
            }
            $users[$uid]['blocked'] = true;
            save_json($USERS_FILE, $users);
            sendMessage($ADMIN_ID, "کاربر <code>{$uid}</code> بلاک شد 🚫");
            exit;
        }

        if (strpos($text, '/unblock ') === 0) {
            $uid = (int)trim(substr($text, 9));
            if (!$uid || !isset($users[$uid])) {
                sendMessage($ADMIN_ID, "کاربر با این آیدی یافت نشد.");
                exit;
            }
            $users[$uid]['blocked'] = false;
            save_json($USERS_FILE, $users);
            sendMessage($ADMIN_ID, "کاربر <code>{$uid}</code> آن‌بلاک شد ✅");
            exit;
        }

        if (strpos($text, '/user ') === 0) {
            $uid = (int)trim(substr($text, 6));
            if (!$uid || !isset($users[$uid])) {
                sendMessage($ADMIN_ID, "کاربر با این آیدی یافت نشد.");
                exit;
            }
            $u = $users[$uid];
            $u_un = $u['username'] ? '@' . $u['username'] : 'بدون یوزرنیم';
            $approved = !empty($u['approved']) ? '✅' : '❌';
            $blocked  = !empty($u['blocked']) ? '🚫' : '✅';
            $sent     = $u['stats']['sent_anon'] ?? 0;
            $recv     = $u['stats']['received_anon'] ?? 0;
            $link     = "https://t.me/YOUR_BOT_USERNAME?start=user_{$uid}";
            $expires  = $u['link']['expires_at'] ?? null;
            $one_time = !empty($u['link']['one_time']);
            $used     = !empty($u['link']['used']);

            $exp_txt = $expires ? date('Y-m-d H:i', $expires) : 'بدون انقضا';
            $ot_txt  = $one_time ? 'بله' : 'خیر';
            $used_txt= $used ? 'استفاده شده' : 'استفاده نشده';

            $msg = "👤 <b>اطلاعات کاربر</b>\n\n"
                 . "ID: <code>{$uid}</code>\n"
                 . "Username: {$u_un}\n"
                 . "نام: {$u['first']}\n"
                 . "لینک فعال: {$approved}\n"
                 . "بلاک: {$blocked}\n\n"
                 . "📨 ارسال ناشناس: <b>{$sent}</b>\n"
                 . "📥 دریافت ناشناس: <b>{$recv}</b>\n\n"
                 . "🔗 لینک:\n<code>{$link}</code>\n\n"
                 . "⏱ انقضا: {$exp_txt}\n"
                 . "1️⃣ یک‌بارمصرف: {$ot_txt}\n"
                 . "وضعیت استفاده: {$used_txt}\n\n"
                 . "دستورات لینک:\n"
                 . "/link_expire {$uid} ساعت\n"
                 . "/link_onetime {$uid}\n"
                 . "/link_reset {$uid}";
            sendMessage($ADMIN_ID, $msg);
            exit;
        }

        if (strpos($text, '/link_expire ') === 0) {
            $parts = explode(' ', $text);
            if (count($parts) < 3) {
                sendMessage($ADMIN_ID, "فرمت: /link_expire ID ساعت");
                exit;
            }
            $uid = (int)$parts[1];
            $hours = (int)$parts[2];
            if (!$uid || !isset($users[$uid])) {
                sendMessage($ADMIN_ID, "کاربر یافت نشد.");
                exit;
            }
            $expires_at = time() + $hours * 3600;
            $users[$uid]['link']['expires_at'] = $expires_at;
            save_json($USERS_FILE, $users);
            sendMessage($ADMIN_ID, "انقضای لینک کاربر <code>{$uid}</code> روی {$hours} ساعت تنظیم شد.");
            exit;
        }

        if (strpos($text, '/link_onetime ') === 0) {
            $uid = (int)trim(substr($text, 13));
            if (!$uid || !isset($users[$uid])) {
                sendMessage($ADMIN_ID, "کاربر یافت نشد.");
                exit;
            }
            $users[$uid]['link']['one_time'] = true;
            save_json($USERS_FILE, $users);
            sendMessage($ADMIN_ID, "لینک کاربر <code>{$uid}</code> به حالت یک‌بارمصرف تنظیم شد.");
            exit;
        }

        if (strpos($text, '/link_reset ') === 0) {
            $uid = (int)trim(substr($text, 11));
            if (!$uid || !isset($users[$uid])) {
                sendMessage($ADMIN_ID, "کاربر یافت نشد.");
                exit;
            }
            $users[$uid]['link'] = [
                'expires_at' => null,
                'one_time'   => false,
                'used'       => false,
            ];
            save_json($USERS_FILE, $users);
            sendMessage($ADMIN_ID, "تنظیمات لینک کاربر <code>{$uid}</code> ریست شد.");
            exit;
        }
    }

    /***********************************************
     * /mylink برای درخواست لینک ناشناس
     ***********************************************/
    if ($text == '/mylink') {
        if ($from_id == $ADMIN_ID) {
            $admin_link = "https://t.me/YOUR_BOT_USERNAME?start=anon";
            sendMessage($ADMIN_ID, "لینک ناشناس ادمین:\n{$admin_link}");
            exit;
        }

        if (has_request($from_id)) {
            sendMessage($chat_id, "درخواست لینک ناشناس شما قبلاً ثبت شده و در انتظار تأیید ادمین است.");
            exit;
        }

        add_request($from_id);
        sendMessage($chat_id, "درخواست لینک ناشناس شما برای ادمین ارسال شد ✅\nپس از تأیید، لینک برای شما ارسال می‌شود.");

        $kb = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ تأیید', 'callback_data' => 'approve:' . $from_id],
                    ['text' => '❌ رد',   'callback_data' => 'reject:' . $from_id],
                ]
            ]
        ];
        $txt = "درخواست لینک ناشناس جدید:\n\n"
             . "SenderID: <code>{$from_id}</code>\n"
             . ($username ? "Username: @{$username}\n" : "")
             . "نام: {$first}";
        sendMessage($ADMIN_ID, $txt, null, $kb);
        exit;
    }

    /***********************************************
     * /start (با یا بدون پارامتر)
     ***********************************************/
    if (isset($text) && strpos($text, '/start') === 0) {

        if ($start_param === 'anon') {
            set_session($from_id, $ADMIN_ID, 'admin');
            sendMessage($chat_id, "شما در حالت ارسال پیام ناشناس به ادمین قرار گرفتید ✅\n\nاولین پیام (متن یا مدیا) که بفرستید، به صورت ناشناس برای ادمین ارسال می‌شود.");
            exit;
        }

        if ($start_param && strpos($start_param, 'user_') === 0) {
            $target_id = (int)substr($start_param, 5);
            if ($target_id > 0) {
                $users = load_json($USERS_FILE);
                if (!isset($users[$target_id]) || empty($users[$target_id]['approved'])) {
                    sendMessage($chat_id, "این لینک ناشناس فعال نیست یا کاربر هنوز تأیید نشده است.");
                    exit;
                }
                $link_info = $users[$target_id]['link'] ?? [
                    'expires_at' => null,
                    'one_time'   => false,
                    'used'       => false,
                ];
                if ($link_info['expires_at'] && time() > $link_info['expires_at']) {
                    sendMessage($chat_id, "⏱ این لینک ناشناس منقضی شده است.");
                    exit;
                }
                if (!empty($link_info['one_time']) && !empty($link_info['used'])) {
                    sendMessage($chat_id, "این لینک ناشناس یک‌بارمصرف بوده و قبلاً استفاده شده است.");
                    exit;
                }

                set_session($from_id, $target_id, 'user');
                sendMessage($chat_id, "شما در حالت ارسال پیام ناشناس به کاربر با آیدی <code>{$target_id}</code> قرار گرفتید ✅\n\nاولین پیام (متن یا مدیا) که بفرستید، به صورت ناشناس برای او ارسال می‌شود.");
                exit;
            }
        }

        $users = load_json($USERS_FILE);
        $approved = isset($users[$from_id]) ? ($users[$from_id]['approved'] ?? false) : false;

        $msg = "سلام {$first} 👋\n\n"
             . "با این ربات می‌تونی پیام ناشناس بفرستی.\n\n"
             . "🔹 لینک ناشناس ادمین:\n"
             . "<code>https://t.me/YOUR_BOT_USERNAME?start=anon</code>\n\n";

        if ($from_id == $ADMIN_ID) {
            $msg .= "شما ادمین هستید.\n"
                  . "دستورات مهم:\n"
                  . "/panel - پنل ادمین\n"
                  . "/users - لیست کاربران\n"
                  . "/mylink - لینک ناشناس ادمین\n";
        } else {
            if ($approved) {
                $msg .= "✅ لینک ناشناس شما فعال است.\n"
                      . "لینک شما:\n"
                      . "<code>https://t.me/YOUR_BOT_USERNAME?start=user_{$from_id}</code>\n\n";
            } else {
                $msg .= "برای دریافت لینک ناشناس اختصاصی، دستور /mylink را ارسال کنید.\n\n";
            }
            $msg .= "همچنین هر پیامی بدون لینک خاص بفرستی، به صورت ناشناس برای ادمین ارسال می‌شود.";
        }

        sendMessage($chat_id, $msg);
        exit;
    }

    /***********************************************
     * اگر سشن فعال است → ارسال ناشناس به هدف
     ***********************************************/
    $session = get_session($from_id);
    if ($session && $from_id != $ADMIN_ID) {
        $target_id   = $session['target_id'];
        $target_type = $session['target_type'];

        $is_text = !empty($text);
        $has_media = !empty($message['photo']) ||
                     !empty($message['document']) ||
                     !empty($message['video']) ||
                     !empty($message['voice']) ||
                     !empty($message['audio']) ||
                     !empty($message['animation']) ||
                     !empty($message['sticker']);

        if (!$is_text && !$has_media) {
            sendMessage($chat_id, "نوع این پیام پشتیبانی نمی‌شود. لطفاً متن یا مدیا (عکس، ویدیو، فایل، ویس و...) ارسال کنید.");
            exit;
        }

        $users = load_json($USERS_FILE);
        if (isset($users[$target_id])) {
            if (!isset($users[$target_id]['stats'])) {
                $users[$target_id]['stats'] = ['sent_anon'=>0,'received_anon'=>0];
            }
            if (!isset($users[$from_id]['stats'])) {
                $users[$from_id]['stats'] = ['sent_anon'=>0,'received_anon'=>0];
            }
        }

        if ($target_type == 'user') {
            $link_info = $users[$target_id]['link'] ?? [
                'expires_at' => null,
                'one_time'   => false,
                'used'       => false,
            ];
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
        if (isset($users[$from_id])) {
            $users[$from_id]['stats']['sent_anon'] = ($users[$from_id]['stats']['sent_anon'] ?? 0) + 1;
        }
        if (isset($users[$target_id])) {
            $users[$target_id]['stats']['received_anon'] = ($users[$target_id]['stats']['received_anon'] ?? 0) + 1;
        }
        save_json($USERS_FILE, $users);

        $target_label = ($target_type == 'admin') ? "ادمین" : "کاربر";
        $log = "📥 <b>پیام ناشناس جدید</b>\n\n"
             . "👤 <b>فرستنده:</b>\n"
             . "SenderID: <code>{$from_id}</code>\n"
             . ($username ? "Username: @{$username}\n" : "")
             . "نام: {$first}\n\n"
             . "🎯 <b>گیرنده:</b>\n"
             . "Type: {$target_label}\n"
             . "UserID: <code>{$target_id}</code>\n\n";

        if ($is_text) {
            $log .= "📝 <b>متن پیام:</b>\n{$text}";
            sendMessage($ADMIN_ID, $log);
        } else {
            $log .= "🖼 <b>پیام مدیا (بدون نمایش فرستنده به گیرنده)</b>";
            sendMessage($ADMIN_ID, $log);
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

    /***********************************************
     * پیام عادی کاربر → ناشناس برای ادمین
     ***********************************************/
    if ($from_id != $ADMIN_ID) {
        $is_text = !empty($text);
        $has_media = !empty($message['photo']) ||
                     !empty($message['document']) ||
                     !empty($message['video']) ||
                     !empty($message['voice']) ||
                     !empty($message['audio']) ||
                     !empty($message['animation']) ||
                     !empty($message['sticker']);

        if (!$is_text && !$has_media) {
            sendMessage($chat_id, "نوع این پیام پشتیبانی نمی‌شود. لطفاً متن یا مدیا ارسال کنید.");
            exit;
        }

        $users = load_json($USERS_FILE);
        if (isset($users[$from_id])) {
            if (!isset($users[$from_id]['stats'])) {
                $users[$from_id]['stats'] = ['sent_anon'=>0,'received_anon'=>0];
            }
            $users[$from_id]['stats']['sent_anon'] = ($users[$from_id]['stats']['sent_anon'] ?? 0) + 1;
            save_json($USERS_FILE, $users);
        }
        inc_stat('sent_anon');
        inc_stat('received_anon');

        $log = "📥 <b>پیام ناشناس جدید (مستقیم برای ادمین)</b>\n\n"
             . "👤 <b>فرستنده:</b>\n"
             . "SenderID: <code>{$from_id}</code>\n"
             . ($username ? "Username: @{$username}\n" : "")
             . "نام: {$first}\n\n";

        if ($is_text) {
            $log .= "📝 <b>متن پیام:</b>\n{$text}";
            sendMessage($ADMIN_ID, $log);
        } else {
            $log .= "🖼 <b>پیام مدیا</b>";
            sendMessage($ADMIN_ID, $log);
            bot('copyMessage', [
                'from_chat_id' => $from_id,
                'chat_id'      => $ADMIN_ID,
                'message_id'   => $msg_id,
            ]);
        }

        sendMessage($chat_id, "پیام ناشناس شما برای ادمین ارسال شد ✔️");
        exit;
    }

    /***********************************************
     * پیام‌های دیگر ادمین (بدون دستور خاص)
     ***********************************************/
    if ($from_id == $ADMIN_ID && !empty($text)) {
        sendMessage($ADMIN_ID, "پیام شما دریافت شد.\nبرای پاسخ به کاربران، روی گزارش آن‌ها Reply بزنید.\nبرای مدیریت کامل از /panel استفاده کنید.");
        exit;
    }
}

echo "OK";