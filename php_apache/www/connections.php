<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	// Login requirement
	requireLogin();
	requireProfile();
	updateLastOnline($pdo);
	// Today
	$today = new DateTime();
	// Matches
	$matchesReq = $pdo->prepare("SELECT p.*, u.firstName, u.lastName, l1.id AS moreid,
		TIMESTAMPDIFF(YEAR, p.birthdate, CURDATE()) AS age,
		(6371 * ACOS(LEAST(1, COS(RADIANS(:myLat)) * COS(RADIANS(p.lat)) * COS(RADIANS(p.lon) - RADIANS(:myLon)) + SIN(RADIANS(:myLat)) * SIN(RADIANS(p.lat))))) AS distance
		FROM profiles p
		INNER JOIN users u ON p.author = u.id
		INNER JOIN likes l1 ON l1.author = :user AND l1.target = u.id
		INNER JOIN likes l2 ON l2.author = u.id AND l2.target = :user
		WHERE u.id <> :user
		ORDER BY l1.id DESC
		LIMIT 20
	");
	$matchesReq->bindValue(":user", $_SESSION["user"]["id"], PDO::PARAM_INT);
	$matchesReq->bindValue(":myLat", $_SESSION["profile"]["lat"]);
	$matchesReq->bindValue(":myLon", $_SESSION["profile"]["lon"]);
	$matchesReq->execute();
	// Likes
	$likesReq = $pdo->prepare("SELECT p.*, u.firstName, u.lastName, l.id AS moreid,
		TIMESTAMPDIFF(YEAR, p.birthdate, CURDATE()) AS age,
		(6371 * ACOS(LEAST(1, COS(RADIANS(:myLat)) * COS(RADIANS(p.lat)) * COS(RADIANS(p.lon) - RADIANS(:myLon)) + SIN(RADIANS(:myLat)) * SIN(RADIANS(p.lat))))) AS distance
		FROM profiles p
		INNER JOIN users u ON p.author = u.id
		INNER JOIN likes l ON l.author = :user AND l.target = u.id
		WHERE u.id <> :user
		AND NOT EXISTS (
			SELECT 1
			FROM likes l2
			WHERE l2.author = u.id AND l2.target = :user
		)
		ORDER BY l.id DESC
		LIMIT 20
	");
	$likesReq->bindValue(":user", $_SESSION["user"]["id"], PDO::PARAM_INT);
	$likesReq->bindValue(":myLat", $_SESSION["profile"]["lat"]);
	$likesReq->bindValue(":myLon", $_SESSION["profile"]["lon"]);
	$likesReq->execute();
	// Liked
	$likedReq = $pdo->prepare("SELECT p.*, u.firstName, u.lastName, l.id AS moreid,
		TIMESTAMPDIFF(YEAR, p.birthdate, CURDATE()) AS age,
		(6371 * ACOS(LEAST(1, COS(RADIANS(:myLat)) * COS(RADIANS(p.lat)) * COS(RADIANS(p.lon) - RADIANS(:myLon)) + SIN(RADIANS(:myLat)) * SIN(RADIANS(p.lat))))) AS distance
		FROM profiles p
		INNER JOIN users u ON p.author = u.id
		INNER JOIN likes l ON l.author = u.id AND l.target = :user
		WHERE u.id <> :user
		AND NOT EXISTS (
			SELECT 1
			FROM likes l2
			WHERE l2.author = :user AND l2.target = u.id
		)
		AND NOT EXISTS (
			SELECT 1
			FROM blocks b
			WHERE (b.author = :user AND b.target = u.id)
			OR (b.author = u.id AND b.target = :user)
		)
		ORDER BY l.id DESC
		LIMIT 20
	");
	$likedReq->bindValue(":user", $_SESSION["user"]["id"], PDO::PARAM_INT);
	$likedReq->bindValue(":myLat", $_SESSION["profile"]["lat"]);
	$likedReq->bindValue(":myLon", $_SESSION["profile"]["lon"]);
	$likedReq->execute();
	// Visitors
	$visitorReq = $pdo->prepare("SELECT p.*, u.firstName, u.lastName,
		TIMESTAMPDIFF(YEAR, p.birthdate, CURDATE()) AS age,
		(6371 * ACOS(LEAST(1, COS(RADIANS(:myLat)) * COS(RADIANS(p.lat)) * COS(RADIANS(p.lon) - RADIANS(:myLon)) + SIN(RADIANS(:myLat)) * SIN(RADIANS(p.lat))))) AS distance
		FROM profiles p
		INNER JOIN users u ON p.author = u.id
		WHERE u.id <> :user
		AND EXISTS (
			SELECT 1
			FROM visitHistory h
			WHERE h.host = :user
			AND h.visitor = u.id
		)
		AND NOT EXISTS (
			SELECT 1
			FROM blocks b
			WHERE (b.author = :user AND b.target = u.id)
			OR (b.author = u.id AND b.target = :user)
		)
		AND NOT EXISTS (
			SELECT 1
			FROM likes l
			WHERE l.author = :user
			AND l.target = u.id
		)
		AND NOT EXISTS (
			SELECT 1
			FROM likes l
			WHERE l.author = u.id
			AND l.target = :user
		)
		ORDER BY (
			SELECT h2.id
			FROM visitHistory h2
			WHERE h2.host = :user
			AND h2.visitor = u.id
			ORDER BY h2.id DESC
			LIMIT 1
		) DESC
		LIMIT 20
	");
	$visitorReq->bindValue(":user", $_SESSION["user"]["id"], PDO::PARAM_INT);
	$visitorReq->bindValue(":myLat", $_SESSION["profile"]["lat"]);
	$visitorReq->bindValue(":myLon", $_SESSION["profile"]["lon"]);
	$visitorReq->execute();
	// Blocks
	$blockReq = $pdo->prepare("SELECT p.*, u.firstName, u.lastName
		FROM profiles p
		INNER JOIN users u ON p.author = u.id
		INNER JOIN blocks b ON b.author = :user AND b.target = u.id
		WHERE u.id <> :user
		ORDER BY b.id DESC
		LIMIT 20
	");
	$blockReq->bindValue(":user", $_SESSION["user"]["id"], PDO::PARAM_INT);
	$blockReq->execute();
?>

<!DOCTYPE html>
<html lang="en">
	<?php require_once "/usr/local/bin/includes/head.php" ?>
	<body class="index">
		<div class="modal hidden"></div>
		<?php require_once "/usr/local/bin/includes/header.php" ?>
		<main class="connections">
			<div>
				<h2>You are a match!</h2>
				<div>
					<div class="grid-container-connections grid-matches">
					<?php while ($row = $matchesReq->fetch(PDO::FETCH_ASSOC)): ?>
						<?php
							$birthDate = new DateTime($row["birthdate"]);
							$age = $birthDate->diff($today)->y;
						?>
						<div class="grid-items">
							<button onclick="openmodal(this)" class="modal-button" type="button" data-id="<?= htmlspecialchars($row['id']); ?>" data-moreid="<?= htmlspecialchars($row['moreid']); ?>">
								<span class="overlay top">
									<span class="label">
										<i class="fa-solid fa-star label"></i>
										<?= htmlspecialchars(ucfirst($row['fame'])); ?>
									</span>
									<span class="label"><?= htmlspecialchars((int)$row['distance']) . "km" ?></span>
								</span>
								<img src="<?= htmlspecialchars($row['primaryPicture']); ?>" alt="Primary picture of <?= htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?>">
								<span class="overlay bottom"><?= htmlspecialchars($row['firstName'] . " " . $row['lastName'] . ", " . $age) ?></span>
							</button>
						</div>
					<?php endwhile ?>
					</div>
					<button class="show-more" type="button"><span>Show more</span></button>
				</div>
			</div>
			<div>
				<h2>You are still waiting for their callback...</h2>
				<div>
					<div class="grid-container-connections grid-likes">
					<?php while ($row = $likesReq->fetch(PDO::FETCH_ASSOC)): ?>
						<?php
							$birthDate = new DateTime($row["birthdate"]);
							$age = $birthDate->diff($today)->y;
						?>
						<div class="grid-items">
							<button onclick="openmodal(this)" class="modal-button" type="button" data-id="<?= htmlspecialchars($row['id']); ?>" data-moreid="<?= htmlspecialchars($row['moreid']); ?>">
								<span class="overlay top">
									<span class="label">
										<i class="fa-solid fa-star label"></i>
										<?= htmlspecialchars(ucfirst($row['fame'])); ?>
									</span>
									<span class="label"><?= htmlspecialchars((int)$row['distance']) . "km" ?></span>
								</span>
								<img src="<?= htmlspecialchars($row['primaryPicture']); ?>" alt="Primary picture of <?= htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?>">
								<span class="overlay bottom"><?= htmlspecialchars($row['firstName'] . " " . $row['lastName'] . ", " . $age) ?></span>
							</button>
						</div>
					<?php endwhile ?>
					</div>
					<button class="show-more" type="button"><span>Show more</span></button>
				</div>
			</div>
			<div>
				<h2>While they are waiting for you...</h2>
				<div>
					<div class="grid-container-connections grid-liked">
					<?php while ($row = $likedReq->fetch(PDO::FETCH_ASSOC)): ?>
						<?php
							$birthDate = new DateTime($row["birthdate"]);
							$age = $birthDate->diff($today)->y;
						?>
						<div class="grid-items">
							<button onclick="openmodal(this)" class="modal-button" type="button" data-id="<?= htmlspecialchars($row['id']); ?>" data-moreid="<?= htmlspecialchars($row['moreid']); ?>">
								<span class="overlay top">
									<span class="label">
										<i class="fa-solid fa-star label"></i>
										<?= htmlspecialchars(ucfirst($row['fame'])); ?>
									</span>
									<span class="label"><?= htmlspecialchars((int)$row['distance']) . "km" ?></span>
								</span>
								<img src="<?= htmlspecialchars($row['primaryPicture']); ?>" alt="Primary picture of <?= htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?>">
								<span class="overlay bottom"><?= htmlspecialchars($row['firstName'] . " " . $row['lastName'] . ", " . $age) ?></span>
							</button>
						</div>
					<?php endwhile ?>
					</div>
					<button class="show-more" type="button"><span>Show more</span></button>
				</div>
			</div>
			<div>
				<h2>They visited your profile...</h2>
				<div class="grid-container-connections grid-container-scroll">
				<?php while ($row = $visitorReq->fetch(PDO::FETCH_ASSOC)): ?>
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
							<img src="<?= htmlspecialchars($row['primaryPicture']); ?>" alt="Primary picture of <?= htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?>">
							<span class="overlay bottom"><?= htmlspecialchars($row['firstName'] . " " . $row['lastName'] . ", " . $age) ?></span>
						</button>
					</div>
				<?php endwhile ?>
				</div>
			</div>
			<div>
				<h2>You blocked them</h2>
				<div class="grid-container-connections grid-container-scroll grid-blocked">
				<?php while ($row = $blockReq->fetch(PDO::FETCH_ASSOC)): ?>
					<div class="grid-items">
						<div>
							<img src="<?= htmlspecialchars($row['primaryPicture']); ?>" alt="Primary picture of <?= htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?>">
							<span class="overlay bottom"><?= htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?></span>
							<button class="unblock-button" type="button" data-id="<?= htmlspecialchars($row['id']); ?>" aria-label="Unblock this user" title="Unblock this user"><i class="fa-solid fa-xmark"></i></button>
						</div>
					</div>
				<?php endwhile ?>
				</div>
			</div>
		</main>
		<?php require_once "/usr/local/bin/includes/footer.php" ?>
		<script>
			// Show more
			const more = document.getElementsByClassName("show-more");
			const moreMatches = more[0];
			const moreLikes = more[1];
			const moreLiked = more[2];
			moreMatches.addEventListener("click", () => {
				const container = document.getElementsByClassName("grid-matches")[0];
				const cards = container.getElementsByClassName("modal-button");
				if (cards.length > 0)
				{
					const lastId = cards[cards.length - 1].dataset.moreid;
					fetch("/api/load_more.php?type=matches&offset=" + lastId)
						.then(res => res.text())
						.then(data => {
							const tmp = document.createElement("div");
							tmp.innerHTML = data;
							const count = tmp.getElementsByClassName("grid-items").length;
							if (count < 20)
								more[0].remove();
							container.insertAdjacentHTML("beforeend", data);
						});
				}
				else
					more[0].remove();
			});
			moreLikes.addEventListener("click", () => {
				const container = document.getElementsByClassName("grid-likes")[0];
				const cards = container.getElementsByClassName("modal-button");
				if (cards.length > 0)
				{
					const lastId = cards[cards.length - 1].dataset.moreid;
					fetch("/api/load_more.php?type=likes&offset=" + lastId)
						.then(res => res.text())
						.then(data => {
							const tmp = document.createElement("div");
							tmp.innerHTML = data;
							const count = tmp.getElementsByClassName("grid-items").length;
							if (count < 20)
								moreLikes.remove();
							container.insertAdjacentHTML("beforeend", data);
						});
				}
				else
					moreLikes.remove();
			});
			moreLiked.addEventListener("click", () => {
				const container = document.getElementsByClassName("grid-liked")[0];
				const cards = container.getElementsByClassName("modal-button");
				if (cards.length > 0)
				{
					const lastId = cards[cards.length - 1].dataset.moreid;
					fetch("/api/load_more.php?type=liked&offset=" + lastId)
						.then(res => res.text())
						.then(data => {
							const tmp = document.createElement("div");
							tmp.innerHTML = data;
							const count = tmp.getElementsByClassName("grid-items").length;
							if (count < 20)
								moreLiked.remove();
							container.insertAdjacentHTML("beforeend", data);
						});
				}
				else
					moreLiked.remove();
			});
			// Unblock
			const unblock = document.getElementsByClassName("unblock-button");
			Array.from(unblock).forEach(b => {
				b.addEventListener("click", () => {
					fetch("/api/unblock.php", {
						method: "POST",
						headers: {"Content-Type": "application/json"},
						body: JSON.stringify({
							csrfToken: "<?= $_SESSION['csrfToken'] ?>",
							id: b.dataset.id
						})
					});
					b.parentElement.parentElement.remove();
				});
			});
			// Modal for profile
			const modal = document.getElementsByClassName("modal")[0];
			const buttons = document.getElementsByClassName("modal-button");
			modal.addEventListener("click", (e) => {
				if (e.target === modal)
					modal.classList.add("hidden");
			});
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
								const div = document.createElement("div");
								const parent = b.parentElement;
								const inner = b.innerHTML;
								[...b.attributes].forEach(attr => {div.setAttribute(attr.name, attr.value);});
								div.innerHTML = inner + `<button class="unblock-button" type="button" data-id="${div.dataset.id}"><i class="fa-solid fa-xmark"></i></button>`;
								parent.append(div);
								b.remove();
								div.getElementsByClassName("unblock-button")[0].addEventListener("click", () => {
									fetch("/api/unblock.php", {
										method: "POST",
										headers: {"Content-Type": "application/json"},
										body: JSON.stringify({
											csrfToken: "<?= $_SESSION['csrfToken'] ?>",
											id: div.dataset.id
										})
									});
									div.parentElement.remove();
								});
								document.getElementsByClassName("grid-blocked")[0].prepend(parent);
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
												b.parentElement.remove();
												modal.classList.add("hidden");
												modal.innerHTML = "";
												if (data.unmatch)
													setTimeout(() => {alert("You have ended your connection to this user.");}, 100);
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
												const container = document.getElementsByClassName("grid-likes")[0];
												container.prepend(b.parentElement);
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
												const container = document.getElementsByClassName("grid-matches")[0];
												container.prepend(b.parentElement);
												setTimeout(() => {alert("You matched! You can now initiate a discussion in your discussion.");}, 100);
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
		</script>
	</body>
</html>
