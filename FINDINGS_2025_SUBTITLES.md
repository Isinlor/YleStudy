# 2025 Subtitle Scraping Investigation

## Summary
**2025 Selkouutiset episodes do not provide downloadable subtitle files through the Yle Player API.**

## Investigation Details

### Episodes Tested
- **Dec 23, 2025**: `1-72626171` - Status: `not_allowed` (PHP API), `region: World` but `subtitles: []` (yle-dl)
- **Feb 11, 2025**: `1-72626302` - Status: Expired, had `subtitles: []`

### API Behavior
- **2023 episodes**: Return `data.gone` (expired/removed)
- **2025 episodes**: Return `data.not_allowed` OR accessible but with empty `subtitles[]` array

### Web Page Analysis
- HTML shows: `"captionTracks":[{"type":"CaptionTrack","language":"fin","kind":"translation"}]`
- **Captions DO exist** but are embedded in the video player
- Not exposed as downloadable VTT files through API

## Changes Made to scrape.php

### 1. Updated API URL Parameters
```php
$getUrl = fn($id) => "https://player.api.yle.fi/v1/preview/$id.json?language=fin&ssl=true&countryCode=FI&host=areenaylefi&app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b";
```

### 2. Changed from next_episode to previous_episode
- Old: Started from first episode, went forward with `next_episode`
- New: Starts from most recent episode (Dec 23, 2025), goes backward with `previous_episode`

### 3. Added Error Handling
- Checks for `ongoing_ondemand` availability
- Handles `not_allowed` and other statuses
- Continues processing even if episodes fail
- Shows summary of processed vs skipped episodes

### 4. Added 2025 Filtering
- Only processes episodes from 2025
- Stops when reaching pre-2025 episodes

## Conclusion

**The original scraping method that worked in 2023 no longer works for 2025** because:

1. Yle changed subtitle delivery from downloadable VTT files to embedded captions
2. The Player Preview API returns empty `subtitles[]` arrays
3. Captions exist but require playing the video to access them

## Possible Solutions

1. **Browser automation** (Playwright/Selenium) to play video and extract captions
2. **Different API endpoint** (if Yle has one for caption access)
3. **Direct HLS stream parsing** to extract embedded subtitle tracks
4. **Manual download** from Yle Areena using browser tools
5. **Contact Yle** to request subtitle API access or file downloads

## 2025 Episode IDs Found

- 2025-12-23: `1-72626171`
- 2025-12-22: Found via https://yle.fi/a/74-20201137
- 2025-12-19: Found via https://yle.fi/a/74-20200859
- (More can be scraped from https://yle.fi/selkouutiset/kaikki-lahetykset)
