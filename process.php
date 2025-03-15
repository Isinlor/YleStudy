<?php
declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;

require __DIR__ . '/vendor/autoload.php';

$filesystem = new Filesystem();

$client = HttpClient::create();

$sentencesPerFile = [];

$subtitlesDir = 'subtitles';
$files = scandir($subtitlesDir);
foreach ($files as $file) {
    if (strpos($file, '.txt') === false) {
        continue;
    }
    $contents = file_get_contents($subtitlesDir . '/' . $file);
    $sentencesPerFile[$file] = explode("\n", $contents);
}

$prompt = json_decode(file_get_contents('prompt.json'), true);
$process = function(string $sentence) use($client, $prompt) {

    $newPrompt = $prompt;

    $newPrompt['prompt'] .= $sentence;

    $response = $client->request('POST', 'https://api.openai.com/v1/completions', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer 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',
        ],
        'json' => $newPrompt,
    ]);

    $data = json_decode($response->getContent(), true);

    return $sentence . $data['choices'][0]['text'] . "\n";

};

foreach ($sentencesPerFile as $file => $sentences) {
    foreach ($sentences as $sentence) {
        file_put_contents("api/$file", $process($sentence) . "\n--- new line ---\n", FILE_APPEND);
    }
}