<?php
	require_once "db.php";
	require_once "auth.php";

	if (isset($_SESSION["user"]) && isset($_SESSION["user"]["id"]))
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
		$username = trim($_POST["username"]);
		$password = $_POST["password"];
		$req = $pdo->prepare("SELECT * FROM users WHERE username = ?");
		$req->execute([$username]);
		$user = $req->fetch(PDO::FETCH_ASSOC);
		if ($user)
		{
			$email = $user["email"];
			if ($user["isActive"] == false)
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
				$_SESSION["error"] = "Your account is not activated.<br>A new mail was sent.";
			}
			elseif (password_verify($password, $user["password"]))
			{
				$token = bin2hex(random_bytes(64));
				$hashedToken = hash("sha256", $token);
				date_default_timezone_set("Europe/Paris");
				$time = time() + 60 * 60 * 24;
				$date = date("Y-m-d H:i:s", $time);
				$now = date("Y-m-d H:i:s");
				$cookieReq = $pdo->prepare("UPDATE users SET cookieToken = ?, cookieExpires = ?, lastOnline = ? WHERE id = ?");
				$cookieReq->execute([$hashedToken, $date, $now, $user["id"]]);
				$cookieValue = $token;
				setcookie("rememberMe", $cookieValue, ["expires" => $time, "path" => "/", "httponly" => true, "secure" => true, "samesite" => "Lax"]);
				$_SESSION["user"] = $user;
				$_SESSION["user"]["lastOnline"] = $now;
				$_SESSION["csrfToken"] = bin2hex(random_bytes(32));
				if ($_SESSION["user"]["isComplete"])
				{
					$profileReq = $pdo->prepare("SELECT * FROM profiles WHERE author = ?");
					$profileReq->execute([$_SESSION["user"]["id"]]);
					$profile = $profileReq->fetch(PDO::FETCH_ASSOC);
					$_SESSION["profile"] = $profile;
					$intReq = $pdo->prepare("SELECT * FROM userInterests WHERE userId = ?");
					$intReq->execute([$_SESSION["user"]["id"]]);
					$int = $intReq->fetchAll(PDO::FETCH_ASSOC);
					$interestIds = array_column($int, 'interestId');
					$_SESSION["profile"]["interests"] = $interestIds;
					header("Location: /");
				}
				else
					header("Location: /set_profile.php");
				exit;
			}
			else
				$_SESSION["error"] = "Invalid username or password.";
		}
		else
			$_SESSION["error"] = "Invalid username or password.";
		$_SESSION["csrfToken"] = bin2hex(random_bytes(32));
		header("Location: /login.php");
		exit;
	}
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="description" content="Match with your soulmate!">
		<title>Matcha - login</title>
		<link rel="icon" type="image/x-icon" href="/images/favicon.ico">
		<link rel="stylesheet" type="text/css" href="https://necolas.github.io/normalize.css/8.0.1/normalize.css">
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
		<link rel="stylesheet" type="text/css" href="./styles.css">
	</head>
	<body class="login-body">
		<div class="login-container">
			<h1>Login</h1>
			<form action="login.php" method="POST">
				<input type="hidden" name="csrfToken" value="<?php echo $_SESSION['csrfToken']; ?>">
				<input type="text" name="username" placeholder="Username" autocomplete="current-username" aria-label="Username" required>
				<input type="password" name="password" placeholder="Password" autocomplete="current-password" aria-label="Password" required>
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
			<p>Forgot your password?<br><a href="reset.php">Request a reset</a></p>
			<p>No account yet? <a href="register.php">Register</a></p>
		</div>
	</body>
</html>
