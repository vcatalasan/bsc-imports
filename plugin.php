<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

require(plugin_dir_path(__FILE__) . 'includes/bsc-imports.php');

class BSC_Imports_Plugin extends BSC_Imports
{

	// plugin general initialization

	private static $instance = null;

	static $settings;

	// required plugins to used in this application
	var $required_plugins = array(
		'BuddyPress' => 'buddypress/bp-loader.php',
		'PaidMembershipPro' => 'paid-memberships-pro/paid-memberships-pro.php'
	);

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	function __construct() {
		if (!$this->required_plugins_active()) return;

		// program basename and dir
		self::$settings['program'] = array(
			'basename' => plugin_basename( __FILE__ ),
			'dir_path' => plugin_dir_path( __FILE__ ),
			'dir_url'  => plugin_dir_url( __FILE__ )
		);

		parent::__construct();
	}

	function required_plugins_active()
	{
		$status = true;
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		foreach ($this->required_plugins as $name => $plugin) {
			if (is_plugin_active($plugin)) continue;
			?>
			<div class="error">
				<p>BSC Imports plugin requires <strong><?php echo $name ?></strong> plugin to be installed and activated</p>
			</div>
			<?php
			$status = false;
		}
		return $status;
	}

}

?>