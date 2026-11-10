#!/usr/bin/env python3
"""
Generate complete vector SVG brand suite and raster assets for "Teri" (ጠሪ).
"""
import os
import shutil
import subprocess
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from PIL import Image, ImageFilter, ImageDraw

RESOURCES_BRAND = 'resources/brand'
PUBLIC_BRAND = 'public/brand'
PUBLIC_DIR = 'public'
DOCS_DESIGN = 'docs/design'

for d in [RESOURCES_BRAND, PUBLIC_BRAND, PUBLIC_DIR, DOCS_DESIGN]:
    os.makedirs(d, exist_ok=True)

# Brand Colors
COLOR_INK = '#1C2140'
COLOR_MESKEL = '#F4B400'
COLOR_PAPER = '#F6F5FA'
COLOR_MIST = '#E4E3EE'
COLOR_WHITE = '#FFFFFF'
COLOR_GREEN = '#1E5B47'
COLOR_MUTED = '#6B7280'
COLOR_MUTED_LIGHT = '#9CA3AF'

# Monogram vector path (ጠ on 100x100 canvas, baseline 78, arches apex 27.9, rounded foot terminals)
TE_MONOGRAM_PATH = (
    "M 18.0 52.5 "
    "L 18.0 75.8 Q 18.0 78.0 20.2 78.0 L 26.2 78.0 Q 28.4 78.0 28.4 75.8 L 28.4 52.8 "
    "Q 28.4 46.5 31.8 40.5 Q 36.8 36.0 43.2 36.0 Q 44.0 36.0 44.8 36.1 "
    "L 44.8 75.8 Q 44.8 78.0 47.0 78.0 L 53.0 78.0 Q 55.2 78.0 55.2 75.8 L 55.2 38.5 "
    "Q 57.5 36.8 61.5 36.0 Q 64.5 36.0 68.0 37.5 Q 71.6 40.5 71.6 48.0 "
    "L 71.6 75.8 Q 71.6 78.0 73.8 78.0 L 79.8 78.0 Q 82.0 78.0 82.0 75.8 L 82.0 45.6 "
    "Q 82.0 39.5 78.8 33.5 Q 74.0 27.9 66.0 27.9 Q 61.5 27.9 57.0 29.8 "
    "Q 55.2 30.8 55.2 31.2 L 55.2 27.9 L 43.0 27.9 "
    "Q 34.5 27.9 25.2 32.5 Q 18.0 41.5 18.0 52.5 Z"
)

def extract_text_path(font_path, text, font_size, x_offset=0, y_baseline=0):
    font = TTFont(font_path)
    cmap = font.getBestCmap()
    glyph_set = font.getGlyphSet()
    units_per_em = font['head'].unitsPerEm
    scale = font_size / units_per_em
    
    x = x_offset
    paths = []
    for ch in text:
        if ch == ' ':
            if ord(' ') in cmap:
                space_width = glyph_set[cmap[ord(' ')]].width * scale
            else:
                space_width = font_size * 0.35
            x += space_width
            continue
        code = ord(ch)
        if code not in cmap:
            continue
        gname = cmap[code]
        glyph = glyph_set[gname]
        pen = SVGPathPen(glyph_set)
        tpen = TransformPen(pen, (scale, 0, 0, -scale, x, y_baseline))
        glyph.draw(tpen)
        paths.append(pen.getCommands())
        x += glyph.width * scale
    
    return ' '.join(paths), x

# Pre-extract text paths
YOUNG_SERIF = 'public/fonts/young-serif-normal-400.ttf'
NOTO_SERIF_ETH = 'public/fonts/noto-serif-ethiopic-normal-700.ttf'
INSTRUMENT_SANS = 'public/fonts/instrument-sans-normal-500.ttf'

print("Extracting outlined typography paths...")

# -------------------------------------------------------------
# 1. MARK SVGs (100x100)
# -------------------------------------------------------------

def make_mark_svg(tile_fill, glyph_fill, dot_fill, stroke=None):
    stroke_attr = f' stroke="{stroke}" stroke-width="2"' if stroke else ''
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100">
  <rect width="100" height="100" rx="22" fill="{tile_fill}"{stroke_attr}/>
  <circle cx="50" cy="16.5" r="5" fill="{dot_fill}"/>
  <path d="{TE_MONOGRAM_PATH}" fill="{glyph_fill}"/>
