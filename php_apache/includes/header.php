		<header>
			<h1>Matcha</h1>
			<nav>
				<ul>
					<?php if (isset($_SESSION["user"]) && isset($_SESSION["user"]["id"]) && isset($_SESSION["user"]["isComplete"]) && $_SESSION["user"]["isComplete"]): ?>
					<li><a href="/"><i class="fa-solid fa-magnifying-glass"></i><span>Search</span></a></li>
					<li><a href="/connections.php"><i class="fa-solid fa-people-arrows"></i><span>Connections</span></a></li>
					<li>
						<button class="notif-trigger" type="button"><span class="notif-count hidden">0</span><i class="fa-solid fa-bell"></i><span>Notifications</span></button>
						<div class="notif-wrapper hidden">
							<div class="notif-wrapper-header">
								<button class="notif-mark-all" onclick="markAsRead(this)" type="button" data-id="all"><i class="fa-solid fa-check"></i> Mark all as read</button>
							</div>
							<div class="notif-container"></div>
						</div>
					</li>
					<li><a href="/settings.php"><i class="fa-solid fa-gear"></i><span>Settings</span></a></li>
					<?php endif; ?>
					<?php if (isset($_SESSION["user"]) && isset($_SESSION["user"]["id"])): ?>
					<li><a href="/api/logout.php"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a></li>
					<?php endif; ?>
				</ul>
			</nav>
		</header>
