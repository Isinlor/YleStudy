<?php
declare(strict_types=1);

use Symfony\Component\HttpClient\HttpClient;

require __DIR__ . '/vendor/autoload.php';

$httpClient = HttpClient::create();

$getUrl = fn($id) => "https://player.api.yle.fi/v1/preview/$id.json?language=fin&app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b";
$getResponse = fn($id) => json_decode($httpClient->request("GET", $getUrl($id))->getContent(), true);

$id = '1-72626171'; // Dec 23, 2025

echo "Testing episode: $id (Dec 23, 2025)\n\n";

try {
    $data = $getResponse($id);

    // Print full response
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

    // Check for specific fields
    echo "Has ongoing_ondemand: " . (isset($data["data"]["ongoing_ondemand"]) ? "YES" : "NO") . "\n";
    echo "Has subtitles: " . (isset($data["data"]["ongoing_ondemand"]["subtitles"][0]["uri"]) ? "YES" : "NO") . "\n";
    echo "Has next_episode: " . (isset($data["data"]["ongoing_ondemand"]["next_episode"]["id"]) ? "YES" : "NO") . "\n";
    echo "Has previous_episode: " . (isset($data["data"]["ongoing_ondemand"]["previous_episode"]["id"]) ? "YES" : "NO") . "\n";

    if (isset($data["data"]["ongoing_ondemand"]["previous_episode"]["id"])) {
        echo "Previous episode ID: " . $data["data"]["ongoing_ondemand"]["previous_episode"]["id"] . "\n";
    }

    if (isset($data["data"]["ongoing_ondemand"]["adobe"]["ns_st_ep"])) {
        echo "Date: " . $data["data"]["ongoing_ondemand"]["adobe"]["ns_st_ep"] . "\n";
    }

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
