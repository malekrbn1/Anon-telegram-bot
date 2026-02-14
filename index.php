<?php
/*************************************************
 * ربات پیام ناشناس حرفه‌ای (یک فایل PHP)
 * نسخه ارتقایافته با:
 * - حفظ کامل تمام امکانات قبلی
 * - لینک‌های ناشناس حرفه‌ای (انقضا، یک‌بارمصرف، وضعیت)
 * - ضد اسپم
 * - مدیریت حرفه‌ای کاربران و پنل ادمین
 * - پنل کاربر
 * - لینک اختصاصی توسط ادمین و درخواست لینک اختصاصی
 * - نمایش عکس پروفایل کاربر برای ادمین
 * - لینک‌دار شدن آیدی عددی (tg://user?id=...)
 *************************************************/

// توکن ربات (همان مقدار قبلی را نگه دار)
$BOT_TOKEN = "8402908611:AAFduJ2ho-RkNd6mztDvLb5O9FBk7ED7bxM";

// آیدی عددی ادمین کل (همان مقدار قبلی را نگه دار)
$ADMIN_ID  = 5986250975;

// فایل‌های ذخیره‌سازی
$USERS_FILE    = __DIR__ . '/users.json';
$SESSIONS_FILE = __DIR__ . '/sessions.json';
$REQUESTS_FILE = __DIR__ . '/requests.json';
$SPAM_FILE     = __DIR__ . '/spam.json';
$STATS_FILE    = __DIR__ . '/stats.json';
$CUSTOM_LINKS_FILE = __DIR__ . '/custom_links.json'; // لینک‌های اختصاصی

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

