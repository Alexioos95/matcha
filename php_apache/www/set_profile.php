<?php
	require_once "db.php";
	require_once "auth.php";

	if (isset($_SESSION["user"]) && isset($_SESSION["user"]["id"]) && isset($_SESSION["profile"]))
	{
		header("Location: /");
		exit;
	}
	elseif (!isset($_SESSION["user"]) || !isset($_SESSION["user"]["id"]))
	{
		header("Location: /login.php");
		exit;
	}
	if (empty($_SESSION["csrfToken"]))
		$_SESSION["csrfToken"] = bin2hex(random_bytes(32));
	if (isset($_POST["submit"]))
	{
		if (!isset($_POST["csrfToken"]) || !isset($_SESSION["csrfToken"]) || !hash_equals($_SESSION["csrfToken"], $_POST["csrfToken"]))
			exit("Invalid CSRF token.");

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
				echo json_encode(["error" => curl_error($ch)]);
				exit;
			}
			curl_close($ch);
			$output = json_decode($res, true);
			if (empty($output))
			{
				$_SESSION["error"] = "Sorry, we couldn't identify your location.";
				exit;
			}
			$lat = $output[0]["lat"];
			$lon = $output[0]["lon"];
			// Pictures
			function saveImage($file, $dir, $pdo, $userId, &$pictures)
			{
				$filename = $file["name"];
				$tempname = $file["tmp_name"];
				$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$mime = finfo_file($finfo, $tempname);
				finfo_close($finfo);

				if (($extension != "jpg" && $extension != "jpeg" && $extension != "png") || ($mime != "image/jpeg" && $mime != "image/png"))
					return (false);
				if ($extension === "png")
					$image = imagecreatefrompng($tempname);
				else
					$image = imagecreatefromjpeg($tempname);
				if (!$image)
					return (false);
				$path = $dir . "/" . uniqid() . "." . $extension;
				if (($extension === "png" && !imagepng($image, __DIR__ . $path)) || (($extension === "jpg" || $extension === "jpeg") && !imagejpeg($image, __DIR__ . $path)))
					return (false);
				imagedestroy($image);
				$pictures[] = $path;
				return (true);
			}

			$dir = "/uploads/" . $_SESSION["user"]["id"];
			if (!is_dir(__DIR__ . $dir))
				mkdir(__DIR__ . $dir, 0774, true);
			if (!saveImage($_FILES["picture-primary"], $dir, $pdo, $_SESSION["user"]["id"], $pictures))
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
					saveImage($file, $dir, $pdo, $_SESSION["user"]["id"], $pictures);
					$saved++;
				}
				for (; $saved < 5; $saved++)
				{
					$pictures[] = NULL;
				}
				foreach ($interests as $interestId)
				{
					$req = $pdo->prepare("INSERT INTO userInterests (userId, interestId) VALUES (?, ?)");
					$req->execute([$_SESSION["user"]["id"], $interestId]);
				}
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
				header("Location: /");
				exit;
			}
		}
		$_SESSION["csrfToken"] = bin2hex(random_bytes(32));
	}
	$req = $pdo->prepare("SELECT * from interests");
	$req->execute();
	$months = [
		1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
		7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
	];
	$currYear = date("Y");
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="description" content="Match with your soulmate!">
		<title>Matcha - Complete your profile</title>
		<link rel="icon" type="image/x-icon" href="/images/favicon.ico">
		<link rel="stylesheet" type="text/css" href="https://necolas.github.io/normalize.css/8.0.1/normalize.css">
		<script src="https://kit.fontawesome.com/70111f5ad5.js" crossorigin="anonymous"></script>
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
		<link rel="stylesheet" type="text/css" href="./styles.css">
	</head>
	<body class="index-body">
		<header>
			<h1>Matcha</h1>
			<nav>
				<ul>
					<li><a href='/api/logout.php'><i class='fa-solid fa-right-from-bracket'></i><span>Logout</span></a></li>
				</ul>
			</nav>
		</header>
		<main>
			<div class="settings">
				<div class="error-wrapper">
					<p class="error">
						<?php 
							echo isset($_SESSION["error"]) ? $_SESSION["error"] : " "; 
							unset($_SESSION["error"]);
						?>
					</p>
				<div>
					<h2>Create your profile</h2>
					<form action="set_profile.php" method="POST" autocomplete="off" enctype="multipart/form-data">
						<input type="hidden" name="csrfToken" value="<?php echo $_SESSION['csrfToken']; ?>">
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
										<option value="<?php echo $d ?>"><?php echo $d ?></option>
									<?php endfor; ?>
								</select>
								<select name="birth-month" id="birth-month" required>
									<option>Month</option>
									<?php foreach ($months as $num => $name): ?>
										<option value="<?php echo $num ?>"><?php echo $name ?></option>
									<?php endforeach; ?>
								</select>
								<select name="birth-year" id="birth-year" required>
									<option value="">Year</option>
									<?php for ($y = $currYear - 18; $y >= 1900; $y--): ?>
										<option value="<?php echo $y ?>"><?php echo $y ?></option>
									<?php endfor; ?>
								</select>
							</div>
						</div>
						<div class="form-group group-location">
							<label for="location-country">Location</label>
							<input type="text" id="location-city" name="location-city" placeholder="City" required>
							<input type="text" id="location-country" name="location-country" placeholder="Country" required>
							<i class="fa-solid fa-spinner loader hidden"></i>
							<button class="geo-button" type="button"><i class="fa-solid fa-location-arrow"></i> Geolocate me!</button>
							<p class="error error-geo"></p>
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
								<button type="button" class="interest" data-interest-id="<?php echo $row['id']; ?>">
									<span><?php echo htmlspecialchars($row['name']); ?></span>
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
		<footer>
			<p>Matcha by apayen@student.42.fr</p>
		</footer>
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
									csrfToken: "<?php echo $_SESSION['csrfToken'] ?>",
									lat: lat,
									lon: lon,
									id: <?php echo $_SESSION["user"]["id"] ?>
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
								});
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
		geoButton.addEventListener("click", () => { geolocation(); });
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
			uploadButtons[i].addEventListener("click", () => {
				fileInputs[i].click();
			});
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
