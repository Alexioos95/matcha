<?php
	require_once "db.php";
	require_once "auth.php";

	if (isset($_SESSION["user"] && isset([$_SESSION]["user"]["id"])))
	{
		header("Location: /");
		exit;
	}
	if (empty($_SESSION["csrfToken"]))
		$_SESSION["csrfToken"] = bin2hex(random_bytes(32));
	if (isset($_POST["submit"]))
	{
		if (!isset($_POST["csrfToken"]) || !hash_equals($_SESSION["csrfToken"], $_POST["csrfToken"]))
			exit("Invalid CSRF token");
		$password = $_POST["password"];
		$code = $_POST["code"];
		$email = trim($_POST["email"]);

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
				list($hashSuffix, $count) = explode(':', trim($line));
				if ($hashSuffix === $suffix)
					return (true);
			}
			return (false);
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL))
			$_SESSION["error"] = "Invalid email format.";
		elseif (!preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/[0-9]/", $password) || !preg_match("/[\W_]/", $password) || strlen($password) < 8)
			$_SESSION["error"] = "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
		elseif (isCommonPassword($password))
			$_SESSION["error"] = "Password is too common.";
		else
		{		
			$hashed = password_hash($password, PASSWORD_DEFAULT);
			$req = $pdo->prepare("UPDATE users SET password = ?, verifCode = NULL WHERE email = ? AND verifCode = ?");
			$req->execute([$hashed, $email, $code]);
			if ($req->rowCount() <= 0)
				$_SESSION["error"] = "An error occured.";
			else
			{
				header("Location: login.php");
				exit;
			}
		}
	}
	else
	{
		$code = $_GET["code"] ?? NULL;
		$email = $_GET["email"] ?? NULL;

		if (!$code || !$email)
			exit("Invalid link.");
		else
		{
			$req = $pdo->prepare("SELECT * FROM users WHERE email = ? AND verifCode = ?");
			$req->execute([$email, $code]);
			if ($req->rowCount() <= 0)
				exit("Invalid link.");
			$user = $req->fetch(PDO::FETCH_ASSOC);
			if (!$user)
				exit("Sorry, a problem occured on our side. Try again later.");
			if (strtotime($user["verifCodeExpires"]) < time())
				exit("Link has expired. Please make a new reset request.");
		}
	}
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="description" content="Match with your soulmate!">
		<title>Matcha</title>
		<link rel="icon" type="image/x-icon" href="/images/favicon.ico">
		<link rel="stylesheet" type="text/css" href="https://necolas.github.io/normalize.css/8.0.1/normalize.css">
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
		<link rel="stylesheet" type="text/css" href="./styles.css">
	</head>
	<body class="login-body">
		<div class="login-container">
			<h2>Set new password</h2>
			<form action="new_password.php" method="POST" autocomplete="off">
				<input type="hidden" name="csrfToken" value="<?php echo $_SESSION['csrfToken']; ?>">
				<input type="password" name="password" placeholder="New password" autocapitalize="off" required>
				<input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
				<input type="hidden" name="code" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
				<div class="error-wrapper">
					<p class="error">
						<?php 
							echo isset($_SESSION["error"]) ? $_SESSION["error"] : " "; 
							unset($_SESSION["error"]);
						?>
					</p>
					<button type="submit" name="submit">Submit</button>
				</div>
			</form>
			<p><a href="login.php">Go back</a></p>
		</div>
	</body>
</html>
