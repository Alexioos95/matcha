<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	$id = $_GET["id"] ?? null;

	if ($id === "all")
	{
		$req = $pdo->prepare("UPDATE notifs SET isRead = 1 WHERE toUser = ? AND isRead = 0");
		$req->execute([$_SESSION["user"]["id"]]);
		exit;
	}
	if ($id == null || !filter_var($id, FILTER_VALIDATE_INT) || $id < 0)
		exit;
	$req = $pdo->prepare("UPDATE notifs SET isRead = 1 WHERE id = ? AND toUser = ? AND isRead = 0");
	$req->execute([$id, $_SESSION["user"]["id"]]);
?>
