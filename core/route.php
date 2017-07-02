<?php

/*
 * ======================================================================
 * Route Core Component
 * ======================================================================
 *
 * Not Support: Beautifying URLs, Parameters
 *
 * Developer:    Nick Tsai
 * Version:      1.3.0
 * Last Updated: 2015/02/10
 * PHP Version:	 5.3.0 (Anonymous functions)
 *
 *
 * @ Demo

	Route::setRouteKey('op'); 							# Fetch $_GET['op']; 

	Route::get('1',function ()
	{
		echo 'URL now is ?op=1 & $_GET['op']=:' . Route::getRouteName();
	});
	Route::get('login','Login_Controller@index');		# Call Contr
	Route::get('login/login','Login_Controller@login');
	Route::get('index'); 								# Do Nothing

	Route::setExceptionalRouteName('login');			# Default Route (If not hit)

	if (!$isLogin) {
		Route::setRouteName('login'); 					# Force to set Route Name
	}

	Route::run();

	echo 'After Route's Action';

 *
 * @ Demo in View
	
	<a href="<?=Route::getUrl()?>">Link to Self</a>

	<a href="<?=Route::getUrl('user/list')?>">Link to user/list</a>

	<a href="<?=Route::getUrl().Route::getUrlParam('page',$i)?>"><?=$i?></a>

	<a href="<?=Route::getUrl().Route::getUrlSymbol()?>page=<?=$i?>"><?=$i?></a>
	
	# Route is 'user/list'
	<li <?php if(Route::isRouteName('user/list')) echo 'class="active"'?>
	User-List</li>
	<li <?php if(Route::getRouteName()=='user/list') echo 'class="active"'?>
	User-List</li>

	# Route is 'user/list' and use level parameter to classify
	<li <?php if(Route::inRouteName('user')) echo 'class="active"'?>
	All User</li>
	<li <?php if(Route::getRouteName(1)=='user') echo 'class="active"'?>
	All User</li>

 *
 * @ Demo with Redirect

 	# Direct to "?r=user/edit&id={$uid}"
 	Route::redirect(Route::getUrl('user/edit').Route::getUrlParam('id', $uid));

 	# History back to previous page
 	Route::goBack();
	
	# This is base on Route::redirect for easily using
 	Route::to('user/list');
 	
 *
 */


class Route
{

	# Controller Postfix Setting

	private static $controller_postfix = ''; 		# Ex.'_controller'


	# Route Setting

	private static $route_key = 'route';			# $_GET[$route_key]
	
	private static $current_route_name = NULL;		# Current Route Name

	private static $exceptional_route_name = '';	# Going to this Route If not matched any


	# Route Components
	
	private static $route_list_get = array();

	private static $route_list_post = array();

	private static $route_parameters = array();


	/**
	 * ======================================================================
	 * Controller Instance Call
	 * ======================================================================
	 */
	public static function controller($controller_name)
	{

		$controller_name .= self::$controller_postfix;

		return new $controller_name;
	}

	public static function setControllerPostfix($value='')
	{
		self::$controller_postfix = $value;
	}

	/**
	 * ======================================================================
	 * Javascript: Alert Function
	 * ======================================================================
	 *
	 * @param (string) $msg: Message of Alert
	 */
	public static function js_alert($msg='')
	{
		
		echo "<script type=\"text/javascript\">alert('{$msg}');</script>";

		return true;
	}

	/**
	 * ======================================================================
	 * Redirect Function
	 * ======================================================================
	 *
	 * @param (string) 	$url: 		URL of redirection
	 * @param (boolean) $header: 	Location with header
	 */
	public static function redirect($url='', $header=false)
	{
		
		if ($header) {

			header('Location: '.$url);
		}
		 
		echo '<script>location.href="'.$url.'";</script>';

		exit;
	}

	/**
	 * ======================================================================
	 * Go Back Function
	 * ======================================================================
	 *
	 * @param (string) $header: Location with header
	 */
	public static function goBack($header=false)
	{

		if ($header) {
			
			header('Location: ' . $_SERVER['HTTP_REFERER']);
		}	
		 
		echo '<script>history.back();</script>';

		exit;
	}

	/**
	 * ======================================================================
	 * Route Direct Function
	 * ======================================================================
	 *
	 * @param (string) $route: Route Name
	 * @param (string) $get_params: Array of URL params
	 *
	 * Example:
	 * 	Route::to('user/edit', array('id'=>1));
	 *
	 */
	public static function to($route='', $get_params=array())
	{	

		if ($get_params) {

			$params_query = '';
			
			foreach ($get_params as $key => $value) {
				
				$params_query .= self::getUrlParam($key, $value);
			}

			self::redirect(self::getUrl($route) . $params_query);

		} else {

			self::redirect(self::getUrl($route));
		}
	}

