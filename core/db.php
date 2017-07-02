<?php

/**
 * ======================================================================
 * Database Driver
 * ======================================================================
 *
 * @author    	Nick Tsai
 * @version     1.4.0
 * @date 		2015/02/25
 *
 * The config array consisted of databases data, each database key 
 * consisted of configuration arrays. The example data setting is below:
 *
 *   array(
 *	    'default' => array(
 *	        'host'      => '10.1.2.1',
 *	        'database'  => 'MyBaby',
 *	        'username'  => 'YamieGo',
 *	        'password'  => 'GoBaby',
 *	        'charset'   => 'utf8',
 *	        'collation' => 'utf8_genera_ci',
 *	        'prefix'    => '',
 *	        'host_r'    => array('10.1.2.2','10.1.2.3')
 *	        )
 *	    );
 *
 * @example 	(Getting DB Object with DB-Key 'default')
 *
 * 	$db = DB::connect('default')->getDB(); 	# Get Master DB Object
 *	$db_r = DB::getDB('read');				# Get Slave DB Object
 *
 *
 * @example 	(Excute query and reconnect to another database)
 *
 * 	$r1 = DB::connect('DB1')->getDB()->query('SELECT * FROM D1_T1');
 * 	$r2 = DB::getDB()->query('SELECT * FROM D1_T2');
 *
 * 	$r3 = DB::connect('DB2')->getDB()->query('SELECT * FROM D2_T1');
 *
 */ 

class DB
{	
	/**
	 * ======================================================================
	 * Variables Setting
	 * ======================================================================
	 *
	 * @var $_default_db_engine: Database Engine, default with PDO extention
	 * @var $_default_db_key: Default key name of DB config array
	 * @var $_db_config_filepath: 
	 *		File path of Database Configuration for Connection
	 */

	private static $_default_db_engine = 'pdo';

	private static $_default_db_key = 'default'; 

	private static $_db_config_filepath = '/config/database.php';


	/**
	 * ======================================================================
	 * Components
	 * ======================================================================
	 */
	private static $_db_object_write;		// Master DB Object
	private static $_db_object_read;		// Slave DB Object
	private static $_all_db_config;			// All DB configuration
	private static $_db_config;				// Selected DB configuration
	private static $_current_db_key;		// NULL means no connection.
	private static $_cache_filepath;		// Cache of $_db_config_filepath


	/**
	 * ======================================================================
	 * Setting of Config Path
	 * ======================================================================
	 */
	private static function getConfigPath()
	{
		
		if (!self::$_cache_filepath) {
			
			self::$_cache_filepath = 
				Config::get('path_root','app') . self::$_db_config_filepath;
		}

		return self::$_cache_filepath;
	}

	/**
	 * ======================================================================
	 * Set $_default_db_engine
	 * ======================================================================
	 */
	public static function setDefaultEngine($value)
	{
		self::$_default_db_engine = $value;

		return new self;
	}

	/**
	 * ======================================================================
	 * Set $_default_db_key
	 * ======================================================================
	 */
	public static function setDefaultKey($value)
	{
		self::$_default_db_key = $value;

		return new self;
	}

	/**
	 * ======================================================================
	 * Set $_db_config_filepath
	 * ======================================================================
	 */
	public static function setConfigFilepath($value)
	{
		self::$_db_config_filepath = $value;

		return new self;
	}


	/**
	 * ======================================================================
	 * Get Current DB Key (DB Connection Status)
	 * ======================================================================
	 */
	public static function currentDB()
	{
		return self::$_current_db_key;
	}

	/**
	 * ======================================================================
	 * Get Default DB Key (For checking model without setting database)
	 * ======================================================================
	 */
	public static function defaultDB()
	{
		return self::$_default_db_key;
	}

