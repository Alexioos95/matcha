<?php
	session_start();
	require_once "../db.php";
	require_once "../auth.php";

	header("Content-Type: application/json");
	if (!isset($_SESSION["user"]) || !isset($_SESSION["user"]["id"]))
	{
		echo json_encode(["error" => "Not authenticated"]);
		exit;
	}
	if (!isset($_POST["csrfToken"]) || !isset($_SESSION["csrfToken"]) || !hash_equals($_SESSION["csrfToken"], $_POST["csrfToken"]))
	{
		echo json_encode(["error" => "Invalid CSRF token"]);
		exit;
	}

	$i = $_POST["i"] ?? null;

	if (!$i || !is_numeric($i))
	{
		echo json_encode(["error" => "Invalid ID"]);
		exit;
	}
	$target = null;
	if ($i == 1)
	{
		$target = $_SESSION["profile"]["secondaryPictureOne"];
		$req = $pdo->prepare("UPDATE profiles SET secondaryPictureOne = ? WHERE author = ?");
		$req->execute([NULL, $_SESSION["user"]["id"]]);
		unlink(__DIR__ . "/.." . $target);
		$_SESSION["profile"]["secondaryPictureOne"] = NULL;
	}
	if ($i == 2)
	{
		$target = $_SESSION["profile"]["secondaryPictureTwo"];
		$req = $pdo->prepare("UPDATE profiles SET secondaryPictureTwo = ? WHERE author = ?");
		$req->execute([NULL, $_SESSION["user"]["id"]]);
		unlink(__DIR__ . "/.." . $target);
		$_SESSION["profile"]["secondaryPictureTwo"] = NULL;
	}
	if ($i == 3)
	{
		$target = $_SESSION["profile"]["secondaryPictureThree"];
		$req = $pdo->prepare("UPDATE profiles SET secondaryPictureThree = ? WHERE author = ?");
		$req->execute([NULL, $_SESSION["user"]["id"]]);
		unlink(__DIR__ . "/.." . $target);
		$_SESSION["profile"]["secondaryPictureThree"] = NULL;
	}
	if ($i == 4)
	{
		$target = $_SESSION["profile"]["secondaryPictureFour"];
		$req = $pdo->prepare("UPDATE profiles SET secondaryPictureFour = ? WHERE author = ?");
		$req->execute([NULL, $_SESSION["user"]["id"]]);
		unlink(__DIR__ . "/.." . $target);
		$_SESSION["profile"]["secondaryPictureFour"] = NULL;
	}
	echo json_encode(["sucess" => "true"]);
	exit;
?>
