<?php
	require_once "../db.php";
	require_once "../auth.php";

	header('Content-Type: application/json');
	if (!isset($_SESSION["user"]) || !isset($_SESSION["user"]["id"]))
	{
		echo json_encode(["error" => "Not authenticated."]);
		exit;
	}
	$data = json_decode(file_get_contents("php://input"), true);

	if (!$data)
	{
		echo json_encode(["error" => "Invalid link."]);
		exit;
	}
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
	try
	{
		$del = $pdo->prepare("DELETE FROM blocks WHERE author = ? AND target = ?");
		$del->execute([$_SESSION["user"]["id"], $id]);
		updateLastOnline($pdo);
	}
	catch (PDOException $e)
	{
		echo json_encode(["error" => true]);
		exit;
	}
	echo json_encode(["success" => true]);
	exit;
?>
