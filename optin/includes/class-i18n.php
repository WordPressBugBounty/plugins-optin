<?php
/**
 * Loads the plugin's translation files for internationalized strings.
 *
 * @package Optin
 */

namespace OPTN\Includes;

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://wpxpo.com
 * @since      1.0.0
 *
 * @package    optin
 * @subpackage optin/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    optin
 * @subpackage optin/includes
 */
class I18n {

	/**
	 * Preserve the legacy translation callback for integrations.
	 *
	 * WordPress loads the optin domain automatically from language packs or the
	 * plugin's declared Domain Path when a translated string is first requested.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public function load_plugin_textdomain() {
		// Kept callable for integrations; WordPress handles translation loading.
	}
}
