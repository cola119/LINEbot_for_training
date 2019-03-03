<?php

require_once "../vendor/autoload.php";
require_once "config.php";

function getLog($_arr) {
    ob_start();
    var_dump($_arr);
    $_log = ob_get_contents();
    ob_end_clean();
    $fp = fopen('log.txt', 'w');
    fwrite($fp, $_log);
    fclose($fp);
    return false;
}

function insert_db($user_id, $date, $type, $value, $score) {
	if(!isset($value, $user_id)) return '構文エラー';

	try {
		$pdo = new PDO(DB_DSN, DB_USER, DB_PASSWORD);
	} catch (PDOException $e) {
		getLog([$e->getMessage()]);
		return 'DB接続エラー';
	}

	$stmt = $pdo->prepare("INSERT INTO trainings (user_id, date, type, value, score) VALUES (:user_id, :date, :type, :value, :score)");
	$stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
	$stmt->bindParam(":date", $date, PDO::PARAM_STR);
	$stmt->bindParam(":type", $type, PDO::PARAM_INT);
	$stmt->bindParam(":value", $value, PDO::PARAM_INT);
	$stmt->bindParam(":score", $score, PDO::PARAM_INT);
	$stmt->execute();

	if($type === 1) return "ユーザー".$user_id."さん。ラン".$value."kmを記録しました。";
	
	return "ユーザー".$user_id."さん。オリエン".$value."kmを記録しました。";

}

function callback($response, $event) {
	if(!$response->isSucceeded()) return "botエラー";

	$profile = $response->getJSONDecodedBody();
	$message = $event->message;
	$date = $event->timestamp;
	if($message->type !== "text") return "構文エラー";

	$date = date("Y-m-d", $date/1000);
	$type = 1;
	$user_id = null;
	$value = null;
	$score = null;

	$message = $message->text;
	$message = explode("\n", $message);
	$message = array_map('trim', $message);
	$message = array_filter($message, 'strlen');
	$message = array_values($message); #[トレキャン1, 10, ごじらん]
	if(count($message) < 2 || count($message) > 3) return '構文エラー';
	if(strpos($message[0], 'トレキャン') === false) return '構文エラー';

	$user_id = explode("トレキャン", $message[0]);
	if(!is_numeric($user_id[1])) return '構文エラー';
	$user_id = intval($user_id[1]);


	if(is_numeric($message[1])) {
		$value = floatval($message[1]);
	} else if(strpos($message[1], 'ランニング') !== false) {
		$value = floatval(explode("ランニング", $message[1])[1]);
	} else if(strpos($message[1], 'オリエン') !== false) {
		$type = 2;
		$value = floatval(explode("オリエン", $message[1])[1]);
	} else {
		return '構文エラー';
	}

	if($type === 1) $score = $value;
	else if($type === 2) $score = $value * 3;
	else return '構文エラー';

	$res = insert_db($user_id, $date, $type, $value, $score);
	if($message[2] == 'ごじらん') insert_db($user_id, $date, 8, 1, 2);

	return $res;
}

date_default_timezone_set('Asia/Tokyo');
$contents = file_get_contents('php://input');
$event = json_decode($contents)->events[0];

$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(CHANNEL_ACCESS_TOKEN);
$bot = new \LINE\LINEBot($httpClient, ['channelSecret' => CHANNEL_SECRET]);
$response = $bot->getProfile($event->source->userId);

$send_message = callback($response, $event);

$textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($send_message);
$bot->replyMessage($event->replyToken, $textMessageBuilder);

?>