</svg>'''.strip()

marks = {
    'teri-mark.svg': make_mark_svg(COLOR_INK, COLOR_PAPER, COLOR_MESKEL),
    'teri-mark-mono-ink.svg': make_mark_svg(COLOR_INK, COLOR_PAPER, COLOR_PAPER),
    'teri-mark-mono-white.svg': make_mark_svg('transparent', COLOR_WHITE, COLOR_WHITE, stroke=COLOR_WHITE),
    'teri-mark-reversed.svg': make_mark_svg(COLOR_PAPER, COLOR_INK, COLOR_MESKEL),
}

# -------------------------------------------------------------
# 2. HORIZONTAL LOCKUP SVGs (280x80)
# -------------------------------------------------------------
# Mark: 60x60 placed at X=10, Y=10
# Teri: Young Serif, font-size=40, X=84, Y_base=52
# Separator dot: cx=176, cy=42, r=2.5
# ጠሪ: Noto Serif Ethiopic, font-size=30, X=188, Y_base=50

teri_h_path, _ = extract_text_path(YOUNG_SERIF, 'Teri', 40, 84, 52)
te_word_h_path, _ = extract_text_path(NOTO_SERIF_ETH, 'ጠሪ', 30, 188, 50)

def make_horizontal_lockup_svg(tile_fill, glyph_fill, dot_fill, text_fill, sub_text_fill, stroke=None):
    stroke_attr = f' stroke="{stroke}" stroke-width="2"' if stroke else ''
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280 80" width="280" height="80">
  <!-- Mark Tile (60x60) -->
  <g transform="translate(10, 10) scale(0.6)">
    <rect width="100" height="100" rx="22" fill="{tile_fill}"{stroke_attr}/>
    <circle cx="50" cy="16.5" r="5" fill="{dot_fill}"/>
    <path d="{TE_MONOGRAM_PATH}" fill="{glyph_fill}"/>
  </g>
  <!-- Wordmark 'Teri' (Young Serif) -->
  <path d="{teri_h_path}" fill="{text_fill}"/>
  <!-- Separator Dot -->
  <circle cx="178" cy="42" r="2.5" fill="{dot_fill}"/>
  <!-- Amharic 'ጠሪ' (Noto Serif Ethiopic) -->
  <path d="{te_word_h_path}" fill="{sub_text_fill}"/>
</svg>'''.strip()

horizontal_lockups = {
    'teri-lockup-horizontal.svg': make_horizontal_lockup_svg(COLOR_INK, COLOR_PAPER, COLOR_MESKEL, COLOR_INK, COLOR_INK),
    'teri-lockup-horizontal-reversed.svg': make_horizontal_lockup_svg(COLOR_PAPER, COLOR_INK, COLOR_MESKEL, COLOR_WHITE, COLOR_MESKEL),
    'teri-lockup-horizontal-mono-ink.svg': make_horizontal_lockup_svg(COLOR_INK, COLOR_PAPER, COLOR_PAPER, COLOR_INK, COLOR_INK),
    'teri-lockup-horizontal-mono-white.svg': make_horizontal_lockup_svg('transparent', COLOR_WHITE, COLOR_WHITE, COLOR_WHITE, COLOR_WHITE, stroke=COLOR_WHITE),
}

# -------------------------------------------------------------
# 3. STACKED LOCKUP SVGs (200x180)
# -------------------------------------------------------------
# Mark: 70x70 centered at X=65, Y=14
# Teri: font-size=36, centered at X=62, Y_base=124
# ጠሪ: font-size=24, centered at X=81, Y_base=154

teri_s_path, teri_s_w = extract_text_path(YOUNG_SERIF, 'Teri', 36, 0, 0)
teri_s_x = (200 - teri_s_w) / 2
teri_s_path, _ = extract_text_path(YOUNG_SERIF, 'Teri', 36, teri_s_x, 124)

