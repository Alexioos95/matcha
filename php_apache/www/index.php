<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	requireLogin();
	requireProfile();
	updateLastOnline($pdo);
	// Today
	$today = new DateTime();
	// Parameters - Filters
	$filters = [
		$ageMin = $_GET["ageMin"] ?? null,
		$ageMax = $_GET["ageMax"] ?? null,
		$distMin = $_GET["distMin"] ?? null,
		$distMax = $_GET["distMax"] ?? null,
		$fameMin = $_GET["fameMin"] ?? null,
		$fameMax = $_GET["fameMax"] ?? null,
		$int1 = $_GET["int1"] ?? null,
		$int2 = $_GET["int2"] ?? null,
		$int3 = $_GET["int3"] ?? null
	];
	foreach ($filters as &$f)
	{
		if ($f != null && ((!filter_var($f, FILTER_VALIDATE_INT) || $f < 0)))
			$f = null;
	}
	$filterPhReq = $pdo->prepare("SELECT
		MIN(TIMESTAMPDIFF(YEAR, birthdate, CURDATE())) AS minAge,
		MAX(TIMESTAMPDIFF(YEAR, birthdate, CURDATE())) AS maxAge,
		MIN(fame) AS minFame,
		MAX(fame) AS maxFame,
		MIN(6371 * ACOS(GREATEST(-1, LEAST(1, COS(RADIANS(:myLat)) * COS(RADIANS(lat)) * COS(RADIANS(lon) - RADIANS(:myLon)) + SIN(RADIANS(:myLat)) * SIN(RADIANS(lat)))))) AS minDist,
		MAX(6371 * ACOS(GREATEST(-1, LEAST(1, COS(RADIANS(:myLat)) * COS(RADIANS(lat)) * COS(RADIANS(lon) - RADIANS(:myLon)) + SIN(RADIANS(:myLat)) * SIN(RADIANS(lat)))))) AS maxDist
		FROM profiles p
		WHERE author <> :user
		AND (preference = 'either' OR preference = :myGender)
		AND (:myPreference = 'either' OR gender = :myPreference)
		AND NOT EXISTS (
			SELECT 1
			FROM blocks b
			WHERE (b.author = :user AND b.target = p.author) OR (b.author = p.author AND b.target = :user)
		)
	");
	$filterPhReq->bindValue(":user", $_SESSION["user"]["id"], PDO::PARAM_INT);
	$filterPhReq->bindValue(":myGender", $_SESSION["profile"]["gender"], PDO::PARAM_STR);
	$filterPhReq->bindValue(":myPreference", $_SESSION["profile"]["preference"], PDO::PARAM_STR);
	$filterPhReq->bindValue(":myLat", $_SESSION["profile"]["lat"]);
	$filterPhReq->bindValue(":myLon", $_SESSION["profile"]["lon"]);
	$filterPhReq->execute();
	$filterPh = $filterPhReq->fetch(PDO::FETCH_ASSOC);
	$defaults = [
		(int)$filterPh["minAge"],
		(int)$filterPh["maxAge"],
		(int)$filterPh["minDist"],
		(int)$filterPh["maxDist"],
		(int)$filterPh["minFame"],
		(int)$filterPh["maxFame"] + 1,
	];
	for ($i = 0; $i < count($defaults); $i++)
	{
		if (!$filters[$i])
			$filters[$i] = $defaults[$i];
	}
	for ($i = 6; $i < count($filters); $i++)
	{
		if ($filters[$i] < 1)
			$filters[$i] = null;
	}
	// Parameters - Sort
	$sort = $_GET["sort"] ?? null;
	$app = "";
	if ($sort == "age-asc")
		$app = "age ASC, distance ASC, tags DESC, p.fame DESC";
	elseif ($sort == "age-desc")
 		$app = "age DESC, distance ASC, tags DESC, p.fame DESC";
	elseif ($sort == "tags")
		$app = "tags DESC, distance ASC, p.fame DESC";
	elseif ($sort == "fame")
		$app = "p.fame DESC, distance ASC, tags DESC";
	else
		$app = "distance ASC, tags DESC, p.fame DESC";
	// Query
	$query = "SELECT p.*, u.firstName, u.lastName,
		TIMESTAMPDIFF(YEAR, p.birthdate, CURDATE()) AS age,
		(6371 * ACOS(LEAST(1, COS(RADIANS(:myLat)) * COS(RADIANS(p.lat)) * COS(RADIANS(p.lon) - RADIANS(:myLon)) + SIN(RADIANS(:myLat)) * SIN(RADIANS(p.lat))))) AS distance,
		(
			SELECT COUNT(*)
			FROM userInterests myTags
			INNER JOIN userInterests theirTags ON myTags.interest = theirTags.interest
			WHERE myTags.user = :user
			AND theirTags.user = u.id
		) AS tags
		FROM profiles p
		INNER JOIN users u ON p.author = u.id
		WHERE u.id <> :user AND u.isComplete = TRUE
		AND (p.preference = 'either' OR p.preference = :myGender)
		AND (:myPreference = 'either' OR p.gender = :myPreference)
		AND TIMESTAMPDIFF(YEAR, p.birthdate, CURDATE()) BETWEEN :ageMin AND :ageMax
		AND (6371 * ACOS(LEAST(1, COS(RADIANS(:myLat)) * COS(RADIANS(p.lat)) * COS(RADIANS(p.lon) - RADIANS(:myLon)) + SIN(RADIANS(:myLat)) * SIN(RADIANS(p.lat))))) BETWEEN :distMin AND :distMax
		AND p.fame BETWEEN :fameMin AND :fameMax
		AND NOT EXISTS (
			SELECT 1
			FROM blocks b
			WHERE (b.author = :user AND b.target = u.id) OR (b.author = u.id AND b.target = :user)
		)
	";
	if ($filters[6])
	{
		$query .= " AND EXISTS (SELECT 1 FROM userInterests ui WHERE ui.user = u.id AND ui.interest = :interest1) ";
		if ($filters[7])
		{
			$query .= " AND EXISTS (SELECT 1 FROM userInterests ui WHERE ui.user = u.id AND ui.interest = :interest2) ";
			if ($filters[8])
				$query .= " AND EXISTS (SELECT 1 FROM userInterests ui WHERE ui.user = u.id AND ui.interest = :interest3) ";
		}
	}
	$query .= "ORDER BY " . $app . " LIMIT 40";
	$req = $pdo->prepare($query);
	$req->bindValue(":user", $_SESSION["user"]["id"], PDO::PARAM_INT);
	$req->bindValue(":myGender", $_SESSION["profile"]["gender"], PDO::PARAM_STR);
	$req->bindValue(":myPreference", $_SESSION["profile"]["preference"], PDO::PARAM_STR);
	$req->bindValue(":myLat", $_SESSION["profile"]["lat"]);
	$req->bindValue(":myLon", $_SESSION["profile"]["lon"]);
	$req->bindValue(":ageMin", $filters[0], PDO::PARAM_INT);
	$req->bindValue(":ageMax", $filters[1], PDO::PARAM_INT);
	$req->bindValue(":distMin", $filters[2]);
	$req->bindValue(":distMax", $filters[3]);
	$req->bindValue(":fameMin", $filters[4], PDO::PARAM_INT);
	$req->bindValue(":fameMax", $filters[5], PDO::PARAM_INT);
	if ($filters[6])
	{
		$req->bindValue(":interest1", $filters[6], PDO::PARAM_INT);
		if ($filters[7])
		{
			$req->bindValue(":interest2", $filters[7], PDO::PARAM_INT);
			if ($filters[8])
				$req->bindValue(":interest3", $filters[8], PDO::PARAM_INT);
		}
	}
	$req->execute();
	$intReq = $pdo->prepare("SELECT * from interests");
	$intReq->execute();
	$ints = $intReq->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
	<?php require_once "/usr/local/bin/includes/head.php" ?>
	<body class="index">
		<div class="modal hidden"></div>
		<?php require_once "/usr/local/bin/includes/header.php" ?>
		<main>
			<div class="grid-container">
				<form method="GET">
					<div class="filter-container">
						<div class="filter-wrapper">
							<div class="filter-range-wrapper">
								<div class="search-range">
									<label for="age-slider">Age</label>
									<div class="drange">
										<input class="sliders-min" id="age-slider" name="ageMin" type="range" min="<?= htmlspecialchars($filterPh['minAge']) ?>" max="<?= htmlspecialchars($filterPh['maxAge']) ?>" value="<?= htmlspecialchars($filters[0]) ?>" aria-label="Minimum age for filter">
										<input class="sliders-max" name="ageMax" type="range" min="<?= htmlspecialchars($filterPh['minAge']) ?>" max="<?= htmlspecialchars($filterPh['maxAge']) ?>" value="<?= htmlspecialchars($filters[1]) ?>" aria-label="Maximum age for filter">
										<div class="dmin"><?= htmlspecialchars($filters[0]) ?></div>
										<div class="dmax"><?= htmlspecialchars($filters[1]) ?></div>
									</div>
								</div>
								<div class="search-range">
									<label for="distance-slider">Distance</label>
									<div class="drange">
										<input class="sliders-min" id="distance-slider" name="distMin" type="range" min="<?= htmlspecialchars((int)$filterPh['minDist']) ?>" max="<?= htmlspecialchars((int)$filterPh['maxDist'] + 1) ?>" value="<?= htmlspecialchars($filters[2]) ?>" aria-label="Minimum distance for filter">
										<input class="sliders-max" name="distMax" type="range" min="<?= htmlspecialchars((int)$filterPh['minDist']) ?>" max="<?= htmlspecialchars((int)$filterPh['maxDist'] + 1) ?>" value="<?= htmlspecialchars($filters[3]) ?>" aria-label="Maximum distance for filter">
										<div class="dmin"><?= htmlspecialchars((int)$filters[2]) ?></div>
										<div class="dmax"><?= htmlspecialchars((int)$filters[3]) ?></div>
									</div>
								</div>
								<div class="search-range">
									<label for="fame-slider">Fame</label>
									<div class="drange">
										<input class="sliders-min" id="fame-slider" name="fameMin" type="range" min="<?= htmlspecialchars((int)$filterPh['minFame']) ?>" max="<?= htmlspecialchars((int)$filterPh['maxFame'] + 1) ?>" value="<?= htmlspecialchars($filters[4]) ?>" aria-label="Minimum fame for filter">
										<input class="sliders-max" name="fameMax" type="range" min="<?= htmlspecialchars((int)$filterPh['minFame']) ?>" max="<?= htmlspecialchars((int)$filterPh['maxFame'] + 1) ?>" value="<?= htmlspecialchars($filters[5]) ?>" aria-label="Maximum fame for filter">
										<div class="dmin"><?= htmlspecialchars($filters[4]) ?></div>
										<div class="dmax"><?= htmlspecialchars($filters[5]) ?></div>
									</div>
								</div>
							</div>
							<div class="filter-select-wrapper">
								<select class="filter-select" name="int1" aria-label="Select an interest to filter">
									<option value="">Select an interest</option>
									<?php foreach ($ints as $int): ?>
									<option value="<?= $int['id']; ?>" <?= $int["id"] == $filters[6] ? "selected" : ""; ?>><?= htmlspecialchars($int["name"]); ?></option>
									<?php endforeach; ?>
								</select>
								<select class="filter-select hidden" name="int2" aria-label="Select an interest to filter">
									<option value="">Select an interest</option>
									<?php foreach ($ints as $int): ?>
									<option value="<?= $int['id']; ?>" <?= $int["id"] == $filters[7] ? "selected" : ""; ?>><?= htmlspecialchars($int["name"]); ?></option>
									<?php endforeach; ?>
								</select>
								<select class="filter-select hidden" name="int3" aria-label="Select an interest to filter">
									<option value="">Select an interest</option>
									<?php foreach ($ints as $int): ?>
									<option value="<?= $int['id']; ?>" <?= $int["id"] == $filters[8] ? "selected" : ""; ?>><?= htmlspecialchars($int["name"]); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<div class="filter-button-wrapper">
							<button class="reset-search" type="button">Reset filters</button>
							<button class="submit-search" type="submit">Apply filters</button>
						</div>
					</div>
					<div class="sort-wrapper">
						<label for="sorting"><i class="fa-solid fa-filter"></i> Sort by:</label>
						<input class="sort-input" name="sort" type="hidden"></input>
						<button type="button" id="sorting" class="sort-button interest <?= $sort == 'dist' || $sort == null ? 'selected' : '' ?>" data-value="dist"><i class="fa-solid fa-earth-americas"></i> Distance</button>
						<button type="button" class="sort-button interest <?= $sort == 'age-asc' ? 'selected' : '' ?>" data-value="age-asc"><i class="fa-solid fa-cake-candles"></i> <span>Age <i class="fa-solid fa-arrow-down-short-wide"></i></span></button>
						<button type="button" class="sort-button interest <?= $sort == 'age-desc' ? 'selected' : '' ?>" data-value="age-desc"><i class="fa-solid fa-cake-candles"></i> <span>Age <i class="fa-solid fa-arrow-down-wide-short"></i></span></button>
						<button type="button" class="sort-button interest <?= $sort == 'tags' ? 'selected' : '' ?>" data-value="tags"><i class="fa-solid fa-tags"></i> Shared interest</button>
						<button type="button" class="sort-button interest <?= $sort == 'fame' ? 'selected' : '' ?>" data-value="fame"><i class="fa-solid fa-star"></i> Fame</button>
					</div>
				</form>
			<?php while ($row = $req->fetch(PDO::FETCH_ASSOC)): ?>
				<?php
					$birthDate = new DateTime($row["birthdate"]);
					$age = $birthDate->diff($today)->y;
				?>
				<div class="grid-items">
					<button onclick="openmodal(this)" class="modal-button" type="button" data-id="<?= htmlspecialchars($row['id']); ?>">
						<span class="overlay top">
							<span class="label">
								<i class="fa-solid fa-star label"></i>
								<?= htmlspecialchars(ucfirst($row['fame'])); ?>
							</span>
							<span class="label"><?= htmlspecialchars((int)$row['distance']) . "km" ?></span>
						</span>
						<img src="<?= htmlspecialchars($row['primaryPicture']); ?>" alt="Primary picture of <?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?>"></img>
						<span class="overlay bottom"><?= htmlspecialchars($row['firstName'] . " " . $row['lastName'] . ", " . $age) ?></span>
					</button>
				</div>
			<?php endwhile; ?>
			</div>
			<div class="load-trigger"></div>
		</main>
		<?php require_once "/usr/local/bin/includes/footer.php" ?>
		<script>
			// Advanced search
			const submitSearch = document.getElementsByClassName("submit-search")[0];
			const sortInput = document.getElementsByClassName("sort-input")[0];
			// Advanced search - Sliders
			const slidersMin = document.getElementsByClassName("sliders-min");
			const slidersMax = document.getElementsByClassName("sliders-max");
			const dmin = document.getElementsByClassName("dmin");
			const dmax = document.getElementsByClassName("dmax");
			for (let i = 0; i < slidersMin.length; i++)
			{
				slidersMin[i].addEventListener("input", () => {
					if (parseInt(slidersMin[i].value, 10) > parseInt(slidersMax[i].value, 10))
						slidersMin[i].value = slidersMax[i].value;
					dmin[i].innerHTML = slidersMin[i].value;
				});
				slidersMax[i].addEventListener("input", () => {
					if (parseInt(slidersMax[i].value, 10) < parseInt(slidersMin[i].value, 10))
						slidersMax[i].value = slidersMin[i].value;
					dmax[i].innerHTML = slidersMax[i].value;
				});
			}
			const sortButtons = document.getElementsByClassName("sort-button");
			for (let i = 0; i < sortButtons.length; i++)
			{
				sortButtons[i].addEventListener("click", () => {
					for (let j = 0; j < sortButtons.length; j++)
					{
						sortButtons[j].classList.remove("selected");
					}
					sortButtons[i].classList.add("selected");
					sortInput.value = sortButtons[i].dataset.value;
					submitSearch.click();
				});
				if (sortButtons[i].classList.contains("selected"))
					sortInput.value = sortButtons[i].dataset.value;
			}
			// Advanced search - Interests
			const selects = document.getElementsByClassName("filter-select");
			if (selects[0].value != "")
				selects[1].classList.remove("hidden");
			if (selects[1].value != "")
				selects[2].classList.remove("hidden");
			selects[0].addEventListener("change", () => {
				if (selects[0].value != "")
					selects[1].classList.remove("hidden");
				else
				{
					selects[1].classList.add("hidden");
					selects[1].value = "";
					selects[2].classList.add("hidden");
					selects[2].value = "";
				}
			});
			selects[1].addEventListener("change", () => {
				if (selects[1].value != "")
					selects[2].classList.remove("hidden");
				else
				{
					selects[2].classList.add("hidden");
					selects[2].value = "";
				}
			});
			function updateOptions()
			{
				Array.from(selects).forEach(el => {
					[...el.options].forEach(option => {
						option.disabled = false;
					});
				});
				const val = [...selects].map(el => el.value).filter(val => val !== "");
				Array.from(selects).forEach(el => {
					[...el.options].forEach(option => {
						if (option.value !== "" && val.includes(option.value) && option.value !== el.value)
							option.disabled = true;
					});
				});
			}
			Array.from(selects).forEach(select => {
				select.addEventListener("change", updateOptions);
			});
			updateOptions();
			// Advanced search - Reset
			const resetButton = document.getElementsByClassName("reset-search")[0];
			resetButton.addEventListener("click", () => {
				slidersMin[0].value = <?= $defaults[0] ?>;
				slidersMax[0].value = <?= $defaults[1] ?>;
				slidersMin[1].value = <?= $defaults[2] ?>;
				slidersMax[1].value = <?= $defaults[3] ?>;
				slidersMin[2].value = <?= $defaults[4] ?>;
				slidersMax[2].value = <?= $defaults[5] ?>;
				selects[0].value = "";
				selects[1].value = "";
				selects[2].value = "";
				submitSearch.click();
			});
			// Modal for profile
			function openmodal(b)
			{
				modal.classList.remove("hidden");
				fetch(`/api/profile.php?id=${b.dataset.id}`)
					.then(res => res.text())
					.then(data => {
						// Set HTML
						modal.innerHTML = data;
						// Carousel handlers
						const carousel = document.getElementsByClassName("modal-carousel")[0];
						const carouselButtons = document.getElementsByClassName("carousel-button");
						if (carousel && carouselButtons)
						{
							carouselButtons[0].addEventListener("click", () => {
								carousel.scrollBy({
									left: -carousel.clientWidth,
									behavior: "smooth"
								});
							});
							carouselButtons[1].addEventListener("click", () => {
								carousel.scrollBy({
									left: carousel.clientWidth,
									behavior: "smooth"
								});
							});
						}
						// Buttons
						const modalActions = document.querySelectorAll(".modal-profile .modal-footer button");
						if (modalActions && modalActions[0] && modalActions[1] && modalActions[2])
						{
							modalActions[0].addEventListener("click", () => {
								fetch("/api/block.php", {
									method: "POST",
									headers: {"Content-Type": "application/json"},
									body: JSON.stringify({
										csrfToken: "<?= $_SESSION['csrfToken'] ?>",
										id: b.dataset.id
									})
								});
								modal.classList.add("hidden");
								modal.innerHTML = "";
								b.parentElement.remove();
							});
							modalActions[1].addEventListener("click", () => {
								fetch("/api/like.php", {
									method: "POST",
									headers: {"Content-Type": "application/json"},
									body: JSON.stringify({
										csrfToken: "<?= $_SESSION['csrfToken'] ?>",
										id: b.dataset.id
									})
								})
									.then(res => res.json())
									.then(data => {
										if (!data.error)
										{
											if (data.status == "none")
											{
												modalActions[1].innerHTML = `
												<svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="64.000000pt" height="64.000000pt" viewBox="0 0 64.000000 64.000000" preserveAspectRatio="xMidYMid meet">
													<g transform="translate(0.000000,64.000000) scale(0.100000,-0.100000)" fill="white" stroke="none">
														<path d="M91 593 c-48 -24 -84 -83 -89 -148 -8 -96 60 -207 209 -340 95 -85 104 -90 131 -73 38 25 161 139 201 188 89 106 117 212 77 294 -26 55 -67 86 -120 93 -61 8 -106 -5 -146 -42 l-36 -35 -19 24 c-42 53 -141 72 -208 39z"/>
													</g>
												</svg>`;
											}
											if (data.status == "liked")
											{
												modalActions[1].innerHTML = `
												<svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="64.000000pt" height="64.000000pt" viewBox="0 0 64.000000 64.000000" preserveAspectRatio="xMidYMid meet">
													<g transform="translate(0.000000,64.000000) scale(0.100000,-0.100000)" fill="white" stroke="none">
														<path d="M91 593 c-18 -9 -45 -35 -60 -57 -22 -33 -26 -51 -26 -105 1 -104 54 -188 200 -320 101 -91 110 -97 137 -78 40 26 150 128 196 181 59 68 102 159 102 215 0 56 -32 124 -71 152 -63 44 -158 37 -215 -16 l-36 -35 -19 24 c-42 53 -141 72 -208 39z m455 -64 c33 -31 48 -89 35 -137 -20 -71 -125 -208 -204 -264 l-28 -20 3 190 c3 187 3 191 28 217 14 15 34 31 45 36 32 14 95 3 121 -22z"/>
													</g>
												</svg>`;
											}
											if (data.status == "likes")
											{
												modalActions[1].innerHTML = `
												<svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="64.000000pt" height="64.000000pt" viewBox="0 0 64.000000 64.000000" preserveAspectRatio="xMidYMid meet">
													<g transform="translate(0.000000,64.000000) scale(0.100000,-0.100000)" fill="rgb(214, 46, 46)" stroke="none">
														<path d="M91 593 c-48 -24 -84 -83 -89 -148 -8 -96 60 -207 209 -340 95 -85 104 -90 131 -73 38 25 161 139 201 188 89 106 117 212 77 294 -26 55 -67 86 -120 93 -61 8 -106 -5 -146 -42 l-36 -35 -19 24 c-42 53 -141 72 -208 39z m163 -72 l31 -31 3 -191 3 -191 -30 21 c-48 35 -145 146 -176 203 -49 90 -35 178 36 216 38 20 97 8 133 -27z"/>
													</g>
												</svg>`;
											}
											if (data.status == "matched")
											{
												modalActions[1].innerHTML = `
												<svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="64.000000pt" height="64.000000pt" viewBox="0 0 64.000000 64.000000" preserveAspectRatio="xMidYMid meet">
													<g transform="translate(0.000000,64.000000) scale(0.100000,-0.100000)" fill="rgb(214, 46, 46)" stroke="none">
														<path d="M91 593 c-48 -24 -84 -83 -89 -148 -8 -96 60 -207 209 -340 95 -85 104 -90 131 -73 38 25 161 139 201 188 89 106 117 212 77 294 -26 55 -67 86 -120 93 -61 8 -106 -5 -146 -42 l-36 -35 -19 24 c-42 53 -141 72 -208 39z"/>
													</g>
												</svg>`;
												const link = document.createElement("a");
												link.href = "#";
												const icon = document.createElement("i");
												icon.classList.add("fa-solid", "fa-comments");
												link.append(icon);
												modalActions[1].insertAdjacentElement("afterend", link);
												setTimeout(() => {alert("You matched! You can now initiate a discussion.");}, 100);
											}
										}
									});
							});
							modalActions[2].addEventListener("click", () => {
								if (confirm("You are about to report this user. Proceed?"))
								{
									fetch("/api/report.php", {
										method: "POST",
										headers: {"Content-Type": "application/json"},
										body: JSON.stringify({
											csrfToken: "<?= $_SESSION['csrfToken'] ?>",
											id: b.dataset.id
										})
									});
									modalActions[2].disabled = true;
								}
							});
						}
					});
			}
			const modal = document.getElementsByClassName("modal")[0];
			modal.addEventListener("click", (e) => {
				if (e.target === modal)
					modal.classList.add("hidden");
			});
			// Infinite scrolling
			let loading = false;
			const container = document.getElementsByClassName("grid-container")[0];
			const trigger = document.getElementsByClassName("load-trigger")[0];
			const observer = new IntersectionObserver(entries => {
				if (entries[0].isIntersecting && !loading)
				{
					loading = true;
					const cards = document.getElementsByClassName("modal-button");
					if (cards && cards.length > 0)
					{
						fetch("/api/load_more.php?type=index&ageMin=<?= $filters[0] ?>&ageMax=<?= $filters[1] ?>&distMin=<?= $filters[2] ?>&distMax=<?= $filters[3] ?>&fameMin=<?= $filters[4] ?>&fameMax=<?= $filters[5] ?>&int1=<?= $filters[6] ?>&int2=<?= $filters[7] ?>&int3=<?= $filters[8] ?>&sort=<?= $sort ?>&offset=" + cards.length)
							.then(res => res.text())
							.then(data => {
								if (data.trim() === "")
								{
									observer.disconnect();
									return;
								}
								container.insertAdjacentHTML("beforeend", data);
								loading = false;
							})
							.catch(() => loading = false);
					}
					else
						loading = false;
				}
			}, {});
			observer.observe(trigger);
		</script>
	</body>
</html>
