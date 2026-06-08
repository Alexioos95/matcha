<?php
	require_once "../db.php";
	require_once "../auth.php";

	header('Content-Type: application/json');
	$data = json_decode(file_get_contents("php://input"), true);
	if (!isset($data["csrfToken"]) || !isset($_SESSION["csrfToken"]) || !hash_equals($_SESSION["csrfToken"], $data["csrfToken"]))
	{
		echo json_encode(["error" => "Invalid CSRF token."]);
		exit;
	}
	$lat = $data["lat"];
	$lon = $data["lon"];
	$url = "https://nominatim.openstreetmap.org/reverse?lat=$lat&lon=$lon&format=json";
	$ref = "https://" . getenv("DUMP") . ":8443";
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_USERAGENT => "Matcha/1.0",
		CURLOPT_HTTPHEADER => [
			"Accept: application/json",
			"Referer: $ref"
		]
	]);
	$res = curl_exec($ch);
	if ($res === false)
	{
		echo json_encode(["error" => curl_error($ch)]);
		exit;
	}
	curl_close($ch);
	$output = json_decode($res, true);
	$city = $output["address"]["city"] ?? $output["address"]["town"] ?? $output["address"]["village"] ?? "";
	$country = $output["address"]["country"] ?? "";
	echo json_encode(["city" => $city, "country" => $country]);
	exit;
?>
