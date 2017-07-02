<?php

/**
 * ======================================================================
 * Auth Module
 * ======================================================================
 *
 * This model is a vender class for packaging authorization system,
 * including Users, Roles, Modules, Groups, Failedlogin modules.
 *
 * Root: If user set is_root, user will pass all verification(check).
 *
 * Database: Installation with SQL is at the bottom in this class
 *  
 * Developer:    Nick Tsai
 * Version:      1.7.0
 * Last Updated: 2015/02/10
 *
 * Extend Model Version: 1.3.0
 *
 */

class Auth extends Model
{
	# Extends Model Setting

	protected $_table = '';	# Selected Table Name (table is set in function)

	protected $_debug = 0;	# Debug Mode


	# Auth Model Setting

	private static $session_name = 'auth';			// Session Name of Auth Module

	private static $failedlogin_time = 5;			// Max Time of Failed Login

	private static $failedlogin_lock_minutes = 30; 	// Minutes of Login Lock 


	# Auth Model Componemts

	private static $model; 							// Static Model Object

	private static $user_module_names_cache;		// User's Modules Cache Name List


	/**
	 * ======================================================================
	 * Model GetInstance
	 * ======================================================================
	 */
	public static function model()
	{
		if (!self::$model) {

			self::$model = new self;

		} 

		return self::$model;	
	}

	/**
	 * ======================================================================
	 * Login Function
	 * ======================================================================
	 *
	 * @param (string) $username, Username in auth_users
	 * @param (string) $password, Password before hash in auth_users
	 * @param (boolean) $force_login, 1 => login without password
	 *
	 * @return (int) Result code:
	 * 200 => Login Success
	 * 401 => Username Not Found
	 * 402 => Password Not Matched
	 * 403 => Locked cause reaching the Failed-Login limitation
	 * 406 => User's status is 'N' (false) 
	 *
	 * Example:
	 * login($id, $pw);		# Login with password verification
	 * login($id, NULL, 1);	# Force login without password
	 *
	 */
	public static function login($username, $password, $force_login=0)
	{


		$failed_login_status = self::checkFailedLoginStatus();

		# Check if locked by failed login
		if ($failed_login_status==2) {
			
				return 403; // Locked cause reaching the Failed-Login limitation
		}


		# Username Verification

		$where = array();
		$where['=']['username'] = $username;

		$user_data = Auth::model()->table('auth_users')->selectOne($where);
		
		if (!$user_data) {	

			self::failedLogin();

			return 401;	// Username Not Found
		} 


		# Password Verification

		if (!$force_login) {

			$where['=']['password'] = self::hash($password);

			$user_data = Auth::model()->table('auth_users')->selectOne($where);

			if (!$user_data) {	

				self::failedLogin();

				return 402;	// Password Not Matched
			} 
		}


		# Check User Status

		if ($user_data['status'] != 'Y') {
			
			return 406; // User's status is 'N' (false)
		}


		# Login Success

			// print_r($user_data);


			# Update User Log

			$params = array();
			$params['last_ip'] = $_SERVER['REMOTE_ADDR'];
			$params['last_datetime'] = date("Y-m-d H:i:s");;

			Auth::model()->update($params, array('='=>array('id'=>$user_data['id'])));


			# Unlock Failed Login record if exist
			if ($failed_login_status == 1) {

				self::releaseFailedLogin();
			}

			


			# Session Start

			self::sessionCheck();

			$_SESSION[self::$session_name] = array();

			$_SESSION[self::$session_name]['is_login'] = true;

			# Building User Data in Session
			self::initUserData($user_data);

		return 200;

	}

	private static function initUserData($user_data)
	{
		# Auth_Users Table
		$_SESSION[self::$session_name]['user_data'] = $user_data;

		# Get Role's Group ID
		
		$where['=']['id'] = $user_data['rid'];

		$role_data =  self::model()->table('auth_roles')->selectOne($where, 'gid');

		$gid = ($role_data) ? $role_data['gid'] : 0;

		$_SESSION[self::$session_name]['user_data']['role_gid'] = $gid;

	}

	public static function logout()
	{
		self::sessionCheck();

		session_destroy();
	}

