<?php
/*
Plugin Name: Tidings
Plugin URI: http://www.tidings.com
Description: Connect with your tidings.com account and create posts based on your newsletters.
Version: 1.0.3
Author: tidingsco, radishconcepts
*/

define( 'TIDINGS_VERSION', '1.0.3' );
define( 'TIDINGS_PLUGIN_FILE', __FILE__ );
define( 'TIDINGS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include classes
require_once( 'classes/class-form-fields.php' );
require_once( 'classes/class-settings.php' );
require_once( 'classes/class-publisher.php' );
require_once( 'classes/class-admin.php' );
require_once( 'classes/class-frontend.php' );

// Load classes
Tidings_Settings::instance();
Tidings_Publisher::instance();
Tidings_Admin::instance();
Tidings_Frontend::instance();