	/**
	 * ======================================================================
	 * Route Setting
	 * ======================================================================
	 */
	public static function setRouteKey($name)
	{
		self::$route_key = $name;
	}

	public static function setExceptionalRouteName($route)
	{
		self::$exceptional_route_name = $route;
	}

	public static function setRouteName($route)
	{
		self::$current_route_name = $route;
	}

	/**
	 * ======================================================================
	 * Get Current Route Name
	 * ======================================================================
	 *
	 * @param (int) $level: Which level of route name dividing by slash.
	 *
	 * @return (string) Route name with level.
	 *
	 */
	public static function getRouteName($level=0)
	{
		
		# After self::run()
		if (isset(self::$current_route_name)) {

			if ($level) {
				
				$position = 0;

				# Find the subtract position according to the number of levels
				for ($i=0; $i < (int)$level; $i++) { 
					
					$position = strpos(self::$current_route_name, '/', ($position + 1) );
				}
				
				if ($position) {
					
					return substr(self::$current_route_name, 0, $position);
				}

			}

			return self::$current_route_name;
		} 

		# Calling from self::run() (Initialization) 
		elseif (isset($_GET[self::$route_key])) {

			return $_GET[self::$route_key];

		} else {

			return NULL;
		}
	}

	/**
	 * ======================================================================
	 * Check Is Matched Route Name
	 * ======================================================================
	 *
	 * @param (string) $route_name: The route name to match with
	 *
	 * @return (boolean) Is matched or not.
	 *
	 */
	public static function isRouteName($route_name)
	{
		
		return (self::$current_route_name == $route_name) ? true : false;
	}

	/**
	 * ======================================================================
	 * Check Is In Route Name
	 * ======================================================================
	 *
	 * @param (string) $route_name: The route name to match with
	 *
	 * @return (boolean) Is in route name or not.
	 *
	 */
	public static function inRouteName($route_name)
	{
		
		return (strpos(self::$current_route_name, $route_name) === 0) 
			? true : false;
	}

	/**
	 * ======================================================================
	 * Get URL by Route Name
	 * ======================================================================
	 */
	public static function getUrl($route=NULL)
	{
		$route = ($route) ? $route : self::getRouteName();

		return '?'.self::$route_key.'='.$route;
	}

	/**
	 * ======================================================================
	 * Get URL Parameter
	 * ======================================================================
	 */
	public static function getUrlParam($key, $value=NULL)
	{
		return '&'.$key.'='.$value; # no URL beautify situation
	}

	/**
	 * ======================================================================
	 * Get URL Parameter
	 * ======================================================================
	 */
	public static function getUrlSymbol()
	{
		return '&'; # no URL beautify situation
	}

	/**
	 * ======================================================================
	 * Route Register
	 * ======================================================================
	 */
	public static function get($route_name, $action=NULL)
	{
		self::$route_list_get[$route_name] = $action;
	}

	public static function post($route_name, $action=NULL)
	{
		self::$route_list_post[$route_name] = $action;
	}

	
	/**
	 * ======================================================================
	 * Fetch Parameters (alpha)
	 * ======================================================================
	 */
	public static function parametersFetch($route_name)
	{
		if (strpos($route_name, '{')) {
			$start = strpos($route_name, '{') + 1;
			$param = substr($route_name, $start, strpos($route_name, '}') - $start);
			$route_name = str_replace('/{'.$param.'}', '', $route_name);
			echo $route_name;exit;
		}
	}

	/**
	 * ======================================================================
	 * Routes Execution 
	 * ======================================================================
	 */
	public static function run()
	{
		// print_r(self::$route_list_get);

		# Route Assignation

		self::$current_route_name = (isset(self::$current_route_name)) ? self::$current_route_name : self::getRouteName();

		# Predicate Route come from GET or POST
		$route_list = ($_POST) ? self::$route_list_post : self::$route_list_get;

		# If Current Route Name is not registered
		if (!array_key_exists(self::$current_route_name, $route_list)) {

			# Exceptional Route Name is set and is registered
			if (isset(self::$exceptional_route_name) && array_key_exists(self::$exceptional_route_name, $route_list)) {

				self::redirect('./?'.self::$route_key.'='.self::$exceptional_route_name);

			} else { # Exceptional Route Name is not registered

				throw new Exception("Route has not been registered", 1);
			}
		} 

		# Run Route's Action

		$action = $route_list[self::$current_route_name];

		if ($action) {

			if (is_callable($action)) { # Is Function

				$action();

			} else { 					# Is Controller

				$controller_name = substr($action, 0, strpos($action, '@'));

				$function_name =  substr($action, strpos($action, '@')+1);

				self::controller($controller_name)->$function_name();
			}
		}
	}

}