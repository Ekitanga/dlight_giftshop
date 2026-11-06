<?php
/**
 * Contact page.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

dl_help_open( 'contact-us', 'Contact Us', 'We are happy to help you choose, or sort out an order.' );
?>
<ul class="dl-ways">
	<li><a href="<?php echo esc_url( dl_whatsapp_url( 'Hello Dlight Giftshop, I have a question.' ) ); ?>" target="_blank" rel="noopener"><span>WhatsApp</span><strong>Chat with us</strong></a></li>
	<li><a href="tel:<?php echo esc_attr( DL_PHONE ); ?>"><span>Phone</span><strong><?php echo esc_html( DL_PHONE_DISPLAY ); ?></strong></a></li>
	<li><a href="mailto:<?php echo esc_attr( DL_EMAIL ); ?>"><span>Email</span><strong><?php echo esc_html( DL_EMAIL ); ?></strong></a></li>
	<li><a href="<?php echo esc_url( DL_MAP ); ?>" target="_blank" rel="noopener"><span>Directions</span><strong>Open in Maps</strong></a></li>
</ul>
<h2>Visit our shop</h2>
<p>Rasumal House, 4th floor, shop 14, Tom Mboya Street (opposite Imenti House), Nairobi.</p>
<h2>Opening hours</h2>
<p>Monday to Saturday: 8:30am to 7:00pm<br>Sunday: closed</p>
<h2>Follow us</h2>
<p><a href="https://www.instagram.com/dlightgiftshopofficial/" target="_blank" rel="noopener">Instagram</a> &middot; <a href="https://www.facebook.com/Dlightgiftshop_Kenya-108544315194461/" target="_blank" rel="noopener">Facebook</a></p>
<?php
dl_help_close();
