<?php
declare(strict_types=1);

use Symfony\Component\HttpClient\HttpClient;

require __DIR__ . '/vendor/autoload.php';
require "./transform.php";

$httpClient = HttpClient::create(['timeout' => 30]);

/**
 * Get HLS manifest URL from yle-dl for a given episode URL
 */
function getManifestUrl(string $episodeUrl): ?string {
    $output = [];
    $returnCode = 0;
    exec("yle-dl --showurl " . escapeshellarg($episodeUrl) . " 2>&1", $output, $returnCode);

    foreach ($output as $line) {
        if (preg_match('#(https://[^:]+\.m3u8\?[^:]+)#', $line, $matches)) {
            return $matches[1];
        }
    }

    return null;
}

/**
 * Parse master manifest and extract subtitle playlist URL
 */
function getSubtitlePlaylistUrl(string $masterManifestContent, string $baseUrl): ?string {
    if (preg_match('#TYPE=SUBTITLES.*?URI="([^"]+)"#', $masterManifestContent, $matches)) {
        $relativeUrl = $matches[1];
        // Resolve relative URL
        return resolveUrl($baseUrl, $relativeUrl);
    }
    return null;
}

/**
 * Resolve a relative URL against a base URL
 */
function resolveUrl(string $baseUrl, string $relativeUrl): string {
    if (preg_match('#^https?://#', $relativeUrl)) {
        return $relativeUrl; // Already absolute
    }

    $baseUrlParts = parse_url($baseUrl);
    $basePath = $baseUrlParts['path'] ?? '/';

    // Count ../ occurrences
    $upLevels = substr_count($relativeUrl, '../');
    $relativeUrl = str_replace('../', '', $relativeUrl);

    // Remove file and go up directories
    $pathParts = explode('/', trim($basePath, '/'));
    array_pop($pathParts); // Remove filename
    $pathParts = array_slice($pathParts, 0, count($pathParts) - $upLevels);

    $pathParts[] = $relativeUrl;

    return $baseUrlParts['scheme'] . '://' . $baseUrlParts['host'] . '/' . implode('/', $pathParts);
}

/**
 * Parse subtitle playlist and extract all VTT segment URLs
 */
function getVttSegmentUrls(string $playlistContent, string $baseUrl): array {
    $segments = [];
    $lines = explode("\n", $playlistContent);

    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && !str_starts_with($line, '#')) {
            $segments[] = resolveUrl($baseUrl, $line);
        }
    }

    return $segments;
}

/**
 * Download and merge VTT segments
 */
function downloadAndMergeSubtitles($httpClient, array $segmentUrls): string {
    $allCues = [];

    foreach ($segmentUrls as $i => $url) {
        try {
            $response = $httpClient->request('GET', $url, [
                'headers' => ['Referer' => 'https://yle.fi/']
            ]);
            $content = $response->getContent();

            // Remove WEBVTT header
            $content = preg_replace('/^WEBVTT.*?\n\n/s', '', $content);

            // Extract cues (timestamp + text blocks)
            preg_match_all('/(\d+\n)?(\d{2}:\d{2}:\d{2}\.\d{3}\s+-->\s+\d{2}:\d{2}:\d{2}\.\d{3})\n((?:.*\n)*?)(?=\n\n|\z)/s', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $timestamp = $match[2];
                $text = trim($match[3]);

                // Skip empty cues (just dots)
                if ($text !== '.' && !empty($text)) {
                    // Normalize text for deduplication (remove extra whitespace)
                    $normalizedText = preg_replace('/\s+/', ' ', $text);
                    $key = $timestamp . '|' . $normalizedText;
                    $allCues[$key] = ['timestamp' => $timestamp, 'text' => $text];
                }
            }

            if (($i + 1) % 10 == 0) {
                echo "  Downloaded segment " . ($i + 1) . "/" . count($segmentUrls) . "\n";
            }

        } catch (Throwable $e) {
            echo "  Warning: Failed to download segment $i: " . $e->getMessage() . "\n";
        }
    }

    // Build merged VTT with deduplicated cues
    $mergedVtt = "WEBVTT\n\n";
    $cueNumber = 1;

    foreach ($allCues as $cue) {
        $mergedVtt .= $cueNumber . "\n";
        $mergedVtt .= $cue['timestamp'] . "\n";
        $mergedVtt .= $cue['text'] . "\n\n";
        $cueNumber++;
    }

    return $mergedVtt;
}

