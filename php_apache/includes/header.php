		<header>
			<h1>Matcha</h1>
			<nav>
				<ul>
					<?php if (isset($_SESSION["user"]) && isset($_SESSION["user"]["id"]) && isset($_SESSION["user"]["isComplete"]) && $_SESSION["user"]["isComplete"]): ?>
					<li title="Search"><a href="/"><i class="fa-solid fa-magnifying-glass"></i></a></li>
					<li><div class="separator"></div></li>
					<li title="Connections"><a href="/connections.php"><i class="fa-solid fa-people-arrows"></i></a></li>
					<li><div class="separator"></div></li>
					<li title="Notifications">
						<button class="notif-trigger" type="button"><span class="notif-count hidden">0</span><i class="fa-solid fa-bell"></i></button>
						<div class="notif-wrapper hidden">
							<div class="notif-wrapper-header">
								<button class="notif-markall-button" onclick="markAsRead(this)" type="button" data-id="all">
									<span>Notifications</span>
									<span class="active"><i class="fa-solid fa-check"></i> Mark all as read</span>
								</button>
							</div>
							<div class="notif-container"></div>
						</div>
					</li>
					<li><div class="separator"></div></li>
					<li title="Settings"><a href="/settings.php"><i class="fa-solid fa-gear"></i></a></li>
					<?php endif; ?>
					<?php if (isset($_SESSION["user"]) && isset($_SESSION["user"]["id"])): ?>
					<li><div class="separator"></div></li>
					<li class="header-logout" title="Logout">
						<span class="active"><?= htmlspecialchars(ucwords($_SESSION["user"]["firstName"])) ?><span class="lastName"><?= htmlspecialchars(ucwords(" " . $_SESSION["user"]["lastName"])) ?></span></span>
						<a href="/api/logout.php"><i class="fa-solid fa-right-from-bracket"></i></a>
					</li>
					<?php endif; ?>
				</ul>
			</nav>
		</header>