	/**
	 * ======================================================================
	 * User Data in Session Get Function
	 * ======================================================================
	 *
	 * @param (string) $key, Column name in auth_users
	 *
	 * @return (mixed) result
	 */
	public static function getUserData($key=NULL)
	{
		self::sessionCheck();

		if (isset($_SESSION[self::$session_name]['user_data'])) {

			if (isset($_SESSION[self::$session_name]['user_data'][$key])) {

				return $_SESSION[self::$session_name]['user_data'][$key];

			} else {

				return $_SESSION[self::$session_name]['user_data'];
			}
				

		} else {

			return false;
		}			
	}

	/**
	 * ======================================================================
	 * User Data in Session Modify Function
	 * ======================================================================
	 *
	 * @param (string) $key: Column name in auth_users
	 * @param (mixed)  $value: New value
	 * @return (boolean) result
	 */
	public static function editUserData($key=NULL, $value=NULL)
	{
		self::sessionCheck();

		if (isset($_SESSION[self::$session_name]['user_data'])) {

			$_SESSION[self::$session_name]['user_data'][$key] = $value;

			return true;
				
		} else {

			return false;
		}			
	}

	/**
	 * ======================================================================
	 * Login Permission Check
	 * ======================================================================
	 *
	 * Example:
	 * Auth::checkIsLogin(1);
	 *
	 * @param (boolean) $type, Action type when deined (1 => exit)
	 *
	 * @return (boolean) when type != 1
	 */
	public static function checkIsLogin($type=0)
	{	
		self::sessionCheck();

		if (isset($_SESSION[self::$session_name]['is_login']) && 
			$_SESSION[self::$session_name]['is_login'] == true) 
		{

			return true;

		} else {

			if ($type==1) {
				
				echo 'Access Denied'; exit;
			}

			return false;
		}
		
	}

	/**
	 * ======================================================================
	 * Group Permission Check
	 * ======================================================================
	 *
	 * Example:
	 * Auth::checkGroup(array('admin','rd'), 1);
	 *
	 * @param (string)/(mixed) $name, Group name (Array is allowed)
	 * @param (boolean) $type, Action type when deined (1 => exit)
	 *
	 * @return (boolean) when type != 1
	 */
	public static function checkGroup($name, $type=0)
	{

		$where['=']['id'] = self::getUserData('role_gid');
		$where['IN']['name'] = $name;

		$result = self::model()->table('auth_groups')->select($where);
		
		if ($result) {

			return true;

		} else {

			# If User is root
			if (self::getUserData('is_root')=='Y') {
				
				return true;
			}

			if ($type==1) {		

				echo 'Group Permission Denied'; exit;
			}
				
			return false;
		}
			
	}

	/**
	 * ======================================================================
	 * Module Permission Check
	 * ======================================================================
	 *
	 * Example:
	 * Auth::checkModule('account_delete', 1);
	 *
	 * @param (string)/(mixed) $name, Module name
	 * @param (boolean) $type, Action type when deined (1 => exit)
	 *
	 * @return (boolean) when type != 1
	 */
	public static function checkModule($name, $type=0)
	{

		# Check User's Modules Cache Name List
		if (!self::$user_module_names_cache) {

			self::$user_module_names_cache = self::roleGetModuleNames(self::getUserData('rid'));
		}

		# Match
		$result = (self::$user_module_names_cache) 
			? in_array($name, self::$user_module_names_cache) : false;
		
		if ($result) {

			return true;

		} else {

			# If User is root
			if (self::getUserData('is_root')=='Y') {
				
				return true;
			}

			if ($type==1) {		

				echo 'Module Permission Denied'; exit;
			}
				
			return false;
		}
			
	}

	/**
	 * ======================================================================
	 * Users: Get One
	 * ======================================================================
	 *
	 * @param (int) $uid, User ID
	 *
	 * @return (mixed) User Data
	 */
	public static function userGet($uid)
	{

		self::model()->_table = 'auth_users';

		return self::model()->selectOne(array('='=>array('id'=>$uid)));
	}

