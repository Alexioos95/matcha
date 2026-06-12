<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	if (isset($_SESSION["profile"]))
	{
		header("Location: /");
		exit;
	}
	requireLogin();
	generateCsrfToken();
	if (isset($_POST["submit"]))
	{
		verifyCsrfToken();
		$gender = $_POST["gender"];
		$preference = $_POST["preference"];
		$day = (int)$_POST["birth-day"];
		$month = (int)$_POST["birth-month"];
		$year = (int)$_POST["birth-year"];
		$bio = trim($_POST["bio"]);
		$city = trim($_POST["location-city"]);
		$country = trim($_POST["location-country"]);
		$interests = json_decode($_POST["interests-selected"], true);
		$pictures = [];
		if (!is_array($interests))
			$interests = [];
		if ($_FILES["picture-primary"])
			$code = isset($_FILES["picture-primary"]["error"]) ? $_FILES["picture-primary"]["error"] : "";
		if ($gender != "male" && $gender != "female")
			$_SESSION["error"] = "Incorrect gender.";
		elseif ($year > (date("Y") - 18) || !checkdate($month, $day, $year))
			$_SESSION["error"] = "The birthdate is incorrect.";
		elseif ($city == "" || $country == "")
			$_SESSION["error"] = "Your location is mandatory.";
		elseif ($preference != "male" && $preference != "female" && $preference != "either")
			$_SESSION["error"] = "Incorrect preference.";
		elseif (strlen($bio) < 10 || strlen($bio) > 255)
			$_SESSION["error"] = "Biography is mandatory and must be between 10 to 255 characters.";
		elseif (!isset($_FILES["picture-primary"]))
			$_SESSION["error"] = "A profile picture is mandatory.";
		elseif ($code != UPLOAD_ERR_OK)
		{
			if ($code == UPLOAD_ERR_NO_FILE)
				$_SESSION["error"] = "A profile picture is mandatory.";
			elseif ($code == UPLOAD_ERR_INI_SIZE)
				$_SESSION["error"] = "The profile picture is too large.";
			elseif ($code == UPLOAD_ERR_FORM_SIZE)
				$_SESSION["error"] = "The profile picture is too large.";
			elseif ($code == UPLOAD_ERR_PARTIAL)
				$_SESSION["error"] = "An error occured. Please try again later.";
			elseif ($code == UPLOAD_ERR_NO_TMP_DIR)
				$_SESSION["error"] = "An error occured. Please try again later.";
			elseif ($code == UPLOAD_ERR_CANT_WRITE)
				$_SESSION["error"] = "An error occured. Please try again later.";
			elseif ($code == UPLOAD_ERR_EXTENSION)
				$_SESSION["error"] = "The upload was blocked by an extension.";
			else
				$_SESSION["error"] = "An error occured. Please try again later.";
		}
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
			if ($res === false)
			{
				$_SESSION["error"] = "Sorry, we couldn't identify your location. Try again later or contact the administrator.";
				header("Location: /set_profile.php");
				exit;
			}
			curl_close($ch);
			$output = json_decode($res, true);
			if (empty($output))
			{
				$_SESSION["error"] = "Sorry, we couldn't identify your location. Try again later or contact the administrator.";
				header("Location: /set_profile.php");
				exit;
			}
			$lat = $output[0]["lat"];
			$lon = $output[0]["lon"];
			// Pictures
			$dir = "/uploads/" . $_SESSION["user"]["id"];
			if (!is_dir(__DIR__ . $dir))
				mkdir(__DIR__ . $dir, 0774, true);
			if (!saveImage($_FILES["picture-primary"], __DIR__, $dir, $pdo, $pictures))
				$_SESSION["error"] = "An error occured with file uploading. Please try again later.";
			else
			{
				$saved = 0;
				foreach ($_FILES["picture-secondary"]["name"] as $key => $name)
				{
					if ($saved == 4)
						break;
					if ($_FILES["picture-secondary"]["error"][$key] === UPLOAD_ERR_NO_FILE)
						continue;
					$file = [
						"name" => $_FILES["picture-secondary"]["name"][$key],
						"tmp_name" => $_FILES["picture-secondary"]["tmp_name"][$key],
						"error" => $_FILES["picture-secondary"]["error"][$key],
						"size" => $_FILES["picture-secondary"]["size"][$key],
						"type" => $_FILES["picture-secondary"]["type"][$key]
					];
					saveImage($file, __DIR__, $dir, $pdo, $pictures);
					$saved++;
				}
				for (; $saved < 5; $saved++)
				{
					$pictures[] = NULL;
				}
				// Interests
				foreach ($interests as $interest)
				{
					$req = $pdo->prepare("INSERT INTO userInterests (user, interest) VALUES (?, ?)");
					$req->execute([$_SESSION["user"]["id"], $interest]);
				}
				// Insert
   				$date = sprintf('%04d-%02d-%02d', $year, $month, $day);
				$req = $pdo->prepare("INSERT INTO profiles
					(author, gender, preference, birthdate, bio, city, country, lat, lon, primaryPicture, secondaryPictureOne, secondaryPictureTwo, secondaryPictureThree, secondaryPictureFour)
					VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
				");
				$req->execute([$_SESSION["user"]["id"], $gender, $preference, $date, $bio, $city, $country, $lat, $lon, $pictures[0], $pictures[1], $pictures[2], $pictures[3], $pictures[4]]);
				$_SESSION["profile"]["gender"] = $gender;
				$_SESSION["profile"]["preference"] = $preference;
				$_SESSION["profile"]["bio"] = $bio;
				$_SESSION["profile"]["city"] = $city;
				$_SESSION["profile"]["country"] = $country;
				$_SESSION["profile"]["lat"] = $lat;
				$_SESSION["profile"]["lon"] = $lon;
				$_SESSION["profile"]["interests"] = $interests;
				$_SESSION["profile"]["primaryPicture"] = $pictures[0];
				$_SESSION["profile"]["secondaryPictureOne"] = $pictures[1];
				$_SESSION["profile"]["secondaryPictureTwo"] = $pictures[2];
				$_SESSION["profile"]["secondaryPictureThree"] = $pictures[3];
				$_SESSION["profile"]["secondaryPictureFour"] = $pictures[4];
				$req = $pdo->prepare("UPDATE users SET isComplete = 1 WHERE id = ?");
				$req->execute([$_SESSION["user"]["id"]]);
				$_SESSION["user"]["isComplete"] = true;
				regenerateCsrfToken();
				header("Location: /");
				exit;
			}
		}
		regenerateCsrfToken();
		header("Location: /set_profile.php");
		exit;
	}
	$req = $pdo->prepare("SELECT * from interests");
	$req->execute();
	$months = [
		1 => "January", 2 => "February", 3 => "March", 4 => "April", 5 => "May", 6 => "June",
		7 => "July", 8 => "August", 9 => "September", 10 => "October", 11 => "November", 12 => "December"
	];
	$currYear = date("Y");
