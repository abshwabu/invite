#!/bin/bash
set -e

FONTS_DIR="$(pwd)/public/fonts"
mkdir -p "$FONTS_DIR"
rm -f "$FONTS_DIR/fonts.css"

FONTS=(
  "Noto+Sans+Ethiopic:wght@400;700"
  "Noto+Serif+Ethiopic:wght@400;700"
  "Cinzel:wght@400;700"
  "Playfair+Display:ital,wght@0,400;0,700;1,400"
  "Great+Vibes"
  "Montserrat:wght@400;600;700"
  "Oswald:wght@400;600"
  "Caveat:wght@400;700"
)

echo "/* Self-hosted fonts for Invite */" > "$FONTS_DIR/fonts.css"

for FONT in "${FONTS[@]}"; do
  echo "Processing $FONT..."
  CSS=$(curl -4 -s "https://fonts.googleapis.com/css2?family=$FONT&display=swap")
  
  # Parse each url(...)
  while read -r URL; do
    if [ -n "$URL" ]; then
      FILENAME=$(basename "$URL")
      if [ ! -f "$FONTS_DIR/$FILENAME" ]; then
        echo "  Downloading $FILENAME..."
        curl -4 -s -o "$FONTS_DIR/$FILENAME" "$URL"
      fi
      CSS="${CSS//$URL/\/fonts\/$FILENAME}"
    fi
  done < <(echo "$CSS" | grep -o 'https://fonts.gstatic.com/[^)]*')

  echo "$CSS" >> "$FONTS_DIR/fonts.css"
  echo "" >> "$FONTS_DIR/fonts.css"
done

echo "Fonts downloaded successfully into $FONTS_DIR"
ls -lh "$FONTS_DIR"
