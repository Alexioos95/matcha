<?php
	$host = "mysql";
	$dbname = getenv("MYSQL_DATABASE");
	$user = getenv("MYSQL_USER");
	$pass = getenv("MYSQL_PASSWORD");

	if (!$dbname || !$user || !$pass)
		die("Couldn't access env variables\n");
	try
	{
		$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
	catch (PDOException $e)
	{ die("Couldn't connect to the database\n"); }
		
	while (true)
	{
		try
		{
			$req = $pdo->prepare("SELECT * FROM mailQueue WHERE sent = 0 LIMIT 5");
			$req->execute();
			$mails = $req->fetchAll(PDO::FETCH_ASSOC);
			if (!$mails)
			{
				sleep(5);
				continue;
			}
			foreach ($mails as $m)
			{
				$headers = "MIME-Version: 1.0" . "\r\n";
				$headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
				$headers .= "From: " . getenv("GMAIL_SENDER") . "\r\n";
				if (mail($m["email"], $m["subject"], $m["body"], $headers))
				{
					$update = $pdo->prepare("UPDATE mailQueue SET sent = 1 WHERE id = ?");
					$update->execute([$m["id"]]);
				}
			}
		}
		catch (PDOException $e)
		{ sleep(10); }
	}
?>
