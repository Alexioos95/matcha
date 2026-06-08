<?php
	require_once "db.php";
	require_once "auth.php";

	$email = "";
	
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
		$email = trim($_POST["email"]);
		if (!filter_var($email, FILTER_VALIDATE_EMAIL))
			$_SESSION["error"] = "Invalid email format.";
		else
		{
			$req = $pdo->prepare("SELECT * FROM users WHERE email = ?");
			$req->execute([$email]);
			if ($req->rowCount() <= 0)
			{
				$email = "";
				$_SESSION["error"] = "Invalid email.";
			}
			else
			{
				$user = $req->fetch(PDO::FETCH_ASSOC);
				if (!$user)
					$_SESSION["error"] = "Sorry, a problem occured. Try again later.";
				elseif ($user["isActive"] == false)
					$_SESSION["error"] = "This account is not verified.";
				else
				{
					$verifCode = bin2hex(random_bytes(16));
					$verifCodeExpires = date("Y-m-d H:i:s", strtotime("+24 hours"));
					$req = $pdo->prepare("UPDATE users SET verifCode = ?, verifCodeExpires = ? WHERE email = ?");
					$req->execute([$verifCode, $verifCodeExpires, $email]);
					$emailLink = htmlspecialchars(("https://" . getenv("DUMP") . ":8443/new_password.php?code=" . $verifCode . "&email=" . urlencode($email)), ENT_QUOTES, "UTF-8");
					$emailBody = "
						<html>
							<head>
								<title>Matcha - Your reset request</title>
							</head>
							<body>
								<p>Click the link below to reset your password to Matcha:</p>
								<p><a href='$emailLink'>$emailLink</a></p>
							</body>
						</html>
					";
					$mailReq = $pdo->prepare("INSERT INTO mailQueue (email, subject, body) VALUES (?, ?, ?)");
					$mailReq->execute([$email, "Matcha - Your reset request", $emailBody]);
					$_SESSION["success"] = "The email was sent.<br>Check your inbox!";
				}
			}
			$_SESSION["csrfToken"] = bin2hex(random_bytes(32));
			header("Location: /reset.php");
			exit;
		}
	}
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="description" content="Match with your soulmate!">
		<title>Matcha - reset password</title>
		<link rel="icon" type="image/x-icon" href="/images/favicon.ico">
		<link rel="stylesheet" type="text/css" href="https://necolas.github.io/normalize.css/8.0.1/normalize.css">
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
		<link rel="stylesheet" type="text/css" href="./styles.css">
	</head>
	<body class="login-body">
		<div class="login-container">
			<h1>Reset password</h1>
			<form action="reset.php" method="POST" autocomplete="off">
				<input type="hidden" name="csrfToken" value="<?php echo $_SESSION['csrfToken']; ?>">
				<input type="text" name="email" placeholder="Email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" autocapitalize="off" aria-label="Email" required>
				<div class="error-wrapper">
					<p class="error">
						<?php 
							echo isset($_SESSION["error"]) ? $_SESSION["error"] : " "; 
							unset($_SESSION["error"]);
						?>
					</p>
					<button type="submit" name="submit">Submit</button>
					<p class="success">
					<?php
						echo isset($_SESSION["success"]) ? $_SESSION["success"] : " "; 
						unset($_SESSION["success"]);
					?>
					</p>
				</div>
			</form>
			<p><a href="login.php">Go back</a></p>
		</div>
	</body>
</html>