/**
 * Scrape subtitles for a single episode
 */
function scrapeEpisode($httpClient, string $episodeId, string $episodeUrl): ?array {
    echo "Processing episode $episodeId\n";

    // Get HLS manifest URL
    echo "  Getting manifest URL...\n";
    $manifestUrl = getManifestUrl($episodeUrl);
    if (!$manifestUrl) {
        echo "  Failed to get manifest URL\n";
        return null;
    }

    // Download master manifest
    echo "  Downloading master manifest...\n";
    try {
        $masterManifest = $httpClient->request('GET', $manifestUrl, [
            'headers' => ['Referer' => 'https://yle.fi/']
        ])->getContent();
    } catch (Throwable $e) {
        echo "  Failed to download master manifest: " . $e->getMessage() . "\n";
        return null;
    }

    // Extract subtitle playlist URL
    $subtitlePlaylistUrl = getSubtitlePlaylistUrl($masterManifest, $manifestUrl);
    if (!$subtitlePlaylistUrl) {
        echo "  No subtitles found in manifest\n";
        return null;
    }

    echo "  Downloading subtitle playlist...\n";
    try {
        $subtitlePlaylist = $httpClient->request('GET', $subtitlePlaylistUrl, [
            'headers' => ['Referer' => 'https://yle.fi/']
        ])->getContent();
    } catch (Throwable $e) {
        echo "  Failed to download subtitle playlist: " . $e->getMessage() . "\n";
        return null;
    }

    // Extract VTT segment URLs
    $segmentUrls = getVttSegmentUrls($subtitlePlaylist, $subtitlePlaylistUrl);
    echo "  Found " . count($segmentUrls) . " subtitle segments\n";

    if (empty($segmentUrls)) {
        echo "  No subtitle segments found\n";
        return null;
    }

    // Download and merge segments
    echo "  Downloading segments...\n";
    $mergedVtt = downloadAndMergeSubtitles($httpClient, $segmentUrls);

    // Extract date from episode using yle-dl metadata
    $metadata = shell_exec("yle-dl --showmetadata " . escapeshellarg($episodeUrl) . " 2>&1");
    $date = null;
    if ($metadata && preg_match('/"title":\s*"[^"]*(\d{4}-\d{2}-\d{2})[^"]*"/', $metadata, $matches)) {
        $date = str_replace('-', '', $matches[1]); // Convert to YYYYMMDD format
    }

    if (!$date) {
        $date = $episodeId; // Fallback to episode ID
    }

    return [
        'date' => $date,
        'vtt' => $mergedVtt,
        'txt' => transform($mergedVtt)
    ];
}

// Main execution
echo "=== Yle Selkouutiset Subtitle Scraper (2025) ===\n\n";

// For now, test with the December 23, 2025 episode
$testEpisodes = [
    ['id' => '1-72626171', 'url' => 'https://yle.fi/a/74-20201299'], // Dec 23, 2025
];

$processedCount = 0;
$skippedCount = 0;

foreach ($testEpisodes as $episode) {
    try {
        $result = scrapeEpisode($httpClient, $episode['id'], $episode['url']);

        if ($result) {
            // Save files
            file_put_contents("subtitles/{$result['date']}.vtt", $result['vtt']);
            file_put_contents("subtitles/{$result['date']}.txt", $result['txt']);
            echo "  ✓ Saved subtitles for {$result['date']}\n\n";
            $processedCount++;
        } else {
            echo "  ✗ Failed to process episode\n\n";
            $skippedCount++;
        }

    } catch (Throwable $e) {
        echo "  Error: " . $e->getMessage() . "\n\n";
        $skippedCount++;
    }
}

echo "Summary:\n";
echo "Successfully scraped: $processedCount episodes\n";
echo "Skipped: $skippedCount episodes\n";
