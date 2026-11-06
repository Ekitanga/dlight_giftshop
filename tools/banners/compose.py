"""Compose banners from real, in-stock Dlight products. Text is added in HTML, not baked in."""
import os
import random
import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageEnhance

OUT = r"C:\Projects\dlight_giftshop v3 wp\theme-dev\assets\img"
os.makedirs(OUT + r"\cats", exist_ok=True)


def part(name):
    return Image.open(f'parts/{name}.png').convert('RGBA')


def backdrop(w, h, top, bottom, glow, glow_xy, floor, seed=1, spark=(226, 188, 120), sparks=70):
    """Dark studio backdrop: gradient, light pool, soft out-of-focus lights, polished floor, vignette."""
    random.seed(seed)
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    t = (yy / h)[..., None]
    img = np.array(top, np.float32) * (1 - t) + np.array(bottom, np.float32) * t

    cx, cy = glow_xy[0] * w, glow_xy[1] * h
    rx, ry = 0.46 * w, 0.8 * h
    d = ((xx - cx) / rx) ** 2 + ((yy - cy) / ry) ** 2
    img += np.exp(-d * 1.6)[..., None] * np.array(glow, np.float32)

    # Out-of-focus lights.
    lay = Image.new('RGB', (w, h), (0, 0, 0))
    dr = ImageDraw.Draw(lay)
    fy = int(floor * h)
    for _ in range(sparks):
        x = random.uniform(0.25, 1.02) * w
        y = random.uniform(0, 0.8) * fy
        r = random.uniform(0.006, 0.03) * w
        k = random.uniform(0.08, 0.38)
        dr.ellipse((x - r, y - r, x + r, y + r), fill=tuple(int(c * k) for c in spark))
    lay = lay.filter(ImageFilter.GaussianBlur(max(5, w / 130)))
    img += np.array(lay, np.float32) * 0.42

    # Polished floor below the horizon.
    fl = np.clip((yy - fy) / max(1, h - fy), 0, 1)[..., None]
    img = img * (1 - 0.72 * (fl > 0) * (0.55 + 0.45 * fl))
    # Soft horizon glow.
    hz = np.exp(-(((yy - fy) / (0.012 * h + 2)) ** 2))[..., None]
    side = np.exp(-(((xx - cx) / (0.42 * w)) ** 2))[..., None]
    img += hz * side * np.array(spark, np.float32) * 0.34

    # Vignette and darker text side.
    v = 1 - 0.34 * np.clip(((xx - w / 2) / (w / 2)) ** 2 + ((yy - h / 2) / (h / 2)) ** 2, 0, 1)[..., None]
    img *= v
    img += np.random.default_rng(seed).normal(0, 1.1, img.shape)
    return Image.fromarray(np.clip(img, 0, 255).astype(np.uint8), 'RGB').convert('RGBA')


def pedestal(c, cx, floor_y, rx, ry, rim=(214, 178, 112), face=(34, 26, 21)):
    """Disabled: a flat ellipse looked artificial next to real product photos."""
    return
    w, h = c.size
    lay = Image.new('RGBA', (w, h), (0, 0, 0, 0))
    d = ImageDraw.Draw(lay)
    depth = int(ry * 0.62)
    d.rectangle((cx - rx, floor_y, cx + rx, floor_y + depth), fill=tuple(int(v * .55) for v in face) + (255,))
    d.ellipse((cx - rx, floor_y - ry + depth, cx + rx, floor_y + ry + depth), fill=tuple(int(v * .55) for v in face) + (255,))
    d.ellipse((cx - rx, floor_y - ry, cx + rx, floor_y + ry), fill=face + (255,))
    d.ellipse((cx - rx, floor_y - ry, cx + rx, floor_y + ry), outline=rim + (235,), width=max(2, int(rx / 70)))
    # Light falling across the top surface.
    sh = Image.new('L', (w, h), 0)
    ImageDraw.Draw(sh).ellipse((cx - rx * .72, floor_y - ry * .62, cx + rx * .72, floor_y + ry * .62), fill=52)
    sh = sh.filter(ImageFilter.GaussianBlur(rx / 7))
    lay.alpha_composite(Image.merge('RGBA', (*[Image.new('L', (w, h), v) for v in rim], sh)))
    c.alpha_composite(lay)


