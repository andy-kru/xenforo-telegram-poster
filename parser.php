<?php
require_once __DIR__ . '/vendor/autoload.php';

use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;
use Sunra\PhpSimple\HtmlDomParser;
use WriteiniFile\WriteiniFile;

$ini = parse_ini_file('settings.ini');
$writer = new WriteiniFile(__DIR__ . '/settings.ini');
$bot_api_key  = 'xxx';
$bot_username = 'xxx';
$chat_id = '-xxx';
$username = 'xxx@mail.com';
$password = 'xxx';
$login_url = 'https://forum.com/login/login';
$thread_url = 'https://forum.com/conversations/xxxx.111111/';
$pageNumber = $ini['pageNumber'];
$postId = $ini['postId'];
if ($pageNumber > 0)
    $parse_url = $thread_url . 'page-' . $pageNumber;
$cookie_file = __DIR__ . '/cookie.txt';
$post_array = [
    'login' => $username,
    'register' => 0,
    'password' => $password,
    'remember' => 1,
    'cookie_check' => 1,
    '_xfToken' => '',
    'redirect' => $parse_url
];

$telegram = new Telegram($bot_api_key, $bot_username);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $login_url);
curl_setopt($ch, CURLOPT_COOKIESESSION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36 OPR/50.0.2762.58');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_array));
$output = curl_exec($ch);

curl_setopt($ch, CURLOPT_POST, false);

$exec_counter = 0;
while ($exec_counter++ < 12) {
    $dom = HtmlDomParser::str_get_html($output);
    $latest_page = $dom->find('div[class=PageNav]', 0)->getAttribute('data-last');
    $messages = $dom->find('ol[id=messageList]', 0)->children();

    $message_index = array();
    foreach ($messages as $msg) {
        array_push($message_index, substr($msg->id, -7));
    }
    $last_post_position = array_search($postId, $message_index);
    if ($last_post_position === false) {
        $last_post_position = -1;
    }

    for ($i = $last_post_position + 1; $i < count($messages); $i++) {
        $text_element_array = $messages[$i]->find('blockquote[class=messageText]', 0)->children(0)->children();
        $text_message = strip_tags(html_entity_decode(implode(PHP_EOL, $text_element_array), ENT_QUOTES));
        $data = [
            'chat_id' => $chat_id,
            'text' => $text_message
        ];
        //echo $text_message, PHP_EOL;
        Request::sendMessage($data);
    }

    $latest_post = array_pop($message_index);
    $in_process = false;

    if ($latest_post != $postId) {
        $writer->update([
            'general' => ['postId' => $latest_post]
        ]);
        $writer->write();
        $postId = $latest_post;
    }

    if ($latest_page != $pageNumber) {
        $pageNumber++;
        $parse_url = $thread_url . 'page-' . $pageNumber;
        $writer->update([
            'general' => ['pageNumber' => $pageNumber]
        ]);
        $writer->write();

        curl_setopt($ch, CURLOPT_URL, $parse_url);
        $output = curl_exec($ch);
        continue;
    }

    usleep(4800000);
}

