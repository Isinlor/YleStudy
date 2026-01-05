<?php
declare(strict_types=1);

use Symfony\Component\HttpClient\HttpClient;

require __DIR__ . '/vendor/autoload.php';

$httpClient = HttpClient::create();

$getUrl = fn($id) => "https://player.api.yle.fi/v1/preview/$id.json?language=fin&app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b";

// Try to find a 2025 episode ID
// Based on the pattern, 2023 was around 64177000, so 2025 might be around 73000000-75000000
// Let's try some IDs

// Start from last known 2023 episode and increment by much smaller amounts
// Last known is around 64177261, let's try forward from there
$startId = 64177261;
$testIds = [];

// First, try to find the next few episodes to understand the increment pattern
for ($i = 1; $i <= 500; $i++) {
    $testIds[] = '1-' . ($startId + $i);
    if ($i == 100) {
        echo "Testing every 1000 now...\n";
    }
    if ($i > 100) {
        $i += 999; // Jump by 1000 after first 100
    }
}

foreach ($testIds as $testId) {
    try {
        $response = $httpClient->request("GET", $getUrl($testId));
        $data = json_decode($response->getContent(), true);

        if (isset($data["data"]["ongoing_ondemand"]["adobe"]["ns_st_ep"])) {
            $date = $data["data"]["ongoing_ondemand"]["adobe"]["ns_st_ep"];
            echo "✓ Found: $testId -> Date: $date\n";

            if (str_starts_with($date, '2025')) {
                echo "\n🎉 SUCCESS! Found 2025 episode: $testId with date $date\n";
                exit(0);
            }
        }
    } catch (Throwable $e) {
        // Silently skip 404s
    }
}
