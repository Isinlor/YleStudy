<?php
declare(strict_types=1);

use Symfony\Component\HttpClient\HttpClient;

require __DIR__ . '/vendor/autoload.php';

$httpClient = HttpClient::create();

// Updated URL with additional parameters from yle-dl
$getUrl = fn($id) => "https://player.api.yle.fi/v1/preview/$id.json?language=fin&ssl=true&countryCode=FI&host=areenaylefi&app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b";
$getResponse = fn($id) => json_decode($httpClient->request("GET", $getUrl($id))->getContent(), true);

$id = '1-72626171'; // Dec 23, 2025

echo "Testing episode: $id (Dec 23, 2025) with additional parameters\n\n";

try {
    $data = $getResponse($id);

    echo "Response structure:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

    // Check for specific fields
    echo "Has ongoing_ondemand: " . (isset($data["data"]["ongoing_ondemand"]) ? "YES" : "NO") . "\n";

    if (isset($data["data"]["ongoing_ondemand"])) {
        echo "Has subtitles: " . (isset($data["data"]["ongoing_ondemand"]["subtitles"][0]["uri"]) ? "YES" : "NO") . "\n";
        if (isset($data["data"]["ongoing_ondemand"]["subtitles"][0]["uri"])) {
            echo "Subtitle URL: " . $data["data"]["ongoing_ondemand"]["subtitles"][0]["uri"] . "\n";
        }
        echo "Has previous_episode: " . (isset($data["data"]["ongoing_ondemand"]["previous_episode"]["id"]) ? "YES" : "NO") . "\n";
        if (isset($data["data"]["ongoing_ondemand"]["previous_episode"]["id"])) {
            echo "Previous episode ID: " . $data["data"]["ongoing_ondemand"]["previous_episode"]["id"] . "\n";
        }
        if (isset($data["data"]["ongoing_ondemand"]["adobe"]["ns_st_ep"])) {
            echo "Date: " . $data["data"]["ongoing_ondemand"]["adobe"]["ns_st_ep"] . "\n";
        }
    }

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