te_word_s_path, te_word_s_w = extract_text_path(NOTO_SERIF_ETH, 'ጠሪ', 24, 0, 0)
te_word_s_x = (200 - te_word_s_w) / 2
te_word_s_path, _ = extract_text_path(NOTO_SERIF_ETH, 'ጠሪ', 24, te_word_s_x, 154)

def make_stacked_lockup_svg(tile_fill, glyph_fill, dot_fill, text_fill, sub_text_fill, stroke=None):
    stroke_attr = f' stroke="{stroke}" stroke-width="2"' if stroke else ''
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 180" width="200" height="180">
  <!-- Mark Tile (70x70) Centered -->
  <g transform="translate(65, 14) scale(0.7)">
    <rect width="100" height="100" rx="22" fill="{tile_fill}"{stroke_attr}/>
    <circle cx="50" cy="16.5" r="5" fill="{dot_fill}"/>
    <path d="{TE_MONOGRAM_PATH}" fill="{glyph_fill}"/>
  </g>
  <!-- Wordmark 'Teri' -->
  <path d="{teri_s_path}" fill="{text_fill}"/>
  <!-- Amharic 'ጠሪ' -->
  <path d="{te_word_s_path}" fill="{sub_text_fill}"/>
</svg>'''.strip()

stacked_lockups = {
    'teri-lockup-stacked.svg': make_stacked_lockup_svg(COLOR_INK, COLOR_PAPER, COLOR_MESKEL, COLOR_INK, COLOR_INK),
    'teri-lockup-stacked-reversed.svg': make_stacked_lockup_svg(COLOR_PAPER, COLOR_INK, COLOR_MESKEL, COLOR_WHITE, COLOR_MESKEL),
    'teri-lockup-stacked-mono-ink.svg': make_stacked_lockup_svg(COLOR_INK, COLOR_PAPER, COLOR_PAPER, COLOR_INK, COLOR_INK),
    'teri-lockup-stacked-mono-white.svg': make_stacked_lockup_svg('transparent', COLOR_WHITE, COLOR_WHITE, COLOR_WHITE, COLOR_WHITE, stroke=COLOR_WHITE),
}

# -------------------------------------------------------------
# 4. LOCKUP WITH SLOGAN SVGs (380x116)
# -------------------------------------------------------------
# Mark: 64x64 at X=12, Y=14
# Teri: font-size=38 at X=92, Y_base=46
# ጠሪ: font-size=26 at X=178, Y_base=45
# Slogan 1: 'ጠሪ አክባሪ' font-size=18 at X=92, Y_base=78
# Slogan 2: 'Invite with honor' font-size=13 at X=92, Y_base=98

teri_slog_path, _ = extract_text_path(YOUNG_SERIF, 'Teri', 38, 92, 46)
te_word_slog_path, _ = extract_text_path(NOTO_SERIF_ETH, 'ጠሪ', 26, 178, 45)
slogan_am_path, _ = extract_text_path(NOTO_SERIF_ETH, 'ጠሪ አክባሪ', 18, 92, 78)
slogan_en_path, _ = extract_text_path(INSTRUMENT_SANS, 'Invite with honor', 13, 92, 98)

def make_slogan_lockup_svg(tile_fill, glyph_fill, dot_fill, text_fill, slogan_am_fill, slogan_en_fill, stroke=None):
    stroke_attr = f' stroke="{stroke}" stroke-width="2"' if stroke else ''
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 380 116" width="380" height="116">
  <!-- Mark Tile (64x64) -->
  <g transform="translate(12, 14) scale(0.64)">
    <rect width="100" height="100" rx="22" fill="{tile_fill}"{stroke_attr}/>
    <circle cx="50" cy="16.5" r="5" fill="{dot_fill}"/>
    <path d="{TE_MONOGRAM_PATH}" fill="{glyph_fill}"/>
  </g>
  <!-- Wordmark 'Teri' -->
  <path d="{teri_slog_path}" fill="{text_fill}"/>
  <!-- Separator Dot -->
  <circle cx="168" cy="38" r="2.2" fill="{dot_fill}"/>
  <!-- Amharic 'ጠሪ' -->
  <path d="{te_word_slog_path}" fill="{text_fill}"/>
  <!-- Slogan Amharic 'ጠሪ አክባሪ' -->
  <path d="{slogan_am_path}" fill="{slogan_am_fill}"/>
  <!-- Slogan English 'Invite with honor' -->
  <path d="{slogan_en_path}" fill="{slogan_en_fill}"/>
</svg>'''.strip()

