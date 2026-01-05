<?php
declare(strict_types=1);

use Symfony\Component\HttpClient\HttpClient;

require __DIR__ . '/vendor/autoload.php';

$httpClient = HttpClient::create();

$getUrl = fn($id) => "https://player.api.yle.fi/v1/preview/$id.json?language=fin&app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b";
$getResponse = fn($id) => json_decode($httpClient->request("GET", $getUrl($id))->getContent(), true);
$getNextEpisodeId = fn($data) => $data["data"]["ongoing_ondemand"]["next_episode"]["id"] ?? "";
$getDate = fn($data, $id) => $data["data"]["ongoing_ondemand"]["adobe"]["ns_st_ep"] ?? $id;

$testIds = ['1-72626334', '1-72626321'];

foreach ($testIds as $id) {
    echo "\n=== Testing ID: $id ===\n";
    try {
        $data = $getResponse($id);
        $date = $getDate($data, $id);
        $nextId = $getNextEpisodeId($data);

        echo "Date: $date\n";
        echo "Next episode: $nextId\n";
        echo "Has subtitles: " . (isset($data["data"]["ongoing_ondemand"]["subtitles"][0]["uri"]) ? "YES" : "NO") . "\n";

    } catch (Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
