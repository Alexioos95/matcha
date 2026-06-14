<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	requireNotLogged();
	generateCsrfToken();
	$username = "";
	$email = "";
	$firstName = "";
	$lastName = "";
	if (empty($_SESSION["pleaseVerify"]))
		$_SESSION["pleaseVerify"] = false;
	if (isset($_POST["submit"]))
	{
		verifyCsrfToken();
		$username = trim($_POST["username"]);
		$email = trim($_POST["email"]);
		$firstName = trim($_POST["firstName"]);
		$lastName = trim($_POST["lastName"]);
		$password = $_POST["password"];
		$confirm = $_POST["confirm"];

		if (!filter_var($email, FILTER_VALIDATE_EMAIL))
			$_SESSION["error"] = "Invalid email format.";
		elseif ($firstName == "" || $lastName == "")
			$_SESSION["error"] = "Names are mandatory.";
		elseif (!preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/[0-9]/", $password) || !preg_match("/[\W_]/", $password) || strlen($password) < 8)
			$_SESSION["error"] = "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
		elseif (isCommonPassword($password))
			$_SESSION["error"] = "Password is too common.";
		elseif ($password !== $confirm)
			$_SESSION["error"] = "Passwords not matching.";
		else
		{
			$req = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
			$req->execute([$username, $email]);
			if ($req->rowCount() > 0)
			{
				$email = "";
				$username = "";
				$_SESSION["error"] = "Username or email is not available.";
			}
			else
			{
				$hashed = password_hash($password, PASSWORD_DEFAULT);
				$verifCode = bin2hex(random_bytes(16));
				$verifCodeExpires = date("Y-m-d H:i:s", strtotime("+24 hours"));
				$req = $pdo->prepare("INSERT INTO users (username, email, firstName, lastName, password, verifCode, verifCodeExpires) VALUES (?, ?, ?, ?, ?, ?, ?)");
				$req->execute([$username, $email, $firstName, $lastName, $hashed, $verifCode, $verifCodeExpires]);
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
				regenerateCsrfToken();
				$_SESSION["pleaseVerify"] = true;
				header("Location: /register.php");
				exit;
			}
		}
	}
?>

<!DOCTYPE html>
<html lang="en">
	<?php $title = "- Register"; require_once "/usr/local/bin/includes/head.php" ?>
	<body class="login">
		<?php if (!$_SESSION["pleaseVerify"]): ?>
		<main class="login-card">
			<h1>Register</h1>
			<form action="register.php" method="POST" autocomplete="off">
				<input type="hidden" name="csrfToken" value="<?= $_SESSION['csrfToken']; ?>">
				<input type="text" name="email" placeholder="Email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="email" autocorrect="off" autocapitalize="off" aria-label="Email" required>
				<input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username" autocorrect="off" autocapitalize="off" aria-label="Username" required>
				<input type="text" name="firstName" placeholder="First name" value="<?= htmlspecialchars(ucwords($firstName), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="given-name" autocorrect="off" autocapitalize="off" aria-label="first name" required>
				<input type="text" name="lastName" placeholder="Last name" value="<?= htmlspecialchars(ucwords($lastName), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="family-name" autocorrect="off" autocapitalize="off" aria-label="last name" required>
				<input type="password" name="password" placeholder="Password" autocomplete="new-password" aria-label="Password" required>
				<input type="password" name="confirm" placeholder="Confirm password" autocomplete="new-password" aria-label="Confirm password" required>
				<?php if (isset($_SESSION["error"])): ?>
				<div class="error-wrapper">
					<p class="log error">
						<?= isset($_SESSION["error"]) ? $_SESSION["error"] : " "; unset($_SESSION["error"]); ?>
					</p>
				</div>
				<?php endif; ?>
				<button type="submit" name="submit">Submit</button>
			</form>
			<p>Already have an account? <a href="login.php">Login</a></p>
		</main>
		<?php else: ?>
		<p>Please check your mails to activate your account.</p>
		<?php unset($_SESSION["pleaseVerify"]); ?>
		<?php endif; ?>
	</body>
</html>
