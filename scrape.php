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

$getUrl = fn($id) => "https://player.api.yle.fi/v1/preview/$id.json?language=fin&app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b";

$getResponse = fn($id) => json_decode($httpClient->request("GET", $getUrl($id))->getContent(), true);

$getSubtitles = fn($data) => $httpClient->request("GET", $data["data"]["ongoing_ondemand"]["subtitles"][0]["uri"])->getContent();
$getNextEpisodeId = fn($data) => $data["data"]["ongoing_ondemand"]["next_episode"]["id"] ?? "";

$getDate = fn($data, $id) => $data["data"]["ongoing_ondemand"]["adobe"]["ns_st_ep"] ?? $id;

// $id = "1-64177138";
// $id = "1-64177292";
$id = '1-64177261';
do {

    echo "Getting $id\n";

    $data = $getResponse($id);

    try {

        $subtitles = $getSubtitles($data);
        file_put_contents("subtitles/{$getDate($data, $id)}.vtt", $subtitles);
        file_put_contents("subtitles/{$getDate($data, $id)}.txt", transform($subtitles));

    } catch (Throwable $e) {
        echo "Failed to get subtitles for $id\n";
    }

//    echo $getSubtitles($data) . "\n\n";

} while($id = $getNextEpisodeId($data));