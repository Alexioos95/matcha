<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	header("Content-Type: application/json");

	if (!isset($_SESSION["user"]) || !isset($_SESSION["user"]["id"]))
	{
		echo json_encode(["error" => "Not authenticated."]);
		exit;
	}

	$withUser = isset($_GET["with"]) ? $_GET["with"] : null;
	if ($withUser === null || !is_numeric($withUser))
	{
		echo json_encode(["error" => "Invalid user."]);
		exit;
	}

	$above = isset($_GET["above"]) ? $_GET["above"] : 0;
	if (!is_numeric($above) || $above < 0)
		$above = 0;

	$myId = $_SESSION["user"]["id"];

	$matchReq = $pdo->prepare("
		SELECT COUNT(*) FROM likes l1
		INNER JOIN likes l2 ON l2.author = :them AND l2.target = :me
		WHERE l1.author = :me AND l1.target = :them
	");
	$matchReq->bindValue(":me",   $myId,    PDO::PARAM_INT);
	$matchReq->bindValue(":them", $withUser, PDO::PARAM_INT);
	$matchReq->execute();
	if ((int)$matchReq->fetchColumn() === 0)
	{
		echo json_encode(["error" => "You are not matched with this user."]);
		exit;
	}

	$req = $pdo->prepare("
		SELECT id, fromUser, content, createdAt
		FROM messages
		WHERE id > :above
		AND (
			(fromUser = :me  AND toUser = :them)
			OR
			(fromUser = :them AND toUser = :me)
		)
		ORDER BY id ASC
	");
	$req->bindValue(":me",    $myId,    PDO::PARAM_INT);
	$req->bindValue(":them",  $withUser, PDO::PARAM_INT);
	$req->bindValue(":above", $above,   PDO::PARAM_INT);
	$req->execute();

	$messages = [];
	while ($row = $req->fetch(PDO::FETCH_ASSOC))
	{
		$messages[] = [
			"id"      => (int)$row["id"],
			"fromMe"  => ((int)$row["fromUser"] === (int)$myId),
			"content" => $row["content"],
			"time"    => date("H:i", strtotime($row["createdAt"]))
		];
	}

	echo json_encode($messages);
	exit;
?>
