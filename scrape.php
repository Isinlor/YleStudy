<?php
declare(strict_types=1);

use Monolog\Logger;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;

require __DIR__ . '/vendor/autoload.php';
require "./transform.php";

$logger = new Logger('Logger');

$domCrawler = new Crawler();
$filesystem = new Filesystem();

$httpClient = HttpClient::create([
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:146.0) Gecko/20100101 Firefox/146.0',
        'Accept' => '*/*',
        'Accept-Language' => 'en-GB,en;q=0.5',
        'Origin' => 'https://yle.fi',
        'Referer' => 'https://yle.fi/',
    ]
]);

$getUrl = fn($id) => "https://player.api.yle.fi/v1/preview/$id.json?app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b&language=fin&countryCode=FI&host=ylefi&isMobile=false&isPortabilityRegion=true&ssl=true";

$getResponse = fn($id) => json_decode($httpClient->request("GET", $getUrl($id))->getContent(), true);

$getSubtitles = fn($data) => $httpClient->request("GET", $data["data"]["ongoing_ondemand"]["subtitles"][0]["uri"])->getContent();
$getNextEpisodeId = fn($data) => $data["data"]["ongoing_ondemand"]["next_episode"]["id"] ?? "";

$getDate = fn($data, $id) => $data["data"]["ongoing_ondemand"]["adobe"]["ns_st_ep"] ?? $id;

// Starting from 2025 episode
$id = '1-72626171';
$maxTries = 100; // Try up to 100 IDs if next_episode doesn't work
$triesCount = 0;
$baseId = 72626171;

while ($triesCount < $maxTries) {
    echo "Getting $id\n";

    try {
        $data = $getResponse($id);
        $ondemand = $data['data']['ongoing_ondemand'] ?? null;

        if (!$ondemand) {
            echo "Episode $id not available (status: " . array_keys($data['data'])[0] . ")\n";

            // Try next sequential ID
            $triesCount++;
            $baseId--;
            $id = "1-$baseId";
            continue;
        }

        // Check if it has subtitles
        if (empty($ondemand['subtitles'])) {
            echo "Episode $id has no subtitles\n";

            // Try to follow next_episode link
            $nextId = $getNextEpisodeId($data);
            if ($nextId) {
                $id = $nextId;
            } else {
                $baseId--;
                $id = "1-$baseId";
            }
            $triesCount++;
            continue;
        }

        // Get the episode date
        $episodeDate = $getDate($data, $id);
        echo "  → Episode date: $episodeDate\n";

        // Check if it's from 2025
        if (strpos($episodeDate, '2025') === 0) {
            echo "  ✓ Found 2025 episode!\n";

            try {
                $subtitles = $getSubtitles($data);
                file_put_contents("subtitles/{$episodeDate}.vtt", $subtitles);
                file_put_contents("subtitles/{$episodeDate}.txt", transform($subtitles));
                echo "  ✓ Saved subtitles for $episodeDate\n";
            } catch (Throwable $e) {
                echo "  ✗ Failed to get subtitles: " . $e->getMessage() . "\n";
            }
        }

        // Follow the next_episode link
        $nextId = $getNextEpisodeId($data);
        if ($nextId) {
            $id = $nextId;
            $triesCount = 0;  // Reset counter when we find a valid chain
        } else {
            echo "No more episodes in chain\n";
            break;
        }

    } catch (Throwable $e) {
        echo "Error getting $id: " . $e->getMessage() . "\n";
        $triesCount++;
        $baseId--;
        $id = "1-$baseId";
    }
}

echo "\nFinished scraping.\n";