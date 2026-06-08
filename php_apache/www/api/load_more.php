<?php
	require_once "../db.php";
	require_once "../auth.php";

	$type = $_GET["type"] ?? NULL;
	$offset = $_GET["offset"] ?? NULL;

	if (!$type || !$offset || !is_numeric($offset))
		return ;
	if ($type == "index")
	{
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
			WHERE author <> :userId
			AND (preference = 'either' OR preference = :myGender)
			AND (:myPreference = 'either' OR gender = :myPreference)
			AND NOT EXISTS (
				SELECT 1
				FROM blocks b
				WHERE (b.author = :userId AND b.target = p.author) OR (b.author = p.author AND b.target = :userId)
			)
		");
		$filterPhReq->bindValue(":userId", $_SESSION["user"]["id"], PDO::PARAM_INT);
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
			(int)$filterPh["maxFame"],
		];
		for ($i = 0; $i < count($defaults); $i++)
		{
			if (!$filters[$i])
				$filters[$i] = $defaults[$i];
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
				INNER JOIN userInterests theirTags ON myTags.interestId = theirTags.interestId
				WHERE myTags.userId = :userId
				AND theirTags.userId = u.id
			) AS tags
			FROM profiles p
			INNER JOIN users u ON p.author = u.id
			WHERE u.id <> :userId AND u.isComplete = TRUE
			AND (p.preference = 'either' OR p.preference = :myGender)
			AND (:myPreference = 'either' OR p.gender = :myPreference)
			AND TIMESTAMPDIFF(YEAR, p.birthdate, CURDATE()) BETWEEN :ageMin AND :ageMax
			AND (6371 * ACOS(LEAST(1, COS(RADIANS(:myLat)) * COS(RADIANS(p.lat)) * COS(RADIANS(p.lon) - RADIANS(:myLon)) + SIN(RADIANS(:myLat)) * SIN(RADIANS(p.lat))))) BETWEEN :distMin AND :distMax
			AND p.fame BETWEEN :fameMin AND :fameMax
			AND NOT EXISTS (
				SELECT 1
				FROM blocks b
				WHERE (b.author = :userId AND b.target = u.id) OR (b.author = u.id AND b.target = :userId)
			)
			ORDER BY ";
		$query .= $app . " LIMIT 40 OFFSET :offset";
		$req = $pdo->prepare($query);
	}
	elseif ($type == "matches")
	{
		$query = "SELECT p.*, u.firstName, u.lastName, l.id AS moreid
			FROM profiles p
			INNER JOIN users u ON p.author = u.id
			INNER JOIN likes l ON l.author = :userId AND l.target = u.id
			INNER JOIN likes l2 ON l2.author = u.id AND l2.target = :userId
			WHERE u.id <> :userId AND l.id < :offset
			ORDER BY l.id DESC
			LIMIT 20
		";
	}
	elseif ($type == "likes")
	{
		$query = "SELECT p.*, u.firstName, u.lastName, l.id AS moreid
			FROM profiles p
			INNER JOIN users u ON p.author = u.id
			INNER JOIN likes l ON l.author = :userId AND l.target = u.id
			WHERE u.id <> :userId AND l.id < :offset
			AND NOT EXISTS (
				SELECT 1
				FROM likes l2
				WHERE l2.author = u.id AND l2.target = :userId
			)
			ORDER BY l.id DESC
			LIMIT 20
		";
	}
	elseif ($type == "liked")
	{
		$query = "SELECT p.*, u.firstName, u.lastName, l.id AS moreid
			FROM profiles p
			INNER JOIN users u ON p.author = u.id
			INNER JOIN likes l ON l.author = u.id AND l.target = :userId
			WHERE u.id <> :userId AND l.id < :offset
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
		";
	}
	$req = $pdo->prepare($query);
	$req->bindValue(":userId", $_SESSION["user"]["id"], PDO::PARAM_INT);
	if ($type == "index")
	{
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
	}
	$req->bindValue(":offset", $offset, PDO::PARAM_INT);
	$req->execute();
	$today = new DateTime();
	updateLastOnline($pdo);
?>

<?php while ($row = $req->fetch(PDO::FETCH_ASSOC)): ?>
<?php $birthDate = new DateTime($row["birthdate"]); $age = $birthDate->diff($today)->y; ?>
	<div class="grid-items">
		<button onclick="openmodal(this)" class="modal-button" type="button" data-id="<?php echo htmlspecialchars($row['id']); ?>">
			<?php if ($type == "index"): ?>
			<span class="overlay top">
				<span class="label">
					<i class="fa-solid fa-star label"></i>
					<?php echo htmlspecialchars(ucfirst($row['fame'])); ?>
				</span>
				<span class="label"><?php echo htmlspecialchars((int)$row['distance']) . "km" ?></span>
			</span>
			<?php endif; ?>
			<img src="<?php echo htmlspecialchars($row['primaryPicture']); ?>" alt="Primary picture of <?php echo htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?>"></img>
			<span class="overlay bottom"><?php echo htmlspecialchars($row['firstName'] . " " . $row['lastName']); echo $type == 'index' ? htmlspecialchars(", " . $age) : '' ?></span>
		</button>
	</div>
<?php endwhile; ?>
