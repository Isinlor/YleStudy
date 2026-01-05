#!/bin/bash

for id in 72626170 72626169 72626168 72500000 72000000 71500000 71000000 70500000 70000000; do
  echo "Testing 1-$id..."
  response=$(curl -s "https://player.api.yle.fi/v1/preview/1-$id.json?app_id=player_static_prod&app_key=8930d72170e48303cf5f3867780d549b&language=fin&countryCode=FI&host=ylefi&isMobile=false&isPortabilityRegion=true&ssl=true" -H 'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:146.0) Gecko/20100101 Firefox/146.0')

  if echo "$response" | grep -q "ongoing_ondemand"; then
    echo "✓ FOUND VALID: 1-$id"
    echo "$response" | python3 -m json.tool | grep -A2 "ns_st_ep"
    break
  elif echo "$response" | grep -q "not_allowed"; then
    echo "  Not allowed"
  elif echo "$response" | grep -q "gone"; then
    echo "  Gone"
  else
    echo "  Unknown response"
  fi
done
