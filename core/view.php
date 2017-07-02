<?php

/**
 * ======================================================================
 * View
 * ======================================================================
 *
 * Support: Smarty (Enable to get instance of Smarty Object with setting)
 *
 * @author 		Nick Tsai
 * @version     1.4.1
 * @date  		2015/02/25
 *
 * @filesource 	Core-Config version: 1.4.0
 *
 *
 * @example 	(Without library)
 *
 *	View::assign('data', $data);
 *	View::assign(array('data1'=>$data1, 'data2'=>$data2));
 *
 *	View::display('index.php');
 *
 *
 * @example 	(With Smarty library)
 *
 *	View::smarty()->assign('data', $data);
 *
 *	View::smarty()->display('index.tpl');
 *
 *
 */



class view {

	private static $_variables; 			// Template Variable

	private static $_views_path;			// Template Path

	private static $_smarty;				// Smarty Library Instance

	# Smarty Class Path for including
	private static $_smarty_lib_path = '/libs/smarty/Smarty.class.php';			

	/**
	 * ======================================================================
	 * Assgin Variable
	 * ======================================================================
	 *
	 * @param (string/mixed) $views_var, 
	 * 	String as key name / Array as key=>value
	 * @param (string) $value, As value if $views_var is not array
	 *
	 */
	public static function assign($views_var, $value = null)
	{
		if (is_array($views_var)) {
			
			foreach ($views_var as $key => $value) {
				
				if ($key != '') {
					
					self::$_variables[$key] = $value;

				}

			}

		} else {

			if ($views_var != '') {
				
				self::$_variables[$views_var] = $value;

			}

		}
	}

	/**
	 * ======================================================================
	 * Display Template
	 * ======================================================================
	 *
	 * @param (string) $views_name, Template file name including path
	 *
	 */
	public static function display($views_name)
	{
		if (is_array(self::$_variables)) {

			extract(self::$_variables);

		}

		# Check Views Path
		if (!self::$_views_path) {
			
			self::$_views_path = Config::get('path_root','app') . '/views/';
		}

		require (self::$_views_path . $views_name);
	}

	/**
	 * ======================================================================
	 * Echo Variable applying in Template
	 * ======================================================================
	 *
	 * @param (string) $variable_key, Variable Key Name in view's variables
	 *
	 * Example: (In view)
	 * <?=View::val('username')?>
	 *
	 */
	public static function val($variable_key)
	{
		if (isset(self::$_variables[$variable_key])) {

			echo self::$_variables[$variable_key];

		}
	}

	/**
	 * ======================================================================
	 * Set Views Path
	 * ======================================================================
	 *
	 * @param (string) $path, The template folder's path
	 *
	 */
	public static function setViewsPath($path)
	{
		self::$_views_path = $path;
	}

	/**
	 * ======================================================================
	 * Smarty
	 * ======================================================================
	 *
	 * @return (obj) The same smarty object with setting already
	 *
	 */
	public static function smarty()
	{
		if (!self::$_smarty)
			self::smarty_init();

		return self::$_smarty;
	}

	/**
	 * ======================================================================
	 * Smarty Initialization with Setting
	 * ======================================================================
	 */
	public static function smarty_init()
	{
		$_path_root = Config::get('path_root','app');

		require $_path_root . self::$_smarty_lib_path;

		$smarty = new Smarty();


		# Setting

		$smarty->setTemplateDir($_path_root . '/views/');
		$smarty->setCompileDir($_path_root . '/storage/view_compile/');
		$smarty->setCacheDir($_path_root . '/storage/view_cache/');

		$smarty->left_delimiter="{{";
	    $smarty->right_delimiter="}}";
	    $smarty->inheritance_merge_compiled_includes = false; # Allowing {include} variable in {block}

	    $smarty->caching=false;

	    # Save Instance
	    self::$_smarty = $smarty;

	}

}