<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	header("Content-Type: application/json");

	if (!isset($_SESSION["user"]) || !isset($_SESSION["user"]["id"]))
	{
		echo json_encode(["error" => "Not authenticated."]);
		exit;
	}

	$myId = (int)$_SESSION["user"]["id"];

	$req = $pdo->prepare("
		SELECT
			u.id,
			u.firstName,
			u.lastName,
			p.primaryPicture
		FROM users u
		INNER JOIN profiles p ON p.author = u.id
		INNER JOIN likes l1 ON l1.author = :me  AND l1.target = u.id
		INNER JOIN likes l2 ON l2.author = u.id AND l2.target = :me
		WHERE u.id != :me
		AND NOT EXISTS (
			SELECT 1 FROM blocks
			WHERE (author = :me AND target = u.id)
			   OR (author = u.id AND target = :me)
		)
		ORDER BY (
			SELECT createdAt
			FROM messages
			WHERE (fromUser = :me AND toUser = u.id)
			   OR (fromUser = u.id AND toUser = :me)
			ORDER BY id DESC
			LIMIT 1
		) DESC, u.firstName ASC
	");
	$req->bindValue(":me", $myId, PDO::PARAM_INT);
	$req->execute();

	$matches = [];
	while ($row = $req->fetch(PDO::FETCH_ASSOC))
	{
		$matches[] = [
			"id"             => (int)$row["id"],
			"firstName"      => $row["firstName"],
			"lastName"       => $row["lastName"],
			"primaryPicture" => $row["primaryPicture"]
		];
	}

	echo json_encode($matches);
	exit;
?>
