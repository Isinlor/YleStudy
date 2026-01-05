<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

$httpClient = HttpClient::create([
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:146.0) Gecko/20100101 Firefox/146.0',
        'Accept' => '*/*',
        'Origin' => 'https://yle.fi',
        'Referer' => 'https://yle.fi/',
    ],
    'timeout' => 5,
]);

$getUrl = fn($id) => "https://player.api.yle.fi/v1/preview/$id.json?app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b&language=fin&countryCode=FI&host=ylefi&isMobile=false&isPortabilityRegion=true&ssl=true";

function getEpisode($httpClient, $getUrl, $id) {
    try {
        $response = $httpClient->request("GET", $getUrl("1-$id"));
        $data = json_decode($response->getContent(), true);
        $ondemand = $data['data']['ongoing_ondemand'] ?? null;

        if ($ondemand && !empty($ondemand['subtitles'])) {
            $title = $ondemand['title']['fin'] ?? 'unknown';
            if (stripos($title, 'selkosuomeksi') !== false) {
                $startTime = $ondemand['start_time'] ?? 'unknown';
                $date = substr($startTime, 0, 10);
                return ['id' => "1-$id", 'date' => $date, 'title' => $title, 'prev' => $ondemand['previous_episode']['id'] ?? null];
            }
        }
    } catch (Exception $e) {
        // Skip
    }
    return null;
}

echo "Binary search for first 2025 episode...\n";

// We know September 2023 episodes were around 64177000
// Let's binary search between that and 75000000
$low = 64177000;
$high = 75000000;
$first2025 = null;

while ($low <= $high) {
    $mid = intval(($low + $high) / 2);

    echo "Checking $mid (range: $low - $high)...\n";

    $ep = getEpisode($httpClient, $getUrl, $mid);

    if ($ep) {
        echo "  Found: {$ep['id']} - {$ep['date']}\n";

        if (strpos($ep['date'], '2025') === 0) {
            $first2025 = $ep;
            $high = $mid - 1;  // Search for earlier 2025 episodes
        } elseif ($ep['date'] < '2025-01-01') {
            $low = $mid + 1;  // Search higher
        } else {
            $high = $mid - 1;  // Search lower
        }
    } else {
        // Try a bit higher
        $low = $mid + 1000;
    }
}

if ($first2025) {
    echo "\n✓✓✓ Found a 2025 episode: {$first2025['id']} ✓✓✓\n";
    echo "Date: {$first2025['date']}\n";
    echo "Previous: " . ($first2025['prev'] ?? 'none') . "\n";

    // Try to find the FIRST 2025 episode by going backward
    if ($first2025['prev']) {
        $currentId = $first2025['prev'];
        echo "\nSearching backward for first 2025 episode...\n";

        for ($i = 0; $i < 50; $i++) {
            $ep = getEpisode($httpClient, $getUrl, substr($currentId, 2));
            if ($ep && strpos($ep['date'], '2025') === 0) {
                echo "  {$ep['id']} - {$ep['date']}\n";
                $first2025 = $ep;
                if ($ep['prev']) {
                    $currentId = $ep['prev'];
                } else {
                    break;
                }
            } else {
                break;
            }
        }
    }

    echo "\n✓✓✓ FIRST 2025 EPISODE: {$first2025['id']} - {$first2025['date']} ✓✓✓\n";
} else {
    echo "No 2025 episode found\n";
}
