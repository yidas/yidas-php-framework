<?php

/**
 * ======================================================================
 * SQL-Builder Model for PDO Databse Engine
 * ======================================================================
 *
 * This model is a abstract class for models to extend as a SQL-Builder, 
 * and also provides PDO connection objects including Write and Read DB.
 *  
 * @author    	Nick Tsai
 * @version   	2.2.0
 * @date 	  	2015/04/01
 *
 * @filesource 	Core-DB version: 1.2.2
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

abstract class Model 
{

	# Setting in Child Class

		protected $_debug = FALSE;		// Debug Mode

		protected $_pdo_fetch_type = PDO::FETCH_ASSOC;


		# Database & Table

		protected $_database_key = '';  // Connected Database Config Key

		protected $_table = '';			// Selected Table Name


		# Datetime Columns Setting

		protected $_cdatetime_column = ''; // Auto Create Datetime

		protected $_udatetime_column = ''; // Auto Update Datetime

	

	# PDO Connection Objects

	protected $db;			// Write (Master) DB Connection Object

	protected $db_r;		// Read-Only (Slave) DB Connection Object


	# Model Components

	protected $_exception_code = NULL;	// Exception Code On Error 

	protected $_exception_msg = NULL;	// Exception Message On Error


	function __construct($database_key=NULL) 
	{
		
		# Check if Force to set Database Key

		if ($database_key) {
			
			$this->_database_key = $database_key;

		} else {

			# Checking model's database setting with default
			$this->_database_key = ($this->_database_key) ? $this->_database_key : DB::defaultDB();
		}
		

		# Call Connect-DB if DB has not initialized yet or the database has changed

		if (!DB::currentDB() || DB::currentDB() != $this->_database_key ) {

			// echo "<script>alert('Current:".DB::currentDB().'\nNew:'.$this->_database_key."');</script>";

			DB::connect($this->_database_key,'pdo');
		}


		# Get PDO Connection Objects

		$this->db = DB::getDB();

		$this->db_r = DB::getDB('read');


		# Setting PDO Error Mode in Debug Mode

		if ($this->_debug) {

	        self::setAttrErrorMode();
	    }
	}

	/**
	 * ======================================================================
	 * Model Settings
	 * ======================================================================
	 */

	public function debugOn()
	{
		
		$this->_debug = true;

		self::setAttrErrorMode();
	}

	public function setPdoFetchType($value)
	{
		$this->_pdo_fetch_type = $value;
	}

	/**
	 * ======================================================================
	 * Model Debug Attribute Handler
	 * ======================================================================
	 */
	private function setAttrErrorMode()
	{
		
		$this->db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );

	    $this->db_r->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );
	}

	/**
	 * ======================================================================
	 * Model Getting Methods
	 * ======================================================================
	 */

	public function getTable()
	{
		return $this->_table;
	}

	public function getDatabaseKey()
	{
		return $this->$_database_key;
	}

	/**
	 * ======================================================================
	 * Exception Message Handlers
	 * ======================================================================
	 */

	public function setException($code=500)
	{
		$this->_exception_code = $code;
	}

	public function setExceptionMsg($msg='Unexpected Error')
	{
		$this->_exception_msg = $msg;
	}

	public function getException()
	{
		return $this->_exception_code;
	}

	public function getExceptionMsg()
	{
		return $this->_exception_msg;
	}

	/**
	 * ======================================================================
	 * Table Name Setting
	 * ======================================================================
	 *
	 * Notice: 
	 * Beware of using input as parameter for injection 
	 *
	 * @param (string) $table_name: Table name in current database.
	 * @return (object) $this.
	 *
	 * @example
	 * 	$Model->table('user')->insert(array('name'=>'new'));
	 */
	public function table($table_name)
	{
		$this->_table = $table_name;

		return $this;
	}

	/**
	 * ======================================================================
	 * PDO Connection Object Get
	 * ======================================================================
	 *
	 * @param (string) $read_only: Get $db_r if true
	 * @return (mixed) PDO Connection Object ($db/$db_r)
	 */
	public function getPDO($read_only=false)
	{
		
		if ($read_only) {
			
			return $this->db_r;

		} else {

			return $this->db;
		}	
	}

	/**
	 * ======================================================================
	 * PDO Original Query Methods 
	 * ======================================================================
	 *
	 * Notice: 
	 * Beware of using SQL Query String in parameter for injection
	 *
	 * @param (string) $sql: SQL Query String
	 * @return (mixed) The return same as PDO Method
	 */

	public function query($sql)
	{
		return $this->db->query($sql);
	}

	public function exec($sql)
	{
		return $this->db->exec($sql);
	}

	public function prepare($sql)
	{
		return $this->db->prepare($sql);
	}

	/**
	 * ======================================================================
	 * PDO Prepare with Execute
	 * ======================================================================
	 *
	 * @param (string) $sql: SQL Query String
	 * @return (mixed) The return same as PDO Method
	 *
	 * @example 
	 * 	$model->prepareExecute("select * from {$model->getTable()} 
	 * 		where `id` = ?;", array(3));
	 */
	public function prepareExecute($sql, $var_array=array())
	{

		$result = $this->db->prepare($sql);

		$result->execute($var_array);

		return $result;
	}

	/**
	 * ======================================================================
	 * INSERT Statement
	 * ======================================================================
	 *
	 * @param (mixed) $params, Array of parameters
	 * @return (int) Last Insert ID
	 *
	 * @example
	 * 	$Model->insert(array('name'=>'new'));
	 */
	public function insert($params=array())
	{
		# Parameters Checking

		if (!is_array($params) || count($params)<=0) {

			return false;
		}


		# Parameters to SQL String

		$sql_columns = '';

		$sql_values = '';

		foreach ($params as $column => $value) {

			$sql_columns .= ($sql_columns ? ',' : '').sprintf("`%s`", $column);

			$sql_values .= ($sql_values ? ',' : '').sprintf(":%s", $column);

		}


		# Auto Create Datetime

		$sql_cdatetime_column = '';

		$sql_now = '';

		if ($this->_cdatetime_column) {

			$sql_cdatetime_column = sprintf(",`%s`", $this->_cdatetime_column);

			$sql_now = ',NOW()';

		}


		# SQL Query String

		$sql = sprintf("INSERT IGNORE INTO `%s` ( %s%s ) VALUES ( %s%s );", $this->_table, $sql_columns, $sql_cdatetime_column, $sql_values, $sql_now);
			
			if ($this->_debug)
				echo $sql.'<br/>';


		# SQL Processing

		$sth = $this->db->prepare($sql);


			# Parameters Binding

			foreach ($params as $key => $value) {

				$sth->bindValue(':'.$key, $value, PDO::PARAM_STR);

				if ($this->_debug) 
					echo $key.'=>'.$value.'<br/>';
			}	

		$result = $sth->execute();

    	if(!$result){

			return false;
		}

		return $this->db->lastInsertId();
	}

	/**
	 * ======================================================================
	 * UPDATE Statement
	 * ====================================================================== 
	 * 
	 * @param (mixed) $params, Array of parameters
	 * @param (mixed) $where, Array of conditions
	 * @return (int) Number of affected rows (return false if error)
	 *
	 * @example
	 * 	$Model->update(array('name'=>'new'), array('='=>array('id'=>1)));
	 */
	public function update($params, $where=NULL)
	{

		# Parameters Checking

		if (!is_array($params) || count($params)<=0) {

			return false;
		}


		# Parameters to SQL String

		$sql_set = '';

		foreach ($params as $column => $value) {

			$sql_set .= ($sql_set ? ',' : '').sprintf("`%s` = :%s", $column, $column);

		}


		# Conditions parsing to WHERE SQL string

		$where = self::parseCondition($where);

		$sql_where = ($where['cSQL']) ? 'WHERE '.$where['cSQL'] : '';


		# Auto Update Datetime

		$sql_udatetime = '';

		if ($this->_udatetime_column) {

			$sql_udatetime = sprintf(",`%s` = NOW()", $this->_udatetime_column);
		}


		# SQL Query String

		$sql = sprintf("UPDATE `%s` SET %s%s %s;", $this->_table, $sql_set, $sql_udatetime, $sql_where);

			if ($this->_debug)
				echo $sql.'<br/>';


		# SQL Processing

		$sth = $this->db->prepare($sql);

			# Parameters Binding

			foreach ($params as $column => $value) {

				$sth->bindValue(':'.$column, $value, PDO::PARAM_STR);
				
				if ($this->_debug) 
					echo $column.'=>'.$value.'<br/>';
			}


			# Condition Parameters Binding

			if ($where['cParams']) {

				foreach ($where['cParams'] as $key => $value) {

					$sth->bindValue($key, $value);
					
					if ($this->_debug) 
						echo $key.'=>'.$value.'<br/>';
				}
			}	
		
		$result = $sth->execute();

		if (!$result) {

			return false;
		}


		# Return Number of Affected Rows

		$result = $sth->rowCount();

		return $result;
	}

	/**
	 * ======================================================================
	 * DELETE Statement
	 * ======================================================================
	 * 
	 * @param (mixed) $where, Array of conditions
	 * @return (int) Number of affected rows (return false if error)
	 *
	 * @example
	 *	$Model->delete(array('='=>array('id'=>1)));
	 */
	public function delete($where=NULL)
	{
		
		# Conditions parsing to WHERE SQL string

		$where = self::parseCondition($where);

		$sql_where = ($where['cSQL']) ? 'WHERE '.$where['cSQL'] : '';


		# SQL Query String

		$sql = sprintf("DELETE FROM `%s` %s;", $this->_table, $sql_where);

			if ($this->_debug)
				echo $sql.'<br/>';


		# SQL Processing

		$sth = $this->db->prepare($sql);

			# Condition Parameters Binding

			if ($where['cParams']) {

				foreach ($where['cParams'] as $key => $value) {

					$sth->bindValue($key, $value);
					
					if ($this->_debug) 
						echo $key.'=>'.$value.'<br/>';
				}
			}	
		
		$result = $sth->execute();

		if (!$result) {

			return false;
		}


		# Return Number of Affected Rows

		$result = $sth->rowCount();

		return $result;
	}

	/**
	 * ======================================================================
	 * SELECT-ONE Statement
	 * ======================================================================
	 *
	 * @param (mixed) $where, Array of conditions
	 * @return (mixed) Array of selected row data
	 *
	 * @example
	 *	$Model->selectOne(array('='=>array('id'=>1)));
	 */
	public function selectOne($where=NULL, $columns=NULL)
	{
		
		# Columns parsing to COLUMNS SQL string
		$sql_column = self::parseColumn($columns);


		# Conditions parsing to WHERE SQL string

		$where = self::parseCondition($where);

		$sql_where = ($where['cSQL']) ? 'WHERE '.$where['cSQL'] : '';


		# SQL Query String

		$sql = sprintf("SELECT %s FROM `%s` %s;", $sql_column, $this->_table, $sql_where);

			if ($this->_debug) 
				echo $sql.'<br/>';


		# SQL Processing

		$sth = $this->db_r->prepare($sql);

			# Condition Parameters Binding

			if ($where['cParams']) {

				foreach ($where['cParams'] as $key => $value) {

					$sth->bindValue($key, $value);

					if ($this->_debug) 
						echo $key.'=>'.$value.'<br/>';
				}
			}	
		
		$result = $sth->execute();

		if (!$result) {

			return false;
		}

		return $sth->fetch($this->_pdo_fetch_type);
	}

	/**
	 * ======================================================================
	 * SELECT Statement
	 * ======================================================================
	 * 
	 * @param (mixed) $where, Array of conditions
	 * @param (mixed) $sort, Array of parameters for ORDER BY
	 * @param (mixed) $limit, Array of offset
	 * @param (mixed) $columns, Array of columns
	 * @return (mixed) Array of selected row data
	 *
	 * @example
	 * 	$Model->select(
	 * 				array('='=>array('gid'=>1)),
	 * 				array('date'=>'DESC'),
	 * 				array('page'=>1, 'limit'=>10),
	 * 				array('id','name')
	 * 				);
	 */
	public function select($where=NULL, $sort=NULL, $limit=NULL, $columns=NULL)
	{
		
		# Columns parsing to COLUMNS SQL string
		$sql_column = self::parseColumn($columns);


		# Conditions parsing to WHERE SQL string

		$where = self::parseCondition($where);

		$sql_where = ($where['cSQL']) ? 'WHERE '.$where['cSQL'] : '';

		# Sort data parsing to ORDER BY SQL string
		$sql_order = self::parseSort($sort);

		# Limit data parsing to LIMIT SQL string
		$sql_limit = self::parseLimit($limit);


		# SQL Query String

		$sql = sprintf("SELECT %s FROM `%s` %s %s %s;", $sql_column, $this->_table, $sql_where, $sql_order, $sql_limit);

			if ($this->_debug)
				echo $sql.'<br/>';


		# SQL Processing

		$sth = $this->db_r->prepare($sql);

			# Condition Parameters Binding

			if ($where['cParams']) {

				foreach ($where['cParams'] as $key => $value) {

					$sth->bindValue($key, $value);

					if ($this->_debug) 
						echo $key.'=>'.$value.'<br/>';
				}
			}	
		
		$result = $sth->execute();

		if (!$result) {

			return false;
		}

		return $sth->fetchAll($this->_pdo_fetch_type);
	}

	/**
	 * ======================================================================
	 * Left Join Statement
	 * ======================================================================
	 * 
	 * @param (mixed) $where_on, Array of join-on conditions
	 * @param (mixed) $where, Array of conditions
	 * @param (mixed) $sort, Array of parameters for ORDER BY
	 * @param (mixed) $limit, Array of offset
	 * @param (mixed) $columns, Array of columns
	 * @return (mixed) Array of selected row data
	 * 
	 * @example
	 *	$Model->leftJoin(
	 *		array('table2' => array('=' => array('table.id'=>'table2.uid')),
	 *			  'table3' => array('=' => array('table.id'=>'table3.id'))),
	 *		array('>' => array('table.id'=>5)),
	 *		array('date'=>'DESC'),
	 * 		array('page'=>1, 'limit'=>10),
	 * 		array('table.*','table2.uid','t3_id'=>'table3.id')	
	 *		);
	 *	// 'table' could be replace with $Model->getTable()
	 */

	public function leftJoin($join_data, $where=NULL, $sort=NULL, $limit=NULL, $columns=NULL)
	{

		# Join Data parsing to JOIN ON SQL string
		$sql_join = self::parserJoin($join_data, 'left');

		# Columns parsing to COLUMNS SQL string
		$sql_column = self::parseColumn($columns);


		# Conditions parsing to WHERE SQL string

		$where = self::parseCondition($where);

		$sql_where = ($where['cSQL']) ? 'WHERE '.$where['cSQL'] : '';

		# Sort data parsing to ORDER BY SQL string
		$sql_order = self::parseSort($sort);

		# Limit data parsing to LIMIT SQL string
		$sql_limit = self::parseLimit($limit);


		# SQL Query String

		$sql = sprintf("SELECT %s FROM `%s` %s %s %s %s;", 
			$sql_column, $this->_table, $sql_join, $sql_where, $sql_order, $sql_limit);

			if ($this->_debug)
				echo $sql.'<br/>';


		# SQL Processing

		$sth = $this->db_r->prepare($sql);	

			# Condition Parameters Binding

			if ($where['cParams']) {

				foreach ($where['cParams'] as $key => $value) {

					$sth->bindValue($key, $value);

					if ($this->_debug) 
						echo $key.'=>'.$value.'<br/>';
				}
			}	
		
		$result = $sth->execute();

		if (!$result) {

			return false;
		}

		return $sth->fetchAll($this->_pdo_fetch_type);
	}

	/**
	 * ======================================================================
	 * COUNT of SELECT Statement
	 * ======================================================================
	 *
	 * @param (mixed)  $where, Array of conditions
	 * @param (string) $column, Column name of query
	 * @return (mixed) Number of count
	 *
	 * @example
	 *  $Model->count(array('='=>array('gid'=>1));
	 *  $Model->count(array('>'=>array('gid'=>1), 'nickname');
	 */
	public function count($where=NULL, $column='*')
	{

		# Conditions parsing to WHERE SQL string

		$where = self::parseCondition($where);

		$sql_where = ($where['cSQL']) ? 'WHERE '.$where['cSQL'] : '';


		# SQL Query String

		$sql = sprintf("SELECT COUNT(%s) FROM `%s` %s;", self::brackets($column), $this->_table, $sql_where);

			if ($this->_debug)
				echo $sql.'<br/>';


		# SQL Processing

		$sth = $this->db_r->prepare($sql);

			# Condition Parameters Binding

			if ($where['cParams']) {

				foreach ($where['cParams'] as $key => $value) {

					$sth->bindValue($key, $value);

					if ($this->_debug) 
						echo $key.'=>'.$value.'<br/>';
				}
			}	
		
		$result = $sth->execute();

		if (!$result) {
			
			return false;
		}

		# Count Data Process
		$count = $sth->fetch(PDO::FETCH_ASSOC);
		$count = $count['COUNT(*)'];

		return $count;
	}

	/**
	 * ======================================================================
	 * SUM of SELECT Statement
	 * ======================================================================
	 *
	 * @param (mixed)  $where, Array of conditions
	 * @param (string) $column, Column name of query
	 * @return (mixed) Number of sum with the field
	 *
	 * @example
	 *  $Model->count(array('='=>array('gid'=>1));
	 *  $Model->count(array('>'=>array('gid'=>1), 'count');
	 */
	public function sum($where=NULL, $column='count')
	{

		# Conditions parsing to WHERE SQL string

		$where = self::parseCondition($where);

		$sql_where = ($where['cSQL']) ? 'WHERE '.$where['cSQL'] : '';


		# SQL Query String

		$sql = sprintf("SELECT SUM(%s) FROM `%s` %s;", self::brackets($column), $this->_table, $sql_where);

			if ($this->_debug)
				echo $sql.'<br/>';


		# SQL Processing

		$sth = $this->db_r->prepare($sql);

			# Condition Parameters Binding

			if ($where['cParams']) {

				foreach ($where['cParams'] as $key => $value) {

					$sth->bindValue($key, $value);

					if ($this->_debug) 
						echo $key.'=>'.$value.'<br/>';
				}
			}	
		
		$result = $sth->execute();

		if (!$result) {
			
			return false;
		}

		# Count Data Process
		$count = $sth->fetch(PDO::FETCH_NUM);
		$count = $count[0];

		return $count;
	}

	/**
	 * ======================================================================
	 * Counter Statement
	 * ======================================================================
	 * 
	 * @param (mixed) $where, Array of conditions
	 * @param (string) $params, Column with update
	 * @param (string) $column, Column name of counter
	 * @param (int) $count, Counter number
	 * @param (string) $op, Counter Operator
	 * @return (int) Number of affected rows (return false if error)
	 * 
	 * @example
	 * 	$Model->counter(array('='=>array('id'=>1)), 'count', 1, '-');
	 */
	public function counter($where=NULL, $params=NULL, $column='count', $count=1, $op='+')
	{

		# Counter SQL
		$sql_counter = sprintf("`%s` = `%s` %s %s", $column, $column, $op, $count);


		# Parameters to SQL String

		$sql_set = '';

		if ($params) {

			foreach ($params as $column => $value) {

				$sql_set .= sprintf(",`%s` = :%s", $column, $column);
			}
		}

		# Conditions parsing to WHERE SQL string

		$where = self::parseCondition($where);

		$sql_where = ($where['cSQL']) ? 'WHERE '.$where['cSQL'] : '';


		# Auto Update Datetime

		$sql_udatetime = '';

		if ($this->_udatetime_column) {

			$sql_udatetime = sprintf(",`%s` = NOW()", $this->_udatetime_column);

		}


		# SQL Query String

		$sql = sprintf("UPDATE `%s` SET %s%s%s %s;", $this->_table, $sql_counter, $sql_set, $sql_udatetime, $sql_where);

			if ($this->_debug)
				echo $sql.'<br/>';


		# SQL Processing

		$sth = $this->db->prepare($sql);

			# Parameters Binding
		
			if ($params) 

				foreach ($params as $column => $value) {

					$sth->bindValue(':'.$column, $value, PDO::PARAM_STR);
					
					if ($this->_debug) 
						echo $column.'=>'.$value.'<br/>';
				}


			# Condition Parameters Binding

			if ($where['cParams']) {

				foreach ($where['cParams'] as $key => $value) {

					$sth->bindValue($key, $value);
					
					if ($this->_debug) 
						echo $key.'=>'.$value.'<br/>';
				}
			}	
		
		$result = $sth->execute();

		if (!$result) {
			return false;
		}


		# Return Number of Affected Rows

		$result = $sth->rowCount();

		return $result;
	}

	/**
	 * ======================================================================
	 * SHOW COLUMNS Statement
	 * ======================================================================
	 *
	 * @param (boolean) $field_only: Only return array of single field data
	 * @param (string) 	$field_name: Set single field name if $field_only on
	 * @return (mixed) Columns data of the table
	 *
	 * @example
	 *  $Model->show_columns();
	 *  $Model->show_columns(true, 'Field');
	 */
	public function show_columns($field_only=false, $field_name='Field')
	{

		# SQL Query String

		$sql = sprintf("SHOW FULL COLUMNS FROM `%s`", $this->_table);

			if ($this->_debug)
				echo $sql.'<br/>';


		# SQL Processing

		$pdo = $this->db->query($sql);

		if (!$pdo) {

			return false;
		}

		$result = $pdo->fetchAll($this->_pdo_fetch_type);

		# Return array of field only
		if ($field_only && $result) {

			foreach ($result as $key => $column) {

				$result[$key] = $column[$field_name];
			}
		}

		return $result;
	}

	/**
	 * ======================================================================
	 * Conditions Parser
	 * ======================================================================
	 *
	 * Notice: 
	 * Beware of using input as key(column) in parameter for injection
	 * 
	 * @param (mixed) $conditions, Array of conditions
	 * @return (mixed) Array consist of (string)cSQL & (mixed)cParams
	 *
	 * @example
	 *	self::parseCondition(array('='=>array('gid'=>1,'mid'=>1)));
	 */
	private static function parseCondition($conditions=array())
	{
		
		# Acceptable Operators
		$operators = array(
						'eq'=>'=',
						'gt'=>'>',
						'lt'=>'<',
						'ge'=>'>=',
						'le'=>'<=',
						'ne'=>'!=',
						'like'=>'LIKE',
						'in'=>'IN',
						'nin'=>'NOT IN'
						);

		# Operators to Predicates for Bind-Key
		$predicates = array(
						'='=>'eq',
						'>'=>'gt',
						'<'=>'lt',
						'>='=>'ge',
						'<='=>'le',
						'!='=>'ne',
						'LIKE'=>'like',
						'IN'=>'in',
						'NOT IN'=>'nin'
						);

		if (is_array($conditions)) {

			$cParams=array(); # Params of Condition

			$cSQL='';         # SQL String of Condition

			foreach ($conditions as $op => $row) {

				# Row Array Check
				if (!is_array($row)) { return false; }

				foreach ($row as $column => $value) {

					# Operator Checking (Default Operator:'=')

					if (!in_array($op, $operators)) {

						$op = (isset($operators[$op])) ? $operators[$op] : '=';
					}

					switch ($op) {

						case 'IN':
						case 'NOT IN':

							# Check the value is array or not  
							if (is_array($value) && $value) {

								$params_key_str = '';

								foreach ($value as $key => $value_in) {

									$params_key = ":{$predicates[$op]}_".self::parameterName($column)."_{$key}"; # ex.':in_id_0'
									
									$cParams[$params_key] = $value_in;
									
									$params_key_str .= ($params_key_str ? ',' : '') . $params_key;
								
								}

								$cSQL .= ($cSQL ? ' AND ' : '') . sprintf("%s %s (%s)", self::brackets($column), $op, $params_key_str);
							
							} elseif ($value) {
								
								$params_key=":{$predicates[$op]}_".self::parameterName($column); # ex.':in_id'
								
								$cParams[$params_key]=$value;
								
								$cSQL .= ($cSQL ? ' AND ' : '') . sprintf("%s %s (%s)", self::brackets($column), $op, $params_key);
							
							}

							break;

						case 'LIKE':
							
							$params_key=":{$predicates[$op]}_".self::parameterName($column); # ex.':eq_id'
							
							$cParams[$params_key]=sprintf("%%%s%%",$value);
							
							$cSQL .= ($cSQL ? ' AND ' : '') . sprintf("%s %s %s", self::brackets($column), $op, $params_key);	
							
							break;
						
						default:

							$params_key=":{$predicates[$op]}_".self::parameterName($column); # ex.':eq_id'
							
							$cParams[$params_key]=$value;
							
							$cSQL .= ($cSQL ? ' AND ' : '') . sprintf("%s %s %s", self::brackets($column), $op, $params_key);	
							
							break;
					}
				}
			}

			return array('cSQL'=>$cSQL, 'cParams'=>$cParams);
		}
	}

	/**
	 * ======================================================================
	 * Sort Parser
	 * ======================================================================
	 * 
	 * @param (mixed) $sort, Array of sort data
	 * @return (string) ORDERY BY SQL string 
	 *
	 * @example
	 * 	self::parseSort(array('price'=>'asc','cdatetime'=>'desc'));
	 * 	self::parseSort(array('FIELD'=>array('id'=>array('3','1','2'))));
	 */
	private static function parseSort($sort=array())
	{
		if ($sort) {

			$sql = '';

			$sort = (is_array($sort)) ? $sort : array($sort) ;
			
			foreach ($sort as $key => $value) {

				$sql .= ($sql) ? ',' : '' ;

				if (is_numeric($key)) { // Array key is not defined

					$sql .= sprintf("`%s`", $value);

				} elseif (strtoupper($key) == 'FIELD') { // ORDER BY FIELD(`FIELD`, array())

					$sql_field_sort = '';

					foreach ($value as $field_key => $sort_value_list) {

						$sql_field_sort .= sprintf("`%s`", $field_key);
						
						foreach ($sort_value_list as $sort_value) {
							
							$sql_field_sort .= sprintf(",'%s'", $sort_value);
						}
					}

					$sql .= sprintf("%s(%s)", $key, $sql_field_sort);

				} elseif (in_array(strtoupper($value), array('ASC','DESC'))) {

					$sql .= sprintf("`%s` %s", $key, strtoupper($value));
				}
			}

			if ($sql) {
				
				$sql = sprintf("ORDER BY %s", $sql);
			}

			return $sql;
		}
	}

	/**
	 * ======================================================================
	 * Limit Parser
	 * ======================================================================
	 * 
	 * @param (mixed) $limit, Array of limit data
	 * @return (string) LIMIT SQL string 
	 *
	 * @example
	 *  self::parseLimit(array('page'=>'1','limit'=>'10')); # LIMIT 0,10
	 * 	self::parseLimit(array('from'=>'0','limit'=>'10')); # LIMIT 0,10
	 */
	private static function parseLimit($limit=array())
	{
		if ($limit) {

			$sql = '';

			$limit = (is_array($limit)) ? $limit : array('limit'=>$limit) ;
			
			if (isset($limit['limit']) && isset($limit['page'])) {

				$from = ($limit['page'] - 1) * $limit['limit'];

			} elseif (isset($limit['limit']) && isset($limit['from'])) {

				$from = $limit['from'];
			} 

			$from = (!isset($from) || $from < 0) ? 0 : floor($from);

			$limit = (!isset($limit['limit']) || $limit['limit'] < 1) ? 1 : floor($limit['limit']);

			$sql = sprintf("LIMIT %d,%d", $from, $limit);

			return $sql;
		}

	}

	/**
	 * ======================================================================
	 * Column Parser
	 * ======================================================================
	 *
	 * Notice: 
	 * Beware of using input as value(column) in parameter for injection
	 * 
	 * @param (mixed) $columns, Array of columns names
	 * @return (string) COLUMNS SQL string 
	 *
	 * @example
	 * 	self::parseColumn(array('id','name','phone'));
	 * 	self::parseColumn('id');
	 * 	self::parseColumn('table1.id');	// `table1`.`id`
	 * 	self::parseColumn(array('table1.*','t2_id'=>'table2.id'));
	 *	// Output: `table1`.*, `table2`.`id` as `t2_id`
	 */
	private static function parseColumn($columns=array())
	{
		$sql = '*';

		if ($columns) {

			if (is_array($columns) && count($columns) > 0) {
				
				$sql = '';

				foreach ($columns as $key => $column) {
					
					$sql .= (($sql) ? ',' : '') . sprintf("%s", self::brackets($column));

					# Alias of Column
					if (!is_numeric($key)) {
						
						$sql .= sprintf(" as `%s`", $key);
					}
				}

			} else 

				$sql = sprintf("%s", self::brackets($columns));
		}

		return $sql;
	}

	/**
	 * ======================================================================
	 * Join SQL Parser
	 * ======================================================================
	 *
	 * Notice: 
	 * Beware of using input as key or value in parameter for injection
	 * 
	 * @param (mixed) $join_data, Array of join data
	 * @return (string) SQL string of join
	 *
	 * @example
	 *  self::parserJoin(
	 *		array('table2' => array('=' => array('table.id'=>'table2.uid')),
	 *			  'table3' => array('=' => array('table.id'=>'table3.id')))	
	 *		);
	 */
	private static function parserJoin($join_data, $type=NULL)
	{
		
		# Acceptable Operators
		$operators = array(
						'eq'=>'=',
						'gt'=>'>',
						'lt'=>'<',
						'ge'=>'>=',
						'le'=>'<=',
						'ne'=>'!='
						);

		$sql = '';

		foreach ($join_data as $table => $on) {

			$sql_on = (isset($sql_on)) ? $sql_on : '';
			
			foreach ($on as $operator => $conditions) {

				foreach ($conditions as $column_left => $column_right) {
					
					if (!in_array($operator, $operators)) {

						$op = (isset($operators[$operator])) ? $operators[$operator] : '=';

					}
					
					$sql_on .= ($sql_on ? ' AND ' : '') . sprintf("%s %s %s", 
						self::brackets($column_left), $operator, self::brackets($column_right));					
				}	
			}

			switch (strtolower($type)) {

				case 'left':
					$type = 'LEFT ';
					break;

				case 'right':
					$type = 'RIGHT ';
					break;
				
				default:
					$type = '';
					break;
			}

			$sql .= ($sql ? ' ' : '') . sprintf("%sJOIN `%s` ON %s", $type, $table, $sql_on);
		}

		return $sql;
	}

	/**
	 * ======================================================================
	 * Parameter Name 
	 * ======================================================================
	 * 
	 * @param (string) $column, Column name without brackets
	 * @return (string) Parameter Name using in BindParam
	 *
	 * @example
	 *	self::parameterName('id'); 			# Output: id
	 * 	self::parameterName('table.id');	# Output: table_id
	 */
	private static function parameterName($column)
	{
		return str_replace('.', '_', $column);
	}

	/**
	 * ======================================================================
	 * Column Brackets
	 * ======================================================================
	 *
	 * Notice: 
	 * Beware of using input as parameter for injection
	 * 
	 * @param (string) $column, Column name without brackets
	 * @return (string) Column name with brackets
	 *
	 * @example
	 *	self::brackets('id'); 			# Output: `id`
	 * 	self::brackets('table.id');		# Output: `table`.`id`
	 */
	private static function brackets($column)
	{
		$sql = '';

		if (strpos($column, '.')) {
			
			$columns = explode('.', $column);

			foreach ($columns as $key => $each) {

				$sql .= ($sql ? '.' : '') . (strpos($each, '*')!==false ? "{$each}" : "`{$each}`");	
			}

		} else {

			$sql = (strpos($column, '*')!==false) ? "{$column}" : "`{$column}`";
		}	

		return $sql;
	}
	
}