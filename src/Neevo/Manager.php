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

namespace Neevo;


/**
 * Core Neevo class.
 * @author Martin Srank
 */
class Manager implements IObservable, IObserver {


	/** @var string Default Neevo driver */
	public static $defaultDriver = 'mysqli';

	/** @var string */
	private $last;

	/** @var int */
	private $queries = 0;

	/** @var Connection */
	private $connection;

	/** @var ObserverMap */
	private $observers;


	// Neevo version
	const VERSION = '2.0',
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
	 * @param ICache $cache Cache to use.
	 * @return void
	 * @throws NeevoException
	 */
	public function __construct($config, ICache $cache = null){
		$this->connection = new Connection($config, $cache);
		$this->observers = new ObserverMap;
		$this->attachObserver($this, self::QUERY);
	}

	/*	 * ***********  Statement factories  ************  */


	/**
	 * SELECT statement factory.
	 * @param string|array $columns Array or comma-separated list (optional)
	 * @param string $table
	 * @return Result fluent interface
	 */
	public function select($columns = null, $table = null){
		$result = new Result($this->connection, $columns, $table);
		foreach($this->observers as $observer){
			$result->attachObserver($observer, $this->observers->getEvent());
		}
		return $result;
	}


	/**
	 * INSERT statement factory.
	 * @param string $table
	 * @param array $values
	 * @return Statement fluent interface
	 */
	public function insert($table, array $values){
		$statement = Statement::createInsert($this->connection, $table, $values);
		foreach($this->observers as $observer){
			$statement->attachObserver($observer, $this->observers->getEvent());
		}
		return $statement;
	}


	/**
	 * UPDATE statement factory.
	 * @param string $table
	 * @param array $data
	 * @return Statement fluent interface
	 */
	public function update($table, array $data){
		$statement = Statement::createUpdate($this->connection, $table, $data);
		foreach($this->observers as $observer){
			$statement->attachObserver($observer, $this->observers->getEvent());
		}
		return $statement;
	}


	/**
	 * DELETE statement factory.
	 * @param string $table
	 * @return Statement fluent interface
	 */
	public function delete($table){
		$statement = Statement::createDelete($this->connection, $table);
		foreach($this->observers as $observer){
			$statement->attachObserver($observer, $this->observers->getEvent());
		}
		return $statement;
	}


	/**
	 * Import a SQL dump from given file.
	 *
	 * Based on implementation in Nette\Database,
	 * Copyright (c) 2004, 2011 David Grudl (http://davidgrudl.com), new BSD license.
	 * @copyright 2004, 2011 David Grudl
	 * @param string $filename
	 * @return int Number of executed commands
	 */
	public function loadFile($filename){
		$this->connection->connect();
		$abort = ignore_user_abort();
		@set_time_limit(0);
		ignore_user_abort(true);

		$handle = @fopen($filename, 'r');
		if($handle === false){
			ignore_user_abort($abort);
			throw new Exception("Cannot open file '$filename' for SQL import.");
		}

		$sql = '';
		$count = 0;
		while(!feof($handle)){
			$content = fgets($handle);
			$sql .= $content;
			if(substr(rtrim($content), -1) === ';'){
				// Passed directly to driver without logging.
				$this->connection->getDriver()->runQuery($sql);
				$sql = '';
				$count++;
			}
		}
		fclose($handle);
		ignore_user_abort($abort);
		return $count;
	}

	/*	 * ***********  Transactions  ************  */


	/**
	 * Begin a transaction if supported.
	 * @param string $savepoint
	 * @return Neevo fluent interface
	 */
	public function begin($savepoint = null){
		$this->connection->getDriver()->beginTransaction($savepoint);
		$this->notifyObservers(IObserver::BEGIN);
		return $this;
	}


	/**
	 * Commit statements in a transaction.
	 * @param string $savepoint
	 * @return Neevo fluent interface
	 */
	public function commit($savepoint = null){
		$this->connection->getDriver()->commit($savepoint);
		$this->notifyObservers(IObserver::COMMIT);
		return $this;
	}


	/**
	 * Rollback changes in a transaction.
	 * @param string $savepoint
	 * @return Neevo fluent interface
	 */
	public function rollback($savepoint = null){
		$this->connection->getDriver()->rollback($savepoint);
		$this->notifyObservers(IObserver::ROLLBACK);
		return $this;
	}

	/*	 * ***********  Implementation of IObservable & IObserver  ************  */


	/**
	 * Attach an observer for debugging.
	 * @param IObserver $observer
	 * @param int $event Event to attach the observer to.
	 * @return void
	 */
	public function attachObserver(IObserver $observer, $event){
		$this->observers->attach($observer, $event);
		$this->connection->attachObserver($observer, $event);
		$e = new NeevoException;
		$e->attachObserver($observer, $event);
	}


	/**
	 * Detach given observer.
	 * @param IObserver $observer
	 * @return void
	 */
	public function detachObserver(IObserver $observer){
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
			if($event & $this->observers->getEvent())
				$observer->updateStatus($this, $event);
		}
	}


	/**
	 * Receive update from observable.
	 * @param IObservable $observable
	 * @param int $event Event type
	 * @return void
	 */
	public function updateStatus(IObservable $observable, $event){
		$this->last = $observable->__toString();
		++$this->queries;
	}


	/**
	 * Current Connection instance.
	 * @return Connection
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
		$sql = preg_replace_callback("~(/\\*.*\\*/)|($keywords1)|($keywords2)|('[^']+'|[0-9]+)~", 'self::_highlightCallback', $sql);
		$sql = str_replace('\\&#39;', "\\'", $sql);
		return '<pre style="color:#555" class="sql-dump">' . trim($sql) . "</pre>\n";
	}


	private static function _highlightCallback($match){
		// Comment
		if(!empty($match[1]))
			return '<em style="color:#999">' . $match[1] . '</em>';
		// Basic keywords
		if(!empty($match[2]))
			return "\n" . '<strong style="color:#e71818">' . $match[2] . '</strong>';
		// Other keywords
		if(!empty($match[3]))
			return '<strong style="color:#d59401">' . $match[3] . '</strong>';
		// Values
		if(!empty($match[4]) || $match[4] === '0')
			return '<em style="color:#008000">' . $match[4] . '</em>';
	}


}