slogan_lockups = {
    'teri-lockup-with-slogan.svg': make_slogan_lockup_svg(COLOR_INK, COLOR_PAPER, COLOR_MESKEL, COLOR_INK, COLOR_MESKEL, COLOR_MUTED),
    'teri-lockup-with-slogan-reversed.svg': make_slogan_lockup_svg(COLOR_PAPER, COLOR_INK, COLOR_MESKEL, COLOR_WHITE, COLOR_MESKEL, COLOR_MIST),
    'teri-lockup-with-slogan-mono-ink.svg': make_slogan_lockup_svg(COLOR_INK, COLOR_PAPER, COLOR_PAPER, COLOR_INK, COLOR_INK, COLOR_MUTED),
    'teri-lockup-with-slogan-mono-white.svg': make_slogan_lockup_svg('transparent', COLOR_WHITE, COLOR_WHITE, COLOR_WHITE, COLOR_WHITE, COLOR_MIST, stroke=COLOR_WHITE),
}

all_svgs = {}
all_svgs.update(marks)
all_svgs.update(horizontal_lockups)
all_svgs.update(stacked_lockups)
all_svgs.update(slogan_lockups)

for filename, content in all_svgs.items():
    res_path = os.path.join(RESOURCES_BRAND, filename)
    pub_path = os.path.join(PUBLIC_BRAND, filename)
    with open(res_path, 'w', encoding='utf-8') as f:
        f.write(content)
    with open(pub_path, 'w', encoding='utf-8') as f:
        f.write(content)

print(f"Generated {len(all_svgs)} brand SVGs in resources/brand and public/brand.")

# Favicon SVG (pointing to mark)
favicon_svg_content = marks['teri-mark.svg']
with open(os.path.join(PUBLIC_DIR, 'favicon.svg'), 'w', encoding='utf-8') as f:
    f.write(favicon_svg_content)
with open(os.path.join(PUBLIC_BRAND, 'favicon.svg'), 'w', encoding='utf-8') as f:
    f.write(favicon_svg_content)

# -------------------------------------------------------------
# 5. RASTER ASSET GENERATION
# -------------------------------------------------------------
mark_svg_path = os.path.join(PUBLIC_BRAND, 'teri-mark.svg')

# Favicon PNGs (16, 32, 48)
for size in [16, 32, 48]:
    out_png = os.path.join(PUBLIC_BRAND, f'favicon-{size}.png')
    subprocess.run(['rsvg-convert', '-w', str(size), '-h', str(size), mark_svg_path, '-o', out_png], check=True)

# Generate multi-layer favicon.ico (16, 32, 48) using ImageMagick
ico_sources = [os.path.join(PUBLIC_BRAND, f'favicon-{size}.png') for size in [16, 32, 48]]
subprocess.run(['convert'] + ico_sources + [os.path.join(PUBLIC_DIR, 'favicon.ico')], check=True)
shutil.copy(os.path.join(PUBLIC_DIR, 'favicon.ico'), os.path.join(PUBLIC_BRAND, 'favicon.ico'))
print("Generated favicon.ico (16/32/48) and favicon.svg.")

# Apple Touch Icon (180x180)
apple_icon = os.path.join(PUBLIC_DIR, 'apple-touch-icon.png')
subprocess.run(['rsvg-convert', '-w', '180', '-h', '180', mark_svg_path, '-o', apple_icon], check=True)
shutil.copy(apple_icon, os.path.join(PUBLIC_BRAND, 'apple-touch-icon.png'))
print("Generated apple-touch-icon.png (180x180).")

# PWA Standard Icons (192, 512)
for size in [192, 512]:
    pwa_out = os.path.join(PUBLIC_DIR, f'icon-{size}.png')
    subprocess.run(['rsvg-convert', '-w', str(size), '-h', str(size), mark_svg_path, '-o', pwa_out], check=True)
    shutil.copy(pwa_out, os.path.join(PUBLIC_BRAND, f'icon-{size}.png'))
print("Generated standard PWA icons (192, 512).")

