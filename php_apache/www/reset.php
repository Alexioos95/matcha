<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	$email = "";
	
	requireNotLogged();
	generateCsrfToken();
	if (isset($_POST["submit"]))
	{
		verifyCsrfToken();
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
			regenerateCsrfToken();
			header("Location: /reset.php");
			exit;
		}
	}
?>

<!DOCTYPE html>
<html lang="en">
	<?php $title = "- Reset your password"; require_once "/usr/local/bin/includes/head.php" ?>
	<body class="login">
		<main class="login-card">
			<h1>Reset password</h1>
			<form action="reset.php" method="POST" autocomplete="off">
				<input type="hidden" name="csrfToken" value="<?= $_SESSION['csrfToken']; ?>">
				<input type="text" name="email" placeholder="Email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" autocapitalize="off" autocomplete="email" aria-label="Email" required>
				<?php if (isset($_SESSION["error"]) || isset($_SESSION["success"])): ?>
				<div class="error-wrapper">
					<p class="log error">
						<?= isset($_SESSION["error"]) ? $_SESSION["error"] : " "; unset($_SESSION["error"]); ?>
					</p>
					<p class="log success">
					<?= isset($_SESSION["success"]) ? $_SESSION["success"] : " "; unset($_SESSION["success"]); ?>
					</p>
				</div>
				<?php endif; ?>
				<button type="submit" name="submit">Submit</button>
			</form>
			<p><a href="login.php">Go back</a></p>
		</main>
	</body>
</html>
