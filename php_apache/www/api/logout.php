<?php
	require_once "../db.php";
	require_once "../auth.php";

	$req = $pdo->prepare("UPDATE users SET cookieToken = NULL, cookieExpires = NULL WHERE id = ?");
	$req->execute([$_SESSION["user"]["id"]]);
	setcookie("rememberMe", "", time() - 3600, "/");
	session_unset();
	session_destroy();
	header("Location: /");
	exit;
?>
