<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

$httpClient = HttpClient::create([
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:146.0) Gecko/20100101 Firefox/146.0',
        'Accept' => '*/*',
        'Origin' => 'https://yle.fi',
        'Referer' => 'https://yle.fi/',
    ]
]);

$getUrl = fn($id) => "https://player.api.yle.fi/v1/preview/$id.json?app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b&language=fin&countryCode=FI&host=ylefi&isMobile=false&isPortabilityRegion=true&ssl=true";

echo "Searching for December 2025 episodes...\n";

// Start from September 2025 ID and search forward
$startId = 72626110;

for ($offset = -1000; $offset < 10000; $offset++) {
    $id = $startId + $offset;

    try {
        $response = $httpClient->request("GET", $getUrl("1-$id"));
        $data = json_decode($response->getContent(), true);

        $ondemand = $data['data']['ongoing_ondemand'] ?? null;

        if ($ondemand && !empty($ondemand['subtitles'])) {
            $title = $ondemand['title']['fin'] ?? 'unknown';
            $startTime = $ondemand['start_time'] ?? $ondemand['adobe']['ns_st_ep'] ?? 'unknown';
            $date = substr($startTime, 0, 10);
            $nextId = $ondemand['next_episode']['id'] ?? 'none';
            $prevId = $ondemand['previous_episode']['id'] ?? 'none';

            if (strpos($title, 'selkosuomeksi') !== false) {
                echo "Found: 1-$id | $date | Prev: $prevId | Next: $nextId\n";

                if (strpos($date, '2025-12') === 0 && $date >= '2025-12-23') {
                    echo "✓✓✓ FOUND DECEMBER 23+ EPISODE: 1-$id ✓✓✓\n";
                    echo "Date: $date\n";
                    echo "Previous: $prevId\n";
                    exit(0);
                }
            }
        }
    } catch (Exception $e) {
        // Skip
    }
}

echo "No December 2025 episode found\n";
