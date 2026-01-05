#!/bin/bash

# Start from the found ID and search backward for episodes with subtitles from 2025
start_id=73200000

echo "Searching for 2025 episodes with subtitles..."

for offset in $(seq 0 50000 200000); do
  for delta in 0 100 500 1000 5000 10000; do
    id=$((start_id - offset - delta))

    if [ $id -lt 64000000 ]; then
      echo "Reached episodes from 2023, stopping"
      exit 1
    fi

    response=$(curl -s "https://player.api.yle.fi/v1/preview/1-$id.json?app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b&language=fin&countryCode=FI&host=ylefi&isMobile=false&isPortabilityRegion=true&ssl=true" -H 'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:146.0) Gecko/20100101 Firefox/146.0')

    if echo "$response" | grep -q '"subtitles":\[{'; then
      title=$(echo "$response" | python3 -c "import sys, json; data=json.load(sys.stdin); print(data.get('data', {}).get('ongoing_ondemand', {}).get('title', {}).get('fin', 'unknown'))" 2>/dev/null)
      date=$(echo "$response" | python3 -c "import sys, json; data=json.load(sys.stdin); print(data.get('data', {}).get('ongoing_ondemand', {}).get('start_time', 'unknown')[:10])" 2>/dev/null)

      echo "Found episode with subtitles: 1-$id (Title: $title, Date: $date)"

      if echo "$date" | grep -q "2025"; then
        if echo "$title" | grep -qi "selkosuomeksi"; then
          echo "✓✓✓ FOUND 2025 SELKOSUOMEKSI EPISODE WITH SUBTITLES: 1-$id ✓✓✓"
          echo "Date: $date"
          echo "Title: $title"
          exit 0
        fi
      fi
    fi
  done
done
