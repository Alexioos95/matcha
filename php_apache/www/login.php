<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	requireNotLogged();
	generateCsrfToken();
	if (isset($_POST["submit"]))
	{
		verifyCsrfToken();
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
				$_SESSION["error"] = "Your account is not activated.";
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
				regenerateCsrfToken();
				if ($_SESSION["user"]["isComplete"])
				{
					$profileReq = $pdo->prepare("SELECT * FROM profiles WHERE author = ?");
					$profileReq->execute([$_SESSION["user"]["id"]]);
					$profile = $profileReq->fetch(PDO::FETCH_ASSOC);
					$_SESSION["profile"] = $profile;
					$intReq = $pdo->prepare("SELECT * FROM userInterests WHERE user = ?");
					$intReq->execute([$_SESSION["user"]["id"]]);
					$int = $intReq->fetchAll(PDO::FETCH_ASSOC);
					$interests = array_column($int, 'interest');
					$_SESSION["profile"]["interests"] = $interests;
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
		regenerateCsrfToken();
		header("Location: /login.php");
		exit;
	}
?>

<!DOCTYPE html>
<html lang="en">
	<?php require_once "/usr/local/bin/includes/head.php" ?>
	<body class="login">
		<main class="login-card">
			<h1>Login</h1>
			<form action="login.php" method="POST">
				<input type="hidden" name="csrfToken" value="<?= $_SESSION['csrfToken']; ?>">
				<input type="text" name="username" placeholder="Username" autocomplete="username" aria-label="Username" required>
				<input type="password" name="password" placeholder="Password" autocomplete="current-password" aria-label="Password" required>
				<?php if (isset($_SESSION["error"])): ?>
				<div class="error-wrapper">
					<p class="log error">
						<?= isset($_SESSION["error"]) ? $_SESSION["error"] : " "; unset($_SESSION["error"]); ?>
					</p>
				</div>
				<?php endif; ?>
				<button type="submit" name="submit">Submit</button>
			</form>
			<p>Forgot your password?<br><a href="reset.php">Request a reset</a></p>
			<p>No account yet? <a href="register.php">Register</a></p>
		</main>
	</body>
</html>