?>

<!DOCTYPE html>
<html lang="en">
	<?php $title = " - Complete your profile"; require_once "/usr/local/bin/includes/head.php"; ?>
	<body class="index">
		<?php require_once "/usr/local/bin/includes/header.php" ?>
		<main>
			<div class="settings">
				<?php if (isset($_SESSION["error"])): ?>
				<div class="error-wrapper">
					<p class="log error">
						<?= isset($_SESSION["error"]) ? $_SESSION["error"] : " "; unset($_SESSION["error"]); ?>
					</p>
				</div>
				<?php endif; ?>
				<div>
					<h2>Create your profile</h2>
					<form action="set_profile.php" method="POST" autocomplete="off" enctype="multipart/form-data">
						<input type="hidden" name="csrfToken" value="<?= $_SESSION['csrfToken']; ?>">
						<div class="form-group">
							<label for="gender">Gender</label>
							<select id="gender" name="gender" required>
								<option value="">Select gender</option>
								<option value="male">Male</option>
								<option value="female">Female</option>
							</select>
						</div>
						<div class="form-group">
							<label for="preference">Sexual Preferences</label>
							<select id="preference" name="preference" required>
								<option value="">Select preference</option>
								<option value="male">Men</option>
								<option value="female">Women</option>
								<option value="either">Either</option>
							</select>
						</div>
						<div class="form-group">
							<label for="birth-day">Date of birth</label>
							<div>
								<select name="birth-day" id="birth-day" required>
									<option value="">Day</option>
									<?php for ($d = 1; $d <= 31; $d++): ?>
										<option value="<?= $d ?>"><?= $d ?></option>
									<?php endfor; ?>
								</select>
								<select name="birth-month" id="birth-month" required>
									<option>Month</option>
									<?php foreach ($months as $n => $m): ?>
										<option value="<?= $n ?>"><?= $m ?></option>
									<?php endforeach; ?>
								</select>
								<select name="birth-year" id="birth-year" required>
									<option value="">Year</option>
									<?php for ($y = $currYear - 18; $y >= ($currYear - 120); $y--): ?>
										<option value="<?= $y ?>"><?= $y ?></option>
									<?php endfor; ?>
								</select>
							</div>
						</div>
						<div class="form-group group-location">
							<label for="location-country">Location</label>
							<input type="text" id="location-city" name="location-city" placeholder="City" required>
							<input type="text" id="location-country" name="location-country" placeholder="Country" required>
							<i class="fa-solid fa-spinner loader hidden"></i>
							<button class="geo-button" type="button" title="Let us input your location through a geolocation with the GPS of your device."><i class="fa-solid fa-location-arrow"></i> Use my current location</button>
							<p class="log error error-geo"></p>
						</div>
						<div class="form-group">
							<p>Profile picture</p>
							<div class="picture-secondary-wrapper">
								<div>
									<div class="picture-item picture-item-primary">
									</div>
									<button class="picture-upload" type="button"><i class="fa-solid fa-cloud-arrow-up"></i></button>
									<input class="picture-input" type="file" name="picture-primary" aria-label="Upload an image" accept=".jpg, .jpeg, .png" hidden required/>
								</div>
							</div>
						</div>
						<div class="form-group form-group-secondary hidden">
							<p>Secondary picture (max 4)</p>
							<div class="picture-secondary-wrapper">
							<?php for ($i = 0; $i < 4; $i++): ?>
								<div>
									<div class="picture-item">
									</div>
									<button class="picture-upload" type="button"><i class="fa-solid fa-cloud-arrow-up"></i></button>
									<input class="picture-input" type="file" name="picture-secondary[]" accept=".jpg, .jpeg, .png" hidden></input>
								</div>
							<?php endfor; ?>
							</div>
						</div>
						<div class="form-group form-group-interest">
							<p>Interests</p>
							<div class="interests-wrapper">
							<?php while ($row = $req->fetch(PDO::FETCH_ASSOC)): ?>
								<button type="button" class="interest" data-interest-id="<?= $row['id']; ?>">
									<span><?= htmlspecialchars($row['name']); ?></span>
								</button>
							<?php endwhile; ?>
								<input type="hidden" class="interests-selected" name="interests-selected">
							</div>
						</div>
						<div class="form-group">
							<label for="bio">Biography</label>
							<textarea id="bio" name="bio" placeholder="Tell us about yourself... (10 to 255 characters)" minlength="10" maxlength="255" required></textarea>
						</div>
						<button type="submit" name="submit">Submit</button>
					</form>
				</div>
			</div>
		</main>
		<?php require_once "/usr/local/bin/includes/footer.php" ?>
	</body>
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
		const buttons = document.getElementsByClassName("interest");
		const hiddenInput = document.getElementsByClassName("interests-selected")[0];
		let selected = [];
		Array.from(buttons).forEach(button => {
			button.addEventListener("click", () => {
				const id = button.dataset.interestId;
				button.classList.toggle("selected");
				if (selected.includes(id))
					selected = selected.filter(item => item !== id);
				else
					selected.push(id);
				hiddenInput.value = JSON.stringify(selected);
			});
		});
		const secondary = document.getElementsByClassName("form-group-secondary")[0];
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
					previews[i].innerHTML = "";
					return ;
				}
				const url = URL.createObjectURL(file);
				previews[i].innerHTML = `
					<img class="picture-preview" src="${url}" alt="Preview">
					<div class="grid-items-overlay">
						<button type="button"><i class="fa-solid fa-trash-can"></i></button>
					</div>
				`;
				if (i == 0)
					secondary.classList.remove("hidden");
				const newButton = previews[i].querySelector("button");
				newButton.addEventListener("click", () => {
					previews[i].innerHTML = "";
					if (i == 0)
						secondary.classList.add("hidden");
				});
			});
		}
	</script>
</html>
