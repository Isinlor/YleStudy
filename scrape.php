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

$httpClient = HttpClient::create();

// Updated URL with additional parameters for better access
$getUrl = fn($id) => "https://player.api.yle.fi/v1/preview/$id.json?language=fin&ssl=true&countryCode=FI&host=areenaylefi&app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b";

$getResponse = fn($id) => json_decode($httpClient->request("GET", $getUrl($id))->getContent(), true);

$getSubtitles = fn($data) => $httpClient->request("GET", $data["data"]["ongoing_ondemand"]["subtitles"][0]["uri"])->getContent();
$getPreviousEpisodeId = fn($data) => $data["data"]["ongoing_ondemand"]["previous_episode"]["id"] ?? "";

$getDate = fn($data, $id) => $data["data"]["ongoing_ondemand"]["adobe"]["ns_st_ep"] ?? $id;

// Start from December 23, 2025 (most recent available episode)
// Working backwards through previous episodes to scrape all of 2025
$id = '1-72626171'; // December 23, 2025
$processedCount = 0;
$skippedCount = 0;

do {
    echo "Processing episode $id (processed: $processedCount, skipped: $skippedCount)\n";

    try {
        $data = $getResponse($id);

        // Check if episode data is available
        if (!isset($data["data"]["ongoing_ondemand"])) {
            $status = array_key_first($data["data"] ?? []);
            echo "Episode $id is not available (status: $status)\n";
            $skippedCount++;

            // Try to continue with previous episode
            if (isset($data["data"]["ongoing_ondemand"])) {
                $id = $getPreviousEpisodeId($data);
            } else {
                break;
            }
            continue;
        }

        $date = $getDate($data, $id);
        echo "  Date: $date\n";

        // Check if this is a 2025 episode
        if (!str_starts_with($date, '2025')) {
            echo "Reached episodes before 2025. Stopping.\n";
            break;
        }

        // Check if episode has subtitles
        if (!isset($data["data"]["ongoing_ondemand"]["subtitles"][0]["uri"])) {
            echo "  No subtitles available for $id ($date)\n";
            $skippedCount++;
        } else {
            try {
                $subtitles = $getSubtitles($data);
                file_put_contents("subtitles/{$date}.vtt", $subtitles);
                file_put_contents("subtitles/{$date}.txt", transform($subtitles));
                echo "  ✓ Saved subtitles for $date\n";
                $processedCount++;
            } catch (Throwable $e) {
                echo "  Failed to download subtitles for $id: " . $e->getMessage() . "\n";
                $skippedCount++;
            }
        }

        // Move to previous episode
        $id = $getPreviousEpisodeId($data);

    } catch (Throwable $e) {
        echo "Error processing $id: " . $e->getMessage() . "\n";
        $skippedCount++;
        break;
    }

} while($id);

echo "\n" . "Summary:\n";
echo "Successfully scraped: $processedCount episodes\n";
echo "Skipped: $skippedCount episodes\n";