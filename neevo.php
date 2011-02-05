<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010-2011 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * @author   Martin Srank (http://smasty.net)
 * @license  http://neevo.smasty.net/license  MIT license
 * @link     http://neevo.smasty.net/
 *
 */


// PHP compatibility
if(version_compare(PHP_VERSION, '5.2.0', '<')){
  trigger_error('Neevo requires PHP version 5.2.0 or newer', E_USER_ERROR);
}


// Nette Framework compatibility
if(interface_exists('Nette\IDebugPanel')){
  class_alias('Nette\IDebugPanel', 'IDebugPanel');
}
if(!interface_exists('IDebugPanel')){
  /** Nette Framework compatibility. */
  interface IDebugPanel{
    function getId();
    function getTab();
    function getPanel();
  }
}


// Neevo classes autoloader
spl_autoload_register('_neevo_autoload');

@set_magic_quotes_runtime(FALSE);



/**
 * Core Neevo class.
 * @author Martin Srank
 * @package Neevo
 */
class Neevo implements INeevoObservable, INeevoObserver {

  /** @var string */
  private $last;

  /** @var int */
  private $queries = 0;

  /** @var NeevoConnection */
  private $connection;

  /** @var string Default Neevo driver */
  public static $defaultDriver = 'mysql';

  // Neevo revision
  const REVISION = 348;

  // Data types
  const BOOL = 'b';
  const INT = 'i';
  const FLOAT = 'f';
  const TEXT = 't';
  const BINARY = 'bin';
  const DATETIME = 'd';

  // Statement types
  const STMT_SELECT = 'stmt_select';
  const STMT_INSERT = 'stmt_insert';
  const STMT_UPDATE = 'stmt_update';
  const STMT_DELETE = 'stmt_delete';

  // JOIN types
  const JOIN_LEFT = 'join_left';
  const JOIN_INNER = 'join_inner';

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

    // Nette Framework
    if($this->connection->getConfig('observer') !== false && defined('NETTE')){
      $this->attachObserver(new NeevoObserver);
    }
    return $this;
  }

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
   * @return int|NeevoStmt Last inserted ID or NeevoStmt on lazy connection
   */
  public function insert($table, array $values){
    $q = new NeevoStmt($this->connection);
    $ins = $q->insert($table, $values);
    return $this->connection->getConfig('lazy')
      ? $ins : $ins->insertId();
  }

  /**
   * UPDATE statement factory.
   * @param string $table
   * @param array $data
   * @return NeevoStmt fluent interface
   */
  public function update($table, array $data){
    $q = new NeevoStmt($this->connection);
    return $q->update($table, $data);
  }

  /**
   * DELETE statement factory.
   * @param string $table
   * @return NeevoStmt fluent interface
   */
  public function delete($table){
    $q = new NeevoStmt($this->connection);
    return $q->delete($table);
  }

  /**
   * Import a SQL dump from given file.
   * @param string $filename
   * @return int Number of executed commands
   */
  public function loadFile($filename){
    $this->connection->realConnect();
    @set_time_limit(0);

    $handle = @fopen($filename, 'r');
    if($handle === false){
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
    return $count;
  }

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

  public function notifyObservers($event, NeevoStmtBase $statement = null){
    $this->connection->notifyObservers($event, $statement);
  }

  public function updateStatus(INeevoObservable $observable, $event, NeevoStmtBase $statement = null){
    if($statement instanceof NeevoStmtBase){
      $this->last = (string) $statement;
      ++$this->queries;
    }
  }

  /**
   * Current NeevoConnection instance.
   * @return NeevoConnection
   */
  public function connection(){
    return $this->connection;
  }

  /**
   * Last executed query.
   * @return string
   */
  public function last(){
    return $this->last;
  }

  /**
   * Get number of executed queries.
   * @return int
   */
  public function queries(){
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
    $sql = preg_replace_callback("~($keywords1)|($keywords2)|('[^']+'|[0-9]+)|(/\*.*\*/)|(--\s?[^;]+)|(#[^;]+)~", array('Neevo', '_highlightCallback'), $sql);
    $sql = str_replace('\\&#39;', "\\'", $sql);
    return '<code style="color:#555" class="sql-dump">' . $sql . "</code>\n";
  }

  private static function _highlightCallback($match){
    if(!empty($match[1])){ // Basic keywords
      return '<strong style="color:#e71818">'.$match[1].'</strong>';
    }
    if(!empty($match[2])){ // Other keywords
      return '<strong style="color:#d59401">'.$match[2].'</strong>';
    }
    if(!empty($match[3])){ // Values
      return '<em style="color:#008000">'.$match[3].'</em>';
    }
    if(!empty($match[4])){ // /* comment */
      return '<em style="color:#999">'.$match[4].'</em>';
    }
    if(!empty($match[5])){ // -- comment
      return '<em style="color:#999">'.$match[5].'</em>';
    }
    if(!empty($match[6])){ // # comment
      return '<em style="color:#999">'.$match[6].'</em>';
    }
  }

  /**
   * Close connection to server.
   * @return void
   */
  public function  __destruct(){
    try{
      $this->connection->driver()->close();
    } catch(NeevoImplemenationException $e){}
  }

}



/**
 * Representation of SQL literal.
 * @author Martin Srank
 * @package Neevo
 */
class NeevoLiteral {
  public $value;
  public function __construct($value) {
    $this->value = $value;
  }
}



/**
 * Neevo classes autoloader.
 * @param string $class
 * @return bool
 * @author Martin Srank
 */
function _neevo_autoload($class){
  static $classes = array(
    'neevo' => 'neevo.php',
    'neevoliteral' => 'neevo.php',
    'neevoexception' => '/neevo/NeevoException.php',
    'neevodriverexception' => '/neevo/NeevoException.php',
    'neevoimplementationexception' => '/neevo/NeevoException.php',
    'neevoconnection' => '/neevo/NeevoConnection.php',
    'ineevocache' => '/neevo/NeevoCache.php',
    'neevocache' => '/neevo/NeevoCache.php',
    'neevocacheapc' => '/neevo/NeevoCache.php',
    'neevocachedb' => '/neevo/NeevoCache.php',
    'neevocachefile' => '/neevo/NeevoCache.php',
    'neevocacheinclude' => '/neevo/NeevoCache.php',
    'neevocachememcache' => '/neevo/NeevoCache.php',
    'neevocachesession' => '/neevo/NeevoCache.php',
    'neevostmtbase' => '/neevo/NeevoStmtBase.php',
    'neevostmtparser' => '/neevo/NeevoStmtParser.php',
    'neevostmt' => '/neevo/NeevoStmt.php',
    'neevoresult' => '/neevo/NeevoResult.php',
    'neevoresultiterator' => '/neevo/NeevoResultIterator.php',
    'neevorow' => '/neevo/NeevoRow.php',
    'ineevodriver' => '/neevo/INeevoDriver.php',
    'ineevoobserver' => '/neevo/NeevoObserver.php',
    'neevoobserver' => '/neevo/NeevoObserver.php',
    'ineevoobservable' => '/neevo/NeevoObserver.php'
  );
  $class = strtolower($class);

  if(isset($classes[$class])){
    return include_once dirname(__FILE__).$classes[$class];
  }
  return false;
}