def place(c, name, height, cx, floor_y, reflect=0.3, sat=1.04, shadow=True):
    p = part(name)
    s = height / p.height
    p = p.resize((max(1, round(p.width * s)), round(height)), Image.LANCZOS)
    rgb, a = p.convert('RGB'), p.getchannel('A')
    rgb = ImageEnhance.Color(ImageEnhance.Contrast(rgb).enhance(1.04)).enhance(sat)
    p = rgb.copy()
    p.putalpha(a)
    x, y = int(cx - p.width / 2), int(floor_y - p.height)

    if shadow:
        lay = Image.new('L', c.size, 0)
        ImageDraw.Draw(lay).ellipse((x + p.width * .08, floor_y - height * .024, x + p.width * .92, floor_y + height * .03), fill=190)
        lay = lay.filter(ImageFilter.GaussianBlur(max(3, height / 44)))
        c.paste(Image.new('RGBA', c.size, (0, 0, 0, 255)), (0, 0), lay)

    if reflect:
        m = p.transpose(Image.FLIP_TOP_BOTTOM)
        grad = np.linspace(reflect * 255, 0, m.height, dtype=np.float32)
        grad = np.clip(grad * (np.linspace(1, 0, m.height) ** 1.5 + .02), 0, 255)
        alpha = (np.array(m.getchannel('A'), np.float32) / 255) * grad[:, None]
        m.putalpha(Image.fromarray(alpha.astype(np.uint8)))
        m = m.filter(ImageFilter.GaussianBlur(1.1))
        c.alpha_composite(m, (max(0, x), min(c.height - 1, int(floor_y) - 1))) if x >= 0 else None
    if x >= 0 and y >= 0:
        c.alpha_composite(p, (x, y))
    else:
        c.paste(p, (x, y), p)


