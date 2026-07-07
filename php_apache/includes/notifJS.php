		<script>
			// Trigger
			const notifTrigger = document.getElementsByClassName("notif-trigger")[0];
			const notifDropdown = document.getElementsByClassName("notif-wrapper")[0];
			const notifContainer = document.getElementsByClassName("notif-container")[0];
			notifTrigger.addEventListener("click", (e) => {
				e.stopPropagation();
				if (notifDropdown.classList.contains("hidden"))
				{
					fetch("/api/notifs.php")
						.then(response => response.text())
						.then(data => {
							if (data.trim() != "")
								notifContainer.innerHTML = data;
							else if (notifContainer.children.length == 0)
							{
								const div = document.createElement("div");
								div.classList.add("notif", "notif-unread", "no-notif");
								div.dataset.id = 0;
								const p = document.createElement("p");
								p.innerHTML = "You have no notification yet.";
								div.append(p);
								notifContainer.append(div);
							}
						})
						.catch();
				}
				notifDropdown.classList.toggle("hidden");
			});
			notifDropdown.addEventListener("click", (e) => { e.stopPropagation(); });
			document.addEventListener("click", () => { notifDropdown.classList.add("hidden"); });
			// Mark as read
			function markAsRead(b)
			{
				const id = b.dataset.id;
				fetch("/api/notif_read.php?id=" + id)
					.then(() => {
						if (id == "all")
						{
							const unread = document.getElementsByClassName("notif-unread");
							Array.from(unread).forEach((el) => {
								el.classList.add("notif-read");
								el.classList.remove("notif-unread");
							});
						}
						else
						{
							b.classList.add("notif-read");
							b.classList.remove("notif-unread");
						}
						updateNotif();
					})
					.catch();
			}
			// Count
			const notifCount = document.getElementsByClassName("notif-count")[0];
			const markAll = document.getElementsByClassName("notif-markall-button")[0];
			async function updateNotif()
			{
				fetch("/api/notif_count.php")
					.then(response => response.json())
					.then(data => {
						if (data.count > 99)
							notifCount.innerHTML = "99+";
						else
							notifCount.innerHTML = data.count;
						if (data.count > 0)
						{
							markAll.disabled = false;
							notifCount.classList.remove("hidden");
							if (!notifDropdown.classList.contains("hidden"))
							{
								const lastNotif = document.getElementsByClassName("notif")[0];
								const last = lastNotif !== undefined ? lastNotif.dataset.id : 0;
								fetch("/api/notifs.php?above=" + last)
									.then(response => response.text())
									.then(data => {
										if (data.trim() != "")
										{
											const noNotif = document.getElementsByClassName("no-notif")[0];
											if (noNotif)
												noNotif.remove();
										}
										notifContainer.insertAdjacentHTML("afterbegin", data);
									});
							}
						}
						else
						{
							markAll.disabled = true;
							notifCount.classList.add("hidden");
						}
					})
					.catch();
			}
			updateNotif();
			setInterval(updateNotif, 5000);
		</script>