# PWA Maskable Icons (192, 512) - glyph inside 80% safe zone
def make_maskable_svg(size):
    # Padding: 100x100 tile placed inside 125x125 viewBox (80% safe area)
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 125 125" width="{size}" height="{size}">
  <rect width="125" height="125" fill="{COLOR_INK}"/>
  <g transform="translate(12.5, 12.5)">
    <rect width="100" height="100" rx="22" fill="{COLOR_INK}"/>
    <circle cx="50" cy="16.5" r="5" fill="{COLOR_MESKEL}"/>
    <path d="{TE_MONOGRAM_PATH}" fill="{COLOR_PAPER}"/>
  </g>
</svg>'''.strip()

for size in [192, 512]:
    maskable_svg = os.path.join(PUBLIC_BRAND, f'icon-{size}-maskable.svg')
    with open(maskable_svg, 'w', encoding='utf-8') as f:
        f.write(make_maskable_svg(size))
    maskable_png = os.path.join(PUBLIC_DIR, f'icon-{size}-maskable.png')
    subprocess.run(['rsvg-convert', '-w', str(size), '-h', str(size), maskable_svg, '-o', maskable_png], check=True)
    shutil.copy(maskable_png, os.path.join(PUBLIC_BRAND, f'icon-{size}-maskable.png'))
print("Generated maskable PWA icons (192, 512).")

# Social Avatar (1080x1080)
avatar_svg = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1080 1080" width="1080" height="1080">
  <rect width="1080" height="1080" fill="{COLOR_INK}"/>
  <!-- Subtle decorative ambient ring -->
  <circle cx="540" cy="540" r="480" fill="none" stroke="{COLOR_PAPER}" stroke-width="2" opacity="0.08"/>
  <circle cx="540" cy="540" r="460" fill="none" stroke="{COLOR_MESKEL}" stroke-width="1.5" stroke-dasharray="8 12" opacity="0.25"/>
  <!-- Central Mark Tile (640x640) -->
  <g transform="translate(220, 220) scale(6.4)">
    <rect width="100" height="100" rx="22" fill="{COLOR_INK}" stroke="{COLOR_MESKEL}" stroke-width="1.5" stroke-opacity="0.3"/>
    <circle cx="50" cy="16.5" r="5" fill="{COLOR_MESKEL}"/>
    <path d="{TE_MONOGRAM_PATH}" fill="{COLOR_PAPER}"/>
  </g>
</svg>'''.strip()

avatar_svg_path = os.path.join(PUBLIC_BRAND, 'avatar.svg')
with open(avatar_svg_path, 'w', encoding='utf-8') as f:
    f.write(avatar_svg)
avatar_png = os.path.join(PUBLIC_DIR, 'avatar.png')
subprocess.run(['rsvg-convert', '-w', '1080', '-h', '1080', avatar_svg_path, '-o', avatar_png], check=True)
shutil.copy(avatar_png, os.path.join(PUBLIC_BRAND, 'avatar.png'))
print("Generated social avatar (1080x1080).")

# -------------------------------------------------------------
# 6. OPEN GRAPH IMAGE (1200x630)
# -------------------------------------------------------------
# Rich banner: Dark Ink background with warm ambient mesh, brand lockup + slogan on left,
# real template card previews collage on right.

print("Composing OG image (1200x630) with real template previews...")

# Render lockup with slogan in white/color for the OG background
og_lockup_svg = make_slogan_lockup_svg(COLOR_PAPER, COLOR_INK, COLOR_MESKEL, COLOR_WHITE, COLOR_MESKEL, COLOR_MIST)
og_lockup_temp = 'storage/app/temp_og_lockup.svg'
with open(og_lockup_temp, 'w', encoding='utf-8') as f:
    f.write(og_lockup_svg)

og_lockup_png = 'storage/app/temp_og_lockup.png'
subprocess.run(['rsvg-convert', '-w', '480', og_lockup_temp, '-o', og_lockup_png], check=True)

# Create 1200x630 canvas in Pillow
og_img = Image.new('RGBA', (1200, 630), (28, 33, 64, 255)) # #1C2140
draw = ImageDraw.Draw(og_img)

