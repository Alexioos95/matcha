<?php
	require_once "/usr/local/bin/includes/db.php";
	require_once "/usr/local/bin/includes/auth.php";

	requireLogin();
	requireProfile();
	updateLastOnline($pdo);

	$targetId = isset($_GET["user"]) && is_numeric($_GET["user"]) ? (int)$_GET["user"] : null;

	$match = null;
	if ($targetId !== null)
	{
		$req = $pdo->prepare("
			SELECT u.id, u.firstName, u.lastName, p.primaryPicture
			FROM profiles p
			INNER JOIN users u ON p.author = u.id
			INNER JOIN likes l1 ON l1.author = :me    AND l1.target = u.id
			INNER JOIN likes l2 ON l2.author = u.id   AND l2.target = :me
			WHERE u.id = :target
		");
		$req->bindValue(":me",     $_SESSION["user"]["id"], PDO::PARAM_INT);
		$req->bindValue(":target", $targetId,               PDO::PARAM_INT);
		$req->execute();
		$result = $req->fetch(PDO::FETCH_ASSOC);
		if ($result !== false)
			$match = $result;
	}

	$name = $match !== null
		? ucwords($match["firstName"] . " " . $match["lastName"])
		: null;
?>

<!DOCTYPE html>
<html lang="en">
	<?php $title = "- Chat"; require_once "/usr/local/bin/includes/head.php"; ?>
	<body class="index">
		<?php require_once "/usr/local/bin/includes/header.php" ?>
		<main class="chat">
			<div class="chat-layout">
				<aside class="chat-sidebar">
					<div class="chat-sidebar-header">
						<span class="chat-sidebar-title">Messages</span>
					</div>
					<div class="chat-sidebar-list" id="chat-sidebar-list">
						<span class="chat-sidebar-empty">Loading...</span>
					</div>
				</aside>

				<div class="chat-container">
					<section class="chat-panel">
						<div class="chat-panel-header">
							<span class="chat-header-title" id="chat-header-title">
								<?= $name !== null ? htmlspecialchars($name) : "Select a conversation" ?>
							</span>
						</div>
						<div class="chat-panel-body" id="chat-panel-body">
							<?php if ($match === null): ?>
							<span class="chat-empty">Pick a conversation from the list on the left.</span>
							<?php endif; ?>
						</div>
						<div class="chat-panel-footer" id="chat-panel-footer" <?= $match === null ? 'style="display:none"' : '' ?>>
							<input
								type="text"
								id="chat-input"
								placeholder="Type a message..."
								aria-label="Message"
							>
							<button type="button" id="chat-send" aria-label="Send">
								<i class="fa-solid fa-paper-plane"></i>
							</button>
						</div>
					</section>
				</div>

			</div>
		</main>
		<?php require_once "/usr/local/bin/includes/footer.php" ?>

		<script>
		const csrfToken   = "<?= htmlspecialchars($_SESSION["csrfToken"]) ?>";
		let targetUserId  = <?= $match !== null ? (int)$match["id"] : "null" ?>;
		let lastMessageId = 0;
		let isLoading     = false;
		let pollInterval  = null;

		const sidebarList = document.getElementById("chat-sidebar-list");
		const headerTitle = document.getElementById("chat-header-title");
		const panelBody   = document.getElementById("chat-panel-body");
		const panelFooter = document.getElementById("chat-panel-footer");
		const chatInput   = document.getElementById("chat-input");
		const chatSend    = document.getElementById("chat-send");

		function loadSidebar()
		{
			fetch("/api/matches.php")
				.then(function(r) { return r.json(); })
				.then(function(matches)
				{
					sidebarList.innerHTML = "";

					if (!Array.isArray(matches) || matches.length === 0)
					{
						sidebarList.innerHTML = '<span class="chat-sidebar-empty">No matches yet.</span>';
						return;
					}

					matches.forEach(function(m)
					{
						const item = document.createElement("button");
						item.className = "chat-sidebar-item";
						item.type = "button";
						if (targetUserId === m.id)
							item.classList.add("active");

						const avatar = document.createElement("img");
						avatar.src = m.primaryPicture;
						avatar.alt = m.firstName;
						avatar.className = "chat-sidebar-avatar";

						const info = document.createElement("div");
						info.className = "chat-sidebar-info";

						const nameEl = document.createElement("span");
						nameEl.className = "chat-sidebar-name";
						nameEl.textContent = m.firstName + " " + m.lastName;

						const preview = document.createElement("span");
						preview.className = "chat-sidebar-preview";
						preview.textContent = m.lastMessage || "Start chatting!";

						info.appendChild(nameEl);
						info.appendChild(preview);
						item.appendChild(avatar);
						item.appendChild(info);

						item.addEventListener("click", function(e) { openConversation(m, item); });
						sidebarList.appendChild(item);
					});
				});
		}

		function openConversation(m, itemEl)
		{
			targetUserId  = m.id;
			lastMessageId = 0;

			headerTitle.textContent = m.firstName + " " + m.lastName;
			panelBody.innerHTML = "";
			panelFooter.style.display = "";
			chatInput.focus();

			document.querySelectorAll(".chat-sidebar-item").forEach(function(el) {
				el.classList.remove("active");
			});
			itemEl.classList.add("active");

			if (pollInterval !== null)
				clearInterval(pollInterval);

			loadMessages();
			pollInterval = setInterval(loadMessages, 2000);

			history.replaceState(null, "", "/chat.php?user=" + m.id);
		}

		function appendMessage(fromMe, content, time)
		{
			const bubble = document.createElement("div");
			bubble.className = fromMe ? "message message-me" : "message message-them";

			const text = document.createElement("span");
			text.className = "message-content";
			text.textContent = content;

			const timestamp = document.createElement("span");
			timestamp.className = "message-time";
			timestamp.textContent = time;

			bubble.appendChild(text);
			bubble.appendChild(timestamp);
			panelBody.appendChild(bubble);
			panelBody.scrollTop = panelBody.scrollHeight;
		}

		function loadMessages()
		{
			if (targetUserId === null || isLoading)
				return;
			isLoading = true;

			fetch("/api/messages_get.php?with=" + targetUserId + "&above=" + lastMessageId)
				.then(function(r) { return r.json(); })
				.then(function(messages)
				{
					if (!Array.isArray(messages))
						return;

					messages.forEach(function(msg)
					{
						appendMessage(msg.fromMe, msg.content, msg.time);
						if (msg.id > lastMessageId)
							lastMessageId = msg.id;
					});
				})
				.finally(function() { isLoading = false; });
		}

		function sendMessage()
		{
			const content = chatInput.value.trim();
			if (content === "" || targetUserId === null)
				return;

			fetch("/api/messages_send.php", {
				method:  "POST",
				headers: { "Content-Type": "application/json" },
				body:    JSON.stringify({ csrfToken, toUser: targetUserId, content })
			})
			.then(function(r) { return r.json(); })
			.then(function(result)
			{
				if (result.error) { alert(result.error); return; }
				chatInput.value = "";
				loadMessages();
			});
		}

		chatSend.addEventListener("click", sendMessage);
		chatInput.addEventListener("keydown", function(e) {
			if (e.key === "Enter") sendMessage();
		});

		loadSidebar();

		if (targetUserId !== null)
		{
			loadMessages();
			pollInterval = setInterval(loadMessages, 2000);
		}
		</script>

		<?php require_once "/usr/local/bin/includes/notifJS.php" ?>
	</body>
</html>
