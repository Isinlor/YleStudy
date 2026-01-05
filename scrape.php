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

$areenaAppId = 'areena-web-items';
$areenaAppKey = 'wlTs5D9OjIdeS9krPzRQR4I1PYVzoazN';

$playerHeaders = [
    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:146.0) Gecko/20100101 Firefox/146.0',
    'Accept' => '*/*',
    'Accept-Language' => 'en-GB,en;q=0.5',
    'Origin' => 'https://yle.fi',
    'Connection' => 'keep-alive',
    'Referer' => 'https://yle.fi/',
    'Cookie' => 'ylefi_localnews_location_v1={%22municipalityId%22:49%2C%22type%22:%22geo-ip%22}; yle_selva=17546371027345631559; AMCV_3D717B335952EB220A495D55%40AdobeOrg=1585540135%7CMCMID%7C69392746744498345458181459928054945412%7CMCOPTOUT-1767643436s%7CNONE%7CvVersion%7C4.4.0; userconsent=v2|; _sp_id.0d89=0f8c50d3-6b66-44a6-b10c-1f1b6bef0012.1754637247.7.1767636283.1767539819.6eda3fab-2858-4823-8ccd-ca443e303dda.fde32f7d-d471-4d59-ac8a-226a86f83dba.2a23a9d7-5707-4317-8f53-2ce9a12a8c27.1767636226949.18; AMCVS_3D717B335952EB220A495D55%40AdobeOrg=1; s_cc=true; _sp_ses.0d89=*',
];

$areenaHeaders = [
    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:146.0) Gecko/20100101 Firefox/146.0',
    'Accept' => '*/*',
    'Origin' => 'https://areena.yle.fi',
    'Referer' => 'https://areena.yle.fi/1-3233686',
];

$playerRequestOptions = ['headers' => $playerHeaders];
$areenaRequestOptions = ['headers' => $areenaHeaders];

$getUrl = fn($id) => "https://player.api.yle.fi/v1/preview/$id.json?app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b&language=fin&countryCode=FI&host=ylefi&isMobile=false&isPortabilityRegion=true&ssl=true";

$getResponse = fn($id) => json_decode($httpClient->request("GET", $getUrl($id), $playerRequestOptions)->getContent(), true);

$appendQuery = function(string $url, array $params): string {
    $parts = parse_url($url);
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    foreach ($params as $key => $value) {
        $query[$key] = $value;
    }
    $parts['query'] = http_build_query($query);
    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? '';
    $path = $parts['path'] ?? '';
    return sprintf('%s://%s%s?%s', $scheme, $host, $path, $parts['query']);
};

$resolveUrl = function(string $baseUrl, string $relativeUrl): string {
    if (parse_url($relativeUrl, PHP_URL_SCHEME)) {
        return $relativeUrl;
    }
    $baseParts = parse_url($baseUrl);
    $scheme = $baseParts['scheme'] ?? 'https';
    $host = $baseParts['host'] ?? '';
    $basePath = $baseParts['path'] ?? '/';
    $baseDir = rtrim(substr($basePath, 0, strrpos($basePath, '/') + 1), '/');
    $path = $relativeUrl[0] === '/' ? $relativeUrl : $baseDir . '/' . $relativeUrl;
    $segments = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);
            continue;
        }
        $segments[] = $segment;
    }
    return sprintf('%s://%s/%s', $scheme, $host, implode('/', $segments));
};

$getSubtitlePlaylistUrl = function(string $manifestUrl, string $manifestBody) use ($resolveUrl): string {
    foreach (explode("\n", $manifestBody) as $line) {
        if (str_starts_with($line, '#EXT-X-MEDIA') && str_contains($line, 'TYPE=SUBTITLES')) {
            if (preg_match('/URI=\"([^\"]+)\"/', $line, $matches)) {
                return $resolveUrl($manifestUrl, $matches[1]);
            }
        }
    }
    throw new RuntimeException('Subtitle playlist not found in manifest.');
};

