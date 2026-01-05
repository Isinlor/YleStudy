<?php
declare(strict_types=1);

use Symfony\Component\HttpClient\HttpClient;

require __DIR__ . '/vendor/autoload.php';

$httpClient = HttpClient::create();

$getUrl = fn($id) => "https://player.api.yle.fi/v1/preview/$id.json?language=fin&app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b";
$getResponse = fn($id) => json_decode($httpClient->request("GET", $getUrl($id))->getContent(), true);
$getNextEpisodeId = fn($data) => $data["data"]["ongoing_ondemand"]["next_episode"]["id"] ?? "";
$getDate = fn($data, $id) => $data["data"]["ongoing_ondemand"]["adobe"]["ns_st_ep"] ?? $id;

// Start from last known 2023 episode
$id = '1-64177292'; // Try the one that was used previously
$count = 0;

echo "Following episode chain from $id to find first 2025 episode...\n";

do {
    $count++;

    try {
        $data = $getResponse($id);
        $date = $getDate($data, $id);

        if ($count % 10 == 0 || str_starts_with($date, '2024') || str_starts_with($date, '2025')) {
            echo "[$count] $id -> $date\n";
        }

        if (str_starts_with($date, '2025')) {
            echo "\n✓ SUCCESS! First 2025 episode found: $id with date $date\n";
            exit(0);
        }

        $id = $getNextEpisodeId($data);

        if (empty($id)) {
            echo "No more episodes in chain (last was $date)\n";
            break;
        }

        // Safety limit
        if ($count > 2000) {
            echo "Reached safety limit of 2000 episodes\n";
            break;
        }

    } catch (Throwable $e) {
        echo "Error at $id: " . $e->getMessage() . "\n";
        break;
    }

} while($id);
