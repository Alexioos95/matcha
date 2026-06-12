<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	header("Content-Type: application/json");
	if (!isset($_SESSION["user"]) || !isset($_SESSION["user"]["id"]))
	{
		echo json_encode(["error" => "Not authenticated"]);
		exit;
	}
	$req = $pdo->prepare("SELECT COUNT(*) FROM notifs WHERE toUser = ? AND isRead = false");
	$req->execute([$_SESSION["user"]["id"]]);
	$count = $req->fetchColumn();
	echo json_encode(["count" => (int)$count]);
	exit;
?>
