<?php
declare(strict_types=1);

use Symfony\Component\HttpClient\HttpClient;

require __DIR__ . '/vendor/autoload.php';

$httpClient = HttpClient::create();

$getUrl = fn($id) => "https://player.api.yle.fi/v1/preview/$id.json?language=fin&app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b";

$testIds = ['1-64177138', '1-64177292', '1-64177261'];

foreach ($testIds as $id) {
    echo "\n=== Testing ID: $id ===\n";
    try {
        $response = $httpClient->request("GET", $getUrl($id));
        $data = json_decode($response->getContent(), true);
        echo json_encode($data, JSON_PRETTY_PRINT);
        echo "\n";
    } catch (Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
