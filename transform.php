<?php
declare(strict_types=1);

function transform(string $subtitles) {

    $subtitles = preg_replace('/^WEBVTT.*(\r\n|\r|\n)/', '', $subtitles);
    $subtitles = preg_replace('/^X-TIMESTAMP-MAP=.*(\r\n|\r|\n)/m', '', $subtitles);
    // remove empty subtitle lines
    $subtitles = preg_replace('/[\r\n]+\.[\r\n]{0,2}/', "\n\n", $subtitles);
    // remove timing information
    $subtitles = preg_replace('/\d*(\r\n|\r|\n).*?-->.*?(\r\n|\r|\n)/', '', $subtitles);

    // join sentences split in subtitles in two for ease of reading
    $subtitles = preg_replace('/\ -(\r\n|\r|\n)/', ' ', $subtitles);

    // split subtitles into individual sentences
    $sentences = preg_split('/(\r\n\r\n|\r\r|\n\n)/', $subtitles);

    // per sentence remove new lines
    $sentences = array_map(function($line) {
        $parts = preg_split('/\r\n|\r|\n/', $line);
        $parts = array_values(array_filter(array_map('trim', $parts), fn($part) => $part !== ''));
        $parts = array_values(array_unique($parts));
        return implode(' ', $parts);
    }, $sentences);
    // trim spurious spaces that may have been introduced
    $sentences = array_map(fn($line) => preg_replace('/\s\s/', ' ', $line), $sentences);
    $sentences = array_map('trim', $sentences);
    $sentences = array_filter($sentences, fn($line) => $line !== '');

    $sentencesToRemove = [
        "Nyt uutisia helpolla suomen kielellä, hyvää päivää.",
        "Lisää uutisia selkosuomeksi: yle.fi/selkouutiset.",
        "Näkemiin."
    ];

    $sentences = array_filter($sentences, fn($line) => !in_array($line, $sentencesToRemove));
    $deduped = [];
    foreach ($sentences as $line) {
        $previous = $deduped[count($deduped) - 1] ?? null;
        if ($previous !== null && str_contains($previous, $line)) {
            continue;
        }
        $deduped[] = $line;
    }
    $sentences = array_values(array_unique($deduped));

    return implode("\n", $sentences);
}

if(isset($argv[1])) {
    echo transform(file_get_contents($argv[1]));
}
