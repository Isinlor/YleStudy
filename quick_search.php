<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

// Quick search for 2025 episodes with subtitles
$httpClient = HttpClient::create([
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:146.0) Gecko/20100101 Firefox/146.0',
        'Accept' => '*/*',
        'Origin' => 'https://yle.fi',
        'Referer' => 'https://yle.fi/',
    ]
]);

$getUrl = fn($id) => "https://player.api.yle.fi/v1/preview/$id.json?app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b&language=fin&countryCode=FI&host=ylefi&isMobile=false&isPortabilityRegion=true&ssl=true";

// Search backward from a known 2025 ID
$startId = 73200000;

echo "Searching for episodes with subtitles from 2025...\n";

for ($offset = 0; $offset < 5000000; $offset += 1000) {
    $id = $startId - $offset;

    if ($id < 64000000) {
        echo "Reached old episodes, stopping\n";
        break;
    }

    try {
        $response = $httpClient->request("GET", $getUrl("1-$id"));
        $data = json_decode($response->getContent(), true);

        $ondemand = $data['data']['ongoing_ondemand'] ?? null;

        if ($ondemand && !empty($ondemand['subtitles'])) {
            $title = $ondemand['title']['fin'] ?? 'unknown';
            $startTime = $ondemand['start_time'] ?? 'unknown';
            $date = substr($startTime, 0, 10);

            if (strpos($date, '2025') === 0 && stripos($title, 'selkosuomeksi') !== false) {
                echo "✓✓✓ FOUND: 1-$id\n";
                echo "Title: $title\n";
                echo "Date: $date\n";
                echo "Next: " . ($ondemand['next_episode']['id'] ?? 'none') . "\n";
                exit(0);
            }

            if (strpos($date, '2025') === 0 || strpos($date, '2024-12') === 0) {
                echo "Found ($id): $title - $date\n";
            }
        }
    } catch (Exception $e) {
        // Skip invalid IDs
    }

    if ($offset % 10000 == 0) {
        echo "Checked up to offset $offset (ID: 1-$id)...\n";
    }
}

echo "No suitable episode found\n";
