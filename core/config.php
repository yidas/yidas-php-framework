<?php

/**
 * ======================================================================
 * Config Core Component
 * ======================================================================
 *
 * This class bases on directory of config, it use to load each config
 * file while need it then get and set the variable of configuration.
 *
 * This supports multiple config with adding config file to different
 * directory in main config directory (ex. /config/app2/), the sub config
 * file will extend to main config while sub config file is not existent.
 *
 * @author 		Nick Tsai
 * @version     1.4.0
 * @date  		2015/02/25
 *
 *
 * @example
 *	
 *	Config::var_dump(1); // Print all configurations for debuging
 *	
 *	Config::loadFile('app');
 *	date_default_timezone_set(Config::get('timezone','app'));
 *
 *	Config::set('var1', 'value1','other');
 *	echo Config::get('var1', 'other'); 	// Output: value1
 *
 *	Config::set('var2', array('level1'=>array('key'=>'value')),'other');
 *	echo Config::getArray(array('var2','level1','key'), 'other'); 	
 *	// Output: value
 *
 *	print_r( Config::getFile('other') );
 *	// Output: Array of all variables in other.php config 
 *
 *	Config::loadFunction();     // Load config/function.php (default)
 *
 *	
 * @example 	Setting Environment Variable
 *
 *	if ( $_SERVER['SERVER_ADDR'] == Config::get('ip_dev','app') ) {
 *
 *	    Config::set('env', 'dev', 'app');
 *	    Config::set('env_dev', true, 'app');
 *	}
 *	    
 *	elseif ( $_SERVER['SERVER_ADDR'] == Config::get('ip_sta','app') ) {
 *
 *	    Config::set('env', 'sta', 'app');
 *	    Config::set('env_sta', true, 'app');
 *	}
 *	    
 *	else {
 *
 *	    Config::set('env', 'pro', 'app');
 *	    Config::set('env_pro', true, 'app');
 *	}
 *	
 *
 * @example 	Multiple Apps for different config
 *
 *	Config::setConfigPath('../config/app2/');
 *
 *	Config::loadFile('app');    // Load on '../config/app2/app.php'
 *
 */


class Config
{

	# Configuration

	private static $config_path = '../config/';  // Main App Config Path

	private static $config_files = array('app','info','database','other');


	# Components

	private static $cache_config_path;		// Setting in getFullConfigPath()

	private static $variable = array();		// Configuration Variable	


	/**
	 * ======================================================================
	 * Full Root Path GET
	 * ======================================================================
	 *
	 * @return (string) The Full Path of Root Path
	 */
	public static function getRootPath()
	{
		
		return dirname(dirname(__FILE__));
	}

	/**
	 * ======================================================================
	 * $config_path SET
	 * ======================================================================
	 *
	 * @param (string) $config_path: New configuration path
	 *
	 * @return (string) The new Full Path from cache
	 */
	public static function setConfigPath($config_path)
	{
		
		self::$cache_config_path = self::getFullPath($config_path);

		return self::getFullConfigPath();
	}

	/**
	 * ======================================================================
	 * Get Variable
	 * ======================================================================
	 *
	 * @param (string) $variable_key: The Key of variables in file itself
	 * @param (string) $file_name: Which configuration file
	 *
	 * @return (mixed) The Value of Configuration Variable
	 *
	 */
	public static function get($variable_key, $file_name='other')
	{

		self::checkFile($file_name);

		return self::$variable[$file_name][$variable_key];
	}

	/**
	 * ======================================================================
	 * Get Variable in Array
	 * ======================================================================
	 *
	 * @param (mixed) $variable_keys: 
	 *	The Level-Keys Array of variables in file itself
	 * @param (string) $file_name: Which configuration file
	 *
	 * @return (mixed) The Value of Configuration Variable
	 *
	 * @example 
	 *	Config::getArray(array('developers','name',0), 'info');
	 *
	 */
	public static function getArray($variable_keys=array(), $file_name='other')
	{

		self::checkFile($file_name);

		$variable_node = self::$variable[$file_name];

		foreach ($variable_keys as $key => $level_key) {
					
			$variable_node = $variable_node[$level_key];
		}		

		return $variable_node;
	}