# Ambient gradient / decorative radial accents
# Meskel accent glow in top right
for r in range(350, 0, -25):
    alpha = int(18 * (1 - r / 350))
    draw.ellipse([850 - r, 120 - r, 850 + r, 120 + r], fill=(244, 180, 0, alpha))

# Paste brand lockup on the left
lockup_layer = Image.open(og_lockup_png).convert('RGBA')
og_img.paste(lockup_layer, (70, 220), lockup_layer)

# Add value proposition tag on left
tag_w, tag_h = 240, 34
tag_x, tag_y = 74, 160
draw.rounded_rectangle([tag_x, tag_y, tag_x + tag_w, tag_y + tag_h], radius=6, fill=(35, 42, 80, 255), outline=(244, 180, 0, 160), width=1)

# Template card collage on the right
sample_templates = [
    'storage/app/public/templates/wedding-royal-habesha-gold-wedding.png',
    'storage/app/public/templates/birthday-golden-confetti-birthday.png',
    'storage/app/public/templates/graduation-navy-gold-academic-diploma.png',
]

card_positions = [
    # (x, y, angle, scale_height)
    (680, 140, -6, 420),
    (890, 80, 5, 460),
    (790, 240, -1, 380),
]

for i, t_path in enumerate(sample_templates):
    if os.path.exists(t_path):
        card = Image.open(t_path).convert('RGBA')
        pos_x, pos_y, angle, target_h = card_positions[i]
        aspect = card.width / card.height
        target_w = int(target_h * aspect)
        card_resized = card.resize((target_w, target_h), Image.Resampling.LANCZOS)
        
        # Add a soft white border
        bordered = Image.new('RGBA', (target_w + 6, target_h + 6), (255, 255, 255, 180))
        bordered.paste(card_resized, (3, 3))
        
        # Rotate slightly
        rotated = bordered.rotate(angle, expand=True, resample=Image.Resampling.BICUBIC)
        
        # Shadow
        shadow = Image.new('RGBA', rotated.size, (0, 0, 0, 90))
        shadow_blur = shadow.filter(ImageFilter.GaussianBlur(10))
        
        og_img.paste(shadow_blur, (pos_x + 6, pos_y + 12), shadow_blur)
        og_img.paste(rotated, (pos_x, pos_y), rotated)

og_output_path = os.path.join(PUBLIC_DIR, 'og-image.png')
og_img.save(og_output_path, format='PNG', optimize=True)
shutil.copy(og_output_path, os.path.join(PUBLIC_BRAND, 'og-image.png'))
print("Generated og-image.png (1200x630) with real templates collage.")

# -------------------------------------------------------------
# 7. BRAND SHEET GENERATION (docs/design/brand-sheet.png)
# -------------------------------------------------------------
# Showing all variants at 16, 32, 64, and 256 px on light (#F6F5FA) and dark (#1C2140) backgrounds.

print("Rendering brand sheet to docs/design/brand-sheet.png...")

sheet_w, sheet_h = 1600, 1400
sheet = Image.new('RGBA', (sheet_w, sheet_h), (246, 245, 250, 255)) # Paper background
sdraw = ImageDraw.Draw(sheet)

# Header Bar
sdraw.rectangle([0, 0, sheet_w, 110], fill=(28, 33, 64, 255))

# Dark section in bottom half
sdraw.rectangle([0, 750, sheet_w, sheet_h], fill=(28, 33, 64, 255))

# Generate temporary renders of mark and lockups at 16, 32, 64, 256
render_specs = [
    ('mark_color', mark_svg_path),
    ('mark_mono_ink', os.path.join(PUBLIC_BRAND, 'teri-mark-mono-ink.svg')),
    ('mark_mono_white', os.path.join(PUBLIC_BRAND, 'teri-mark-mono-white.svg')),
    ('mark_reversed', os.path.join(PUBLIC_BRAND, 'teri-mark-reversed.svg')),
    ('lockup_h', os.path.join(PUBLIC_BRAND, 'teri-lockup-horizontal.svg')),
    ('lockup_h_rev', os.path.join(PUBLIC_BRAND, 'teri-lockup-horizontal-reversed.svg')),
    ('lockup_slog', os.path.join(PUBLIC_BRAND, 'teri-lockup-with-slogan.svg')),
    ('lockup_slog_rev', os.path.join(PUBLIC_BRAND, 'teri-lockup-with-slogan-reversed.svg')),
]

