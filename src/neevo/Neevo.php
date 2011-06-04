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

	/** @var NeevoObserverMap */
	private $observers;


	// Neevo version
	const VERSION = '1.0-dev',
		REVISION = '@VCREV@ released on @VCDATE@';

	// Data types
	const BOOL = 'b',
		INT = 'i',
		FLOAT = 'f',
		TEXT = 's',
		BINARY = 'bin',
		DATETIME = 'd',
		ARR = 'a',
		LITERAL = 'l',
		IDENTIFIER = 'id',
		SUBQUERY = 'sub';

	// Statement types
	const STMT_SELECT = 'stmt_select',
		STMT_INSERT = 'stmt_insert',
		STMT_UPDATE = 'stmt_update',
		STMT_DELETE = 'stmt_delete';

	// JOIN types
	const JOIN_LEFT = 'join_left',
		JOIN_INNER = 'join_inner';

	// Order types
	const ASC = 'ASC',
		DESC = 'DESC';


	/**
	 * Configure Neevo and establish a connection.
	 * Configuration can be different - see the API for your driver.
	 * @param mixed $config Connection configuration.
	 * @param INeevoCache $cache Cache to use.
	 * @return void
	 * @throws NeevoException
	 */
	public function __construct($config, INeevoCache $cache = null){
		$this->connection = new NeevoConnection($config, $cache);
		$this->observers = new NeevoObserverMap;
		$this->attachObserver($this, self::QUERY);
	}


	/**
	 * Close connection to server.
	 * @return void
	 */
	public function __destruct(){
		try{
			$this->connection->getDriver()->closeConnection();
		} catch(NeevoImplemenationException $e){}
	}


	/*  ************  Statement factories  ************  */

	/**
	 * SELECT statement factory.
	 * @param string|array $columns Array or comma-separated list (optional)
	 * @param string $table
	 * @return NeevoResult fluent interface
	 */
	public function select($columns = null, $table = null){
		$result = new NeevoResult($this->connection, $columns, $table);
		foreach($this->observers as $observer){
			$result->attachObserver($observer, $this->observers->getEvent());
		}
		return $result;
	}


	/**
	 * INSERT statement factory.
	 * @param string $table
	 * @param array $values
	 * @return NeevoStmt fluent interface
	 */
	public function insert($table, array $values){
		$statement = NeevoStmt::createInsert($this->connection, $table, $values);
		foreach($this->observers as $observer){
			$statement->attachObserver($observer, $this->observers->getEvent());
		}
		return $statement;
	}


	/**
	 * UPDATE statement factory.
	 * @param string $table
	 * @param array $data
	 * @return NeevoStmt fluent interface
	 */
	public function update($table, array $data){
		$statement = NeevoStmt::createUpdate($this->connection, $table, $data);
		foreach($this->observers as $observer){
			$statement->attachObserver($observer, $this->observers->getEvent());
		}
		return $statement;
	}


	/**
	 * DELETE statement factory.
	 * @param string $table
	 * @return NeevoStmt fluent interface
	 */
	public function delete($table){
		$statement = NeevoStmt::createDelete($this->connection, $table);
		foreach($this->observers as $observer){
			$statement->attachObserver($observer, $this->observers->getEvent());
		}
		return $statement;
	}


	/*  ************  Transactions  ************  */


	/**
	 * Begin a transaction if supported.
	 * @param string $savepoint
	 * @return void
	 */
	public function begin($savepoint = null){
		$this->connection->getDriver()->beginTransaction($savepoint);
		$this->notifyObservers(INeevoObserver::BEGIN);
	}


	/**
	 * Commit statements in a transaction.
	 * @param string $savepoint
	 * @return void
	 */
	public function commit($savepoint = null){
		$this->connection->getDriver()->commit($savepoint);
		$this->notifyObservers(INeevoObserver::COMMIT);
	}


	/**
	 * Rollback changes in a transaction.
	 * @param string $savepoint
	 * @return void
	 */
	public function rollback($savepoint = null){
		$this->connection->getDriver()->rollback($savepoint);
		$this->notifyObservers(INeevoObserver::ROLLBACK);
	}


	/*  ************  Implementation of INeevoObservable & INeevoObserver  ************  */


	/**
	 * Attach an observer for debugging.
	 * @param INeevoObserver $observer
	 * @param int $event Event to attach the observer to.
	 * @return void
	 */
	public function attachObserver(INeevoObserver $observer, $event){
		$this->observers->attach($observer, $event);
		$this->connection->attachObserver($observer, $event);
		$e = new NeevoException;
		$e->attachObserver($observer, $event);
	}


	/**
	 * Detach given observer.
	 * @param INeevoObserver $observer
	 * @return void
	 */
	public function detachObserver(INeevoObserver $observer){
		$this->connection->detachObserver($observer);
		$this->observers->detach($observer);
		$e = new NeevoException;
		$e->detachObserver($observer);
	}


	/**
	 * Notify all observers attached to given event.
	 * @param int $event
	 * @return void
	 */
	public function notifyObservers($event){
		foreach($this->observers as $observer){
			if($event & $this->observers->getEvent()){
				$observer->updateStatus($this, $event);
			}
		}
	}


	/**
	 * Receive update from observable.
	 * @param INeevoObservable $observable
	 * @param int $event Event type
	 * @return void
	 */
	public function updateStatus(INeevoObservable $observable, $event){
		$this->last = $observable->__toString();
		++$this->queries;
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
