<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	header("Content-Type: application/json");

	if (!isset($_SESSION["user"]) || !isset($_SESSION["user"]["id"]))
	{
		echo json_encode(["error" => "Not authenticated."]);
		exit;
	}

	$data = json_decode(file_get_contents("php://input"), true);

	if (!isset($data["csrfToken"]) || !isset($_SESSION["csrfToken"]) || !hash_equals($_SESSION["csrfToken"], $data["csrfToken"]))
	{
		echo json_encode(["error" => "Invalid CSRF token."]);
		exit;
	}

	$toUser = isset($data["toUser"]) ? $data["toUser"] : null;
	$content = isset($data["content"]) ? trim($data["content"]) : null;

	if ($toUser === null || !is_numeric($toUser))
	{
		echo json_encode(["error" => "Invalid recipient."]);
		exit;
	}
	if ($content === null || $content === "")
	{
		echo json_encode(["error" => "Message is empty."]);
		exit;
	}
	if (strlen($content) > 1000)
	{
		echo json_encode(["error" => "Message is too long."]);
		exit;
	}

	$myId = $_SESSION["user"]["id"];

	$now = microtime(true);
	if (!isset($_SESSION["msgSendLog"]))
		$_SESSION["msgSendLog"] = [];

	// Drop timestamps older than 60 seconds
	$_SESSION["msgSendLog"] = array_values(array_filter(
		$_SESSION["msgSendLog"],
		fn($t) => ($now - $t) < 60
	));

	$lastSend = count($_SESSION["msgSendLog"]) > 0
		? max($_SESSION["msgSendLog"])
		: 0;

	if (($now - $lastSend) < 1.0)
	{
		echo json_encode(["error" => "Please wait before sending another message."]);
		exit;
	}
	if (count($_SESSION["msgSendLog"]) >= 20)
	{
		echo json_encode(["error" => "Too many messages. Please wait a moment."]);
		exit;
	}

	$_SESSION["msgSendLog"][] = $now;

	$matchReq = $pdo->prepare("
		SELECT COUNT(*) FROM likes l1
		INNER JOIN likes l2 ON l2.author = :them AND l2.target = :me
		WHERE l1.author = :me AND l1.target = :them
	");
	$matchReq->bindValue(":me",   $myId,   PDO::PARAM_INT);
	$matchReq->bindValue(":them", $toUser, PDO::PARAM_INT);
	$matchReq->execute();
	$isMatch = (int)$matchReq->fetchColumn();

	if ($isMatch === 0)
	{
		echo json_encode(["error" => "You are not matched with this user."]);
		exit;
	}

	$insertReq = $pdo->prepare("INSERT INTO messages (fromUser, toUser, content) VALUES (?, ?, ?)");
	$insertReq->execute([$myId, $toUser, $content]);

	createNotif($pdo, $toUser, "Message");

	$newId = (int)$pdo->lastInsertId();
	echo json_encode(["success" => true, "id" => $newId]);
	exit;
?>
