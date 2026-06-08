<?php
	require_once "/var/www/html/db.php";

	$data = json_decode(file_get_contents("/usr/local/bin/script/mixture.json"), true);
	$users = json_decode(file_get_contents("/usr/local/bin/script/mixtures/users.json"), true);
	$profiles = json_decode(file_get_contents("/usr/local/bin/script/mixtures/profiles.json"), true);

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
		foreach ($data["interests"] as $i)
		{
			$reqInterests->execute([$i["id"], $i["name"]]);
		}

		echo "Populating 'userInterests'...\n";
		$reqUserInterests = $pdo->prepare("INSERT INTO userInterests (userId, interestId) VALUES (?, ?)");
		foreach ($data["userInterests"] as $ui)
		{
			$reqUserInterests->execute([$ui["userId"], $ui["interestId"]]);
		}

		echo "Populating 'likes'...\n";
		$reqLikes = $pdo->prepare("INSERT INTO likes (author, target) VALUES (?, ?)");
		foreach ($data["likes"] as $l)
		{
			$reqLikes->execute([$l["author"], $l["target"]]);
		}

		echo "Populating 'blocks'...\n";
		$reqBlocks = $pdo->prepare("INSERT INTO blocks (author, target) VALUES (?, ?)");
		foreach ($data["blocks"] as $l)
		{
			$reqBlocks->execute([$l["author"], $l["target"]]);
		}

		echo "Populating 'history'...\n";
		$reqHistory = $pdo->prepare("INSERT INTO history (host, visitor) VALUES (?, ?)");
		foreach ($data["history"] as $l)
		{
			$reqHistory->execute([$l["host"], $l["visitor"]]);
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
