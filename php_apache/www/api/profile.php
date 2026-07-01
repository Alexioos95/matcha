<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	requireLogin();
	$id = $_GET['id'] ?? null;
	if (!$id || !is_numeric($id))
		exit;
	// Get row
	$profileReq = $pdo->prepare("SELECT p.*, u.firstName, u.lastName, u.lastOnline FROM profiles p INNER JOIN users u ON p.author = u.id WHERE p.id = ?");
	$profileReq->execute([$id]);
	$row = $profileReq->fetch(PDO::FETCH_ASSOC);
	// Online status
	date_default_timezone_set("Europe/Paris");
	$birthDate = new DateTime($row["birthdate"]);
	$today = new DateTime();
	$age = $birthDate->diff($today)->y;
	$diff = time() - strtotime($row["lastOnline"]);
	$online = false;
	$status = "";
	if ($diff < 300)
	{
		$online = true;
		$status = "Online";
	}
	elseif ($diff < 3600)
	{
		$time = floor($diff / 60);
		$status = $time . ($time == 1 ? " minute ago" : " minutes ago");
	}
	elseif ($diff < 86400)
	{
		$time = floor($diff / 3600);
		$status = $time . ($time == 1 ? " hour ago" : " hours ago");
	}
	elseif ($diff < 2592000)
	{
		$time = floor($diff / 86400);
		$status = $time . ($time == 1 ? " day ago" : " days ago");
	}
	elseif ($diff < 31536000)
	{
		$time = floor($diff / 2592000);
		$status = $time . ($time == 1 ? " month ago" : " months ago");
	}
	else
	{
		$time = floor($diff / 31536000);
		$status = $time . ($time == 1 ? " year ago" : " years ago");
	}
	// Interests
	$intReq = $pdo->prepare("SELECT * from interests");
	$intReq->execute();
	// User's interests
	$uIntReq = $pdo->prepare("SELECT * FROM userInterests WHERE user = ?");
	$uIntReq->execute([$id]);
	$int = $intReq->fetchAll(PDO::FETCH_ASSOC);
	// Match status
	$myLikeReq = $pdo->prepare("SELECT * FROM likes WHERE author = ? AND target = ?");
	$myLikeReq->execute([$_SESSION["user"]["id"], $row["author"]]);
	$myLike = $myLikeReq->fetch(PDO::FETCH_ASSOC);
	$hisLikeReq = $pdo->prepare("SELECT * FROM likes WHERE author = ? AND target = ?");
	$hisLikeReq->execute([$row["author"], $_SESSION["user"]["id"]]);
	$hisLike = $hisLikeReq->fetch(PDO::FETCH_ASSOC);
	// Visit history
	$checkReq = $pdo->prepare("SELECT 1 FROM visitHistory WHERE host = ? AND visitor = ?");
	$checkReq->execute([$row["author"], $_SESSION["user"]["id"]]);
	if (!$checkReq->fetch(PDO::FETCH_ASSOC))
	{
		$historyReq = $pdo->prepare("INSERT INTO visitHistory (host, visitor) VALUES (?, ?)");
		$historyReq->execute([$row["author"], $_SESSION["user"]["id"]]);
		createNotif($pdo, $row["author"], "Visit");
		updateFameScore($pdo, $row["author"]);
		updateLastOnline($pdo);
	}
?>