$getSubtitlesFromManifest = function(string $manifestUrl) use ($httpClient, $playerRequestOptions, $resolveUrl, $getSubtitlePlaylistUrl): string {
    $manifestBody = $httpClient->request("GET", $manifestUrl, $playerRequestOptions)->getContent();
    $subtitlePlaylistUrl = $getSubtitlePlaylistUrl($manifestUrl, $manifestBody);
    $playlistBody = $httpClient->request("GET", $subtitlePlaylistUrl, $playerRequestOptions)->getContent();
    $segments = [];
    foreach (explode("\n", $playlistBody) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $segments[] = $resolveUrl($subtitlePlaylistUrl, $line);
    }
    if (!$segments) {
        throw new RuntimeException('No subtitle segments found.');
    }
    $combined = '';
    foreach ($segments as $index => $segmentUrl) {
        $segmentBody = $httpClient->request("GET", $segmentUrl, $playerRequestOptions)->getContent();
        if ($index > 0) {
            $segmentBody = preg_replace('/^WEBVTT.*?\\R\\R/s', '', $segmentBody, 1);
        }
        $combined .= $segmentBody;
        if (!str_ends_with($combined, "\n")) {
            $combined .= "\n";
        }
    }
    return $combined;
};

$getSubtitles = function(array $data) use ($httpClient, $playerRequestOptions, $getSubtitlesFromManifest): string {
    $subtitleUri = $data["data"]["ongoing_ondemand"]["subtitles"][0]["uri"] ?? '';
    if ($subtitleUri !== '') {
        return $httpClient->request("GET", $subtitleUri, $playerRequestOptions)->getContent();
    }
    $manifestUrl = $data["data"]["ongoing_ondemand"]["manifest_url"] ?? '';
    if ($manifestUrl === '') {
        throw new RuntimeException('No subtitle or manifest URL available.');
    }
    return $getSubtitlesFromManifest($manifestUrl);
};

$getDate = function(array $data, string $id): string {
    $startTime = $data["data"]["ongoing_ondemand"]["start_time"] ?? null;
    if ($startTime) {
        return (new DateTime($startTime))->format('Ymd');
    }
    return $data["data"]["ongoing_ondemand"]["adobe"]["ns_st_ep"] ?? $id;
};

$getProgramIds = function() use ($httpClient, $areenaRequestOptions, $appendQuery, $areenaAppId, $areenaAppKey): array {
    $html = $httpClient->request('GET', 'https://areena.yle.fi/1-3233686', $areenaRequestOptions)->getContent();
    if (!preg_match('~<script id="__NEXT_DATA__" type="application/json"[^>]*>(.*?)</script>~s', $html, $matches)) {
        throw new RuntimeException('Unable to locate Areena data payload.');
    }
    $payload = json_decode($matches[1], true);
    $listUrl = $payload['props']['pageProps']['view']['tabs'][0]['content'][0]['source']['uri'] ?? null;
    if (!$listUrl) {
        throw new RuntimeException('Unable to locate Areena list URL.');
    }
    $listUrl = $appendQuery($listUrl, ['app_id' => $areenaAppId, 'app_key' => $areenaAppKey]);
    $offset = 0;
    $ids = [];
    do {
        $pageUrl = $appendQuery($listUrl, ['offset' => $offset]);
        $page = json_decode($httpClient->request('GET', $pageUrl, $areenaRequestOptions)->getContent(), true);
        foreach ($page['data'] ?? [] as $item) {
            $uri = $item['pointer']['uri'] ?? '';
            $parts = parse_url($uri);
            $id = isset($parts['path']) ? ltrim($parts['path'], '/') : '';
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        $offset += $page['meta']['limit'] ?? 0;
        $count = $page['meta']['count'] ?? 0;
    } while ($offset < $count);
    return array_values(array_unique($ids));
};

$programIds = $getProgramIds();
foreach ($programIds as $id) {
    echo "Getting $id\n";
    $data = $getResponse($id);
    $date = $getDate($data, $id);
    if (substr($date, 0, 4) !== '2025') {
        continue;
    }
    try {
        $subtitles = $getSubtitles($data);
        file_put_contents("subtitles/{$date}.vtt", $subtitles);
        file_put_contents("subtitles/{$date}.txt", transform($subtitles));
    } catch (Throwable $e) {
        echo "Failed to get subtitles for $id\n";
    }
}
