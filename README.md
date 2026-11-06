# Dlight Giftshop website

Code for [dlightgiftshop.com](https://dlightgiftshop.com), a perfume and watch shop in Nairobi, Kenya, built on WordPress and WooCommerce.

| Folder | Installed on the server as | What it is |
|---|---|---|
| `theme-dev/` | `wp-content/themes/dlight-v4` | The storefront theme: home page, shop, product pages, cart, checkout, help pages, SEO and speed work. |
| `assistant-dev/` | `wp-content/plugins/dlight-assistant` | Dlight AI, the shopping assistant. It answers from the shop's own products and policies and uses no outside AI service. `tests/` is not uploaded to the server. |
| `tools/banners/` | not uploaded | Scripts used to make the home page banners from product photos. |
| `tools/mpesa-session-patch.py` | not uploaded | Patch for the M-Pesa payment plugin so it only starts a PHP session on checkout and payment requests. Run it again if that plugin is ever reinstalled or updated. |

## Notes for whoever works on this next

- Never commit database exports, `wp-config.php`, server backups or any password or key. `.gitignore` only allows the folders above.
- EasyWP's cache ignores `?ver=` on static files. The theme and Dlight AI serve CSS and JS under file names that change with the content (`uploads/dl-build/` and `uploads/dl-assistant/build/`), so updates reach visitors immediately.
- Dlight AI tests: `node assistant-dev/tests/run.js path/to/index.json`.