<div class="modal-profile">
	<div class="modal-header">
		<h3>
			<span><?= htmlspecialchars(ucwords($row["firstName"] . " " . $row["lastName"])) ?><span class="label"><?= ", " . $age ?></span></span>
			<button onclick="closemodal()" type="button" title="Close" aria-label="Close"><i class="fa-solid fa-x"></i></button>
		</h3>
	</div>
	<div class="modal-body">
		<div class="modal-infos">
			<div class="modal-infos-user">
				<div>
					<div title="Gender">
						<i class="fa-solid fa-address-card label"></i>
						<span class="label"><?= $row["gender"] !== null ? htmlspecialchars(ucfirst($row["gender"])) : ""; ?></span>
					</div>
					<div title="Birthday">
						<i class="fa-solid fa-cake-candles label"></i>
						<span class="label"><?= htmlspecialchars(ucfirst($row['birthdate'])); ?></span>
					</div>
					<div title="Preference">
						<i class="fa-solid fa-heart label"></i>
						<span class="label"><?= $row['preference'] !== null ? htmlspecialchars(ucfirst($row['preference'])) : ''; ?></span>
					</div>
				</div>
				<div class="align-right">
					<div title="Fame rating">
						<span class="label"><?= $row['fame'] !== null ? htmlspecialchars(ucfirst($row['fame'])) : ''; ?></span>
						<i class="fa-solid fa-star label"></i>
					</div>
					<div title="Location">
						<span class="label"><?= htmlspecialchars($row["city"] . ", " . $row["country"]); ?></span>
						<i class="fa-solid fa-location-dot label"></i></i>
					</div>
					<div title="<?= htmlspecialchars("Last online: " . $_SESSION['user']['lastOnline']); ?>">
						<span class="label <?= $online == true ? 'online' : 'offline'?>"><?= $status ?></span>
						<i class="fa-solid fa-tower-broadcast label <?= $online == true ? 'online' : 'offline'?>"></i>
					</div>
				</div>
			</div>
		</div>
		<div class="modal-carousel-wrapper">
			<button class="carousel-button prev" type="button" aria-label="Previous picture">
				<i class="fa-solid fa-circle-arrow-left"></i>
			</button>
			<div class="modal-carousel">
				<img src="<?= htmlspecialchars($row['primaryPicture'])?>" alt="Primary picture"></img>
				<?php
					if (isset($row["secondaryPictureOne"]) && $row["secondaryPictureOne"])
						echo "<img src='" . htmlspecialchars($row['secondaryPictureOne']) . "' alt='Secondary picture'>";
					if (isset($row["secondaryPictureTwo"]) && $row["secondaryPictureTwo"])
						echo "<img src='" . htmlspecialchars($row['secondaryPictureTwo']) . "' alt='Secondary picture'>";
					if (isset($row["secondaryPictureThree"]) && $row["secondaryPictureThree"])
						echo "<img src='" . htmlspecialchars($row['secondaryPictureThree']) . "' alt='Secondary picture'>";
					if (isset($row["secondaryPictureFour"]) && $row["secondaryPictureFour"])
						echo "<img src='" . htmlspecialchars($row['secondaryPictureFour']) . "' alt='Secondary picture'>";
				?>
			</div>
			<button class="carousel-button next" type="button" aria-label="Next picture">
				<i class="fa-solid fa-circle-arrow-right"></i>
			</button>
		</div>
		<div class="form-group-modal">
			<span class="label"><i class="fa-solid fa-tags"></i> My interests</span>
			<div class="group-interest">
				<?php while ($uIntRow = $uIntReq->fetch(PDO::FETCH_ASSOC)): ?>
					<div type="button" class="interest" data-interest-id="<?= htmlspecialchars($uIntRow['interest']) ?>">
						<span><?= htmlspecialchars($int[$uIntRow["interest"] - 1]["name"]) ?></span>
					</div>
				<?php endwhile; ?>
			</div>
		</div>
		<div class="form-group-modal">
			<span class="label"><i class="fa-solid fa-quote-left"></i> About me</span>
			<p><?= htmlspecialchars($row["bio"]) ?></p>
		</div>
	</div>
	<div class="modal-footer">
		<div>
			<div class="background"></div>
			<button title="Block this user"><i class="fa-solid fa-ban"></i></button>
			<?php if ($myLike && $hisLike): ?>
			<button title="Unmatch this user">
				<svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="64.000000pt" height="64.000000pt" viewBox="0 0 64.000000 64.000000" preserveAspectRatio="xMidYMid meet">
					<g transform="translate(0.000000,64.000000) scale(0.100000,-0.100000)" fill="rgb(214, 46, 46)" stroke="none">
						<path d="M91 593 c-48 -24 -84 -83 -89 -148 -8 -96 60 -207 209 -340 95 -85 104 -90 131 -73 38 25 161 139 201 188 89 106 117 212 77 294 -26 55 -67 86 -120 93 -61 8 -106 -5 -146 -42 l-36 -35 -19 24 c-42 53 -141 72 -208 39z"/>
					</g>
				</svg>
			</button>
			<?php elseif ($hisLike): ?>
			<button title="'Like' and 'Match' this user!">
				<svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="64.000000pt" height="64.000000pt" viewBox="0 0 64.000000 64.000000" preserveAspectRatio="xMidYMid meet">
					<g transform="translate(0.000000,64.000000) scale(0.100000,-0.100000)" fill="rgb(214, 46, 46)" stroke="none">
						<path d="M91 593 c-18 -9 -45 -35 -60 -57 -22 -33 -26 -51 -26 -105 1 -104 54 -188 200 -320 101 -91 110 -97 137 -78 40 26 150 128 196 181 59 68 102 159 102 215 0 56 -32 124 -71 152 -63 44 -158 37 -215 -16 l-36 -35 -19 24 c-42 53 -141 72 -208 39z m455 -64 c33 -31 48 -89 35 -137 -20 -71 -125 -208 -204 -264 l-28 -20 3 190 c3 187 3 191 28 217 14 15 34 31 45 36 32 14 95 3 121 -22z"/>
					</g>
				</svg>
			</button>
			<?php elseif ($myLike): ?>
			<button title="un'Like' this user">
				<svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="64.000000pt" height="64.000000pt" viewBox="0 0 64.000000 64.000000" preserveAspectRatio="xMidYMid meet">
					<g transform="translate(0.000000,64.000000) scale(0.100000,-0.100000)" fill="rgb(214, 46, 46)" stroke="none">
						<path d="M91 593 c-48 -24 -84 -83 -89 -148 -8 -96 60 -207 209 -340 95 -85 104 -90 131 -73 38 25 161 139 201 188 89 106 117 212 77 294 -26 55 -67 86 -120 93 -61 8 -106 -5 -146 -42 l-36 -35 -19 24 c-42 53 -141 72 -208 39z m163 -72 l31 -31 3 -191 3 -191 -30 21 c-48 35 -145 146 -176 203 -49 90 -35 178 36 216 38 20 97 8 133 -27z"/>
					</g>
				</svg>
			</button>
			<?php elseif (!$myLike && !$hisLike): ?>
			<button title="'Like' this user!">
				<svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="64.000000pt" height="64.000000pt" viewBox="0 0 64.000000 64.000000" preserveAspectRatio="xMidYMid meet">
					<g transform="translate(0.000000,64.000000) scale(0.100000,-0.100000)" fill="white" stroke="none">
						<path d="M91 593 c-48 -24 -84 -83 -89 -148 -8 -96 60 -207 209 -340 95 -85 104 -90 131 -73 38 25 161 139 201 188 89 106 117 212 77 294 -26 55 -67 86 -120 93 -61 8 -106 -5 -146 -42 l-36 -35 -19 24 c-42 53 -141 72 -208 39z"/>
					</g>
				</svg>
			</button>
			<?php endif; ?>
			<?php if ($myLike && $hisLike): ?>
			<a href="/chat.php?user=<?= htmlspecialchars($row['author']) ?>" class="modal-chat-button" title="Chat with this user"><i class="fa-solid fa-comments"></i></a>
			<?php endif; ?>
			<button title="Report this user"><i class="fa-solid fa-exclamation"></i></button>
		</div>
	</div>
</div>
