<?php

/**
 * ======================================================================
 * Bootstrap
 * ======================================================================
 */

# Print $_GET for debuging
// print_r($_GET);exit;

# App Core loading
require '../core/app.php';

# Set is Sub-App with new own config extended by main config
// App::newApp('admin');

App::init();	// App Initialization

?>