def save(c, name, q=82):
    path = os.path.join(OUT, name)
    c.convert('RGB').save(path, quality=q, method=6)
    print(name, c.size, os.path.getsize(path) // 1024, 'KB')


GOLD = (46, 32, 15)
# ---------- Slide 1: best-selling perfumes ----------
for mobile in (False, True):
    w, h = (820, 900) if mobile else (2000, 560)
    fy = .86 if mobile else .86
    c = backdrop(w, h, (22, 15, 10), (8, 5, 4), GOLD, (.5 if mobile else .75, .55), fy, seed=3)
    F = int(h * fy)
    if mobile:
        pedestal(c, w // 2, F, int(w * .34), int(h * .024))
        place(c, '31724-1', h * .34, w * .13, F, .26)
        place(c, '32368-1', h * .32, w * .84, F, .26)
        place(c, '31706-0', h * .42, w * .32, F - 4, .26)
        place(c, '32243-0', h * .36, w * .68, F - 4, .26)
        place(c, '31709-0', h * .47, w * .50, F + 6, .28)
    else:
        pedestal(c, int(w * .68), F, int(w * .26), int(h * .05))
        place(c, '31724-1', h * 0.517, 1050, F, .26)
        place(c, '32243-0', h * 0.592, 1252, F - 6, .28)
        place(c, '31709-0', h * 0.733, 1472, F + 4, .3)
        place(c, '31706-0', h * 0.677, 1702, F, .3)
        place(c, '32368-1', h * 0.583, 1913, F - 2, .28)
    save(c, f"slide-perfume-{'phone' if mobile else 'wide'}.webp", 80)

# ---------- Slide 2: men's watches ----------
for mobile in (False, True):
    w, h = (820, 900) if mobile else (2000, 560)
    fy = .88
    c = backdrop(w, h, (13, 14, 17), (5, 5, 6), (30, 27, 20), (.5 if mobile else .75, .5), fy, seed=11, spark=(198, 170, 120), sparks=55)
    F = int(h * fy)
    if mobile:
        place(c, '24431-0', h * .40, w * .16, F, .22)
        place(c, '28904-0', h * .42, w * .84, F, .22)
        place(c, '32585-0', h * .50, w * .34, F + 3, .24)
        place(c, '32573-0', h * .48, w * .66, F + 3, .24)
        place(c, '32197-0', h * .54, w * .50, F + 8, .26)
    else:
        place(c, '24431-0', h * 0.639, 1078, F, .22)
        place(c, '32585-0', h * 0.733, 1280, F + 3, .24)
        place(c, '32197-0', h * 0.790, 1518, F + 6, .26)
        place(c, '32573-0', h * 0.714, 1757, F + 3, .24)
        place(c, '28904-0', h * 0.620, 1950, F, .22)
    save(c, f"slide-watch-{'phone' if mobile else 'wide'}.webp", 80)

# ---------- Slide 3: for her ----------
for mobile in (False, True):
    w, h = (820, 900) if mobile else (2000, 560)
    fy = .87
    c = backdrop(w, h, (45, 22, 26), (15, 7, 9), (62, 32, 26), (.5 if mobile else .75, .5), fy, seed=21, spark=(236, 186, 150), sparks=75)
    F = int(h * fy)
    if mobile:
        pedestal(c, w // 2, F, int(w * .32), int(h * .024), rim=(226, 180, 140), face=(52, 28, 30))
        place(c, '28746-0', h * .42, w * .14, F, .24)
        place(c, '24475-0', h * .42, w * .86, F, .24)
        place(c, '32137-0', h * .50, w * .33, F, .24)
        place(c, '28832-0', h * .50, w * .67, F, .24)
        place(c, '32368-1', h * .44, w * .50, F + 6, .28)
    else:
        pedestal(c, int(w * .655), F, int(w * .11), int(h * .045), rim=(226, 180, 140), face=(52, 28, 30))
        place(c, '28746-0', h * 0.639, 1078, F, .24)
        place(c, '24475-0', h * 0.677, 1261, F, .24)
        place(c, '32368-1', h * 0.620, 1509, F, .3)
        place(c, '28832-0', h * 0.733, 1739, F, .24)
        place(c, '32137-0', h * 0.696, 1950, F, .24)
    save(c, f"slide-her-{'phone' if mobile else 'wide'}.webp", 80)

# ---------- Offer banners ----------
w, h = 960, 400
c = backdrop(w, h, (40, 18, 13), (12, 6, 5), (54, 28, 12), (.72, .5), .86, seed=31, spark=(230, 160, 90), sparks=40)
F = int(h * .86)
pedestal(c, int(w * .74), F, int(w * .22), int(h * .05), rim=(214, 170, 100), face=(46, 24, 18))
place(c, '32247-0', h * .74, w * .74, F, .3)
save(c, 'offer-arabic.webp', 80)

c = backdrop(w, h, (13, 19, 22), (5, 7, 8), (20, 30, 34), (.72, .5), .86, seed=41, spark=(190, 190, 170), sparks=40)
F = int(h * .86)
place(c, '29198-0', h * .66, w * .57, F, .24)
place(c, '29030-0', h * .74, w * .74, F, .24)
place(c, '28904-0', h * .68, w * .90, F, .24)
save(c, 'offer-casio.webp', 80)

# ---------- Category images: transparent cut-outs ----------
cats = {
    'women-perfumes': '31759-0', 'men-perfumes': '31709-0', 'tester-perfumes': '31733-0',
    'original-perfumes': '32243-0', 'arabic-perfumes': '32247-0', 'men-watches': '32197-0',
    'women-watches': '28832-0', 'brands': '32585-0',
}
for key, name in cats.items():
    p = part(name)
    p.thumbnail((440, 440), Image.LANCZOS)
    path = os.path.join(OUT, 'cats', key + '.webp')
    p.save(path, quality=84, method=6)
    print('cat', key, p.size, os.path.getsize(path) // 1024, 'KB')