	/**
	 * ======================================================================
	 * Get Database Object (ex. PDO Object)
	 * ======================================================================
	 *
	 * @param (string) $mode: Choose read mode
	 *
	 * @return (mixed) DB Object
	 */
	public static function getDB($mode=NULL)
	{
		if (!self::$_current_db_key) {
			self::connect();
		}

		if ($mode) {
			$mode = strtolower(trim($mode));
			if (in_array($mode, array('slave','read','r',1))) {
				return self::$_db_object_read;
			}
		}

		return self::$_db_object_write;

	}

	public static function getWrite()
	{
		return self::getDB();
	}

	public static function getRead()
	{
		return self::getDB('read');
	}


	/**
	 * ======================================================================
	 * Add Database Configuration (Into $_all_db_config)
	 * ======================================================================
	 */
	public static function addConfig($db_configs, $overwrite=true)
	{
		if (!self::$_all_db_config) {
			
			self::$_all_db_config = require self::getConfigPath();
		}

		foreach ($db_configs as $db_key => $db_config) {
			
			if ( !isset(self::$_all_db_config[$db_key]) || $overwrite==true ) {

				self::$_all_db_config[$db_key] = $db_config;
			}
		}

		return new self;
	}

	/**
	 * ======================================================================
	 * Databse Connection (Initialize the DB objects)
	 * ======================================================================
	 *
	 * Example code: 
	 * DB::connect(); # Use $_default_db_key and $_default_db_engine
	 * DB::connect('defualt','PDO'); # Choose 'default' from DB config
	 * 
	 * @param (string) $database_key: Refer from $_db_config_filepath
	 * @param (string) $engine: Database Engine such as PDO
	 *
	 * @return (mixed) DB static self
	 */
	public static function connect($database_key=NULL, $engine=NULL)
	{	
		# Setting current database name giving by config
		self::$_current_db_key = ($database_key) ? $database_key : self::$_default_db_key;

		# Loading All Database Configuration (Require at first time)
		if (!self::$_all_db_config) {

			self::$_all_db_config = require self::getConfigPath();
		}

		# Setting current database configuration referring by config
	    self::$_db_config = self::$_all_db_config[ self::$_current_db_key ];

	    # Setting database engine, PDO is default
	    self::$_default_db_engine = ($engine) ? strtolower($engine) : self::$_default_db_engine;

	    switch (self::$_default_db_engine) {
	    	case 'pdo':
	    	default:
	    		self::connectPDO();
	    		break;
	    }
	    
	    return new self;
	}

	/**
	 * ======================================================================
	 * Connection of PDO (The default engine)
	 * ======================================================================
	 */
	static private function connectPDO()
	{	
		
		# Connect to Write DB
		try {

			self::$_db_object_write = new PDO('mysql:host='.self::$_db_config['host'].
			        ';dbname='.self::$_db_config['database'], 
			        self::$_db_config['username'], 
			        self::$_db_config['password']
			        );

			self::$_db_object_write->query("SET NAMES ".self::$_db_config['charset']);

	    } catch (PDOException $e) {

			print "Database Connection Error!: " . $e->getMessage() . "<br/>";
			die();

	    }

	    # Check if Read DBs exist
	    if (!isset(self::$_db_config['host_r'][0])) {
	    	self::$_db_object_read = self::$_db_object_write;
	    	return;
	    }

	    # Selecting Read DBs by Hash
	    $host_num = count(self::$_db_config['host_r']);
	    if ($host_num > 1) {
	    	$host = self::$_db_config['host_r'][rand(0, $host_num-1)];
	    } else
    		$host = self::$_db_config['host_r'][0];

    	# Connect to Read DB
	    try {

		      self::$_db_object_read = new PDO('mysql:host='.$host.
		                ';dbname='.self::$_db_config['database'], 
		                self::$_db_config['username'], 
		                self::$_db_config['password']
		                );

		      self::$_db_object_read->query("SET NAMES ".self::$_db_config['charset']);

	    } catch (PDOException $e) {

		      print "Database Connection Error!: " . $e->getMessage() . "<br/>";
		      die();

	    }
	}

}

?>