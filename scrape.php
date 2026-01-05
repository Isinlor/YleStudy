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

// Headers from user
$headers = [
    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:146.0) Gecko/20100101 Firefox/146.0',
    'Accept' => '*/*',
    'Accept-Language' => 'en-GB,en;q=0.5',
    'Origin' => 'https://yle.fi',
    'Connection' => 'keep-alive',
    'Referer' => 'https://yle.fi/',
    'Cookie' => 'ylefi_localnews_location_v1={%22municipalityId%22:49%2C%22type%22:%22geo-ip%22}; yle_selva=17546371027345631559; AMCV_3D717B335952EB220A495D55%40AdobeOrg=1585540135%7CMCMID%7C69392746744498345458181459928054945412%7CMCOPTOUT-1767643436s%7CNONE%7CvVersion%7C4.4.0; userconsent=v2|; _sp_id.0d89=0f8c50d3-6b66-44a6-b10c-1f1b6bef0012.1754637247.7.1767636283.1767539819.6eda3fab-2858-4823-8ccd-ca443e303dda.fde32f7d-d471-4d59-ac8a-226a86f83dba.2a23a9d7-5707-4317-8f53-2ce9a12a8c27.1767636226949.18; AMCVS_3D717B335952EB220A495D55%40AdobeOrg=1; s_cc=true; _sp_ses.0d89=*',
    'Sec-Fetch-Dest' => 'empty',
    'Sec-Fetch-Mode' => 'cors',
    'Sec-Fetch-Site' => 'same-site',
    'TE' => 'trailers'
];

$getUrl = fn($id) => "https://player.api.yle.fi/v1/preview/$id.json?app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b&language=fin&countryCode=FI&host=ylefi&isMobile=false&isPortabilityRegion=true&ssl=true";

$getResponse = function($id) use ($httpClient, $getUrl, $headers) {
    try {
        $response = $httpClient->request("GET", $getUrl($id), ['headers' => $headers]);
        if ($response->getStatusCode() !== 200) return null;
        return json_decode($response->getContent(), true);
    } catch (Throwable $e) {
        return null;
    }
};

$resolveUrl = function($baseUrl, $relativeUrl) {
    $baseUrlPath = strtok($baseUrl, '?');
    $baseDir = substr($baseUrlPath, 0, strrpos($baseUrlPath, '/') + 1);
    $parts = explode('/', $relativeUrl);
    $baseParts = explode('/', $baseDir);
    array_pop($baseParts);

    foreach ($parts as $part) {
        if ($part === '..') {
            array_pop($baseParts);
        } elseif ($part !== '.') {
            $baseParts[] = $part;
        }
    }
    return implode('/', $baseParts);
};

$getSubtitles = function($data) use ($httpClient, $resolveUrl) {
    if (!empty($data["data"]["ongoing_ondemand"]["subtitles"])) {
        $uri = $data["data"]["ongoing_ondemand"]["subtitles"][0]["uri"];
        return $httpClient->request("GET", $uri)->getContent();
    }

    $manifestUrl = $data["data"]["ongoing_ondemand"]["manifest_url"] ?? null;
    if ($manifestUrl) {
        $manifestContent = $httpClient->request("GET", $manifestUrl)->getContent();

        if (preg_match('/TYPE=SUBTITLES.*URI="(.*)"/', $manifestContent, $matches)) {
            $subtitleRelUri = $matches[1];
            $subtitlePlaylistUrl = $resolveUrl($manifestUrl, $subtitleRelUri);

            $playlistContent = $httpClient->request("GET", $subtitlePlaylistUrl)->getContent();

            $lines = explode("\n", $playlistContent);
            $vttContent = "WEBVTT\n\n";

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) continue;

                $segmentUrl = $resolveUrl($subtitlePlaylistUrl, $line);

                try {
                    $segment = $httpClient->request("GET", $segmentUrl)->getContent();

                    // Extract cues (timestamp and following text)
                    // We look for the first timestamp line.
                    if (preg_match('/^(\d{2}:\d{2}:\d{2}\.\d{3} -->)/m', $segment, $matches, PREG_OFFSET_CAPTURE)) {
                        $start = $matches[0][1];
                        $vttContent .= substr($segment, $start) . "\n";
                    }
                } catch (Throwable $e) {
                    echo "Failed segment: $segmentUrl\n";
                }
            }
            return $vttContent;
        }
    }

    throw new Exception("No subtitles found");
};

$getDate = function($data, $id) {
    if (isset($data["data"]["ongoing_ondemand"]["start_time"])) {
        $date = substr($data["data"]["ongoing_ondemand"]["start_time"], 0, 10);
        return $date;
    }
    return $data["data"]["ongoing_ondemand"]["adobe"]["ns_st_ep"] ?? $id;
};

// Start ID for Dec 23, 2025
$id = '1-72626171';
$consecutiveFailures = 0;
// We'll check a few IDs forward to be sure no later episodes exist
$maxFailures = 10;

do {
    echo "Getting $id\n";
    $data = $getResponse($id);

    if ($data && !isset($data['data']['gone']) && !isset($data['data']['not_allowed']) && !isset($data['data']['not_found'])) {
        $consecutiveFailures = 0;
        try {
            $date = $getDate($data, $id);
            if (str_starts_with($date, '2025')) {
                echo "Found episode for $date\n";
                $subtitles = $getSubtitles($data);
                if (strlen($subtitles) > 100) {
                    file_put_contents("subtitles/{$date}.vtt", $subtitles);
                    file_put_contents("subtitles/{$date}.txt", transform($subtitles));
                    echo "Saved subtitles for $date\n";
                } else {
                     echo "Subtitles too short or empty for $id\n";
                }
            } else {
                echo "Date $date is not 2025. Stopping.\n";
                break;
            }
        } catch (Throwable $e) {
            echo "Failed to get subtitles for $id: " . $e->getMessage() . "\n";
        }

        $nextId = $data["data"]["ongoing_ondemand"]["next_episode"]["id"] ?? null;
        if (!$nextId) {
             $parts = explode('-', $id);
             $nextId = $parts[0] . '-' . (intval($parts[1]) + 1);
        }
        $id = $nextId;

    } else {
        $consecutiveFailures++;
        echo "Failed to get data for $id (Failures: $consecutiveFailures)\n";
        if ($consecutiveFailures >= $maxFailures) {
            echo "Too many failures. Stopping.\n";
            break;
        }
        $parts = explode('-', $id);
        $id = $parts[0] . '-' . (intval($parts[1]) + 1);
    }

} while($id);
