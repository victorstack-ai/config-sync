<?php
/**
 * Settings page template.
 *
 * Renders the Config Sync settings form using the WordPress Settings API.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

use ConfigSync\Admin\SettingsPage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
		<?php
		settings_fields( SettingsPage::SETTINGS_GROUP );
		do_settings_sections( SettingsPage::MENU_SLUG );
		submit_button();
		?>
	</form>
</div>
