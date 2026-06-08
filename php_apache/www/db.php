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

	function updateFameScore($pdo, $id)
	{
		$historyReq = $pdo->prepare("SELECT COUNT(*) FROM history WHERE host = ?");
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
?>
