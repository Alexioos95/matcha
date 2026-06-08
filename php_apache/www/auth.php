<?php
	session_start();

	if (!isset($_SESSION["user"]["id"]) && isset($_COOKIE["rememberMe"]))
	{
		$token = $_COOKIE["rememberMe"];
		$hashedToken = hash("sha256", $token);
		$userReq = $pdo->prepare("SELECT * FROM users WHERE cookieToken = ? AND cookieExpires > NOW() LIMIT 1");
		$userReq->execute([$hashedToken]);
		$user = $userReq->fetch(PDO::FETCH_ASSOC);
		if ($user)
		{
			session_regenerate_id(true);
			$_SESSION["user"] = $user;
			$profileReq = $pdo->prepare("SELECT * FROM profiles WHERE author = ?");
			$profileReq->execute([$user["id"]]);
			$profile = $profileReq->fetch(PDO::FETCH_ASSOC);
			$_SESSION["profile"] = $profile;
			$intReq = $pdo->prepare("SELECT * FROM userInterests WHERE userId = ?");
			$intReq->execute([$_SESSION["user"]["id"]]);
			$int = $intReq->fetchAll(PDO::FETCH_ASSOC);
			$_SESSION["profile"]["interests"] = array_column($int, "interestId");
			$_SESSION["csrfToken"] = bin2hex(random_bytes(32));
			$newToken = bin2hex(random_bytes(64));
			$newHashedToken = hash("sha256", $newToken);
			$newTime = time() + 60 * 60 * 24;
			date_default_timezone_set("Europe/Paris");
			$newDate = date("Y-m-d H:i:s", $newTime);
			$now = date("Y-m-d H:i:s");
			$update = $pdo->prepare("UPDATE users SET cookieToken = ?, cookieExpires = ?, lastOnline = ? WHERE id = ?");
			$update->execute([$newHashedToken, $newDate, $now, $user["id"]]);
			$_SESSION["user"]["lastOnline"] = $now;
			setcookie("rememberMe", $newToken, ["expires" => $newTime, "path" => "/", "httponly" => true, "secure" => true, "samesite" => "Lax" ]);
		}
	}
	
	function requireCompleteProfile()
	{
		if (isset($_SESSION["user"]) && isset($_SESSION["user"]["id"]) && isset($_SESSION["user"]["isComplete"]) && !$_SESSION["user"]["isComplete"])
		{
			header("Location: /set_profile.php");
			exit;
		}
	}
?>
