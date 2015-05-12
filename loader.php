<?php
/*
Plugin Name: BSC Imports
Plugin URI: http://www.bscmanage.com/my-plugin/
Description: Imports user profiles and transactions from external sources into Wordpress, BuddyPress, and PMPro tables
Version: 1.0.0
Requires at least: WordPress 2.9.1 / BuddyPress 1.2
Tested up to: WordPress 2.9.1 / BuddyPress 1.2
License: GNU/GPL 2
Author: Val Catalasan
Author URI: http://www.bscmanage.com/staff-profiles/
*/

/* release notes:
 * 
*/
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

$bsc_imports_plugin_file  = plugin_dir_path(__FILE__) . 'plugin.php';

require($bsc_imports_plugin_file);

add_action('bp_include', array('BSC_Imports_Plugin', 'get_instance'), PHP_INT_MAX);
