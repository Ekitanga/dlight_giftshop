"""Limit the M-Pesa plugin's PHP session to payment requests, so ordinary pages can be cached."""
import io, sys

src, dst = sys.argv[1], sys.argv[2]
s = io.open(src, encoding="utf-8", newline="").read()
nl = "\r\n" if "\r\n" in s else "\n"
old = "if(!isset($_SESSION)){" + nl + "    session_start(); " + nl + "}"
assert s.count(old) == 1, "unexpected file"
new = nl.join([
    "// Dlight patch (6 Nov 2026): start the PHP session only where M-Pesa needs it: checkout, order-pay,",
    "// WooCommerce checkout requests, the plugin's own payment, status and callback addresses, and wp-admin.",
    "// Before, it started on every page, which added a cookie and no-cache headers to the whole site and",
    "// stopped the host's page cache from working. The gateway and each payment step still start the session",
    "// themselves as before; this only removes it from pages that never use it.",
    "if ( ! function_exists( 'dlight_mpesa_needs_session' ) ) {",
    "\tfunction dlight_mpesa_needs_session() {",
    "\t\tif ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {",
    "\t\t\treturn true;",
    "\t\t}",
    "\t\t$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';",
    "\t\treturn (bool) preg_match( '#/checkout|/order-pay|/order-received|wc-ajax=|wc-api=|payment_action|scanner_action|callback_action|^/(payment|callback|scanner)(/|\\?|$)#i', $uri )",
    "\t\t\t|| 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' );",
    "\t}",
    "}",
    "if ( ! isset( $_SESSION ) && dlight_mpesa_needs_session() ) {",
    "    session_start(); ",
    "}",
])
s = s.replace(old, new, 1)
io.open(dst, "w", encoding="utf-8", newline="").write(s)
print("patched")
