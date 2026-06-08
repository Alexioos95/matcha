<?php
	require_once "db.php";
	require_once "auth.php";

	if (!isset($_SESSION["user"]) || !isset($_SESSION["user"]["id"]))
	{
		header("Location: /login.php");
		exit;
	}
	requireCompleteProfile();
	updateLastOnline($pdo);

	$matchesReq = $pdo->prepare("SELECT p.*, u.firstName, u.lastName, l1.id AS moreid
		FROM profiles p
		INNER JOIN users u ON p.author = u.id
		INNER JOIN likes l1 ON l1.author = :userId AND l1.target = u.id
		INNER JOIN likes l2 ON l2.author = u.id AND l2.target = :userId
		WHERE u.id <> :userId
		ORDER BY l1.id DESC
		LIMIT 20
	");
	$matchesReq->bindValue(":userId", $_SESSION["user"]["id"], PDO::PARAM_INT);
	$matchesReq->execute();

	$likesReq = $pdo->prepare("SELECT p.*, u.firstName, u.lastName, l.id AS moreid
		FROM profiles p
		INNER JOIN users u ON p.author = u.id
		INNER JOIN likes l ON l.author = :userId AND l.target = u.id
		WHERE u.id <> :userId
		AND NOT EXISTS (
			SELECT 1
			FROM likes l2
			WHERE l2.author = u.id AND l2.target = :userId
		)
		ORDER BY l.id DESC
		LIMIT 20
	");
	$likesReq->bindValue(":userId", $_SESSION["user"]["id"], PDO::PARAM_INT);
	$likesReq->execute();

	$likedReq = $pdo->prepare("SELECT p.*, u.firstName, u.lastName, l.id AS moreid
		FROM profiles p
		INNER JOIN users u ON p.author = u.id
		INNER JOIN likes l ON l.author = u.id AND l.target = :userId
		WHERE u.id <> :userId
		AND NOT EXISTS (
			SELECT 1
			FROM likes l2
			WHERE l2.author = :userId AND l2.target = u.id
		)
		AND NOT EXISTS (
			SELECT 1
			FROM blocks b
			WHERE (b.author = :userId AND b.target = u.id)
			OR (b.author = u.id AND b.target = :userId)
		)
		ORDER BY l.id DESC
		LIMIT 20
	");
	$likedReq->bindValue(":userId", $_SESSION["user"]["id"], PDO::PARAM_INT);
	$likedReq->execute();

	$visitorReq = $pdo->prepare("SELECT p.*, u.firstName, u.lastName
		FROM profiles p
		INNER JOIN users u ON p.author = u.id
		WHERE u.id <> :userId
		AND EXISTS (
			SELECT 1
			FROM history h
			WHERE h.host = :userId
			AND h.visitor = u.id
		)
		AND NOT EXISTS (
			SELECT 1
			FROM blocks b
			WHERE (b.author = :userId AND b.target = u.id)
			OR (b.author = u.id AND b.target = :userId)
		)
		AND NOT EXISTS (
			SELECT 1
			FROM likes l
			WHERE l.author = :userId
			AND l.target = u.id
		)
		AND NOT EXISTS (
			SELECT 1
			FROM likes l
			WHERE l.author = u.id
			AND l.target = :userId
		)
		ORDER BY (
			SELECT h2.id
			FROM history h2
			WHERE h2.host = :userId
			AND h2.visitor = u.id
			ORDER BY h2.id DESC
			LIMIT 1
		) DESC
		LIMIT 20
	");
	$visitorReq->bindValue(":userId", $_SESSION["user"]["id"], PDO::PARAM_INT);
	$visitorReq->execute();

	$blockReq = $pdo->prepare("SELECT p.*, u.firstName, u.lastName
		FROM profiles p
		INNER JOIN users u ON p.author = u.id
		INNER JOIN blocks b ON b.author = :userId AND b.target = u.id
		WHERE u.id <> :userId
		ORDER BY b.id DESC
		LIMIT :limit
	");
	$blockReq->bindValue(":userId", $_SESSION["user"]["id"], PDO::PARAM_INT);
	$blockReq->bindValue(":limit", 20, PDO::PARAM_INT);
	$blockReq->execute();
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="description" content="Match with your soulmate!">
		<title>Matcha - My connections</title>
		<link rel="icon" type="image/x-icon" href="/images/favicon.ico">
		<link rel="stylesheet" type="text/css" href="https://necolas.github.io/normalize.css/8.0.1/normalize.css">
		<script src="https://kit.fontawesome.com/70111f5ad5.js" crossorigin="anonymous"></script>
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
		<link rel="stylesheet" type="text/css" href="./styles.css">
	</head>
	<body class="index-body">
		<div class="modal hidden"></div>
		<header>
			<h1>Matcha</h1>
			<nav>
				<ul>
					<li><a href="/"><i class="fa-solid fa-magnifying-glass"></i><span>Search</span></a></li>
					<?php
						if (isset($_SESSION["user"]) && isset($_SESSION["user"]["id"]))
						{
							echo "<li><a href='/connections.php'><i class='fa-solid fa-people-arrows'></i><span>Connections</span></a></li>";
							echo "<li><a href='/settings.php'><i class='fa-solid fa-gear'></i><span>Settings</span></a></li>";
							echo "<li><a href='/api/logout.php'><i class='fa-solid fa-right-from-bracket'></i><span>Logout</span></a></li>";
						}
						else
							echo "<li><a href='/login.php'><i class='fa-solid fa-user'></i><span>Login</span></a></li>";
					?>
				</ul>
			</nav>
		</header>
		<main class="connections">
			<div>
				<h2>You are a match!</h2>
				<div>
					<div class="grid-container-connections grid-matches">
					<?php while ($row = $matchesReq->fetch(PDO::FETCH_ASSOC)): ?>
						<div class="grid-items">
							<button class="modal-button" type="button" data-id="<?php echo htmlspecialchars($row['id']); ?>" data-moreid="<?php echo htmlspecialchars($row['moreid']); ?>">
								<img src="<?php echo htmlspecialchars($row['primaryPicture']); ?>" alt="Primary picture of <?php echo htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?>">
								<span class="overlay bottom"><?php echo htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?></span>
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
						<div class="grid-items">
							<button class="modal-button" type="button" data-id="<?php echo htmlspecialchars($row['id']); ?>" data-moreid="<?php echo htmlspecialchars($row['moreid']); ?>">
								<img src="<?php echo htmlspecialchars($row['primaryPicture']); ?>" alt="Primary picture of <?php echo htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?>">
								<span class="overlay bottom"><?php echo htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?></span>
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
						<div class="grid-items">
							<button class="modal-button" type="button" data-id="<?php echo htmlspecialchars($row['id']); ?>" data-moreid="<?php echo htmlspecialchars($row['moreid']); ?>">
								<img src="<?php echo htmlspecialchars($row['primaryPicture']); ?>" alt="Primary picture of <?php echo htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?>">
								<span class="overlay bottom"><?php echo htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?></span>
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
					<div class="grid-items">
						<button class="modal-button" type="button" data-id="<?php echo htmlspecialchars($row['id']); ?>">
							<img src="<?php echo htmlspecialchars($row['primaryPicture']); ?>" alt="Primary picture of <?php echo htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?>">
							<span class="overlay bottom"><?php echo htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?></span>
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
							<img src="<?php echo htmlspecialchars($row['primaryPicture']); ?>" alt="Primary picture of <?php echo htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?>">
							<span class="overlay bottom"><?php echo htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?></span>
							<button class="unblock-button" type="button" data-id="<?php echo htmlspecialchars($row['id']); ?>"><i class="fa-solid fa-xmark"></i></button>
						</div>
					</div>
				<?php endwhile ?>
				</div>
			</div>
		</main>
		<footer>
			<p>Matcha by apayen@student.42.fr</p>
		</footer>
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
							csrfToken: "<?php echo $_SESSION['csrfToken'] ?>",
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
			Array.from(buttons).forEach(b => {
				b.addEventListener("click", () => {
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
											csrfToken: "<?php echo $_SESSION['csrfToken'] ?>",
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
												csrfToken: "<?php echo $_SESSION['csrfToken'] ?>",
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
											csrfToken: "<?php echo $_SESSION['csrfToken'] ?>",
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
														alert("You have ended your connection to this user.");
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
													alert("You matched! You can now initiate a discussion in your discussion.");
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
												csrfToken: "<?php echo $_SESSION['csrfToken'] ?>",
												id: b.dataset.id
											})
										});
										modalActions[2].disabled = true;
									}
								});
							}
						})
				});
			});
		</script>
	</body>
</html>
