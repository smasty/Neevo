<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2011 Martin Srank (http://smasty.net)
 *
 */


/**
 * Core Neevo class.
 * @author Martin Srank
 * @package Neevo
 */
class Neevo implements INeevoObservable, INeevoObserver {


	/** @var string Default Neevo driver */
	public static $defaultDriver = 'mysql';

	/** @var string */
	private $last;

	/** @var int */
	private $queries = 0;

	/** @var NeevoConnection */
	private $connection;


	// Neevo revision
	const REVISION = 425;

	// Data types
	const BOOL = 'b';
	const INT = 'i';
	const FLOAT = 'f';
	const TEXT = 's';
	const BINARY = 'bin';
	const DATETIME = 'd';
	const ARR = 'a';
	const LITERAL = 'l';
	const IDENTIFIER = 'id';
	const SUBQUERY = 'sub';

	// Statement types
	const STMT_SELECT = 'stmt_select';
	const STMT_INSERT = 'stmt_insert';
	const STMT_UPDATE = 'stmt_update';
	const STMT_DELETE = 'stmt_delete';

	// JOIN types
	const JOIN_LEFT = 'join_left';
	const JOIN_INNER = 'join_inner';

	// Order types
	const ASC = 'ASC';
	const DESC = 'DESC';


	/**
	 * Configure Neevo and establish a connection.
	 * Configuration can be different - see the API for your driver.
	 * @param mixed $config Connection configuration.
	 * @param INeevoCache $cache Cache to use.
	 * @return void
	 * @throws NeevoException
	 */
	public function __construct($config, INeevoCache $cache = null){
		$this->connect($config, $cache);
	}


	/**
	 * Close connection to server.
	 * @return void
	 */
	public function __destruct(){
		try{
			$this->connection->getDriver()->close();
		} catch(NeevoImplemenationException $e){}
	}


	/**
	 * Establish a new connection.
	 * Configuration can be different - see the API for your driver.
	 * @param mixed $config Connection configuration.
	 * @param INeevoCache $cache Cache to use.
	 * @return Neevo fluent interface
	 * @throws NeevoException
	 */
	public function connect($config, INeevoCache $cache = null){
		$this->connection = new NeevoConnection($config, $cache);
		$this->connection->attachObserver($this);
		return $this;
	}


	/*  ************  Statement factories  ************  */

	/**
	 * SELECT statement factory.
	 * @param string|array $columns Array or comma-separated list (optional)
	 * @param string $table
	 * @return NeevoResult fluent interface
	 */
	public function select($columns = null, $table = null){
		return new NeevoResult($this->connection, $columns, $table);
	}


	/**
	 * INSERT statement factory.
	 * @param string $table
	 * @param array $values
	 * @return NeevoStmt fluent interface
	 */
	public function insert($table, array $values){
		return NeevoStmt::createInsert($this->connection, $table, $values);
	}


	/**
	 * UPDATE statement factory.
	 * @param string $table
	 * @param array $data
	 * @return NeevoStmt fluent interface
	 */
	public function update($table, array $data){
		return NeevoStmt::createUpdate($this->connection, $table, $data);
	}


	/**
	 * DELETE statement factory.
	 * @param string $table
	 * @return NeevoStmt fluent interface
	 */
	public function delete($table){
		return NeevoStmt::createDelete($this->connection, $table);
	}


	/**
	 * Import a SQL dump from given file.
	 * @param string $filename
	 * @return int Number of executed commands
	 */
	public function loadFile($filename){
		$this->connection->realConnect();
		$abort = ignore_user_abort();
		@set_time_limit(0);
		ignore_user_abort(true);

		$handle = @fopen($filename, 'r');
		if($handle === false){
			ignore_user_abort($abort);
			throw new NeevoException("Cannot open file '$filename'.");
		}

		$sql = '';
		$count = 0;
		while(!feof($handle)){
			$content = fgets($handle);
			$sql .= $content;
			if(substr(rtrim($content), -1) === ';'){
				// Passed directly to driver without logging.
				$this->connection->driver()->query($sql);
				$sql = '';
				$count++;
			}
		}
		fclose($handle);
		ignore_user_abort($abort);
		return $count;
	}


	/*  ************  Transactions  ************  */


	/**
	 * Begin a transaction if supported.
	 * @param string $savepoint
	 * @return void
	 */
	public function begin($savepoint = null){
		$this->connection->driver()->begin($savepoint);
		$this->notifyObservers(INeevoObserver::BEGIN);
	}


	/**
	 * Commit statements in a transaction.
	 * @param string $savepoint
	 * @return void
	 */
	public function commit($savepoint = null){
		$this->connection->driver()->commit($savepoint);
		$this->notifyObservers(INeevoObserver::COMMIT);
	}


	/**
	 * Rollback changes in a transaction.
	 * @param string $savepoint
	 * @return void
	 */
	public function rollback($savepoint = null){
		$this->connection->driver()->rollback($savepoint);
		$this->notifyObservers(INeevoObserver::ROLLBACK);
	}


	/*  ************  Implementation of INeevoObservable & INeevoObserver  ************  */


	/**
	 * Attach an observer for debugging.
	 * @param INeevoObserver $observer
	 * @param bool $exception Also attach observer to NeevoException
	 * @return void
	 */
	public function attachObserver(INeevoObserver $observer, $exception = true){
		$this->connection->attachObserver($observer);
		if($exception){
			NeevoException::attach($observer);
		}
	}


	/**
	 * Detach given observer.
	 * @param INeevoObserver $observer
	 * @return void
	 */
	public function detachObserver(INeevoObserver $observer){
		$this->connection->detachObserver($observer);
		NeevoException::detach($observer);
	}


	/**
	 * Notify all attached observers.
	 * @param int $event
	 * @return void
	 */
	public function notifyObservers($event){
		call_user_func_array(array($this->connection, 'notifyObservers'), func_get_args());
	}


	/**
	 * Receive update from observable.
	 * @param INeevoObservable $observable
	 * @param int $event Event type
	 * @return void
	 */
	public function updateStatus(INeevoObservable $observable, $event){
		if(func_num_args() < 3){
			return;
		}
		$statement = func_get_arg(2);
		if($statement instanceof NeevoStmtBase){
			$this->last = $statement->__toString();
			++$this->queries;
		}
	}


	/**
	 * Current NeevoConnection instance.
	 * @return NeevoConnection
	 */
	public function getConnection(){
		return $this->connection;
	}


	/**
	 * Last executed query.
	 * @return string
	 */
	public function getLast(){
		return $this->last;
	}


	/**
	 * Get number of executed queries.
	 * @return int
	 */
	public function getQueries(){
		return $this->queries;
	}


	/**
	 * Highlight given SQL code.
	 * @param string $sql
	 * @return string
	 */
	public static function highlightSql($sql){
		$keywords1 = 'SELECT|UPDATE|INSERT\s+INTO|DELETE|FROM|VALUES|SET|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|(?:LEFT |RIGHT |INNER )?JOIN';
		$keywords2 = 'RANDOM|RAND|ASC|DESC|USING|AND|OR|ON|IN|IS|NOT|NULL|LIKE|TRUE|FALSE|AS';

		$sql = str_replace("\\'", '\\&#39;', $sql);
		$sql = preg_replace_callback("~(/\\*.*\\*/)|($keywords1)|($keywords2)|('[^']+'|[0-9]+)~", array('Neevo', '_highlightCallback'), $sql);
		$sql = str_replace('\\&#39;', "\\'", $sql);
		return '<pre style="color:#555" class="sql-dump">' . trim($sql) . "</pre>\n";
	}


	private static function _highlightCallback($match){
		if(!empty($match[1])){ // /* comment */
			return '<em style="color:#999">' . $match[1] . '</em>';
		}
		if(!empty($match[2])){ // Basic keywords
			return "\n" . '<strong style="color:#e71818">' . $match[2] . '</strong>';
		}
		if(!empty($match[3])){ // Other keywords
			return '<strong style="color:#d59401">' . $match[3] . '</strong>';
		}
		if(!empty($match[4]) || $match[4] === '0'){ // Values
			return '<em style="color:#008000">' . $match[4] . '</em>';
		}
	}


}



/**
 * Representation of SQL literal.
 * @author Martin Srank
 * @package Neevo
 */
class NeevoLiteral {


	/** @var string */
	public $value;


	/**
	 * Create instance of SQL literal.
	 * @param string $value
	 * @return void
	 */
	public function __construct($value) {
		$this->value = $value;
	}


}
