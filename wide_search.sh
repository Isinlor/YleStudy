#!/bin/bash

# Try systematic search through different ranges
for base in 64500000 65000000 65500000 66000000 66500000 67000000 67500000 68000000 68500000 69000000 69500000; do
  echo "Checking range starting at $base..."

  for offset in 0 100 1000 5000 10000 50000 100000; do
    id=$((base + offset))

    response=$(curl -s "https://player.api.yle.fi/v1/preview/1-$id.json?app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b&language=fin&countryCode=FI&host=ylefi&isMobile=false&isPortabilityRegion=true&ssl=true" -H 'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:146.0) Gecko/20100101 Firefox/146.0')

    if echo "$response" | grep -q "ongoing_ondemand"; then
      date=$(echo "$response" | python3 -c "import sys, json; data=json.load(sys.stdin); print(data.get('data', {}).get('ongoing_ondemand', {}).get('adobe', {}).get('ns_st_ep', 'unknown'))" 2>/dev/null)
      echo "✓ FOUND: 1-$id (Date: $date)"

      if echo "$date" | grep -q "2025"; then
        echo "✓✓✓ FOUND 2025 EPISODE: 1-$id ✓✓✓"
        echo "$response" | python3 -m json.tool | head -30
        exit 0
      fi
    fi
  done
done

echo "No 2025 episodes found in search range"
