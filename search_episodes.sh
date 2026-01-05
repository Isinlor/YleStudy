#!/bin/bash

# Try range around the given ID 72626171
for offset in 0 1 2 3 4 5 10 15 20 25 30 50 100 200 500 1000; do
  for sign in "+" "-"; do
    if [ "$sign" = "+" ]; then
      id=$((72626171 + offset))
    else
      id=$((72626171 - offset))
    fi

    echo "Testing 1-$id..."
    response=$(curl -s "https://player.api.yle.fi/v1/preview/1-$id.json?app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b&language=fin&countryCode=FI&host=ylefi&isMobile=false&isPortabilityRegion=true&ssl=true" -H 'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:146.0) Gecko/20100101 Firefox/146.0')

    if echo "$response" | grep -q "ongoing_ondemand"; then
      echo "✓✓✓ FOUND VALID: 1-$id ✓✓✓"
      echo "$response" | python3 -m json.tool | head -50
      exit 0
    fi
  done
done