	/**
	 * ======================================================================
	 * Users: Add
	 * ======================================================================
	 *
	 * @param (string) $username, Username
	 * @param (string) $password, Password before hash
	 * @param (int) $rid, Role ID
	 * @param (string) $status, 'Y'/'N'
	 * @param (mixed) $custom, array of Field => Data
	 *
	 * @return (int) User ID
	 */
	public static function userAdd($username, $password='', $rid=0, $status='Y', $custom=array())
	{

		self::model()->_table = 'auth_users';

		# Duplicate Check
		if ( self::model()->selectOne(array('='=>array('username'=>$username))) ) {

			self::model()->setException(1);
			self::model()->setExceptionMsg('Error on: Duplicate Username');
			return false;
		}

		self::model()->_cdatetime_column = 'cdatetime';

		$param = array();
		$param['username'] = $username;
		$param['password'] = self::hash($password);
		$param['rid'] = $rid;
		$param['status'] = $status;

		if ($custom) {
			
			foreach ($custom as $field_name => $data) {
				
				$param[$field_name] = $data;
			}
		}

		return self::model()->insert($param);
	}

	/**
	 * ======================================================================
	 * Users: Edit
	 * ======================================================================
	 *
	 * @param (int) $uid, User ID
	 * @param (string) $username, Username
	 * @param (string) $password, Password before hash
	 * @param (int) $rid, Role ID
	 * @param (string) $status, 'Y'/'N'
	 * @param (mixed) $custom, array of Field => Data
	 *
	 * @return (int) Number of affected row
	 */
	public static function userEdit($uid, $username=NULL, $password=NULL, $rid=NULL, $status=NULL, $custom=array())
	{

		self::model()->_table = 'auth_users';


		# Duplicate Check
		if ( self::model()->selectOne(array(
			'='=>array('username'=>$username),
			'!='=>array('id'=>$uid),
			)) ) {

			self::model()->setException(1);
			self::model()->setExceptionMsg('Error on: Duplicate Username');
			return false; # Duplicate
		}


		# Update

		self::model()->_udatetime_column = 'udatetime';

		$param = array();
		if ($username) 
			$param['username'] = $username;	
		if ($password) 
			$param['password'] = self::hash($password);
		if ($rid)
			$param['rid'] = $rid;
		if ($status)
			$param['status'] = $status;

		if ($custom) {
			
			foreach ($custom as $field_name => $data) {
				
				$param[$field_name] = $data;
			}
		}

		$where = array();
		$where['=']['id'] = $uid;

		return self::model()->update($param, $where);
	}

	/**
	 * ======================================================================
	 * Users: Delete
	 * ======================================================================
	 *
	 * @param (int) $uid, User ID
	 *
	 * @return (int) Number of affected row
	 */
	public static function userDelete($uid)
	{
		
		self::model()->_table = 'auth_users';

		$where = array();
		$where['=']['id'] = $uid;

		return self::model()->delete($where);
	}

	/**
	 * ======================================================================
	 * Roles: Get One
	 * ======================================================================
	 *
	 * @param (int) $rid, Role ID
	 *
	 * @return (mixed) Role Data
	 */
	public static function roleGet($rid)
	{

		self::model()->_table = 'auth_roles';

		return self::model()->selectOne(array('='=>array('id'=>$rid)));
	}

	/**
	 * ======================================================================
	 * Roles: Get List
	 * ======================================================================
	 *
	 * @param (boolean) $all_column: If showing all column 
	 *
	 * @return (mixed) Role Data
	 */
	public static function roleGetList($all_column=false)
	{

		self::model()->_table = 'auth_roles';

		if ($all_column) {
			
			return self::model()->select();

		} else {

			return self::model()->select(NULL,NULL,NULL,
				array('id','gid','name','cname'));
		}
	}

	/**
	 * ======================================================================
	 * Roles: Add
	 * ======================================================================
	 *
	 * @param (string) $name, Role name
	 * @param (string) $cname, Role chinese name
	 *
	 * @return (int) Role ID
	 */
	public static function roleAdd($name, $cname='', $gid=NULL)
	{
		
		self::model()->_table = 'auth_roles';

		# Duplicate Check
		if ( self::model()->selectOne(array('='=>array('name'=>$name))) ) {

			self::model()->setException(1);
			self::model()->setExceptionMsg('Error on: Duplicate Rolename');
			return false;
		}

		self::model()->_cdatetime_column = 'cdatetime';

		$param = array();
		$param['name'] = $name;
		$param['cname'] = $cname;
		$param['gid'] = $gid;

		return self::model()->insert($param);
	}

