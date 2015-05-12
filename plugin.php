<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

class BSC_Imports_Plugin
{

	// plugin general initialization

	private static $instance = null;

	static $settings;
	static $users = 'import_users';
	static $transactions = 'import_transactions';

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {
		// check if dependent plugins are loaded otherwise do not start this plugin
		if ( ! ( function_exists( 'buddypress' ) && function_exists( 'pmpro_init' ) ) ) {
			return;
		}

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	function __construct() {
		// program basename and dir
		self::$settings['program'] = array(
			'basename' => plugin_basename( __FILE__ ),
			'dir_path' => plugin_dir_path( __FILE__ ),
			'dir_url'  => plugin_dir_url( __FILE__ )
		);
	}

}

?>