	/**
	 * ======================================================================
	 * Get Variables of the File
	 * ======================================================================
	 *
	 * @param (string) $file_name: Which configuration file
	 *
	 * @return (mixed) The array of the Configuration Variables in the file
	 *
	 * @example 
	 *	Config::getFile('info');
	 *
	 */
	public static function getFile($file_name='other')
	{

		self::checkFile($file_name);

		return self::$variable[$file_name];
	}

	/**
	 * ======================================================================
	 * Set Variable
	 * ======================================================================
	 *
	 * @param (string) $variable_key: The Key of variable in file itself
	 * @param (mixed)  $value: The Value of Configuration Variable
	 * @param (string) $file_name: Which configuration file
	 *
	 * @return (mixed) The Value of Configuration Variable
	 *
	 */
	public static function set($variable_key, $value, $file_name='other')
	{
		
		self::checkFile($file_name);

		self::$variable[$file_name][$variable_key] = $value;

		return $value;
	}

	/**
	 * ======================================================================
	 * Load File Function
	 * ======================================================================
	 *
	 * Notice:
	 * Load with $config_path if new config not existed 
	 *
	 * @param (string) $file_name: Which configuration file
	 *
	 * @return (boolean) Status
	 *
	 */
	public static function loadFile($file_name='app')
	{

		if (file_exists(self::getFullConfigPath().$file_name.'.php')) {
			
			self::$variable[$file_name] = require self::getFullConfigPath().$file_name.'.php';

			return true;

		} else {

			if (file_exists(self::getFullPath(self::$config_path).$file_name.'.php')) {
			
				self::$variable[$file_name] = require self::getFullPath(self::$config_path).$file_name.'.php';

				return true;
			}

			return false;
		}	
	}

	/**
	 * ======================================================================
	 * Load File Function
	 * ======================================================================
	 */
	public static function loadFunction()
	{
		
		if (file_exists(self::getFullConfigPath().'function.php')) {
			
			require self::getFullConfigPath().'function.php';

		} else {

			require self::getFullPath(self::$config_path).'function.php';
		}

		
	}

	/**
	 * ======================================================================
	 * Load All Function
	 * ======================================================================
	 *
	 */
	public static function loadAll()
	{

		foreach (self::$config_files as $key => $file_name) {
			
			self::loadFile($file_name);
		}

	}

	/**
	 * ======================================================================
	 * Var Dump Function
	 * ======================================================================
	 *
	 * @param (boolean) $load_all: Show all configs with pre-load.
	 *
	 * @return (mixed) All config data structure
	 *
	 */
	public static function var_dump($load_all=false)
	{

		if ($load_all==true)

			self::loadAll();

		
		print_r(self::$variable);

	}

	/**
	 * ======================================================================
	 * Check File of Config
	 * ======================================================================
	 *
	 * @param (string) $file_name: File name of configs.
	 *
	 */
	private static function checkFile($file_name)
	{
		
		if (!isset(self::$variable[$file_name])) {
			
			self::loadFile($file_name);
		}
	}

	/**
	 * ======================================================================
	 * Get Full Config Directory Path
	 * ======================================================================
	 *
	 * @return (string) The Full Config Directory Path
	 *
	 */
	private static function getFullConfigPath()
	{

		if (!self::$cache_config_path) {
			
			self::$cache_config_path = self::getFullPath(self::$config_path);
		}

		return self::$cache_config_path;
	}

	/**
	 * ======================================================================
	 * Get Full Path
	 * ======================================================================
	 *
	 * @param (string) $path: Relative Path
	 *
	 * @return (string) Absolute Path
	 *
	 */

	private static function getFullPath($path)
	{
		
		return dirname(__FILE__).'/'.$path;
	}


}