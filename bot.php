<?php
// === CONFIG ===
$botToken = "7627717721:AAG_IE7taWTO1JIanawga7_daTeIet7ulGo"; // Your bot token (keep secret)
$apiURL = "https://api.telegram.org/bot$botToken/";
$adminId = 6931353821; // <-- REPLACE with your Telegram user ID (admin)
$maxPromptLength = 1000;
$dailyLimitFree = 5; // Free users: 5 images per day

// === Files for persistence ===
$usersFile = __DIR__ . '/users.txt';
$vipFile = __DIR__ . '/vip_users.txt';
$usageFile = __DIR__ . '/usage.json'; // stores per-user daily counts
$statsFile = __DIR__ . '/stats.json'; // stores generation stats per day
$logFile = __DIR__ . '/bot_errors.log';

// === Helpers ===
function logError($message) {
    global $logFile;
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

function saveUser($userId) {
    global $usersFile;
    if (!file_exists($usersFile)) file_put_contents($usersFile, "");
    $users = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!in_array($userId, $users)) {
        file_put_contents($usersFile, $userId . PHP_EOL, FILE_APPEND);
    }
}

function isVip($userId) {
    global $vipFile;
    if (!file_exists($vipFile)) return false;
    $vips = file($vipFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return in_array($userId, $vips);
}

function addVip($userId) {
    global $vipFile;
    if (!file_exists($vipFile)) file_put_contents($vipFile, "");
    $vips = file($vipFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!in_array($userId, $vips)) {
        file_put_contents($vipFile, $userId . PHP_EOL, FILE_APPEND);
        return true;
    }
    return false;
}

function removeVip($userId) {
    global $vipFile;
    if (!file_exists($vipFile)) return false;
    $vips = file($vipFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $new = array_filter($vips, function($v) use ($userId) { return trim($v) != trim($userId); });
    file_put_contents($vipFile, implode(PHP_EOL, $new) . (count($new) ? PHP_EOL : ""));
    return true;
}

function loadUsage() {
    global $usageFile;
    if (!file_exists($usageFile)) return [];
    $json = file_get_contents($usageFile);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function saveUsage($data) {
    global $usageFile;
    file_put_contents($usageFile, json_encode($data));
}

function incrementUsage($userId) {
    global $dailyLimitFree;
    $today = date('Y-m-d');
    $usage = loadUsage();
    if (!isset($usage[$userId]) || $usage[$userId]['date'] !== $today) {
        $usage[$userId] = ['date' => $today, 'count' => 0];
    }
    $usage[$userId]['count'] += 1;
    saveUsage($usage);
}

function getUsageCountToday($userId) {
    $today = date('Y-m-d');
    $usage = loadUsage();
    if (!isset($usage[$userId]) || $usage[$userId]['date'] !== $today) return 0;
    return intval($usage[$userId]['count']);
}

function canGenerate($userId) {
    global $dailyLimitFree;
    if (isVip($userId)) return true;
    $count = getUsageCountToday($userId);
    return ($count < $dailyLimitFree);
}

// Stats
function loadStats() {
    global $statsFile;
    if (!file_exists($statsFile)) return [];
    $json = file_get_contents($statsFile);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function saveStats($data) {
    global $statsFile;
    file_put_contents($statsFile, json_encode($data));
}

function incrementStats() {
    $today = date('Y-m-d');
    $stats = loadStats();
    if (!isset($stats[$today])) $stats[$today] = ['generated'=>0];
    $stats[$today]['generated'] += 1;
    saveStats($stats);
}

// === Telegram helpers ===
function sendMessage($chatId, $text, $parse_mode = null) {
    global $apiURL;
    $url = $apiURL . "sendMessage?chat_id=" . $chatId . "&text=" . urlencode($text);
    if ($parse_mode) $url .= "&parse_mode=" . $parse_mode;
    return @file_get_contents($url);
}

function sendPhotoByUrl($chatId, $photoUrl, $caption = "", $parse_mode = null) {
    global $apiURL;
    $url = $apiURL . "sendPhoto?chat_id=" . $chatId . "&photo=" . urlencode($photoUrl) . "&caption=" . urlencode($caption);
    if ($parse_mode) $url .= "&parse_mode=" . $parse_mode;
    return @file_get_contents($url);
}

function getFileUrl($file_id) {
    global $apiURL;
    $resp = @file_get_contents($apiURL . "getFile?file_id=" . $file_id);
    if ($resp === false) return false;
    $data = json_decode($resp, true);
    if (!isset($data['ok']) || !$data['ok']) return false;
    $file_path = $data['result']['file_path'] ?? null;
    if (!$file_path) return false;
    return "https://api.telegram.org/file/bot" . getenv('BOT_TOKEN_OVERRIDE') ?: $GLOBALS['botToken'] . "/" . $file_path;
    // Note: We will return the path using the standard format below:
    return "https://api.telegram.org/file/bot" . $GLOBALS['botToken'] . "/" . $file_path;
}

// === Receive update ===
$content = file_get_contents("php://input");
$update = json_decode($content, true);
if (!$update) {
    logError("No update or invalid JSON: " . $content);
    exit;
}

// Normalize message container
$message = $update['message'] ?? $update['edited_message'] ?? null;
if (!$message) {
    // nothing to do for non-message updates (callbacks not implemented here)
    exit;
}

$chatId = $message['chat']['id'] ?? null;
$userId = $message['from']['id'] ?? null;
$userName = $message['from']['first_name'] ?? ($message['from']['username'] ?? 'User');
$text = isset($message['text']) ? trim($message['text']) : (isset($message['caption']) ? trim($message['caption']) : "");

if (!$chatId || !$userId) {
    logError("Missing chatId or userId in update: " . json_encode($message));
    exit;
}

// Save every interacting user
saveUser($userId);

// === Handle commands ===
if ($text === "/start") {
    $welcome = "👋 Hello $userName! Welcome to AI Image Generator.\n\n";
    $welcome .= "Send me a text prompt and I will create an image for you using Pollinations AI.\n";
    $welcome .= "To transform an image: send a photo with a caption describing the change (e.g., 'make it look like oil painting').\n\n";
    $welcome .= "Commands:\n";
    $welcome .= "/help - Guidance\n";
    $welcome .= "/stats - (admin) today stats\n";
    $welcome .= "/users - (admin) total users\n";
    $welcome .= "/export - (admin) export users list\n";
    $welcome .= "/addvip <user_id> - (admin) add VIP\n";
    $welcome .= "/removevip <user_id> - (admin) remove VIP\n";
    sendMessage($chatId, $welcome);
    exit;
}

if ($text === "/help") {
    $help = "🤖 How to use:\n";
    $help .= "• Send a text prompt: I'll generate an image.\n";
    $help .= "• Send a PHOTO with a caption prompt: I'll transform your photo.\n";
    $help .= "Limits:\n";
    $help .= "• Free users: $dailyLimitFree images/day\n";
    $help .= "• VIP users: Unlimited (use /addvip and /removevip to manage)\n";
    sendMessage($chatId, $help);
    exit;
}

// Admin commands
if (strpos($text, "/addvip") === 0) {
    if ($userId != $adminId) {
        sendMessage($chatId, "⛔ You are not authorized to use this command.");
        exit;
    }
    $parts = preg_split('/\s+/', $text);
    if (!isset($parts[1]) || !is_numeric($parts[1])) {
        sendMessage($chatId, "Usage: /addvip <user_id>");
        exit;
    }
    $target = trim($parts[1]);
    $ok = addVip($target);
    sendMessage($chatId, $ok ? "✅ User $target added as VIP." : "ℹ️ User $target is already a VIP.");
    exit;
}

if (strpos($text, "/removevip") === 0) {
    if ($userId != $adminId) {
        sendMessage($chatId, "⛔ You are not authorized.");
        exit;
    }
    $parts = preg_split('/\s+/', $text);
    if (!isset($parts[1]) || !is_numeric($parts[1])) {
        sendMessage($chatId, "Usage: /removevip <user_id>");
        exit;
    }
    $target = trim($parts[1]);
    $ok = removeVip($target);
    sendMessage($chatId, "✅ User $target removed from VIP list.");
    exit;
}

if ($text === "/users") {
    if ($userId != $adminId) {
        sendMessage($chatId, "⛔ Not authorized.");
        exit;
    }
    if (!file_exists($usersFile)) {
        sendMessage($chatId, "No users yet.");
        exit;
    }
    $users = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    sendMessage($chatId, "Total users: " . count($users));
    exit;
}

if ($text === "/stats") {
    if ($userId != $adminId) {
        sendMessage($chatId, "⛔ Not authorized.");
        exit;
    }
    $stats = loadStats();
    $today = date('Y-m-d');
    $todayCount = $stats[$today]['generated'] ?? 0;
    $summary = "📊 Stats\n";
    $summary .= "Today ($today) generated images: $todayCount\n";
    // optionally show last 7 days
    $summary .= "\nLast days:\n";
    $days = array_keys($stats);
    rsort($days);
    $last = array_slice($days, 0, 7);
    foreach ($last as $d) {
        $summary .= "$d : " . ($stats[$d]['generated'] ?? 0) . "\n";
    }
    sendMessage($chatId, $summary);
    exit;
}

if ($text === "/export") {
    if ($userId != $adminId) {
        sendMessage($chatId, "⛔ Not authorized.");
        exit;
    }
    if (!file_exists($usersFile)) {
        sendMessage($chatId, "No users to export.");
        exit;
    }
    $users = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    // send as message (for small lists). If large, you may want to implement file send.
    $chunk = "Registered users (" . count($users) . "):\n" . implode("\n", $users);
    // Telegram message size limit ~4096 chars; if larger, split:
    $parts = str_split($chunk, 3900);
    foreach ($parts as $p) sendMessage($chatId, $p);
    exit;
}

// === Broadcast (admin) optionally available if kept previously ===
if (strpos($text, "/broadcast") === 0) {
    if ($userId != $adminId) {
        sendMessage($chatId, "⛔ You are not authorized to use this command.");
        exit;
    }
    $message = trim(str_replace("/broadcast", "", $text));
    if (empty($message)) {
        sendMessage($chatId, "⚠️ Usage: /broadcast Your message here");
        exit;
    }
    if (!file_exists($usersFile)) file_put_contents($usersFile, "");
    $users = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $count = 0;
    foreach ($users as $uid) {
        sendMessage($uid, $message);
        $count++;
        usleep(200000);
    }
    sendMessage($chatId, "✅ Broadcast sent to $count users.");
    exit;
}

// === Image generation handling ===
// If message contains a photo (user wants image-to-image)
if (isset($message['photo']) && is_array($message['photo']) && count($message['photo']) > 0) {
    // Use largest size
    $photoArr = end($message['photo']);
    $file_id = $photoArr['file_id'] ?? null;
    $caption = isset($message['caption']) ? trim($message['caption']) : "";

    if (!$file_id) {
        sendMessage($chatId, "❌ Could not read the uploaded photo.");
        exit;
    }

    if (empty($caption)) {
        sendMessage($chatId, "Please send a caption with your photo describing the transformation you want (e.g., 'Make it look like an oil painting').");
        exit;
    }

    // Check limit
    if (!canGenerate($userId)) {
        sendMessage($chatId, "⚠️ You have reached your daily limit of $dailyLimitFree images. Become VIP to get unlimited generations.");
        exit;
    }

    // Get file path from Telegram and build a public URL to include in prompt
    $fileGet = @file_get_contents($apiURL . "getFile?file_id=" . $file_id);
    if ($fileGet === false) {
        logError("Failed to getFile for $file_id");
        sendMessage($chatId, "❌ Failed to process your photo. Try again later.");
        exit;
    }
    $fileData = json_decode($fileGet, true);
    if (!isset($fileData['ok']) || !$fileData['ok']) {
        logError("getFile returned error: " . $fileGet);
        sendMessage($chatId, "❌ Failed to process your photo.");
        exit;
    }
    $file_path = $fileData['result']['file_path'] ?? null;
    if (!$file_path) {
        sendMessage($chatId, "❌ Could not obtain photo URL.");
        exit;
    }
    $photoUrl = "https://api.telegram.org/file/bot" . $botToken . "/" . $file_path;

    // Build prompt: include the image URL then the caption (many image models accept an image URL in prompt)
    $finalPrompt = $photoUrl . " " . $caption;

    // Send typing
    @file_get_contents($apiURL . "sendChatAction?chat_id=" . $chatId . "&action=upload_photo");

    // Call Pollinations to generate image (image-to-image style by providing image URL in prompt)
    try {
        $encodedPrompt = urlencode($finalPrompt);
        $imageUrl = "https://image.pollinations.ai/prompt/$encodedPrompt?width=1024&height=1024&nologo=true&model=flux";

        $headers = @get_headers($imageUrl);
        if (!$headers || strpos($headers[0], '200') === false) {
            throw new Exception("Pollinations API error for image-to-image");
        }

        // Send result
        $captionText = "🖼️ <b>Transformed Image</b>\n\n<i>Prompt:</i> " . htmlspecialchars($caption);
        sendPhotoByUrl($chatId, $imageUrl, $captionText, "HTML");

        // increment usage & stats
        incrementUsage($userId);
        incrementStats();

    } catch (Exception $e) {
        logError("Image-to-image error for $userId: " . $e->getMessage());
        sendMessage($chatId, "❌ Error generating transformed image. Try a different prompt.");
    }

    exit;
}

// Text-only image generation
if (!empty($text)) {
    // If it's a pure command-like unknown (already handled above), we proceed to generate image
    // Check prompt length
    if (strlen($text) > $maxPromptLength) {
        sendMessage($chatId, "❌ Your prompt is too long. Please keep it under $maxPromptLength characters.");
        exit;
    }

    // Check usage limit
    if (!canGenerate($userId)) {
        sendMessage($chatId, "⚠️ You have reached your daily limit of $dailyLimitFree images. Become VIP to get unlimited generations.");
        exit;
    }

    // Send typing action
    @file_get_contents($apiURL . "sendChatAction?chat_id=" . $chatId . "&action=upload_photo");

    try {
        $encodedPrompt = urlencode($text);
        $imageUrl = "https://image.pollinations.ai/prompt/$encodedPrompt?width=1024&height=1024&nologo=true&model=flux";

        $headers = @get_headers($imageUrl);
        if (!$headers || strpos($headers[0], '200') === false) {
            throw new Exception("Pollinations API returned an error");
        }

        $caption = "🖼️ <b>Your Generated Image</b>\n\n<i>Prompt:</i> " . htmlspecialchars($text);
        sendPhotoByUrl($chatId, $imageUrl, $caption, "HTML");

        // increment usage & stats
        incrementUsage($userId);
        incrementStats();

    } catch (Exception $e) {
        logError("Error for user $userId: " . $e->getMessage());
        sendMessage($chatId, "❌ Sorry, I encountered an error while generating your image. Please try again later.");
    }

    exit;
}

// Fallback: no recognizable content
sendMessage($chatId, "I didn't understand that. Send a text prompt to generate an image, or send a photo with a caption to transform it. Use /help for guidance.");
exit;
?>