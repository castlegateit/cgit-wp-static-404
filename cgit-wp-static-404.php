<?php

use Castlegate\Static404\Plugin;

/*

Plugin Name: Castlegate IT Static 404 Page
Plugin URI: https://github.com/castlegateit/cgit-wp-static-404
Description: Caches the WordPress 404 page and adjusts the default Apache rewrite rules so that 404 requests can be served by a static resource instead of a dynamically generated WordPress page
Version: 1.0.0
Author: Castlegate IT
Author URI: https://www.castlegateit.co.uk/
Network: true

Copyright (c) 2025 Castlegate IT. All rights reserved.

*/

if (!defined('ABSPATH')) {
    wp_die('Access denied');
}

define('CGIT_STATIC_404_VERSION', '1.0.0');
define('CGIT_STATIC_404_NAME', 'Castlegate IT Static 404 Page');
define('CGIT_STATIC_404_PLUGIN_FILE', __FILE__);
define('CGIT_STATIC_404_PLUGIN_DIR', __DIR__);

require_once __DIR__ . '/classes/autoload.php';

Plugin::init();