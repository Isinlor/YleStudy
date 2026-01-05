#!/bin/bash

# Try to find episodes from late 2024/early 2025 by sampling
echo "Searching for recent episodes..."

for base in 70000000 70500000 71000000 71500000 72000000 72500000 73000000 73500000 74000000; do
  for offset in 0 100 500 1000 5000 10000 50000 100000 200000 300000 400000 500000; do
    id=$((base + offset))

    response=$(curl -s "https://player.api.yle.fi/v1/preview/1-$id.json?app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b&language=fin&countryCode=FI&host=ylefi&isMobile=false&isPortabilityRegion=true&ssl=true" -H 'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:146.0) Gecko/20100101 Firefox/146.0')

    if echo "$response" | grep -q "ongoing_ondemand"; then
      date=$(echo "$response" | python3 -c "import sys, json; data=json.load(sys.stdin); print(data.get('data', {}).get('ongoing_ondemand', {}).get('adobe', {}).get('ns_st_ep', 'unknown'))" 2>/dev/null)
      echo "Found valid episode: 1-$id (Date: $date)"

      year=$(echo "$date" | cut -c1-4)
      if [ "$year" = "2024" ] || [ "$year" = "2025" ]; then
        echo "✓✓✓ FOUND RECENT EPISODE: 1-$id (Date: $date) ✓✓✓"
        exit 0
      fi
    fi
  done
done
