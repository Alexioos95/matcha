<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	requireLogin();
	requireProfile();
	generateCsrfToken();
	updateLastOnline($pdo);
	if (isset($_POST["submit_account"]))
	{
		verifyCsrfToken();
		$email = trim($_POST["email"]);
		$username = trim($_POST["username"]);
		$password = $_POST["password"];
		$firstName = trim($_POST["firstName"]);
		$lastName = trim($_POST["lastName"]);
		
		if ($email != "" && !filter_var($email, FILTER_VALIDATE_EMAIL))
			$_SESSION["error"] = "Invalid email format.";
		elseif ($password != "" && (!preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/[0-9]/", $password) || !preg_match("/[\W_]/", $password) || strlen($password) < 8))
			$_SESSION["error"] = "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
		else
		{
			$_SESSION["error"] = "";
			$_SESSION["success"] = "";
			if ($email != "")
			{
				$req = $pdo->prepare("SELECT id FROM users WHERE email = ?");
				$req->execute([$email]);
				if ($req->rowCount() > 0)
					$_SESSION["error"] .= "Email already taken.<br>";
				else
				{
					$req = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
					$req->execute([$email, $_SESSION["user"]["id"]]);
					$_SESSION["user"]["email"] = $email;
					$_SESSION["success"] .= "Updated email.<br>";
				}
			}
			if ($username != "")
			{
				$req = $pdo->prepare("SELECT id FROM users WHERE username = ?");
				$req->execute([$username]);
				if ($req->rowCount() > 0)
					$_SESSION["error"] .= "Username already taken.";
				else
				{
					$req = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
					$req->execute([$username, $_SESSION["user"]["id"]]);
					$_SESSION["user"]["username"] = $username;
					$_SESSION["success"] .= "Updated username.<br>";
				}
			}
			if ($firstName != "")
			{
				$req = $pdo->prepare("UPDATE users SET firstName = ? WHERE id = ?");
				$req->execute([$firstName, $_SESSION["user"]["id"]]);
				$_SESSION["user"]["firstName"] = $firstName;
				$_SESSION["success"] .= "Updated first name.<br>";
			}
			if ($lastName != "")
			{
				$req = $pdo->prepare("UPDATE users SET lastName = ? WHERE id = ?");
				$req->execute([$lastName, $_SESSION["user"]["id"]]);
				$_SESSION["user"]["lastName"] = $lastName;
				$_SESSION["success"] .= "Updated last name.<br>";
			}
			if ($password != "")
			{
				$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
				$req = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
				$req->execute([$hashedPassword, $_SESSION["user"]["id"]]);
				$_SESSION["success"] .= "Updated password.<br>";
			}
		}
		regenerateCsrfToken();
		header("Location: /settings.php");
		exit;
	}
	elseif (isset($_POST["submit_profile"]))
	{
		verifyCsrfToken();
		$gender = $_POST["gender"];
		$preference = $_POST["preference"];
		$city = trim($_POST["location-city"]);
		$country = trim($_POST["location-country"]);
		$bio = trim($_POST["bio"]);
		$interests = json_decode($_POST["interests-selected"], true);
		$pictures = [
			$_SESSION["profile"]["secondaryPictureOne"],
			$_SESSION["profile"]["secondaryPictureTwo"],
			$_SESSION["profile"]["secondaryPictureThree"],
			$_SESSION["profile"]["secondaryPictureFour"]
		];
		if (!is_array($interests))
			$interests = [];
		if ($gender != "male" && $gender != "female")
			$_SESSION["error"] = "Incorrect gender.";
		elseif ($preference != "male" && $preference != "female" && $preference != "either")
			$_SESSION["error"] = "Incorrect preference.";
		elseif ($city == "" || $country == "")
			$_SESSION["error"] = "Your location is mandatory.";
		elseif (strlen($bio) < 10 || strlen($bio) > 255)
			$_SESSION["error"] = "Biography is mandatory and must be between 10 to 255 characters.";
		else
		{
			// Location
			$query = $city . ", " . $country;
			$url = "https://nominatim.openstreetmap.org/search?q=" . urlencode($query) . "&format=json&limit=1";
			$ref = "https://" . getenv("DUMP") . ":8443";
			$ch = curl_init($url);
			curl_setopt_array($ch, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_USERAGENT => "Matcha/1.0",
				CURLOPT_HTTPHEADER => [
					"Accept: application/json",
					"Referer: $ref"
				]
			]);
			$res = curl_exec($ch);
			if ($res == false)
			{
				$_SESSION["error"] = "Sorry, we couldn't identify your location. Try again later or contact the administrator.";
				header("Location: /settings.php");
				exit;
			}
			curl_close($ch);
			$output = json_decode($res, true);
			if (empty($output))
			{
				$_SESSION["error"] = "Sorry, we couldn't identify your location. Try again later or contact the administrator.";
				header("Location: /settings.php");
				exit;
			}
			$lat = $output[0]["lat"];
			$lon = $output[0]["lon"];
			// Pictures
			$dir = "/uploads/" . $_SESSION["user"]["id"];
			if (!is_dir(__DIR__ . $dir))
				mkdir(__DIR__ . $dir, 0774, true);
			$code = isset($_FILES["picture-primary"]) && isset($_FILES["picture-primary"]["error"]) ? $_FILES["picture-primary"]["error"] : "";
			if ($code == UPLOAD_ERR_OK)
			{
				$pic = [];
				if (!saveImage($_FILES["picture-primary"], __DIR__, $dir, $pdo, $pic))
					$_SESSION["error"] = "An error occured with file uploading. Please try again later.";
				else
				{
					$real = realpath(__DIR__ . $_SESSION["profile"]["primaryPicture"]);
					if (str_starts_with($real, realpath(__DIR__ . "/uploads")))
						unlink($real);
					$_SESSION["profile"]["primaryPicture"] = $pic[0];
				}
			}
			elseif ($code != UPLOAD_ERR_NO_FILE)
			{
				$_SESSION["error"] = "An error occured with file uploading. Please try again later.";
				header("Location: /settings.php");
				exit;
			}
			for ($i = 0; $i < 4; $i++)
			{
				$str = "picture-secondary-" . ($i + 1);
				$code = isset($_FILES[$str]) && isset($_FILES[$str]["error"]) ? $_FILES[$str]["error"] : "";
				if ($code == UPLOAD_ERR_OK)
				{
					$pic = [];
					if (!saveImage($_FILES[$str], __DIR__, $dir, $pdo, $pic))
					{
						$_SESSION["error"] = "An error occured with file uploading. Please try again later.";
						$pic[0] = null;
					}
					elseif ($pictures[$i])
					{
						$real = realpath(__DIR__ . $pictures[$i]);
						if (str_starts_with($real, realpath(__DIR__ . "/uploads")))
							unlink($real);
					}
					$pictures[$i] = $pic[0];
				}
			}
			$picArray = array_values(array_filter($pictures, fn($v) => $v != null));
			for ($i = count($picArray); $i < 4; $i++)
			{
				$picArray[] = null;
			}
		}
		// Interests
		$userIntReq = $pdo->prepare("SELECT * from userInterests WHERE user = ?");
		$userIntReq->execute([$_SESSION["user"]["id"]]);
		$userOldInt = array_column($userIntReq->fetchAll(PDO::FETCH_ASSOC), "interest");
		foreach ($userOldInt as $oInt)
		{
			if (!in_array($oInt, $interests))
			{
				$delReq = $pdo->prepare("DELETE FROM userInterests WHERE user = ? AND interest = ?");
				$delReq->execute([$_SESSION["user"]["id"], $oInt]);
			}
		}
		foreach ($interests as $intId)
		{
			if (!in_array($intId, $userOldInt))
			{
				$req = $pdo->prepare("INSERT INTO userInterests (user, interest) VALUES (?, ?)");
				$req->execute([$_SESSION["user"]["id"], $intId]);
			}
		}
		// Update
		$req = $pdo->prepare("UPDATE profiles SET
			gender = ?, preference = ?, bio = ?, city = ?, country = ?, lat = ?, lon = ?, primaryPicture = ?,
			secondaryPictureOne = ?, secondaryPictureTwo = ?, secondaryPictureThree = ?, secondaryPictureFour = ?
			WHERE author = ?");
		$req->execute([
			$gender, $preference, $bio, $city, $country, $lat, $lon, $_SESSION["profile"]["primaryPicture"],
			$picArray[0], $picArray[1], $picArray[2], $picArray[3],
			$_SESSION["user"]["id"]
		]);
		$_SESSION["profile"]["gender"] = $gender;
		$_SESSION["profile"]["preference"] = $preference;
		$_SESSION["profile"]["bio"] = $bio;
		$_SESSION["profile"]["city"] = $city;
		$_SESSION["profile"]["country"] = $country;
		$_SESSION["profile"]["lat"] = $lat;
		$_SESSION["profile"]["lon"] = $lon;
		$_SESSION["profile"]["interests"] = $interests;
		$_SESSION["profile"]["secondaryPictureOne"] = $picArray[0];
		$_SESSION["profile"]["secondaryPictureTwo"] = $picArray[1];
		$_SESSION["profile"]["secondaryPictureThree"] = $picArray[2];
		$_SESSION["profile"]["secondaryPictureFour"] = $picArray[3];
		regenerateCsrfToken();
		if (!isset($_SESSION["error"]))
			$_SESSION["success"] = "Successfully updated profile.";
		header("Location: /settings.php");
		exit;
	}
	$secondaryPictures = [
		$_SESSION["profile"]["secondaryPictureOne"],
		$_SESSION["profile"]["secondaryPictureTwo"],
		$_SESSION["profile"]["secondaryPictureThree"],
		$_SESSION["profile"]["secondaryPictureFour"]
	];
	$intReq = $pdo->prepare("SELECT * from interests");
	$intReq->execute();
?>

<!DOCTYPE html>
<html lang="en">
	<?php $title = " - Settings"; require_once "/usr/local/bin/includes/head.php"; ?>
	<body class="index">
		<?php require_once "/usr/local/bin/includes/header.php" ?>
		<main>
			<div class="settings">
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
				<h2>Update my account</h2>
				<div>
					<form action="settings.php" method="POST" autocomplete="off">
						<input type="hidden" name="csrfToken" value="<?= $_SESSION['csrfToken']; ?>">
						<input type="email" name="email" placeholder="<?= htmlspecialchars($_SESSION['user']['email'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="new-username" aria-label="Email">
						<input type="text" name="username" placeholder="<?= htmlspecialchars($_SESSION['user']['username'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="new-username" aria-label="Username">
						<input type="text" name="firstName" placeholder="<?= htmlspecialchars($_SESSION['user']['firstName'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="new-firstName" aria-label="First name">
						<input type="text" name="lastName" placeholder="<?= htmlspecialchars($_SESSION['user']['lastName'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="new-lastName" aria-label="Last name">
						<input type="password" name="password" placeholder="Password" autocomplete="new-password" aria-label="Password">
						<button type="submit" name="submit_account">Submit</button>
					</form>
				</div>
				<h2>Update my profile</h2>
				<div>
					<form action="settings.php" method="POST" autocomplete="off" enctype="multipart/form-data">
						<input type="hidden" name="csrfToken" value="<?= $_SESSION['csrfToken']; ?>">
						<div class="form-group">
							<label for="gender">Gender</label>
							<select id="gender" name="gender" required>
								<option value="male" <?php if (isset($_SESSION['profile']['gender']) && $_SESSION['profile']['gender'] === 'male') echo 'selected'; ?>>Male</option>
								<option value="female" <?php if (isset($_SESSION['profile']['gender']) && $_SESSION['profile']['gender'] === 'female') echo 'selected'; ?>>Female</option>
							</select>
						</div>
						<div class="form-group">
							<label for="preference">Sexual Preferences</label>
							<select id="preference" name="preference" required>
								<option value="men" <?php if (isset($_SESSION['profile']['preference']) && $_SESSION['profile']['preference'] === 'men') echo 'selected'; ?>>Men</option>
								<option value="female" <?php if (isset($_SESSION['profile']['preference']) && $_SESSION['profile']['preference'] === 'female') echo 'selected'; ?>>Women</option>
								<option value="either" <?php if (isset($_SESSION['profile']['preference']) && $_SESSION['profile']['preference'] === 'either') echo 'selected'; ?>>Either</option>
							</select>
						</div>
						<div class="form-group group-location">
							<label for="location-country">Location</label>
							<input type="text" id="location-city" name="location-city" value="<?= isset($_SESSION['profile']['city']) ? htmlspecialchars($_SESSION['profile']['city']) : 'City'; ?>" aria-label="City">
							<input type="text" id="location-country" name="location-country" value="<?= isset($_SESSION['profile']['country']) ? htmlspecialchars($_SESSION['profile']['country']) : 'Country'; ?>" aria-label="Country">
							<i class="fa-solid fa-spinner loader hidden"></i>
							<button class="geo-button" type="button" title="Let us input your location through a geolocation with the GPS of your device."><i class="fa-solid fa-location-arrow"></i> Use my current location</button>
							<p class="log error geo-error"></p>
						</div>
						<div class="form-group">
							<p>Profile picture</p>
							<div class="picture-secondary-wrapper">
								<div>
									<div class="picture-item picture-item-primary">
									<?php if ($_SESSION["profile"]["primaryPicture"]): ?>
										<img class="picture-preview" src="<?= htmlspecialchars($_SESSION["profile"]["primaryPicture"]); ?>" alt="Profile picture">
									<?php endif; ?>
									</div>
									<button class="picture-upload" type="button" aria-label="Upload your profile's picture" title="Upload your profile's picture"><i class="fa-solid fa-cloud-arrow-up"></i> Upload</button>
									<input class="picture-input" type="file" name="picture-primary" aria-label="Upload an image" accept=".jpg, .jpeg, .png" hidden/>
								</div>
							</div>
						</div>
						<div class="form-group">
							<p>Secondary picture (max 4)</p>
							<div class="picture-secondary-wrapper">
							<?php for ($i = 0; $i < 4; $i++): ?>
								<div>
									<div class="picture-item">
									<?php if ($secondaryPictures[$i]): ?>
										<img class="picture-preview" src="<?= htmlspecialchars($secondaryPictures[$i]); ?>" alt="Secondary picture number <?= $i ?>">
										<div class="grid-items-overlay">
											<button type="button" aria-label="Delete this picture" title="Delete this picture"><i class="fa-solid fa-trash-can"></i></button>
										</div>
									<?php endif; ?>
									</div>
									<button class="picture-upload" type="button" aria-label="Upload a secondary picture" title="Upload a secondary picture"><i class="fa-solid fa-cloud-arrow-up"></i> Upload</button>
									<input class="picture-input" type="file" name="picture-secondary-<?= $i + 1 ?>" accept=".jpg, .jpeg, .png" hidden></input>
								</div>
							<?php endfor; ?>
							</div>
						</div>
						<div class="form-group form-group-interest">
							<p>Interests</p>
							<div class="interests-wrapper">
							<?php while ($row = $intReq->fetch(PDO::FETCH_ASSOC)): ?>
								<button type="button" class="interest <?php if (isset($_SESSION['profile']['interests']) && in_array($row['id'], $_SESSION['profile']['interests'])) echo 'selected'?>" data-interest-id="<?= $row['id']; ?>">
									<span><?= htmlspecialchars($row['name']); ?></span>
								</button>
							<?php endwhile; ?>
								<input type="hidden" class="interests-selected" name="interests-selected">
							</div>
						</div>
						<div class="form-group">
							<label for="bio">Biography</label>
							<textarea id="bio" name="bio" placeholder="Tell us about yourself... (10 to 255 characters)" minlength="10" maxlength="255" required><?php if (isset($_SESSION['profile']['bio'])) echo htmlspecialchars($_SESSION['profile']['bio'], ENT_QUOTES, 'UTF-8'); ?></textarea>
						</div>
						<button type="submit" name="submit_profile">Submit</button>
					</form>
				</div>
			</div>
		</main>
		<?php require_once "/usr/local/bin/includes/footer.php" ?>
		<script>
			// Geolocation
			const locCity = document.getElementById("location-city");
			const locCountry = document.getElementById("location-country");
			const loader = document.getElementsByClassName("loader")[0];
			function locationFallback(error)
			{
				loader.classList.add("hidden");
				const p = document.getElementsByClassName("geo-error")[0];
				p.innerHTML = error;
			}
			function geolocation()
			{
				if (confirm("We'll be using the GPS of your device and browser to locate your coordinates. Proceed?"))
				{
					loader.classList.remove("hidden");
					if (navigator.geolocation)
					{
						navigator.geolocation.getCurrentPosition(
							function(position)
							{
								const lat = position.coords.latitude;
								const lon = position.coords.longitude;
								fetch("/api/geolocation.php", {
									method: "POST",
									headers: {"Content-Type": "application/json"},
									body: JSON.stringify({
										csrfToken: "<?= $_SESSION['csrfToken'] ?>",
										lat: lat,
										lon: lon,
										id: <?= $_SESSION["user"]["id"] ?>
									})
								})
									.then(res => res.json())
									.then(data => {
										if (!data.error)
										{
											locCity.value = data.city;
											locCountry.value = data.country;
											loader.classList.add("hidden");
										}
										else
											locationFallback("Sorry, we couldn't locate you.");
									})
										.catch();
							},
							function(error)
							{ locationFallback("Error with geolocation: " + error.message + "."); }
						);
					}
				else
					locationFallback("Your browser or device is not handling geolocation.");
				}
			}
			const geoButton = document.getElementsByClassName("geo-button")[0];
			geoButton.addEventListener("click", geolocation);
			// Pictures
			const fileInputs = document.getElementsByClassName("picture-input");
			const uploadButtons = document.getElementsByClassName("picture-upload");
			const previews = document.getElementsByClassName("picture-item");
			for (let i = 0; i < 5; i++)
			{
				uploadButtons[i].addEventListener("click", () => { fileInputs[i].click(); });
				fileInputs[i].addEventListener("change", function () {
					const file = this.files[0];
					if (!file)
					{
						if (i == 0)
							previews[i].innerHTML = `<img class="picture-preview" src="<?= htmlspecialchars($_SESSION["profile"]["primaryPicture"]); ?>" alt="Profile picture">`;
						else
							previews[i].innerHTML = "";
						return ;
					}
					const url = URL.createObjectURL(file);
					if (i == 0)
						previews[i].innerHTML = `<img class="picture-preview" src="${url}" alt="Preview">`;
					else
					{
						previews[i].innerHTML = `
							<img class="picture-preview" src="${url}" alt="Preview">
							<div class="grid-items-overlay">
								<button type="button"><i class="fa-solid fa-trash-can"></i></button>
							</div>
						`;
						const newButton = previews[i].querySelector("button");
						newButton.addEventListener("click", () => {
							previews[i].innerHTML = "";
						});
					}
				});
				const deleteButton = previews[i].querySelector("button");
				if (deleteButton)
				{
					deleteButton.addEventListener("click", () => {
						previews[i].innerHTML = "";
						fetch("/api/delete.php", {
							method: "POST",
							headers: {"Content-Type": "application/x-www-form-urlencoded"},
							body: new URLSearchParams({i: i, csrfToken: "<?= $_SESSION['csrfToken']?>"})
						})
							.catch();
					});
				}
			}
			// Interests
			const intButtons = document.getElementsByClassName("interest");
			const hiddenInput = document.getElementsByClassName("interests-selected")[0];
			let selected = <?= json_encode($_SESSION["profile"]["interests"] ?? []); ?>;
			hiddenInput.value = JSON.stringify(selected);
			Array.from(intButtons).forEach(button => {
				button.addEventListener("click", () => {
					const id = Number(button.dataset.interestId);
					button.classList.toggle("selected");
					if (selected.includes(id))
						selected = selected.filter(item => item != id);
					else
						selected.push(id);
					hiddenInput.value = JSON.stringify(selected);
				});
			});
		</script>
		<?php require_once "/usr/local/bin/includes/notifJS.php" ?>
	</body>
</html>
