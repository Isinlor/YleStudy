# YleStudy

YleStudy is a small toolkit for collecting Finnish-language subtitles from Yle Areena, transforming them into clean sentences, and optionally generating language-learning annotations via the OpenAI API. It also includes a starter Vue 3 frontend for exploring or extending the data.

## Repository layout

- `scrape.php` — Scrapes Yle Areena program IDs, downloads subtitle tracks, and writes outputs into `subtitles/`.
- `transform.php` — Converts WebVTT subtitles to cleaned, sentence-per-line text. Can run standalone from the CLI.
- `process.php` — Reads subtitle text and calls the OpenAI Completions API with `prompt.json` to generate annotations.
- `common.php` — Shared bootstrap for Symfony/Monolog dependencies.
- `subtitles/` — Downloaded subtitle files (`.vtt`) and transformed text (`.txt`).
- `api/` — Output from `process.php`, appended per subtitle file.
- `frontend/` — Vue 3 + Vite app scaffold.
- `prompt.json` — Prompt template for the OpenAI completion call.
- `new-subtitles*.txt`, `api-*.txt`, `corrections.txt` — Sample/generated datasets and manual corrections.

## Requirements

- PHP 8+
- Composer
- Node.js + npm (for the `frontend/` app)

## PHP setup

Install dependencies with Composer:

```sh
composer install
```

## Scraping subtitles

Run the scraper to fetch subtitles and write both the raw `.vtt` file and transformed `.txt` file into `subtitles/`:

```sh
php scrape.php
```

The scraper uses Yle Areena endpoints plus a manifest-based fallback for subtitle playlists.

## Transforming subtitles manually

`transform.php` can be used directly against any WebVTT file:

```sh
php transform.php subtitles/20230301.vtt
```

## Generating annotations with OpenAI

`process.php` reads each `.txt` file in `subtitles/` and appends OpenAI completion results into `api/<filename>.txt`:

```sh
php process.php
```

**Note:** `process.php` currently has a hardcoded OpenAI API key and uses `prompt.json` for the completion prompt. Replace the API key before running in your own environment.

## Frontend

The `frontend/` directory is a standard Vue 3 + Vite setup. To run it:

```sh
cd frontend
npm install
npm run dev
```

See `frontend/README.md` for the default Vite/Vue instructions.

## Data notes

- Subtitle and API outputs are stored in the repo for reference; adjust `.gitignore` if you want to exclude generated files.
- The scraper currently focuses on 2025 subtitles (see the date guard in `scrape.php`).

## License

See `LICENSE`.
