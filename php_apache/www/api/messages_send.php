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

	if (isset($_SESSION["lastMsgSend"]) && (microtime(true) - $_SESSION["lastMsgSend"]) < 1.0)
	{
		echo json_encode(["error" => "Please wait before sending another message."]);
		exit;
	}

	if (!isMutualMatch($pdo, $myId, $toUser))
	{
		echo json_encode(["error" => "You are not matched with this user."]);
		exit;
	}
	if (isBlocked($pdo, $myId, $toUser))
	{
		echo json_encode(["error" => "You cannot message this user."]);
		exit;
	}

	$insertReq = $pdo->prepare("INSERT INTO messages (fromUser, toUser, content) VALUES (?, ?, ?)");
	$insertReq->execute([$myId, $toUser, $content]);

	createNotif($pdo, $toUser, "Message");
	$_SESSION["lastMsgSend"] = microtime(true);

	echo json_encode(["success" => true]);
	exit;
?>
