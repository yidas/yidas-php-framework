<?php

/**
 * ======================================================================
 * App Core Component / Autoload Function
 * ======================================================================
 *
 * Notice: 
 * This file includes App class and Autoload function.  
 *  
 * @author    	Nick Tsai
 * @version   	1.0.0
 * @date 	  	2015/02/25
 *
 * @filesource 	Core-Config version: 1.3.0
 *
 * @example 	Model Definition
 *	<code>
 *
 *	class User extends Model
 *	{
 *
 *		protected $_database = 'default'; 			// Database Key
 *
 *		protected $_table = 'user_list';			// Table Name
 *
 *		protected $_cdatetime_column = 'cdatetime';	// Auto Create Time
 *
 *		protected $_udatetime_column = 'udatetime';	// Auto Update Time
 *
 *	}
 *
 *	</code>
 *
 * @example 	Apply in controller
 *	<code>
 *
 * 	$model = new User;
 *
 * 	$result = $mode->select(array('='=>array('id'=>1)));
 *
 *	</code>
 */


class App
{

	# Configuration

	private static $_app_config_path = '';	


	/**
	 * ======================================================================
	 * Assgin a new App with own config path
	 * ======================================================================
	 */

	public static function newApp($subapp_name)
	{

		self::$_app_config_path = $subapp_name;
	}

	/**
	 * ======================================================================
	 * Initialization
	 * ======================================================================
	 *
	 * @return (string) The Full Config Directory Path
	 *
	 */
	public static function init()
	{

		/**
		 * ======================================================================
		 * Config Core Initialization
		 * ======================================================================
		 */

		require dirname(__FILE__).'/../core/config.php';

		# Set config path for new App if existed
		if (self::$_app_config_path) {
			
			Config::setConfigPath('../config/'.self::$_app_config_path.'/');
		}

		// Config::var_dump(1);     // Print all configurations for debuging

		Config::loadFile('app');    // Load variables of config/app.php 


		/**
		 * ======================================================================
		 * System Regional Configuration
		 * ======================================================================
		 */

		header('Content-Type: text/html; charset='.strtolower(Config::get('charset','app')) ); 

		date_default_timezone_set(Config::get('timezone','app'));

		mb_internal_encoding(Config::get('charset','app'));

		/**
		 * ======================================================================
		 * Environment Setting
		 * ======================================================================
		 * 
		 * With Development/Stage/Production Environment, it need to define the
		 * development and stage machines IPs to separate three environments.
		 *
		 */

		if ( $_SERVER['SERVER_ADDR'] == Config::get('ip_dev','app') ) {

		    Config::set('env', 'dev', 'app');
		    Config::set('env_dev', true, 'app');
		}
		    
		elseif ( $_SERVER['SERVER_ADDR'] == Config::get('ip_sta','app') ) {

		    Config::set('env', 'sta', 'app');
		    Config::set('env_sta', true, 'app');
		}
		    
		else {

		    Config::set('env', 'pro', 'app');
		    Config::set('env_pro', true, 'app');
		}
		    

		/**
		 * ======================================================================
		 * Error Reporting Configuration
		 * ======================================================================
		 */

		if (Config::get('env_dev','app')) {

		    error_reporting(-1);
		    ini_set('display_errors', 'On');

		} else {

		    error_reporting(0);
		    ini_set('display_errors', 'Off');
		}


		/**
		 * ======================================================================
		 * Database
		 * ======================================================================
		 */

		# Please using DB driver to load database config than connect.

		/**
		 * ======================================================================
		 * Basic Function requirement
		 * ======================================================================
		 */

		Config::loadFunction();     // Load config/function.php

		/**
		 * ======================================================================
		 * Autoload Initialization - 
		 * ======================================================================
		 *
		 * There are 2 classes of Class-Loader: File Exist / PHP Base. 
		 * You can choose one of them then comment another.
		 * File Exist Class is recommended.
		 *
		 */

		spl_autoload_register(null, false);
		spl_autoload_register('ClassLoader');   // Calling Function below

		/**
		 * ======================================================================
		 * Route Initialization
		 * ======================================================================
		 */

		require dirname(__FILE__).'/../'.Config::get('route_filename', 'app').'.php';
		
	}


}

/**
 * ======================================================================
 * Class Loader Function - Class of File Exist
 * ======================================================================
 *
 * @param $class: PHP Class Name while calling.
 *
 */
function ClassLoader($class)
{

    $class = strtolower($class);    // Case-insensitive for calling

    $file_name = "{$class}.php";

    foreach (Config::get('autoload_paths','app') as $key => $path) {

        $path .= $file_name;        // Full path combination

        if(file_exists($path)) {

            require $path;

            return true;
        } 
    }

    return false;
}

/**
 * ======================================================================
 * Class Loader Function - Class of PHP Base
 * ======================================================================
 *
 * @param $class: PHP Class Name while calling.
 *
 */

/* -- Not using (Could be conflicting such as Smarty) --

function ClassLoader($class){

    $class=strtolower($class); 

    include "{$class}.php";

}

# Define Autoload paths
    $autoload_paths = Config::get('autoload_paths','app');
    $autoload_paths[] = get_include_path();

# Set PHP Include Paths
    set_include_path(join(PATH_SEPARATOR, $autoload_paths));

*/

?>