	/**
	 * ======================================================================
	 * Roles: Edit
	 * ======================================================================
	 *
	 * @param (int) $rid, Role ID
	 * @param (string) $name, Role name
	 * @param (string) $cname, Role chinese name
	 *
	 * @return (int) Number of affected row
	 */
	public static function roleEdit($rid, $name=NULL, $cname=NULL, $gid=NULL)
	{

		self::model()->_table = 'auth_roles';

		# Duplicate Check
		if ( self::model()->selectOne(array(
			'=' => array('name'=>$name),
			'!=' => array('id'=>$rid)
			)) ) {

			self::model()->setException(1);
			self::model()->setExceptionMsg('Error on: Duplicate Rolename');
			return false;
		}

		self::model()->_udatetime_column = 'udatetime';

		$param = array();
		if ($name) 
			$param['name'] = $name;	
		if ($cname) 
			$param['cname'] = $cname;
		if ($gid) 
			$param['gid'] = $gid;

		$where = array();
		$where['=']['id'] = $rid;

		return self::model()->update($param, $where);
	}

	/**
	 * ======================================================================
	 * Roles: Delete
	 * ======================================================================
	 *
	 * @param (int) $rid, Role ID
	 *
	 * @return (int) Number of affected row
	 */
	public static function roleDelete($rid)
	{

		# Delete Roles-Modules relative rows

		$result = self::roleAddEditModules($rid, NULL, 1);

		if ($result===false) {
			self::model()->setException(1);
			self::model()->setExceptionMsg('Error on: Delete Roles-Modules relative rows');
			return false;
		}


		# Delete relative users rid

		$params['rid'] = 0;
		$where_u['=']['rid'] = $rid;

		$result = self::model()->table('auth_users')->update($params, $where_u);
		
		if ($result===false) {
			self::model()->setException(2);
			self::model()->setExceptionMsg('Error on: Delete relative users rid');
			return false;
		}


		# Delete Role

		$where['=']['id'] = $rid;

		$result = self::model()->table('auth_roles')->delete($where);
		
		if ($result===false) {
			self::model()->setException(0);
			self::model()->setExceptionMsg('Error on: Delete Role');
			return false;
		}

		return $result;
	}

	/**
	 * ======================================================================
	 * Roles-Module: Get Module ID List by Role
	 * ======================================================================
	 *
	 * @param (int) $rid: Role ID.
	 *
	 * @return (mixed) Array of Module IDs.
	 */
	public static function roleGetModules($rid)
	{

		self::model()->_table = 'auth_roles_modules';

		$data = self::model()->select(array('='=>array('rid'=>$rid)),
			NULL,NULL,'mid');

		foreach ($data as $key => $row) {
			
			$data[$key] = $row['mid'];
		}

		return $data;
	}

	/**
	 * ======================================================================
	 * Roles-Module: Get Module Name List by Role
	 * ======================================================================
	 *
	 * @param (int) $rid: Role ID.
	 *
	 * @return (mixed) Array of Module Names.
	 */
	public static function roleGetModuleNames($rid)
	{

		if (!self::$user_module_names_cache) {
			
			# Select Module-Names by Role ID
			$list = self::model()
				->table('auth_roles_modules')
				->leftJoin(
					array('auth_modules'=>array(
						'='=>array('auth_roles_modules.mid'=>'auth_modules.id'))),
					array('='=>array('rid'=>$rid)), 
					NULL, NULL, 'name');

			# Reducing Dimensions of List Data and assign into Cache
			foreach ($list as $key => $row) {
				
				self::$user_module_names_cache[] = $row['name'];
			}
		}

		return self::$user_module_names_cache;
	}

	/**
	 * ======================================================================
	 * Roles-Modules: Add & Edit Modules
	 * ======================================================================
	 *
	 * Example:
	 * Auth::roleAddModules(1, array(1,2,4,5));		# Add Modules on RID:1
	 * Auth::roleAddModules(1, array(1,4), 1);		# Delete than Add Modules
	 * Auth::roleAddModules(1, NULL, 1);			# Delete Modules
	 *
	 * @param (int) $rid, Role ID
	 * @param (mixed) $mids, Array of Module IDs
	 * @param (boolean) $clear, Delete before insert
	 *
	 * @return (int) number of affected rows
	 */
	public static function roleAddEditModules($rid, $mids=array(), $clear=1)
	{
		
		self::model()->_table = 'auth_roles_modules';

		
		$params['rid'] = $rid;

		# Detele all relations first
		if ($clear) 
			$result = self::model()->delete(array('='=>array('rid'=>$rid)));

		# Add rows if mids parameter exist
		if ($mids) {

			$params = array();
			$params['rid'] = $rid;

			$affected_row_num = 0;

			# Check mids parameter
			if (!is_array($mids))
				$mids = array($mids);

			# Add Relations
			foreach ($mids as $key => $mid) {

				$params['mid'] = $mid;

				$result = self::model()->insert($params);

				if ($result !== false)
					$affected_row_num += 1;

			}

			return $affected_row_num;
			
		} else
			return $result;

	}

