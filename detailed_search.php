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

// Try specific ranges more carefully
$ranges = [
    [71000000, 71100000],
    [70000000, 70100000],
    [69000000, 69100000],
    [68000000, 68100000],
];

foreach ($ranges as [$start, $end]) {
    echo "Searching range $start to $end...\n";

    for ($id = $start; $id < $end; $id += 100) {
        try {
            $response = $httpClient->request("GET", $getUrl("1-$id"));
            $data = json_decode($response->getContent(), true);

            $ondemand = $data['data']['ongoing_ondemand'] ?? null;

            if ($ondemand && !empty($ondemand['subtitles'])) {
                $title = $ondemand['title']['fin'] ?? 'unknown';
                $startTime = $ondemand['start_time'] ?? 'unknown';
                $date = substr($startTime, 0, 10);
                $nextId = $ondemand['next_episode']['id'] ?? 'none';

                echo "Found: 1-$id | $title | $date | Next: $nextId\n";

                if (strpos($date, '2025') === 0 && stripos($title, 'selkosuomeksi') !== false) {
                    echo "\n✓✓✓ PERFECT MATCH: 1-$id ✓✓✓\n";
                    echo "Title: $title\n";
                    echo "Date: $date\n";
                    echo "Next: $nextId\n";
                    exit(0);
                }

                if (strpos($date, '2024') === 0 || strpos($date, '2025') === 0) {
                    echo "  → This is a 2024/2025 episode!\n";
                }
            }
        } catch (Exception $e) {
            // Skip
        }
    }
}

echo "\nNo perfect match found\n";
