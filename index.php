<?php
/*************************************************
 * ربات پیام ناشناس حرفه‌ای (یک فایل PHP)
 * امکانات:
 * - پیام ناشناس به ادمین (لینک اختصاصی: ?start=anon)
 * - لینک ناشناس اختصاصی برای هر کاربر با تأیید ادمین (?start=user_<id>)
 * - ارسال متن و همهٔ انواع مدیا به صورت ناشناس
 * - پاسخ ادمین به فرستنده (متن و مدیا)
 * - لیست کاربران ربات (/users)
 * - سیستم درخواست لینک ناشناس (/mylink) با تأیید/رد توسط ادمین
 *************************************************/

// توکن ربات
$BOT_TOKEN = "8402908611:AAFduJ2ho-RkNd6mztDvLb5O9FBk7ED7bxM";

// آیدی عددی ادمین کل
$ADMIN_ID  = 5986250975;

// فایل‌های ذخیره‌سازی
$USERS_FILE    = __DIR__ . '/users.json';
$SESSIONS_FILE = __DIR__ . '/sessions.json';
$REQUESTS_FILE = __DIR__ . '/requests.json';

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
            'id'       => $id,
            'username' => $user['username'] ?? '',
            'first'    => $user['first_name'] ?? '',
            'approved' => false,
        ];
    } else {
        $users[$id]['username'] = $user['username'] ?? $users[$id]['username'];
        $users[$id]['first']    = $user['first_name'] ?? $users[$id]['first'];
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
        ];
    } else {
        $users[$user_id]['approved'] = true;
    }
    save_json($USERS_FILE, $users);
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

        $link = "https://t.me/" . ($msg['chat']['username'] ?? 'YOUR_BOT_USERNAME') . "?start=user_" . $uid;

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

        // اطلاع به خود کاربر
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
    global $ADMIN_ID;

    $chat_id  = $message["chat"]["id"];
    $from     = $message["from"];
    $from_id  = $from["id"];
    $username = $from["username"] ?? "";
    $first    = $from["first_name"] ?? "";
    $text     = $message["text"] ?? "";
    $msg_id   = $message["message_id"];
    $reply_to = $message["reply_to_message"] ?? null;

    add_user($from);

    // تشخیص پارامتر /start
    $start_param = null;
    if (isset($text) && strpos($text, '/start') === 0) {
        $parts = explode(' ', $text, 2);
        if (isset($parts[1])) {
            $start_param = trim($parts[1]);
        }
    }

    /***********************************************
     * 1) پاسخ ادمین به کاربر (Reply روی گزارش)
     ***********************************************/
    if ($from_id == $ADMIN_ID && $reply_to) {
        $rtxt = $reply_to['text'] ?? '';

        if (preg_match('/SenderID:\s*(\d+)/', $rtxt, $m)) {
            $target_id = (int)$m[1];

            // اگر پیام ادمین متن است
            if (!empty($text) && strpos($text, '/start') !== 0 && strpos($text, '/users') !== 0 && strpos($text, '/mylink') !== 0) {
                sendMessage($target_id, "📬 <b>پیام از طرف ادمین:</b>\n\n{$text}");
                sendMessage($ADMIN_ID, "پیام متنی برای کاربر <code>{$target_id}</code> ارسال شد ✔️");
                exit;
            }

            // اگر پیام ادمین مدیا است → کپی به کاربر
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
     * 2) دستور /users فقط برای ادمین
     ***********************************************/
    if ($from_id == $ADMIN_ID && $text == '/users') {
        $users = load_json($USERS_FILE);
        if (!$users) {
            sendMessage($ADMIN_ID, "هیچ کاربری ثبت نشده است.");
            exit;
        }
        $out = "👥 <b>لیست کاربران ربات:</b>\n\n";
        $i = 1;
        foreach ($users as $u) {
            $u_un = $u['username'] ? '@' . $u['username'] : 'بدون یوزرنیم';
            $out .= $i++ . ") {$u_un} — <code>{$u['id']}</code>" . ($u['approved'] ? " ✅" : "") . "\n";
        }
        sendMessage($ADMIN_ID, $out);
        exit;
    }

    /***********************************************
     * 3) دستور /mylink برای درخواست لینک ناشناس
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

        // اطلاع به ادمین
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
     * 4) /start (با یا بدون پارامتر)
     ***********************************************/
    if (isset($text) && strpos($text, '/start') === 0) {

        // حالت لینک ناشناس ادمین
        if ($start_param === 'anon') {
            set_session($from_id, $ADMIN_ID, 'admin');
            sendMessage($chat_id, "شما در حالت ارسال پیام ناشناس به ادمین قرار گرفتید ✅\n\nاولین پیام (متن یا مدیا) که بفرستید، به صورت ناشناس برای ادمین ارسال می‌شود.");
            exit;
        }

        // حالت لینک ناشناس کاربر دیگر
        if ($start_param && strpos($start_param, 'user_') === 0) {
            $target_id = (int)substr($start_param, 5);
            if ($target_id > 0) {
                set_session($from_id, $target_id, 'user');
                sendMessage($chat_id, "شما در حالت ارسال پیام ناشناس به کاربر با آیدی <code>{$target_id}</code> قرار گرفتید ✅\n\nاولین پیام (متن یا مدیا) که بفرستید، به صورت ناشناس برای او ارسال می‌شود.");
                exit;
            }
        }

        // /start ساده
        $users = load_json($USERS_FILE);
        $approved = isset($users[$from_id]) ? ($users[$from_id]['approved'] ?? false) : false;

        $msg = "سلام {$first} 👋\n\n"
             . "با این ربات می‌تونی پیام ناشناس بفرستی.\n\n"
             . "🔹 لینک ناشناس ادمین:\n"
             . "<code>https://t.me/YOUR_BOT_USERNAME?start=anon</code>\n\n";

        if ($from_id == $ADMIN_ID) {
            $msg .= "شما ادمین هستید.\nمی‌تونید از /users برای دیدن کاربران و از /mylink برای دیدن لینک خود استفاده کنید.";
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
     * 5) اگر سشن فعال است → ارسال ناشناس به هدف
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

        // ارسال برای هدف
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

        // گزارش برای ادمین
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
            $sent = sendMessage($ADMIN_ID, $log);
            bot('copyMessage', [
                'from_chat_id' => $from_id,
                'chat_id'      => $ADMIN_ID,
                'message_id'   => $msg_id,
            ]);
        }

        // تأیید برای فرستنده
        sendMessage($chat_id, "پیام ناشناس شما ارسال شد ✔️");

        clear_session($from_id);
        exit;
    }

    /***********************************************
     * 6) پیام عادی کاربر → ناشناس برای ادمین
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
     * 7) پیام‌های دیگر ادمین (بدون دستور خاص)
     ***********************************************/
    if ($from_id == $ADMIN_ID && !empty($text)) {
        sendMessage($ADMIN_ID, "پیام شما دریافت شد.\nبرای پاسخ به کاربران، روی گزارش آن‌ها Reply بزنید.");
        exit;
    }
}

echo "OK";