	/**
	 * ======================================================================
	 * Roles-Modules: Edit Module
	 * ======================================================================
	 *
	 * Applied to add or delete single module for API like
	 *
	 * Example:
	 * Auth::roleEditModule(1, 5, 1); # Add Module ID:5 on Role ID:1
	 *
	 * @param (int) $rid, Role ID
	 * @param (int) $mid, Module ID
	 * @param (boolean) $status, 1 => Add, 0 => Delete
	 *
	 * @return (boolean) result
	 */
	public static function roleEditModule($rid, $mid, $status=1)
	{
		
		self::model()->_table = 'auth_roles_modules';

		if ($status==1) {

			$params = array();
			$params['rid'] = $rid;
			$params['mid'] = $mid;

			$result = self::model()->insert($params);

		} else {

			$where = array();
			$where['=']['rid'] = $rid;
			$where['=']['mid'] = $mid;

			$result = self::model()->delete($where);
		}	

		if ($result === false)
			return false;

		return true;
	}

	/**
	 * ======================================================================
	 * Module: Get One
	 * ======================================================================
	 *
	 * @param (int) $mid, Module ID
	 *
	 * @return (mixed) Module Data
	 */
	public static function moduleGet($mid)
	{

		self::model()->_table = 'auth_modules';

		return self::model()->selectOne(array('='=>array('id'=>$mid)));
	}

	/**
	 * ======================================================================
	 * Module: Add
	 * ======================================================================
	 *
	 * @param (string) $name, Module name
	 * @param (string) $cname, Module chinese name
	 * @param (int) $parent_id, Module Parent ID
	 *
	 * @return (int) Module ID
	 */
	public static function moduleAdd($name, $cname='', $parent_id=0)
	{
		
		self::model()->_table = 'auth_modules';

		if ( self::model()->selectOne(array('='=>array('name'=>$name))) ) {
			return false; # Duplicate
		}

		self::model()->_cdatetime_column = 'cdatetime';

		$params = array();
		$params['name'] = $name;
		$params['cname'] = $cname;
		$params['parent_id'] = $parent_id;

		return self::model()->insert($params);
	}

	/**
	 * ======================================================================
	 * Modules: Edit
	 * ======================================================================
	 *
	 * @param (int) $mid, Module ID
	 * @param (string) $name, Module name
	 * @param (string) $cname, Module chinese name
	 *
	 * @return (int) Number of affected row
	 */
	public static function moduleEdit($mid, $name=NULL, $cname=NULL, $parent_id=NULL)
	{

		self::model()->_table = 'auth_modules';

		# Duplicate Check
		if ( self::model()->selectOne(array(
			'=' => array('name'=>$name),
			'!=' => array('id'=>$mid)
			)) ) {

			self::model()->setException(1);
			self::model()->setExceptionMsg('Error on: Duplicate Rolename');
			return false;
		}

		self::model()->_udatetime_column = 'udatetime';

		$param = array();
		if ($name) 
			$param['name'] = $name;	
		if ($cname) 
			$param['cname'] = $cname;
		if (is_numeric($parent_id)) 
			$param['parent_id'] = $parent_id;

		$where = array();
		$where['=']['id'] = $mid;

		return self::model()->update($param, $where);
	}

	/**
	 * ======================================================================
	 * Modules: Delete
	 * ======================================================================
	 *
	 * @param (int) $mid, Module ID
	 *
	 * @return (int) Number of affected row
	 */
	public static function moduleDelete($mid, $clear=1)
	{

		# Delete Groups-Modules relative rows

		$where['='] = array('mid'=>$mid) ;

		$result = self::model()->table('auth_roles_modules')->delete($where);

		if ($result===false) {
			self::model()->setException(1);
			self::model()->setExceptionMsg('Error on: Delete Groups-Modules relative rows');
			return false;
		}


		# Delete Module

		$where['='] = array('id'=>$mid) ;

		$result = self::model()->table('auth_modules')->delete($where);

		if ($result===false) {
			self::model()->setException(0);
			self::model()->setExceptionMsg('Error on: Delete Module');
			return false;
		}
		
		return $result;
	}