rendered_bitmaps = {}
for name, svg_file in render_specs:
    for sz in [16, 32, 64, 256]:
        tmp_png = f'storage/app/tmp_{name}_{sz}.png'
        if 'lockup' in name:
            subprocess.run(['rsvg-convert', '-h', str(sz), svg_file, '-o', tmp_png], check=True)
        else:
            subprocess.run(['rsvg-convert', '-w', str(sz), '-h', str(sz), svg_file, '-o', tmp_png], check=True)
        rendered_bitmaps[(name, sz)] = tmp_png

# Light section:
# Row 1: Mark (Color) at 256, 64, 32, 16
m256 = Image.open(rendered_bitmaps[('mark_color', 256)])
sheet.paste(m256, (80, 160), m256)

m64 = Image.open(rendered_bitmaps[('mark_color', 64)])
sheet.paste(m64, (380, 160), m64)

m32 = Image.open(rendered_bitmaps[('mark_color', 32)])
sheet.paste(m32, (480, 160), m32)

m16 = Image.open(rendered_bitmaps[('mark_color', 16)])
sheet.paste(m16, (550, 160), m16)

# Row 1 Lockups (Light)
lh = Image.open(rendered_bitmaps[('lockup_h', 64)])
sheet.paste(lh, (640, 160), lh)

lslog = Image.open(rendered_bitmaps[('lockup_slog', 64)])
sheet.paste(lslog, (640, 260), lslog)

# Row 2: Monochrome Ink on Light
m256_mono = Image.open(rendered_bitmaps[('mark_mono_ink', 256)])
sheet.paste(m256_mono, (80, 460), m256_mono)

m64_mono = Image.open(rendered_bitmaps[('mark_mono_ink', 64)])
sheet.paste(m64_mono, (380, 460), m64_mono)

m32_mono = Image.open(rendered_bitmaps[('mark_mono_ink', 32)])
sheet.paste(m32_mono, (480, 460), m32_mono)

m16_mono = Image.open(rendered_bitmaps[('mark_mono_ink', 16)])
sheet.paste(m16_mono, (550, 460), m16_mono)

# Dark section (Y >= 750):
# Row 3: Reversed / White on Dark
m256_rev = Image.open(rendered_bitmaps[('mark_reversed', 256)])
sheet.paste(m256_rev, (80, 820), m256_rev)

m64_rev = Image.open(rendered_bitmaps[('mark_reversed', 64)])
sheet.paste(m64_rev, (380, 820), m64_rev)

m32_rev = Image.open(rendered_bitmaps[('mark_reversed', 32)])
sheet.paste(m32_rev, (480, 820), m32_rev)

m16_rev = Image.open(rendered_bitmaps[('mark_reversed', 16)])
sheet.paste(m16_rev, (550, 820), m16_rev)

lh_rev = Image.open(rendered_bitmaps[('lockup_h_rev', 64)])
sheet.paste(lh_rev, (640, 820), lh_rev)

lslog_rev = Image.open(rendered_bitmaps[('lockup_slog_rev', 64)])
sheet.paste(lslog_rev, (640, 920), lslog_rev)

# Row 4: Mono White on Dark
m256_w = Image.open(rendered_bitmaps[('mark_mono_white', 256)])
sheet.paste(m256_w, (80, 1100), m256_w)

m64_w = Image.open(rendered_bitmaps[('mark_mono_white', 64)])
sheet.paste(m64_w, (380, 1100), m64_w)

m32_w = Image.open(rendered_bitmaps[('mark_mono_white', 32)])
sheet.paste(m32_w, (480, 1100), m32_w)

m16_w = Image.open(rendered_bitmaps[('mark_mono_white', 16)])
sheet.paste(m16_w, (550, 1100), m16_w)

sheet_out_path = os.path.join(DOCS_DESIGN, 'brand-sheet.png')
sheet.save(sheet_out_path, format='PNG', optimize=True)
print("Generated brand sheet:", sheet_out_path)

print("Brand asset generation completed successfully!")
