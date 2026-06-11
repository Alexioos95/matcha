<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	requireNotLogged();
	$code = $_GET["code"] ?? NULL;
	$email = $_GET["email"] ?? NULL;

	if (!$code || !$email)
		exit("Invalid link.");
	$req = $pdo->prepare("SELECT * FROM users WHERE email = ? AND verifCode = ?");
	$req->execute([$email, $code]);
	if ($req->rowCount() <= 0)
		exit("Invalid link.");
	$user = $req->fetch(PDO::FETCH_ASSOC);
	if (!$user)
		exit("Sorry, a problem occured on our side. Try again later.");
	elseif (strtotime($user["verifCodeExpires"]) < time())
	{
		$verifCode = bin2hex(random_bytes(16));
		$verifCodeExpires = date("Y-m-d H:i:s", strtotime("+24 hours"));
		$newCodeReq = $pdo->prepare("UPDATE users SET verifCode = ?, verifCodeExpires = ? WHERE email = ?");
		$newCodeReq->execute([$verifCode, $verifCodeExpires, $email]);
		$emailLink = htmlspecialchars(("https://" . getenv("DUMP") . ":8443/api/activate.php?code=" . $verifCode . "&email=" . urlencode($email)), ENT_QUOTES, "UTF-8");
		$emailBody = "
			<html>
				<head>
					<title>Matcha - Activate your account</title>
				</head>
				<body>
					<p>Click the link below to confirm your registration to Matcha:</p>
					<p><a href='$emailLink'>$emailLink</a></p>
				</body>
			</html>
		";
		$mailReq = $pdo->prepare("INSERT INTO mailQueue (email, subject, body) VALUES (?, ?, ?)");
		$mailReq->execute([$email, "Matcha - Activate your account", $emailBody]);
		exit("Verification code has expired. A new mail has been sent.");
	}
	$updateReq = $pdo->prepare("UPDATE users SET isActive = 1, verifCode = NULL WHERE email = ? AND verifCode = ?");
	$updateReq->execute([$email, $code]);
	header("Location: /login.php");
	exit;
?>
