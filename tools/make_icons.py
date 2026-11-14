"""
Generate the PWA icon set for the Rent Management System.

The glyph is drawn programmatically (no external renderer needed) so the
icons stay reproducible and match the app's brand green from style.css.

Usage:
    python tools/make_icons.py

Output (assets/icons/):
    icon-192.png, icon-512.png            - normal icons (rounded square)
    icon-maskable-192.png,
    icon-maskable-512.png                - full-bleed, glyph inset to the 80% safe zone
    apple-touch-icon.png  (180x180)       - opaque square, iOS applies its own mask
    favicon-32.png
"""

import os
from PIL import Image, ImageDraw

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(BASE, "assets", "icons")

# Brand colours (from assets/css/style.css)
BRAND_TOP = (0, 124, 71)     # #007c47
BRAND_BOTTOM = (0, 56, 32)   # #003820
WHITE = (255, 255, 255)

SS = 2048  # supersampled canvas; everything is drawn in a 512 box then scaled


def gradient(size):
    """Vertical brand gradient."""
    img = Image.new("RGB", (size, size))
    d = ImageDraw.Draw(img)
    for y in range(size):
        t = y / max(size - 1, 1)
        d.line(
            [(0, y), (size, y)],
            fill=(
                round(BRAND_TOP[0] + (BRAND_BOTTOM[0] - BRAND_TOP[0]) * t),
                round(BRAND_TOP[1] + (BRAND_BOTTOM[1] - BRAND_TOP[1]) * t),
                round(BRAND_TOP[2] + (BRAND_BOTTOM[2] - BRAND_TOP[2]) * t),
            ),
        )
    return img


def draw_glyph(img, scale=1.0):
    """Two-block building with windows and a door, drawn in a 512 coordinate space.

    `scale` shrinks the glyph about the canvas centre (used to keep maskable
    icons inside the 80% safe zone).
    """
    d = ImageDraw.Draw(img)
    k = img.size[0] * scale / 512.0
    ox = oy = (img.size[0] - 512 * k) / 2

    # The two blocks are not symmetric, so nudge the whole glyph left to make
    # the visual mass sit on the canvas centre.
    dx, dy = -17, -6

    def box(x1, y1, x2, y2, fill=WHITE):
        d.rectangle(
            [
                ox + (x1 + dx) * k,
                oy + (y1 + dy) * k,
                ox + (x2 + dx) * k,
                oy + (y2 + dy) * k,
            ],
            fill=fill,
        )

    # Tall block (right) and short block (left), ground aligned at y=392.
    box(268, 132, 396, 392)
    box(150, 248, 268, 392)

    # Roof ridge on the tall block for a bit of depth.
    box(284, 112, 380, 132)

    # Windows: 3 x 4 on the tall block, 2 x 2 on the short block.
    win = 18
    gx, gy = 34, 44
    for col in range(3):
        for row in range(4):
            x = 296 + col * gx
            y = 168 + row * gy
            box(x, y, x + win, y + win, BRAND_BOTTOM)

    for col in range(2):
        for row in range(2):
            x = 178 + col * 44
            y = 282 + row * 44
            box(x, y, x + 20, y + 20, BRAND_BOTTOM)

    # Door in the tall block, sitting on the ground line.
    box(320, 324, 356, 392, BRAND_BOTTOM)


def rounded_icon(size_px, radius_ratio=0.225, scale=1.0, transparent_corners=True):
    bg = gradient(SS)
    draw_glyph(bg, scale=scale)

    if transparent_corners:
        mask = Image.new("L", (SS, SS), 0)
        ImageDraw.Draw(mask).rounded_rectangle(
            [0, 0, SS - 1, SS - 1], radius=int(SS * radius_ratio), fill=255
        )
        out = Image.new("RGBA", (SS, SS), (0, 0, 0, 0))
        out.paste(bg, (0, 0), mask)
    else:
        out = bg.convert("RGBA")

    return out.resize((size_px, size_px), Image.LANCZOS)


def main():
    os.makedirs(OUT, exist_ok=True)

    jobs = [
        ("icon-192.png", rounded_icon(192)),
        ("icon-512.png", rounded_icon(512)),
        # Maskable: full bleed square, glyph inside the 80% safe zone.
        ("icon-maskable-192.png", rounded_icon(192, scale=0.78, transparent_corners=False)),
        ("icon-maskable-512.png", rounded_icon(512, scale=0.78, transparent_corners=False)),
        # iOS applies its own corner mask, so ship an opaque square.
        ("apple-touch-icon.png", rounded_icon(180, scale=0.92, transparent_corners=False)),
        ("favicon-32.png", rounded_icon(32, radius_ratio=0.20)),
    ]

    for name, img in jobs:
        path = os.path.join(OUT, name)
        img.save(path, "PNG", optimize=True)
        print(f"{name:26} {img.size[0]}x{img.size[1]}  {os.path.getsize(path) // 1024} KB")


if __name__ == "__main__":
    main()
