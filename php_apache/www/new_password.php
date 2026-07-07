<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	requireNotLogged();
	generateCsrfToken();
	if (isset($_POST["submit"]))
	{
		verifyCsrfToken();
		$password = $_POST["password"];
		$code = $_POST["code"];
		$email = trim($_POST["email"]);

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
				regenerateCsrfToken();
				header("Location: /login.php");
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
			date_default_timezone_set("Europe/Paris");
			if (strtotime($user["verifCodeExpires"]) < time())
				exit("Link has expired.");
		}
	}
?>

<!DOCTYPE html>
<html lang="en">
	<?php require_once "/usr/local/bin/includes/head.php" ?>
	<body class="login">
		<div class="login-card">
			<h1>Set new password</h1>
			<form action="new_password.php" method="POST" autocomplete="off">
				<input type="hidden" name="csrfToken" value="<?= $_SESSION['csrfToken']; ?>">
				<input type="password" name="password" placeholder="New password" autocapitalize="off" required>
				<input type="hidden" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
				<input type="hidden" name="code" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
				<?php if (isset($_SESSION["error"])): ?>
				<div class="error-wrapper">
					<p class="log error">
						<?= isset($_SESSION["error"]) ? $_SESSION["error"] : " "; unset($_SESSION["error"]); ?>
					</p>
				</div>
				<?php endif; ?>
				<button type="submit" name="submit">Submit</button>
			</form>
			<p><a href="login.php">Go back</a></p>
		</div>
	</body>
</html>