	/**
	 * ======================================================================
	 * Groups: Add
	 * ======================================================================
	 *
	 * @param (string) $name, Group name
	 * @param (string) $cname, Group chinese name
	 *
	 * @return (int) Group ID
	 */
	public static function groupAdd($name, $cname='')
	{
		
		self::model()->_table = 'auth_groups';

		if ( self::model()->selectOne(array('='=>array('name'=>$name))) ) {
			return false; # Duplicate
		}

		self::model()->_cdatetime_column = 'cdatetime';

		$param = array();
		$param['name'] = $name;
		$param['cname'] = $cname;

		return self::model()->insert($param);
	}

	/**
	 * ======================================================================
	 * Groups: Edit
	 * ======================================================================
	 *
	 * @param (int) $gid, Group ID
	 * @param (string) $name, Group name
	 * @param (string) $cname, Group chinese name
	 *
	 * @return (int) Number of affected row
	 */
	public static function groupEdit($gid, $name=NULL, $cname=NULL)
	{

		self::model()->_table = 'auth_groups';

		if ( self::model()->selectOne(array('='=>array('name'=>$name))) ) {
			return false; # Duplicate
		}

		self::model()->_udatetime_column = 'udatetime';

		$param = array();
		if ($name) 
			$param['name'] = $name;	
		if ($cname) 
			$param['cname'] = $cname;

		$where = array();
		$where['=']['id'] = $gid;

		return self::model()->update($param, $where);
	}

	/**
	 * ======================================================================
	 * Groups: Delete
	 * ======================================================================
	 *
	 * @param (int) $gid, Group ID
	 *
	 * @return (int) Number of affected row
	 */
	public static function groupDelete($gid)
	{

		# Delete Groups-Modules relative rows

		$result = self::groupAddEditModules($gid, NULL, 1);

		if ($result===false) {
			self::model()->setException(1);
			self::model()->setExceptionMsg('Error on: Delete Groups-Modules relative rows');
			return false;
		}


		# Delete relative users gid

		$params['gid'] = 0;
		$where_u['=']['gid'] = $gid;

		$result = self::model()->table('auth_users')->update($params, $where_u);
		
		if ($result===false) {
			self::model()->setException(2);
			self::model()->setExceptionMsg('Error on: Delete relative users gid');
			return false;
		}


		# Delete Group

		$where['=']['id'] = $gid;

		$result = self::model()->table('auth_groups')->delete($where);
		
		if ($result===false) {
			self::model()->setException(0);
			self::model()->setExceptionMsg('Error on: Delete Group');
			return false;
		}

		return $result;
	}

	/**
	 * ======================================================================
	 * Get Failed Login
	 * ======================================================================
	 *
	 * @param (string) $username: Username with login
	 * @param (string) $ip: 	  IPv4 Address with login
	 *
	 * @return (mixed) Array of Failed-Login row data
	 *
	 */
	public static function getFailedLogin($username=NULL, $ip=NULL)
	{
		if (!$ip)
			$ip = $_SERVER['REMOTE_ADDR'];

		$where = array();
		$where['=']['ip'] = $ip;
		if ($username) {
			$where['=']['username'] = $username;
		}

		return self::model()->table('auth_failedlogin')->selectOne($where);
	}

	/**
	 * ======================================================================
	 * Check Failed Login Lock
	 * ======================================================================
	 *
	 * @param (string) $username: Username with login
	 * @param (string) $ip: 	  IPv4 Address with login
	 * @return (int) code:
	 * 0 => No Failed-Login record
	 * 1 => Failed-Login record existed and is not locked
	 * 2 => Locked cause reaching Failed-Login limitation
	 */
	public static function checkFailedLoginStatus($username=NULL, $ip=NULL)
	{
		
		if (!$ip)
			$ip = $_SERVER['REMOTE_ADDR'];

		$record = self::getFailedLogin($username=NULL, $ip=NULL);

		if ($record) {

			# Get Due-Time of lock
			$due_time = time() - (self::$failedlogin_lock_minutes*60);
			
			# Detect if reached the lock limitation
			if ($record['last_utime'] > $due_time && $record['count'] >= self::$failedlogin_time ) {

				return 2; // Login locked due to too much failed login
			} 

			# Delete Failed Login Record becuase lock time is due 
			if ($record['last_utime'] < $due_time) {

				$where['='] = array('ip'=>$ip);
				if ($username) {
					$where['=']['username'] = $username;
				}
						
				Auth::model()->table('auth_failedlogin')->delete($where);

				return 0;
			}

			return 1;
		}

		return 0;
	}

