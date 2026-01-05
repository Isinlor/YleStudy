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

// Start from around September 2023 (last scraped date was 20230929)
// Try IDs around that range
$testIds = [64200000, 64250000, 64300000, 64350000, 64400000, 64450000, 64500000];

echo "Looking for a valid starting episode from late 2023...\n";

$startId = null;
foreach ($testIds as $testId) {
    try {
        $response = $httpClient->request("GET", $getUrl("1-$testId"));
        $data = json_decode($response->getContent(), true);

        $ondemand = $data['data']['ongoing_ondemand'] ?? null;

        if ($ondemand && !empty($ondemand['subtitles'])) {
            $title = $ondemand['title']['fin'] ?? 'unknown';
            if (stripos($title, 'selkosuomeksi') !== false) {
                $startTime = $ondemand['start_time'] ?? 'unknown';
                $date = substr($startTime, 0, 10);
                $nextId = $ondemand['next_episode']['id'] ?? null;

                echo "Found valid episode: 1-$testId - $date\n";

                if ($date >= '2023-09-29' && $nextId) {
                    $startId = "1-$testId";
                    break;
                }
            }
        }
    } catch (Exception $e) {
        // Skip
    }
}

if (!$startId) {
    echo "Could not find a valid starting episode\n";
    exit(1);
}

echo "\nStarting from: $startId\n";
echo "Following the chain forward to find 2025 episodes...\n\n";

$currentId = $startId;
$count = 0;
$found2025 = null;

while ($currentId && $count < 1000) {  // Limit to 1000 iterations
    try {
        $response = $httpClient->request("GET", $getUrl($currentId));
        $data = json_decode($response->getContent(), true);

        $ondemand = $data['data']['ongoing_ondemand'] ?? null;

        if ($ondemand) {
            $startTime = $ondemand['start_time'] ?? 'unknown';
            $date = substr($startTime, 0, 10);
            $nextId = $ondemand['next_episode']['id'] ?? null;

            if ($count % 10 == 0) {
                echo "Progress: $currentId - $date\n";
            }

            if (strpos($date, '2025') === 0) {
                echo "\n✓✓✓ FOUND 2025 EPISODE: $currentId - $date ✓✓✓\n";
                $found2025 = $currentId;
                break;
            }

            if ($nextId) {
                $currentId = $nextId;
                $count++;
            } else {
                echo "Reached end of chain at $date\n";
                break;
            }
        } else {
            echo "Invalid episode: $currentId\n";
            break;
        }
    } catch (Exception $e) {
        echo "Error at $currentId: " . $e->getMessage() . "\n";
        break;
    }
}

if ($found2025) {
    echo "\n✓ First 2025 episode in chain: $found2025\n";
} else {
    echo "\nDid not reach 2025 in $count episodes\n";
}
