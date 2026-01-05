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

echo "Searching for ANY available selkosuomeksi episodes with subtitles...\n\n";

// Try a wide range of recent IDs
for ($baseId = 75000000; $baseId > 64000000; $baseId -= 10000) {
    echo "Checking around ID: 1-$baseId...\n";

    for ($offset = 0; $offset < 1000; $offset += 100) {
        $id = $baseId + $offset;

        try {
            $response = $httpClient->request("GET", $getUrl("1-$id"));
            $data = json_decode($response->getContent(), true);

            $ondemand = $data['data']['ongoing_ondemand'] ?? null;

            if ($ondemand && !empty($ondemand['subtitles'])) {
                $title = $ondemand['title']['fin'] ?? 'unknown';

                if (stripos($title, 'selkosuomeksi') !== false) {
                    $startTime = $ondemand['start_time'] ?? 'unknown';
                    $date = substr($startTime, 0, 10);

                    echo "\n✓✓✓ FOUND AVAILABLE EPISODE WITH SUBTITLES! ✓✓✓\n";
                    echo "ID: 1-$id\n";
                    echo "Title: $title\n";
                    echo "Date: $date\n";
                    echo "Previous: " . ($ondemand['previous_episode']['id'] ?? 'none') . "\n";
                    echo "Next: " . ($ondemand['next_episode']['id'] ?? 'none') . "\n";

                    if (strpos($date, '2025') === 0) {
                        echo "\n🎉 THIS IS A 2025 EPISODE! 🎉\n";
                        exit(0);
                    } else {
                        echo "\n(This is from $date, continuing search for 2025...)\n\n";
                    }
                }
            }
        } catch (Exception $e) {
            // Skip
        }
    }

    if ($baseId % 100000 == 0) {
        echo "  ...still searching...\n";
    }
}

echo "\nNo available episodes found\n";
