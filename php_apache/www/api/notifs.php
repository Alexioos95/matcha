<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	requireLogin();
	$above = $_GET["above"] ?? null;
	if ($above == null || !filter_var($above, FILTER_VALIDATE_INT) || $above < 0)
		$above = 0;
	if ($above == 0)
	{
		$countReq = $pdo->prepare("SELECT COUNT(*) FROM notifs WHERE toUser = ? AND isRead = 0");
		$countReq->execute([$_SESSION["user"]["id"]]);
		$unreadCount = (int)$countReq->fetchColumn();
		$limit = max(0, 20 - $unreadCount);
		$req = $pdo->prepare("
			(
				SELECT *
				FROM notifs
				WHERE toUser = :user AND isRead = 0
			)
			UNION ALL
			(
				SELECT *
				FROM notifs
				WHERE toUser = :user AND isRead = 1
				ORDER BY id DESC
				LIMIT :limit
			)
			ORDER BY id DESC
		");
		$req->bindValue(":user", $_SESSION["user"]["id"], PDO::PARAM_INT);
		$req->bindValue(":user", $_SESSION["user"]["id"], PDO::PARAM_INT);
		$req->bindValue(":limit", $limit, PDO::PARAM_INT);
		$req->execute();
	}
	else
	{
		$req = $pdo->prepare("SELECT * FROM notifs WHERE id > ? AND toUser = ? AND isRead = 0 ORDER BY id DESC");
		$req->execute([$above, $_SESSION["user"]["id"]]);
	}
?>

<?php while ($row = $req->fetch(PDO::FETCH_ASSOC)): ?>
<?php
	$type = $row["type"];
	if ($type == "Visit")
		$message = "has visited your profile.";
	elseif ($type == "Message")
		$message = "has sent you a message.";
	elseif ($type == "Like")
		$message = "liked you! Reciprocate it to chat with him.";
	elseif ($type == "Match")
		$message = "finally liked you back! You are now a match, and can chat together.";
	elseif ($type == "Unmatch")
		$message = "has broken your connection. You are now unmatched, and can't chat together anymore.";
	else
		$message = "Unknown type.";
	$userReq = $pdo->prepare("SELECT * FROM users WHERE id = ?");
	$userReq->execute([$row["fromUser"]]);
	$user = $userReq->fetch(PDO::FETCH_ASSOC);
	if ($user)
		$name = $user["firstName"] . " " . $user["lastName"];
	else
		$name = "<!> A deleted user";
	date_default_timezone_set("Europe/Paris");
	$date = date("Y-m-d, H:i", strtotime($row["createdAt"]));
	$r = $row["isRead"];
?>
<<?= $r ? "div" : "button type='button' title='Mark as read' onclick='markAsRead(this)'" ?> class="notif <?= $r ? "notif-read" : "notif-unread" ?>" data-id="<?= $row['id'] ?>">
	<p class="notif-header">
		<span class="notif-type"><?= htmlspecialchars($type) ?></span>
		<span class="label"><?= htmlspecialchars($date) ?></span>
	</p>
	<p><span class="notif-user"><?= htmlspecialchars($name) ?></span> <?= htmlspecialchars($message) ?></p>
</<?= $r ? "div" : "button" ?>>
<?php endwhile; ?>
