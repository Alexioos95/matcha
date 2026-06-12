<?php
	require_once "/usr/local/bin/includes/db.php";

	$users = json_decode(file_get_contents("/usr/local/bin/script/mixtures/users.json"), true);
	$profiles = json_decode(file_get_contents("/usr/local/bin/script/mixtures/profiles.json"), true);
	$interests = json_decode(file_get_contents("/usr/local/bin/script/mixtures/interests.json"), true);
	$userInterests = json_decode(file_get_contents("/usr/local/bin/script/mixtures/userInterests.json"), true);
	$likes = json_decode(file_get_contents("/usr/local/bin/script/mixtures/likes.json"), true);
	$notifs = json_decode(file_get_contents("/usr/local/bin/script/mixtures/notifs.json"), true);
	$visitHistory = json_decode(file_get_contents("/usr/local/bin/script/mixtures/visitHistory.json"), true);
	$blocks = json_decode(file_get_contents("/usr/local/bin/script/mixtures/blocks.json"), true);

	try
	{
		$pdo->beginTransaction();
		echo "Starting Population.\n";

		echo "Populating 'users'...\n";
		$reqUsers = $pdo->prepare("INSERT INTO users (id, email, username,	firstName, lastName, password, isActive, isComplete)VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
		foreach ($users as $u)
		{
			$reqUsers->execute([$u["id"], $u["email"], $u["username"], $u["firstName"], $u["lastName"], password_hash($u["password"], PASSWORD_DEFAULT), (int)$u["isActive"], (int)$u["isComplete"]]);
		}

		echo "Populating 'profiles'...\n";
		$reqProfiles = $pdo->prepare("INSERT INTO profiles
			(id, author, gender, preference, birthdate, bio, city, country, lat, lon, primaryPicture, secondaryPictureOne, secondaryPictureTwo, secondaryPictureThree, secondaryPictureFour, fame)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
		);
		foreach ($profiles as $p)
		{
			$reqProfiles->execute([$p["id"], $p["author"], $p["gender"], $p["preference"], $p["birthdate"], $p["bio"], $p["city"], $p["country"], $p["lat"], $p["lon"], $p["primaryPicture"], $p["secondaryPictureOne"], $p["secondaryPictureTwo"], $p["secondaryPictureThree"], $p["secondaryPictureFour"], $p["fame"]]);
		}

		echo "Populating 'interests'...\n";
		$reqInterests = $pdo->prepare("INSERT INTO interests (id, name) VALUES (?, ?)");
		foreach ($interests as $i)
		{
			$reqInterests->execute([$i["id"], $i["name"]]);
		}

		echo "Populating 'userInterests'...\n";
		$reqUserInterests = $pdo->prepare("INSERT INTO userInterests (user, interest) VALUES (?, ?)");
		foreach ($userInterests as $ui)
		{
			$reqUserInterests->execute([$ui["user"], $ui["interest"]]);
		}

		echo "Populating 'likes'...\n";
		$reqLikes = $pdo->prepare("INSERT INTO likes (author, target) VALUES (?, ?)");
		foreach ($likes as $l)
		{
			$reqLikes->execute([$l["author"], $l["target"]]);
		}

		echo "Populating 'blocks'...\n";
		$reqBlocks = $pdo->prepare("INSERT INTO blocks (author, target) VALUES (?, ?)");
		foreach ($blocks as $b)
		{
			$reqBlocks->execute([$b["author"], $b["target"]]);
		}

		echo "Populating 'visitHistory'...\n";
		$reqHistory = $pdo->prepare("INSERT INTO visitHistory (host, visitor) VALUES (?, ?)");
		foreach ($visitHistory as $vh)
		{
			$reqHistory->execute([$vh["host"], $vh["visitor"]]);
		}

		echo "Populating 'notifs'...\n";
		$reqNotifs = $pdo->prepare("INSERT INTO notifs (fromUser, toUser, type, isRead) VALUES (?, ?, ?, ?)");
		foreach ($notifs as $n)
		{
			$reqNotifs->execute([$n["fromUser"], $n["toUser"], $n["type"], $n["isRead"]]);
		}

		$pdo->commit();
		echo "Population completed successfully.\n";
	}
	catch (Throwable $e)
	{
		if ($pdo->inTransaction())
			$pdo->rollBack();
		die("Population failed: " . $e->getMessage() . PHP_EOL);
	}
?>