function add_request($user_id, $type = 'normal')
{
    global $REQUESTS_FILE;
    $req = load_json($REQUESTS_FILE);
    $req[$user_id] = [
        'user_id' => $user_id,
        'time'    => time(),
        'type'    => $type, // normal یا custom
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
 * لینک اختصاصی
 *************************************************/

function load_custom_links()
{
    global $CUSTOM_LINKS_FILE;
    return load_json($CUSTOM_LINKS_FILE);
}

function save_custom_links($data)
{
    global $CUSTOM_LINKS_FILE;
    save_json($CUSTOM_LINKS_FILE, $data);
}

function set_custom_link($user_id, $slug)
{
    $links = load_custom_links();
    $links[$slug] = $user_id;
    save_custom_links($links);
}

function get_user_by_custom_slug($slug)
{
    $links = load_custom_links();
    return $links[$slug] ?? null;
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
 * عکس پروفایل
 *************************************************/

function send_user_profile_photo($chat_id, $user_id, $caption = '')
{
    $photos = bot('getUserProfilePhotos', [
        'user_id' => $user_id,
        'limit'   => 1
    ]);
    if (!empty($photos['ok']) && !empty($photos['result']['photos'][0][0]['file_id'])) {
        $file_id = $photos['result']['photos'][0][0]['file_id'];
        bot('sendPhoto', [
            'chat_id' => $chat_id,
            'photo'   => $file_id,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ]);
        return true;
    }
    return false;
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

        $bot_username = $msg['chat']['username'] ?? 'malekeshambot';
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
                'text'       => "درخواست لینک کاربر با موفقیت تأیید شد.\n\nUserID: <a href=\"tg://user?id={$uid}\">{$uid}</a>\nلینک:\n{$link}",
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
                'text'       => "درخواست لینک کاربر رد شد.\n\nUserID: <a href=\"tg://user?id={$uid}\">{$uid}</a>",
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

        if (preg_match('/SenderID:\s*([\d]+)/', $rtxt, $m)) {
            $target_id = (int)$m[1];

            if (!empty($text) && strpos($text, '/start') !== 0 && strpos($text, '/users') !== 0 && strpos($text, '/mylink') !== 0 && strpos($text, '/panel') !== 0 && strpos($text, '/broadcast') !== 0 && strpos($text, '/block') !== 0 && strpos($text, '/unblock') !== 0 && strpos($text, '/user') !== 0 && strpos($text, '/make_link') !== 0 && strpos($text, '/custom_request') !== 0 && strpos($text, '/mypanel') !== 0) {
                sendMessage($target_id, "📬 <b>پیام از طرف ادمین:</b>\n\n{$text}");
                sendMessage($ADMIN_ID, "پیام متنی برای کاربر <a href=\"tg://user?id={$target_id}\">{$target_id}</a> ارسال شد ✔️");
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

    /***********************************************
     * دستورات ادمین: /users /panel /broadcast /block /unblock /user /make_link /custom_request
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
                $uid  = $u['id'];
                $out .= $i++ . ") {$u_un} — <a href=\"tg://user?id={$uid}\">{$uid}</a>"
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
                 . "🛠 <b>دستورات مدیریتی:</b>\n"
                 . "/users - لیست کاربران\n"
                 . "/user ID - اطلاعات کاربر + عکس پروفایل\n"
                 . "/broadcast متن - ارسال پیام همگانی\n"
                 . "/block ID - بلاک کاربر\n"
                 . "/unblock ID - آن‌بلاک کاربر\n"
                 . "/link_expire ID ساعت - انقضای لینک\n"
                 . "/link_onetime ID - لینک یک‌بارمصرف\n"
                 . "/link_reset ID - ریست لینک\n"
                 . "/make_link ID slug - ساخت لینک اختصاصی\n"
                 . "/custom_request ID - تأیید درخواست لینک اختصاصی\n"
                 . "/mylink - لینک ناشناس ادمین\n";
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
            sendMessage($ADMIN_ID, "کاربر <a href=\"tg://user?id={$uid}\">{$uid}</a> بلاک شد 🚫");
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
            sendMessage($ADMIN_ID, "کاربر <a href=\"tg://user?id={$uid}\">{$uid}</a> آن‌بلاک شد ✅");
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
            $link     = "https://t.me/malekeshambot?start=user_{$uid}";
            $expires  = $u['link']['expires_at'] ?? null;
            $one_time = !empty($u['link']['one_time']);
            $used     = !empty($u['link']['used']);

            $exp_txt = $expires ? date('Y-m-d H:i', $expires) : 'بدون انقضا';
            $ot_txt  = $one_time ? 'بله' : 'خیر';
            $used_txt= $used ? 'استفاده شده' : 'استفاده نشده';

            $msg = "👤 <b>اطلاعات کاربر</b>\n\n"
                 . "ID: <a href=\"tg://user?id={$uid}\">{$uid}</a>\n"
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
                 . "/link_reset {$uid}\n\n"
                 . "برای ساخت لینک اختصاصی:\n"
                 . "/make_link {$uid} slug";
            sendMessage($ADMIN_ID, $msg);

            // عکس پروفایل
            send_user_profile_photo($ADMIN_ID, $uid, "📷 عکس پروفایل کاربر");
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
            sendMessage($ADMIN_ID, "انقضای لینک کاربر <a href=\"tg://user?id={$uid}\">{$uid}</a> روی {$hours} ساعت تنظیم شد.");
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
            sendMessage($ADMIN_ID, "لینک کاربر <a href=\"tg://user?id={$uid}\">{$uid}</a> به حالت یک‌بارمصرف تنظیم شد.");
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
            sendMessage($ADMIN_ID, "تنظیمات لینک کاربر <a href=\"tg://user?id={$uid}\">{$uid}</a> ریست شد.");
            exit;
        }

        // ساخت لینک اختصاصی توسط ادمین: /make_link ID slug
        if (strpos($text, '/make_link ') === 0) {
            $parts = explode(' ', $text);
            if (count($parts) < 3) {
                sendMessage($ADMIN_ID, "فرمت: /make_link ID slug");
                exit;
            }
            $uid  = (int)$parts[1];
            $slug = trim($parts[2]);
            if (!$uid || !isset($users[$uid])) {
                sendMessage($ADMIN_ID, "کاربر یافت نشد.");
                exit;
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $slug)) {
                sendMessage($ADMIN_ID, "اسلاگ فقط می‌تواند شامل حروف، اعداد و زیرخط باشد.");
                exit;
            }
            $links = load_custom_links();
            if (isset($links[$slug])) {
                sendMessage($ADMIN_ID, "این اسلاگ قبلاً استفاده شده است.");
                exit;
            }
            set_custom_link($uid, $slug);
            $bot_username = "malekeshambot";
            $link = "https://t.me/{$bot_username}?start=custom_{$slug}";
            sendMessage($ADMIN_ID, "لینک اختصاصی برای کاربر <a href=\"tg://user?id={$uid}\">{$uid}</a> ساخته شد:\n<code>{$link}</code>");
            sendMessage($uid, "ادمین برای شما یک لینک اختصاصی ساخت:\n<code>{$link}</code>");
            exit;
        }

        // تأیید درخواست لینک اختصاصی: /custom_request ID
        if (strpos($text, '/custom_request ') === 0) {
            $uid = (int)trim(substr($text, 16));
            if (!$uid || !isset($users[$uid])) {
                sendMessage($ADMIN_ID, "کاربر یافت نشد.");
                exit;
            }
            // اینجا فقط اطلاع می‌دهیم که ادمین باید با /make_link برایش بسازد
            sendMessage($ADMIN_ID, "درخواست لینک اختصاصی برای کاربر <a href=\"tg://user?id={$uid}\">{$uid}</a> ثبت شد.\nبا دستور /make_link {$uid} slug لینک را بسازید.");
            sendMessage($uid, "ادمین درخواست لینک اختصاصی شما را دریافت کرد و به‌زودی لینک اختصاصی برای شما ساخته می‌شود.");
            exit;
        }
    }

    /***********************************************
     * /mylink برای درخواست لینک ناشناس
     ***********************************************/
    if ($text == '/mylink') {
        if ($from_id == $ADMIN_ID) {
            $admin_link = "https://t.me/malekeshambot?start=anon";
            sendMessage($ADMIN_ID, "لینک ناشناس ادمین:\n{$admin_link}");
            exit;
        }

        if (has_request($from_id)) {
            sendMessage($chat_id, "درخواست لینک ناشناس شما قبلاً ثبت شده و در انتظار تأیید ادمین است.");
            exit;
        }

        add_request($from_id, 'normal');
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
             . "SenderID: <a href=\"tg://user?id={$from_id}\">{$from_id}</a>\n"
             . ($username ? "Username: @{$username}\n" : "")
             . "نام: {$first}";
        sendMessage($ADMIN_ID, $txt, null, $kb);
        exit;
    }

    /***********************************************
     * درخواست لینک اختصاصی توسط کاربر: /custom_link
     ***********************************************/
    if ($text == '/custom_link') {
        if ($from_id == $ADMIN_ID) {
            sendMessage($chat_id, "ادمین می‌تواند مستقیماً با /make_link برای هر کاربر لینک اختصاصی بسازد.");
            exit;
        }
        if (has_request($from_id)) {
            sendMessage($chat_id, "درخواست شما قبلاً ثبت شده و در انتظار بررسی ادمین است.");
            exit;
        }
        add_request($from_id, 'custom');
        sendMessage($chat_id, "درخواست لینک اختصاصی شما برای ادمین ارسال شد ✅\nادمین پس از بررسی، لینک اختصاصی برای شما می‌سازد.");

        $txt = "درخواست لینک اختصاصی جدید:\n\n"
             . "SenderID: <a href=\"tg://user?id={$from_id}\">{$from_id}</a>\n"
             . ($username ? "Username: @{$username}\n" : "")
             . "نام: {$first}\n\n"
             . "نوع درخواست: لینک اختصاصی (custom)";
        sendMessage($ADMIN_ID, $txt);
        exit;
    }

    /***********************************************
     * پنل کاربر: /mypanel
     ***********************************************/
    if ($text == '/mypanel') {
        $users = load_json($USERS_FILE);
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

        $bot_username = "malekeshambot";
        $link = $approved ? "https://t.me/{$bot_username}?start=user_{$uid}" : 'هنوز فعال نشده';

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
                  . "در صورت نیاز به لینک اختصاصی، می‌توانید دستور /custom_link را ارسال کنید.\n";
        } else {
            $msg .= "برای فعال شدن لینک ناشناس، ابتدا با /mylink درخواست بدهید.\n";
        }

        sendMessage($chat_id, $msg);
        exit;
    }

    /***********************************************
     * /start (با یا بدون پارامتر) + راهنمای کامل
     ***********************************************/
    if (isset($text) && strpos($text, '/start') === 0) {

        // لینک ناشناس به ادمین
        if ($start_param === 'anon') {
            set_session($from_id, $ADMIN_ID, 'admin');
            sendMessage($chat_id, "شما در حالت ارسال پیام ناشناس به ادمین قرار گرفتید ✅\n\nاولین پیام (متن یا مدیا) که بفرستید، به صورت ناشناس برای ادمین ارسال می‌شود.");
            exit;
        }

        // لینک اختصاصی custom_slug
        if ($start_param && strpos($start_param, 'custom_') === 0) {
            $slug = substr($start_param, 7);
            $target_id = get_user_by_custom_slug($slug);
            if (!$target_id) {
                sendMessage($chat_id, "این لینک اختصاصی معتبر نیست یا کاربر مربوطه یافت نشد.");
                exit;
            }
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
            sendMessage($chat_id, "شما در حالت ارسال پیام ناشناس به کاربر با آیدی <a href=\"tg://user?id={$target_id}\">{$target_id}</a> قرار گرفتید ✅\n\nاولین پیام (متن یا مدیا) که بفرستید، به صورت ناشناس برای او ارسال می‌شود.");
            exit;
        }

        // لینک user_ID
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
                sendMessage($chat_id, "شما در حالت ارسال پیام ناشناس به کاربر با آیدی <a href=\"tg://user?id={$target_id}\">{$target_id}</a> قرار گرفتید ✅\n\nاولین پیام (متن یا مدیا) که بفرستید، به صورت ناشناس برای او ارسال می‌شود.");
                exit;
            }
        }

        // راهنمای کامل برای ادمین و کاربر در یک پیام
        $users = load_json($USERS_FILE);
        $approved = isset($users[$from_id]) ? ($users[$from_id]['approved'] ?? false) : false;

        $bot_username = "malekeshambot";
        $admin_link   = "https://t.me/{$bot_username}?start=anon";

        if ($from_id == $ADMIN_ID) {
            $msg = "👑 <b>خوش آمدی ادمین!</b>\n\n"
                 . "این ربات یک سیستم کامل پیام ناشناس با پنل مدیریت و پنل کاربری است.\n\n"
                 . "📥 هر پیام از کاربران به صورت ناشناس برای شما ارسال می‌شود.\n"
                 . "📤 با Reply روی گزارش‌ها می‌توانید به کاربران پاسخ دهید.\n\n"
                 . "🔗 لینک ناشناس ادمین:\n<code>{$admin_link}</code>\n\n"
                 . "📌 می‌توانید برای کاربران لینک ناشناس بسازید، لینک اختصاصی تعریف کنید، لینک‌ها را محدود کنید و کاربران را مدیریت کنید.\n\n"
                 . "🛠 <b>دستورات مهم ادمین:</b>\n"
                 . "/panel - پنل ادمین و آمار کامل\n"
                 . "/users - لیست کاربران\n"
                 . "/user ID - اطلاعات کامل کاربر + عکس پروفایل\n"
                 . "/broadcast متن - پیام همگانی\n"
                 . "/block ID - بلاک کاربر\n"
                 . "/unblock ID - آن‌بلاک کاربر\n"
                 . "/link_expire ID ساعت - انقضای لینک\n"
                 . "/link_onetime ID - لینک یک‌بارمصرف\n"
                 . "/link_reset ID - ریست لینک\n"
                 . "/make_link ID slug - ساخت لینک اختصاصی\n"
                 . "/custom_request ID - تأیید درخواست لینک اختصاصی\n"
                 . "/mylink - لینک ناشناس ادمین\n\n"
                 . "ℹ️ آیدی عددی کاربران در همه‌جا به صورت لینک قابل کلیک نمایش داده می‌شود.\n";
            sendMessage($chat_id, $msg);
            exit;
        } else {
            $uid = $from_id;
            $user_link = $approved ? "https://t.me/{$bot_username}?start=user_{$uid}" : 'هنوز فعال نشده';

            $msg = "👋 <b>خوش آمدی {$first}!</b>\n\n"
                 . "این ربات برای ارسال و دریافت <b>پیام‌های ناشناس</b> ساخته شده است.\n\n"
                 . "📥 هر پیامی (متن یا مدیا) که اینجا بفرستی، به صورت ناشناس برای ادمین ارسال می‌شود.\n"
                 . "📤 اگر لینک ناشناس اختصاصی داشته باشی، دیگران می‌توانند به صورت ناشناس برای تو پیام بفرستند.\n\n"
                 . "🔗 لینک ناشناس ادمین:\n<code>{$admin_link}</code>\n\n"
                 . "📌 می‌توانید درخواست لینک ناشناس بدهید:\n"
                 . "با دستور <b>/mylink</b> درخواست لینک ناشناس کنید.\n"
                 . "با دستور <b>/custom_link</b> درخواست لینک اختصاصی (با اسلاگ خاص) بدهید.\n\n"
                 . "🧾 <b>پنل کاربری شما:</b>\n"
                 . "با دستور <b>/mypanel</b> می‌توانید وضعیت لینک، آمار پیام‌ها و اطلاعات خود را ببینید.\n\n"
                 . "🔐 ضداسپم فعال است؛ لطفاً پیام‌ها را پشت‌سرهم ارسال نکنید.\n\n"
                 . "وضعیت لینک شما: " . ($approved ? "فعال ✅" : "غیرفعال ❌") . "\n"
                 . ($approved ? "لینک شما:\n<code>{$user_link}</code>\n\n" : "برای فعال شدن لینک، ابتدا /mylink را ارسال کنید.\n\n")
                 . "هر زمان خواستی، فقط پیام بفرست؛ بقیه‌اش با ما 😉";
            sendMessage($chat_id, $msg);
            exit;
        }
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
             . "SenderID: <a href=\"tg://user?id={$from_id}\">{$from_id}</a>\n"
             . ($username ? "Username: @{$username}\n" : "")
             . "نام: {$first}\n\n"
             . "🎯 <b>گیرنده:</b>\n"
             . "Type: {$target_label}\n"
             . "UserID: <a href=\"tg://user?id={$target_id}\">{$target_id}</a>\n\n";

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

        // عکس پروفایل فرستنده برای ادمین
        send_user_profile_photo($ADMIN_ID, $from_id, "📷 عکس پروفایل فرستنده ناشناس");

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
             . "SenderID: <a href=\"tg://user?id={$from_id}\">{$from_id}</a>\n"
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

        // عکس پروفایل فرستنده برای ادمین
        send_user_profile_photo($ADMIN_ID, $from_id, "📷 عکس پروفایل فرستنده ناشناس");

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