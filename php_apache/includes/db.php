<?php
	$host = "mysql";
	$dbname = getenv("MYSQL_DATABASE");
	$user = getenv("MYSQL_USER");
	$pass = getenv("MYSQL_PASSWORD");
	$dump = getenv("DUMP");

	// set_exception_handler(function ($e) {
	// 	error_log($e->getMessage());
	// 	exit("Something went wrong.");
	// });

	if (!$dbname || !$user || !$pass || !$dump)
	{
		error_log("Missing environment variables");
		die("Couldn't connect to the database");
	}
	try
	{
		$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
	}
	catch (PDOException $e)
	{
		error_log($e->getMessage());
		die("Couldn't connect to the database");
	}
	function isCommonPassword($password)
	{
		$hash = strtoupper(sha1($password));
		$prefix = substr($hash, 0, 5);
		$suffix = substr($hash, 5);
		$url = "https://api.pwnedpasswords.com/range/" . $prefix;
		$res = file_get_contents($url);
		if ($res == false)
			return (false);
		foreach (explode("\n", $res) as $line)
		{
			list($hashSuffix, $count) = explode(":", trim($line));
			if ($hashSuffix === $suffix)
				return (true);
		}
		return (false);
	}

	function saveImage($file, $dest, $dir, $pdo, &$pictures)
	{
		$filename = $file["name"];
		$tempname = $file["tmp_name"];
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($finfo, $tempname);
		finfo_close($finfo);
	
		if (($extension != "jpg" && $extension != "jpeg" && $extension != "png") || ($mime != "image/jpeg" && $mime != "image/png"))
			return (false);
		if ($extension === "png")
			$image = imagecreatefrompng($tempname);
		else
			$image = imagecreatefromjpeg($tempname);
		if (!$image)
			return (false);
			$path = $dir . "/" . uniqid() . "." . $extension;
		if (($extension === "png" && !imagepng($image, $dest . $path)) || (($extension === "jpg" || $extension === "jpeg") && !imagejpeg($image, $dest . $path)))
			return (false);
		imagedestroy($image);
		$pictures[] = $path;
		return (true);
	}
	function updateFameScore($pdo, $id)
	{
		$historyReq = $pdo->prepare("SELECT COUNT(*) FROM visitHistory WHERE host = ?");
		$historyReq->execute([$id]);
		$likeReq = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE target = ?");
		$likeReq->execute([$id]);
		$views = $historyReq->fetchColumn();
		$likes = $likeReq->fetchColumn();
		$popularity = min(log($views + 1) * 10, 50);
		$conversion = min((($likes + 10) / ($views + 100)) * 100, 50);
		$fame = round((log($views + 1) * 0.5) + (log($likes + 1) * 2), 2);
		$updateReq = $pdo->prepare("UPDATE profiles SET fame = ? WHERE author = ?");
		$updateReq->execute([$fame, $id]);
	}
	function updateLastOnline($pdo)
	{
		$now = time();
		if (!isset($_SESSION["user"]["lastOnline"]) || $now - strtotime($_SESSION["user"]["lastOnline"]) > 300)
		{
			$date = date("Y-m-d H:i:s", $now);
			$req = $pdo->prepare("UPDATE users SET lastOnline = ? WHERE id = ?");
			$req->execute([$date, $_SESSION["user"]["id"]]);
			$_SESSION["user"]["lastOnline"] = $date;
		}
	}
	function createNotif($pdo, $id, $type)
	{
		$notif = $pdo->prepare("INSERT INTO notifs (fromUser, toUser, type) VALUES (?, ?, ?)");
		$notif->execute([$_SESSION["user"]["id"], $id, $type]);
	}
	function isMutualMatch($pdo, $me, $them)
	{
		$req = $pdo->prepare("
			SELECT COUNT(*) FROM likes l1
			INNER JOIN likes l2 ON l2.author = :them AND l2.target = :me
			WHERE l1.author = :me AND l1.target = :them
		");
		$req->bindValue(":me",   $me,   PDO::PARAM_INT);
		$req->bindValue(":them", $them, PDO::PARAM_INT);
		$req->execute();
		return ((int)$req->fetchColumn() > 0);
	}
	function isBlocked($pdo, $me, $them)
	{
		$req = $pdo->prepare("
			SELECT 1 FROM blocks
			WHERE (author = :me AND target = :them)
			   OR (author = :them AND target = :me)
			LIMIT 1
		");
		$req->bindValue(":me",   $me,   PDO::PARAM_INT);
		$req->bindValue(":them", $them, PDO::PARAM_INT);
		$req->execute();
		return ($req->fetch() !== false);
	}
?>