	/**
	 * ======================================================================
	 * Failed Login
	 * ======================================================================
	 *
	 * @param (string) $username: Username with login
	 * @param (string) $ip: 	  IPv4 Address with login
	 *
	 * @return (int) code:
	 * 0 => First login failed
	 * 1 => Already login failed
	 *
	 */
	public static function failedLogin($username=NULL, $ip=NULL)
	{

		$record = self::getFailedLogin($username, $ip);

		if ($record) {

			$where['='] = array('ip'=>$record['ip'], 'username'=>$record['username']);

			$params = array();
			$params['last_utime'] = time();

			$result = self::model()->table('auth_failedlogin')->counter($where, $params, 'count', 1);

		} else {

			if (!$ip)
				$ip = $_SERVER['REMOTE_ADDR'];

			$params = array();
			$params['ip'] = $ip;
			$params['username'] = $username;
			$params['last_utime'] = time();

			$result = self::model()->table('auth_failedlogin')->insert($params);

		}

		return $result;
	}

	/**
	 * ======================================================================
	 * Release Failed Login
	 * ======================================================================
	 *
	 * @param (string) $username: Username with login
	 * @param (string) $ip: 	  IPv4 Address with login
	 *
	 * @return (int) Number of affected rows (return false if error)
	 *
	 */
	public static function releaseFailedLogin($username=NULL, $ip=NULL)
	{

		if (!$ip)
			$ip = $_SERVER['REMOTE_ADDR'];

		$where['='] = array('ip'=>$ip);
		if ($username) {
			$where['=']['username'] = $username;
		}

		return self::model()->table('auth_failedlogin')->delete($where);
	}

	public static function sessionCheck()
	{
		if (session_id() == '') {

			session_start();
		}
	}

	public static function hash($value)
	{
		return md5($value);
	}

}

/* Database Installation with SQL

-- --------------------------------------------------------

--
-- TABLE `auth_failedlogin`
--

CREATE TABLE IF NOT EXISTS `auth_failedlogin` (
  `ip` char(15) NOT NULL,
  `username` varchar(32) NOT NULL,
  `count` tinyint(1) NOT NULL DEFAULT '1',
  `last_utime` int(10) NOT NULL,
  PRIMARY KEY (`ip`,`username`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- TABLE `auth_groups`
--

CREATE TABLE IF NOT EXISTS `auth_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `cname` varchar(32) NOT NULL,
  `udatetime` datetime NOT NULL,
  `cdatetime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- TABLE `auth_modules`
--

CREATE TABLE IF NOT EXISTS `auth_modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `cname` varchar(32) NOT NULL,
  `parent_id` int(11) NOT NULL DEFAULT '0',
  `udatetime` datetime NOT NULL,
  `cdatetime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- TABLE `auth_roles`
--

CREATE TABLE IF NOT EXISTS `auth_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gid` int(11) NOT NULL,
  `name` varchar(32) NOT NULL,
  `cname` varchar(32) NOT NULL,
  `udatetime` datetime NOT NULL,
  `cdatetime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `gid` (`gid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- TABLE `auth_roles_modules`
--

CREATE TABLE IF NOT EXISTS `auth_roles_modules` (
  `rid` int(11) NOT NULL,
  `mid` int(11) NOT NULL,
  PRIMARY KEY (`rid`,`mid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- TABLE `auth_users`
--

CREATE TABLE IF NOT EXISTS `auth_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(32) NOT NULL,
  `password` varchar(32) NOT NULL,
  `is_root` enum('Y','N') NOT NULL DEFAULT 'N',
  `rid` int(11) NOT NULL DEFAULT '0',
  `status` enum('Y','N') NOT NULL DEFAULT 'Y',
  `last_ip` varchar(15) NOT NULL,
  `last_datetime` datetime NOT NULL,
  `udatetime` datetime NOT NULL,
  `cdatetime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `rid` (`rid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


*/

?>