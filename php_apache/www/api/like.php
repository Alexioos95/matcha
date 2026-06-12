<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	header('Content-Type: application/json');
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
	$id = $data["id"] ?? null;
	if (!$id || !is_numeric($id))
	{
		echo json_encode(["error" => "Invalid link."]);
		exit;
	}

	$myLikeReq = $pdo->prepare("SELECT * FROM likes WHERE author = ? AND target = ?");
	$myLikeReq->execute([$_SESSION["user"]["id"], $id]);
	$myLike = $myLikeReq->fetch(PDO::FETCH_ASSOC);
	$hisLikeReq = $pdo->prepare("SELECT * FROM likes WHERE author = ? AND target = ?");
	$hisLikeReq->execute([$id, $_SESSION["user"]["id"]]);
	$hisLike = $hisLikeReq->fetch(PDO::FETCH_ASSOC);
	$unmatch = false;

	if ($myLike)
	{
		$delete = $pdo->prepare("DELETE FROM likes WHERE (author = ? AND target = ?) OR (author = ? AND target = ?)");
		$delete->execute([$_SESSION["user"]["id"], $id, $id, $_SESSION["user"]["id"]]);
		$status = "none";
		if ($hisLike)
		{
			$unmatch = true;
			createNotif($pdo, $id, "Unmatch");
		}
	}
	else
	{
		$insert = $pdo->prepare("INSERT INTO likes (author, target) VALUES (?, ?)");
		$insert->execute([$_SESSION["user"]["id"], $id]);
		if ($hisLike)
		{
			$status = "matched";
			createNotif($pdo, $id, "Match");
		}
		else
		{
			$status = "likes";
			createNotif($pdo, $id, "Like");
		}
	}
	updateFameScore($pdo, $id);
	updateLastOnline($pdo);
	echo json_encode(["status" => $status, "unmatch" => $unmatch]);
	exit;
?>
