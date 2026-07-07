<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	$req = $pdo->prepare("UPDATE users SET cookieToken = NULL, cookieExpires = NULL WHERE id = ?");
	$req->execute([$_SESSION["user"]["id"]]);
	date_default_timezone_set("Europe/Paris");
	setcookie("rememberMe", "", time() - 3600, "/");
	session_unset();
	session_destroy();
	header("Location: /");
	exit;
?>
