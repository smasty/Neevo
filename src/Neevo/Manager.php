<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2013 Smasty (http://smasty.net)
 *
 */

namespace Neevo;

use Neevo\Cache\StorageInterface;
use SplObjectStorage;


/**
 * Core Neevo class.
 * @author Smasty
 */
class Manager implements ObservableInterface, ObserverInterface {


	/** @var string Default Neevo driver */
	public static $defaultDriver = 'mysqli';

	/** @var string */
	private $last;

	/** @var int */
	private $queries = 0;

	/** @var Connection */
	private $connection;

	/** @var SplObjectStorage */
	private $observers;


	// Neevo version
	const VERSION = '2.3.2';

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
	 * Configures Neevo and establish a connection.
	 * Configuration can be different - see the API for your driver.
	 * @param mixed $config Connection configuration.
	 * @param StorageInterface $cache Cache to use.
	 * @throws NeevoException
	 */
	public function __construct($config, StorageInterface $cache = null){
		$this->connection = new Connection($config, $cache);
		$this->observers = new SplObjectStorage;
		$this->attachObserver($this, self::QUERY);
	}


	/**
	 * SELECT statement factory.
	 * @param string|array $columns Array or comma-separated list (optional)
	 * @param string $table
	 * @return Result fluent interface
	 */
	public function select($columns = null, $table = null){
		$result = new Result($this->connection, $columns, $table);
		foreach($this->observers as $observer){
			$result->attachObserver($observer, $this->observers->getInfo());
		}
		return $result;
	}


	/**
	 * INSERT statement factory.
	 * @param string $table
	 * @param array|\Traversable $values
	 * @return Statement fluent interface
	 */
	public function insert($table, $values){
		$statement = Statement::createInsert($this->connection, $table, $values);
		foreach($this->observers as $observer){
			$statement->attachObserver($observer, $this->observers->getInfo());
		}
		return $statement;
	}


	/**
	 * UPDATE statement factory.
	 * @param string $table
	 * @param array|\Traversable $data
	 * @return Statement fluent interface
	 */
	public function update($table, $data){
		$statement = Statement::createUpdate($this->connection, $table, $data);
		foreach($this->observers as $observer){
			$statement->attachObserver($observer, $this->observers->getInfo());
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
			$statement->attachObserver($observer, $this->observers->getInfo());
		}
		return $statement;
	}


	/**
	 * Imports a SQL dump from given file.
	 * Based on implementation in Nette\Database.
	 * @copyright 2004 David Grudl, http://davidgrudl.com
	 * @license New BSD license
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
			throw new NeevoException("Cannot open file '$filename' for SQL import.");
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
		if(trim($sql)){
			$this->connection->getDriver()->runQuery($sql);
			$count++;
		}
		fclose($handle);
		ignore_user_abort($abort);
		return $count;
	}


	/**
	 * Begins a transaction if supported.
	 * @param string $savepoint
	 * @return Manager fluent interface
	 */
	public function begin($savepoint = null){
		$this->connection->getDriver()->beginTransaction($savepoint);
		$this->notifyObservers(ObserverInterface::BEGIN);
		return $this;
	}


	/**
	 * Commits statements in a transaction.
	 * @param string $savepoint
	 * @return Manager fluent interface
	 */
	public function commit($savepoint = null){
		$this->connection->getDriver()->commit($savepoint);
		$this->notifyObservers(ObserverInterface::COMMIT);
		return $this;
	}


	/**
	 * Rollbacks changes in a transaction.
	 * @param string $savepoint
	 * @return Manager fluent interface
	 */
	public function rollback($savepoint = null){
		$this->connection->getDriver()->rollback($savepoint);
		$this->notifyObservers(ObserverInterface::ROLLBACK);
		return $this;
	}


	/**
	 * Attaches an observer for debugging.
	 * @param ObserverInterface $observer
	 * @param int $event Event to attach the observer to.
	 */
	public function attachObserver(ObserverInterface $observer, $event){
		$this->observers->attach($observer, $event);
		$this->connection->attachObserver($observer, $event);
		$e = new NeevoException;
		$e->attachObserver($observer, $event);
	}


	/**
	 * Detaches given observer.
	 * @param ObserverInterface $observer
	 */
	public function detachObserver(ObserverInterface $observer){
		$this->connection->detachObserver($observer);
		$this->observers->detach($observer);
		$e = new NeevoException;
		$e->detachObserver($observer);
	}


	/**
	 * Notifies all observers attached to given event.
	 * @param int $event
	 */
	public function notifyObservers($event){
		foreach($this->observers as $observer){
			if($event & $this->observers->getInfo())
				$observer->updateStatus($this, $event);
		}
	}


	/**
	 * Receives update from observable subject.
	 * @param ObservableInterface $subject
	 * @param int $event Event type
	 */
	public function updateStatus(ObservableInterface $subject, $event){
		$this->last = (string) $subject;
		$this->queries++;
	}


	/**
	 * Returns current Connection instance.
	 * @return Connection
	 */
	public function getConnection(){
		return $this->connection;
	}


	/**
	 * Returns last executed query.
	 * @return string
	 */
	public function getLast(){
		return $this->last;
	}


	/**
	 * Returns the number of executed queries.
	 * @return int
	 */
	public function getQueries(){
		return $this->queries;
	}


	/**
	 * Highlights given SQL code.
	 * @param string $sql
	 * @return string
	 */
	public static function highlightSql($sql){
		$keywords1 = 'SELECT|UPDATE|INSERT\s+INTO|DELETE|FROM|VALUES|SET|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|(?:LEFT\s+|RIGHT\s+|INNER\s+)?JOIN';
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
			return '<strong style="color:#e71818">' . $match[2] . '</strong>';
		// Other keywords
		if(!empty($match[3]))
			return '<strong style="color:#d59401">' . $match[3] . '</strong>';
		// Values
		if(!empty($match[4]) || $match[4] === '0')
			return '<em style="color:#008000">' . $match[4] . '</em>';
	}


}
