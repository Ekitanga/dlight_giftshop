"""Split each cut-out into solid objects, dropping leftover poster text."""
import os
import numpy as np
from PIL import Image, ImageDraw
from scipy import ndimage as ndi

os.makedirs('parts', exist_ok=True)
tiles = []
for f in sorted(os.listdir('cut')):
    if not f.endswith('.png'):
        continue
    pid = f[:-4]
    im = Image.open('cut/' + f).convert('RGBA')
    a = np.array(im)[:, :, 3]
    # Solid regions only: erosion removes thin text and fine marks.
    solid = ndi.binary_opening(a > 150, iterations=4)
    lab, n = ndi.label(solid)
    if not n:
        continue
    sizes = ndi.sum(solid, lab, range(1, n + 1))
    keep = [i + 1 for i, s in enumerate(sizes) if s > max(sizes) * 0.12]
    boxes = ndi.find_objects(lab)
    keep.sort(key=lambda k: boxes[k - 1][1].start)
    for j, k in enumerate(keep):
        # Grow the region back to the original edge, so fine edges stay intact.
        mask = ndi.binary_dilation(lab == k, iterations=7) & (a > 8)
        arr = np.array(im).copy()
        arr[:, :, 3] = np.where(mask, arr[:, :, 3], 0)
        ys, xs = np.where(mask)
        crop = Image.fromarray(arr).crop((xs.min(), ys.min(), xs.max() + 1, ys.max() + 1))
        name = f'{pid}-{j}'
        crop.save(f'parts/{name}.png')
        tiles.append((name, crop))

cols = 8
rows = (len(tiles) + cols - 1) // cols
sheet = Image.new('RGB', (cols * 190, rows * 220), (28, 22, 18))
d = ImageDraw.Draw(sheet)
for i, (name, crop) in enumerate(tiles):
    c = crop.copy()
    c.thumbnail((180, 190))
    sheet.paste(c, (i % cols * 190 + 5, i // cols * 220 + 5), c)
    d.text((i % cols * 190 + 6, i // cols * 220 + 202), f'{name} {crop.size[0]}x{crop.size[1]}', fill='white')
sheet.save('sheet_parts.jpg', quality=85)
print(len(tiles))
