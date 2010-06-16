<?php
/**
 * Neevo - Tiny open-source MySQL layer
 *
 * Copyright (c) 2010 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the GNU LGPL license that is bundled
 * with this package in the file license.txt.
 *
 * @copyright  Copyright (c) 2010 Martin Srank (http://smasty.net)
 * @license    http://www.gnu.org/copyleft/lesser.html  GNU LGPL
 * @link       http://labs.smasty.net/neevo/
 * @package    neevo
 * @version    0.01dev
 *
 * @todo mysql_data_seek;
 * @todo LOG files support;
 */


/** Main Neevo layer class
 * @package neevo
 */
class Neevo{

/*  VARIABLES  */

  /** @var resource Resource pointer */
  var $resource_ID;
  /** @var int Amount of executed queries */
  var $queries = 0;
  /** @var string Last executed query */
  var $last = '';
  /** @var int Error-reporting state (0/1) */
  var $error_reporting = 1;
  /** @var string Table prefix */
  var $table_prefix = '';
  /** @var array Connect options */
  private $options = array();

  /** @var array Array of HTML colors for SQL highlighter */
  static $highlight_colors = array(
    'background' => '#f9f9f9',
    'columns'    => '#0000ff',
    'chars'      => '#000000',
    'keywords'   => '#008000',
    'joins'      => '#555555',
    'functions'  => '#008000',
    'constants'  => '#ff0000'
    );


/*  CONSTANTS  */
  const ASSOC=1;
  const NUM=2;
  const OBJECT=3;


  /**
   * Constructor
   * @param array $opts Array of options in following format:
   * <pre>Array(
   *   host         =>  mysql_host,
   *   username     =>  mysql_username,
   *   password     =>  mysql_password,
   *   database     =>  mysql_database,
   *   encoding     =>  mysql_encoding,
   *   table_prefix =>  mysql_table_prefix
   * );</pre>
   */
  function __construct(array $opts){
    $connect = $this->connect($opts);
    $encoding = $this->set_encoding($opts['encoding']);
    $select_db = $this->select_db($opts['database']);
    if($opts['table_prefix']) $this->table_prefix = $opts['table_prefix'];
  }


  /**
   * Connect to database
   * @param array $opts
   * @return boolean
   */
  protected function connect(array $opts){
    $connection = @mysql_connect($opts['host'], $opts['username'], $opts['password']);
    $this->resource_ID = $connection;
    $this->options = $opts;
    return (bool) $connection or self::error("Connection to host '".$opts['host']."' failed");
  }


  /**
   * Sets table names/encoding
   * @param string $encoding
   * @return boolean
   */
  protected function set_encoding($encoding){
    if($encoding){
      $query = $this->query("SET NAMES $encoding", false);
      return (bool) $query;
    } else return true;
  }


  /**
   * Selects database to use
   * @param string $db_name
   * @return boolean
   */
  protected function select_db($db_name){
    $select = @mysql_select_db($db_name, $this->resource_ID);
    return (bool) $select or $this->error("Failed selecting database '$db_name'");
  }


  /**
   * Sets and/or returns table prefix
   * @param string $prefix
   * @return mixed
   */
  public function prefix($prefix = null){
    if(isset($prefix)) $this->table_prefix = $prefix;
    return $this->table_prefix;
  }


  /**
   * Sets and/or returns error-reporting
   * @param boolan $value
   * @return boolean
   */
  public function errors($value = null){
    if(isset($value)) $this->error_reporting = $value;
    return $this->error_reporting;
  }


  /**
   * Performs Query
   * @param string $query Query to perform
   * @param boolean $count Count this query or not?
   * @return resource
   */
  public final function query($query, $count = true){
    $q = @mysql_query($query, $this->resource_ID);
    $count ? $this->queries++ : false;
    $this->last_resource = $q;
    $this->last=$query;
    if($q) return $q;
    else return $this->error('Query failed');
  }


  /**
   * Creates NeevoMySQLQuery object for SELECT query
   * @param mixed $columns Array or comma-separated list of columns to select
   * @param string $table Database table to use for selecting
   * @return NeevoMySQLQuery
   */
  public final function select($columns, $table){
    $q = new NeevoMySQLQuery($this, 'select', $table);
    $q->cols($columns);
    return $q;
  }


  /**
   * Creates NeevoMySQLQuery object for INSERT query
   * @param string $table Database table to use for inserting
   * @param array $data Associative array of values to insert in format column_name=>column_value
   * @return NeevoMySQLQuery
   */
  public final function insert($table,array $data){
    $q = new NeevoMySQLQuery($this, 'insert', $table);
    $q->data($data);
    return $q;
  }


  /**
   * Creates NeevoMySQLQuery object for UPDATE query
   * @param string $table Database table to use for updating
   * @param array $data Associative array of values for update in format column_name=>column_value
   * @return NeevoMySQLQuery
   */
  public final function update($table,array $data){
    $q = new NeevoMySQLQuery($this, 'update', $table);
    $q->data($data);
    return $q;
  }


  /**
   * Creates NeevoMySQLQuery object for DELETE query
   * @param string $table Database table to use for deleting
   * @return NeevoMySQLQuery
   */
  public final function delete($table){
    return new NeevoMySQLQuery($this, 'delete', $table);
  }


  /** 
   * Fetches data from given Query resource
   * @param string $query MySQL (SELECT) query
   * @param int $type Result data as: Possible values:<pre>
   *    - Neevo::ASSOC  (1) - fetches an array with associative arrays as table rows
   *    - Neevo::NUM    (2) - fetches an array with numeric arrays as table rows
   *    - Neevo::OBJECT (3) - fetches an array with objects as table rows</pre>
   * @return array Associative array built from query
   */
  public function fetch($query, $type=1){
    $arr=array();
    if($type==1){ // Assoc
      while($tmp_arr=@mysql_fetch_assoc($query)){
        $arr[]=$tmp_arr;
      }
    }
    if($type==2){ // Numeric
      while($tmp_arr=@mysql_fetch_row($query)){
        $arr[]=$tmp_arr;
      }
    }
    if($type==3){ // Object
      while($tmp_arr=@mysql_fetch_object($query)){
        $arr[]=$tmp_arr;
      }
    }
    if(count($arr)==1) $arr = $arr[0];
    return $query ? $arr : $this->error("Fetching result data failed");
    @mysql_free_result($query);
  }


  /** Returns data from given MySQL query
   *
   * {@source}
   * @param string $query MySQL (SELECT) query
   * @param int $jump Jump to result row number (starts from 0)
   * @return string Result data
   */
  public function result($query, $jump=0){
    return $query ? @mysql_result($query, $jump) :$this->error("Return data failed");
    @mysql_free_result($query);
  }


  /**
   * Generates E_USER_WARNING
   * @param string $err_neevo
   * @return false
   */
  public function error($err_neevo){
    $err_string = mysql_error();
    $err_no = mysql_errno();
    $err = "<b>Neevo error</b> ($err_no) - ";
    $err .= $err_neevo;
    $err_string = str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use', 'Syntax error', $err_string);
    $err .= ". $err_string";
    if($this->errors()) trigger_error($err, E_USER_WARNING);
    return false;
  }

  /**
   * Returns some info about MySQL connection as an array or string
   * @param boolean $return_string Return as a string or not (default: no)
   * @param boolean $html Use HTML or not (default: no)
   * @return mixed
   */
  public function info($return_string = false, $html = false){
    $info = $this->options;
    unset($info['password']);
    $info['queries'] = $this->queries;
    $info['last'] = $this->last;
    $info['table_prefix'] = $this->prefix();
    $info['error_reporting'] = $this->errors();

    if(!$return_string) return $info;
    else{
      $er = array('off', 'on');
      
      $string = "Connected to database '{$info['database']}' on {$info['host']} as {$info['username']} user\n"
      . "Database encoding: {$info['encoding']}\n"
      . "Table prefix: {$info['table_prefix']}\n"
      . "Error-reporting: {$er[$info['error_reporting']]}\n"
      . "Executed queries: {$info['queries']}\n"
      . "Last executed query: {$info['last']}\n";

      return $html ? nl2br($string) : $string;
    }
  }

}




/** Neevo class for MySQL query abstraction
 * @package neevo
 */
class NeevoMySQLQuery {

  private $q_table, $q_type, $q_limit, $q_offset, $neevo, $q_resource, $q_time;
  private $q_where, $q_order, $q_columns, $q_data = array();


  /**
   * Constructor
   * @param array $options
   * @param string $type Query type. Possible values: select, insert, update, delete
   * @param string $table Table to interact with
   */
  function  __construct(Neevo $object, $type = '', $table = ''){
    $this->neevo = $object;

    $this->type($type);
    $this->table($table);
  }


  /**
   * Sets table to interact
   * @param string $table
   * @return NeevoMySQLQuery
   */
  public function table($table){
    $this->q_table = $table;
    return $this;
  }


  /**
   * Sets query type. Possibe values: select, insert, update, delete)
   * @param string $type
   * @return NeevoMySQLQuery
   */
  public function type($type){
    $this->q_type = $type;
    return $this;
  }


  /**
   * Sets columns to retrive in SELECT queries
   * @param mixed $columns
   * @return NeevoMySQLQuery
   */
  public function cols($columns){
    if(!is_array($columns)) $columns = explode(',', $columns);
    $cols = array();
    foreach ($columns as $col) {
      $col = trim($col);
      if($col!='*'){
        if(NeevoStatic::is_as_construction($col)) $col = NeevoStatic::escape_as_construction($col);
        elseif(NeevoStatic::is_sql_function($col)) $col = NeevoStatic::escape_sql_function($col);
        else $col = "`$col`";
      }
      $cols[] = $col;
    }
    $this->q_columns = $cols;
    return $this;
  }


  /**
   * Data for INSERT and UPDATE queries
   * @param array $data data in format "$column=>$value"
   * @return NeevoMySQLQuery
   */
  public function data(array $data){
    $this->q_data = $data;
    return $this;
  }


  /**
   * Sets WHERE condition for Query
   * @param string $where Column to use and optionaly operator: "email LIKE" or "email !="
   * @param string $value Value to search for: "%&#64;example.%" or "spam&#64;example.com"
   * @param string $glue Operator (AND, OR, etc.) to use betweet this and next WHERE condition
   * @return NeevoMySQLQuery
   */
  public function where($where, $value, $glue = null){

    $where_condition = explode(' ', $where);
    if(!isset($where_condition[1])) $where_condition[1] = '=';
    $column = $where_condition[0];

    if(NeevoStatic::is_sql_function($column)) $column = NeevoStatic::escape_sql_function($column);
    else $column = "`$column`";

    $value = NeevoStatic::escape_string($value);

    $condition = array($column, $where_condition[1], $value, strtoupper($glue));

    $this->q_where[] = $condition;

    return $this;
  }


  /**
   * Sets ORDER BY rule for Query
   * @param string $args [Infinite arguments] Order rules: "col_name ASC", "col_name" or "col_name DESC", etc...
   * @return NeevoMySQLQuery
   */
  public function order($args){
    $rules = array();
    $arguments = func_get_args();
    foreach ($arguments as $argument) {
      $order_rule = explode(' ', $argument);
      $order_rule[0] = "`".$order_rule[0]."`";
      $rules[] = $order_rule;
    }
    $this->q_order = $rules;

    return $this;
  }


  /**
   * Sets limit (and offset) for Query
   * @param int $limit Limit
   * @param int $offset Offset
   * @return NeevoMySQLQuery
   */
  public function limit($limit, $offset = null){
    $this->q_limit = $limit;
    if(isset($offset) && $this->q_type=='select') $this->q_offset = $offset;
    return $this;
  }


  /**
   * Prints consequential Query (highlighted by default)
   * @param bool $color Highlight query or not
   * @param bool $return_string Return the string or not
   */
  public function dump($color = true, $return_string = false){
    $code = $color ? NeevoStatic::highlight_sql($this->build()) : $this->build();
    if(!$return_string) echo $code;
    return $return_string ? $code : $this;
  }


  /**
   * Performs Query
   * @return resource
   */
  public function run(){
    $start = explode(" ", microtime());
    $query = $this->neevo->query($this->build());
    $end = explode(" ", microtime());
    $time = round(max(0, $end[0] - $start[0] + $end[1] - $start[1]), 4);
    $this->time($time);
    $this->q_resource = $query;
    return $query;
  }


  /**
   * Returns number of affected rows for INSERT/UPDATE/DELETE queries and number of rows in result for SELECT queries
   * @param bool $string Return rows as a string ("Rows: 5", "Affected: 10"). Default: FALSE
   * @return mixed Number of rows (int) or FALSE if used on invalid query.
   */
  public function rows($string = false){
    if($this->q_type!='select') $aff_rows = $this->time() ? @mysql_affected_rows($this->neevo->resource_ID) : false;
    else $num_rows = @mysql_num_rows($this->q_resource);
    
    if($num_rows || $aff_rows){
      if($string){
        return $num_rows ? "Rows: $num_rows" : "Affected: $aff_rows";
      }
      else return $num_rows ? $num_rows : $aff_rows;
    }
    else return false;
  }


  /**
   * Sets and/or returns Execution time of Query
   * @param int $time Time value to set.
   * @return int Query execution time
   */
  public function time($time = null){
    if(isset($time)) $this->q_time = $time;
    return $this->q_time;
  }

  /**
   * Returns some info about this Query as an array or string
   * @param boolean $return_string Return as a string or not (default: no)
   * @param boolean $html Use HTML or not (default: no)
   * @return mixed Info about Query
   */
  public function info($return_string = false, $html = false){
    $noexec = 'not yet executed';

    $exec_time = $this->time() ? $this->time() : -1;
    $rows = $this->time() ? $this->rows() : -1;

    $info = array(
      'resource' => $this->neevo->resource_ID,
      'query' => $this->dump($html, true),
      'exec_time' => $exec_time,
      'rows' => $rows
    );

    if(!$return_string) return $info;
    
    else{
      if($info['exec_time']==-1) $info['exec_time'] = $noexec;
      else $info['exec_time'] .= " seconds";

      if($info['rows']==-1) $info['rows'] = $noexec;
      $rows_prefix = ($this->q_type != 'select') ? "Affected" : "Fetched";

      if($html){
        $ot = "<strong>";
        $ct = "</strong>";
      }

      $string = "$ot Query-string:$ct {$info['query']}\n"
      . "$ot Resource:$ct {$info['resource']}\n"
      . "$ot Execution time:$ct {$info['exec_time']}\n"
      . "$ot $rows_prefix rows:$ct {$info['rows']}\n";

      return $html ? NeevoStatic::nlbr($string) : $string;
    }

  }


  /**
   * Builds Query from NeevoMySQLQuery instance
   * @return string the Query
   */
  public function build(){

    $table = $this->neevo->prefix().$this->q_table;

    // WHERE statements
    if($this->q_where){
      $wheres = array();
      $wheres2 = array();
      $wheres3 = array();

      // Set ADN glue for queries without glue defined
      foreach ($this->q_where as $in_where) {
        if($in_where[3]=='') $in_where[3] = 'AND';
        $wheres[] = $in_where;
      }

      // Unset glue for last WHERE clause (defind by former loop)
      unset($wheres[count($wheres)-1][3]);

      // Join WHERE clause array to one string
      foreach ($wheres as $in_where2) {
        $wheres2[] = join(' ', $in_where2);
      }
      // Remove some spaces
      foreach ($wheres2 as $rplc_where){
        $wheres3[] = str_replace(array(' = ', ' != '), array('=', '!='), $rplc_where);
      }

      $where = " WHERE ".join(' ', $wheres3);
    }

    // INSERT data
    if($this->q_type == 'insert' && $this->q_data){
      $icols=array();
      $ivalues=array();
      foreach(NeevoStatic::escape_array($this->q_data) as $col => $value){
        $icols[]="`$col`";
        $ivalues[]=$value;
      }
      $insert_data = " (".join(', ',$icols).") VALUES (".join(', ',$ivalues).")";
    }

    // UPDATE data
    if($this->q_type == 'update' && $this->q_data){
      $update=array();
      foreach(NeevoStatic::escape_array($this->q_data) as $col => $value){
        $update[]="`$col`=$value";
      }
      $update_data = " SET " . join(', ', $update);
    }
    
    // ORDER BY statements
    if($this->q_order){
      $orders = array();
      foreach($this->q_order as $in_order) {
        $orders[] = join(' ', $in_order);
      }
      $order = " ORDER BY ".join(', ', $orders);
    }

    // LIMIT & OFFSET
    if($this->q_limit) $limit = " LIMIT ".$this->q_limit;
    if($this->q_offset) $limit .= " OFFSET ".$this->q_offset;


    // SELECT query
    if($this->q_type == 'select'){
      $q .= 'SELECT ';
      $q .= join(', ', $this->q_columns);
      $q .= ' FROM `'.$table.'`';
      $q .= $where . $order . $limit;
    }

    // INSERT query
    if($this->q_type == 'insert'){
      $q .= 'INSERT INTO `' . $table . '`';
      $q .= $insert_data;
    }

    // UPDATE query
    if($this->q_type == 'update'){
      $q .= 'UPDATE `' . $table .'`';
      $q .= $update_data . $where . $order . $limit;
    }

    // DELETE query
    if($this->q_type == 'delete'){
      $q .= 'DELETE FROM `' . $table . '`';
      $q .= $where . $order . $limit;
    }

    return "$q;";

  }

}



/** Main Neevo class for some additional static methods
 * @package neevo
 */
class NeevoStatic extends Neevo {

  protected static $sql_functions=array('MIN', 'MAX', 'SUM', 'COUNT', 'AVG', 'CAST', 'COALESCE', 'CHAR_LENGTH', 'LENGTH', 'SUBSTRING', 'DAY', 'MONTH', 'YEAR', 'DATE_FORMAT', 'CRC32', 'CURDATE', 'SYSDATE', 'NOW', 'GETDATE', 'FROM_UNIXTIME', 'FROM_DAYS', 'TO_DAYS', 'HOUR', 'IFNULL', 'ISNULL', 'NVL', 'NVL2', 'INET_ATON', 'INET_NTOA', 'INSTR', 'FOUND_ROWS', 'LAST_INSERT_ID', 'LCASE', 'LOWER', 'UCASE', 'UPPER', 'LPAD', 'RPAD', 'RTRIM', 'LTRIM', 'MD5', 'MINUTE', 'ROUND', 'SECOND', 'SHA1', 'STDDEV', 'STR_TO_DATE', 'WEEK');

  /** Highlights given MySQL query */
  public static function highlight_sql($sql){
    $chcolors = array('chars'=>'black','keywords'=>'green','joins'=>'grey','functions'=>'green','constants'=>'red');
    $hcolors = self::$highlight_colors;
    unset($hcolors['columns']);unset($hcolors['background']);

    $words = array('keywords'=>array('SELECT', 'UPDATE', 'INSERT', 'DELETE', 'REPLACE', 'INTO', 'CREATE', 'ALTER', 'TABLE', 'DROP', 'TRUNCATE', 'FROM', 'ADD', 'CHANGE', 'COLUMN', 'KEY', 'WHERE', 'ON', 'CASE', 'WHEN', 'THEN', 'END', 'ELSE', 'AS', 'USING', 'USE', 'INDEX', 'CONSTRAINT', 'REFERENCES', 'DUPLICATE', 'LIMIT', 'OFFSET', 'SET', 'SHOW', 'STATUS', 'BETWEEN', 'AND', 'IS', 'NOT', 'OR', 'XOR', 'INTERVAL', 'TOP', 'GROUP BY', 'ORDER BY', 'DESC', 'ASC', 'COLLATE', 'NAMES', 'UTF8', 'DISTINCT', 'DATABASE', 'CALC_FOUND_ROWS', 'SQL_NO_CACHE', 'MATCH', 'AGAINST', 'LIKE', 'REGEXP', 'RLIKE', 'PRIMARY', 'AUTO_INCREMENT', 'DEFAULT', 'IDENTITY', 'VALUES', 'PROCEDURE', 'FUNCTION', 'TRAN', 'TRANSACTION', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'TRIGGER', 'CASCADE', 'DECLARE', 'CURSOR', 'FOR', 'DEALLOCATE'),
      'joins' => array('JOIN', 'INNER', 'OUTER', 'FULL', 'NATURAL', 'LEFT', 'RIGHT'),
      'functions' => self::$sql_functions,
      'chars' => '/([\\.,;!\\(\\)<>:=`]+)/i',
      'constants' => '/(\'[^\']*\'|[0-9]+)/i');

    $sql=str_replace('\\\'','\\&#039;',$sql);

    foreach($chcolors as $key=>$color){
      $regexp=in_array($key,array('constants','chars')) ? $words[$key] : '/\\b('.join("|",$words[$key]).')\\b/i';
      $sql=preg_replace($regexp, "<span style=\"color:$color\">$1</span>",$sql);
    }

    $sql = str_replace($chcolors, $hcolors, $sql);
    return "<code style=\"color:".self::$highlight_colors['columns'].";background:".self::$highlight_colors['background']."\"> $sql </code>\n";
  }

  /** Replaces placeholders in string with value/s from array/string (not used!)
   *
   * @param string String with placeholders '%1, etc.'
   * @param mixed Array/string with values to replace
   * @return string Replaced string
   */
  public static function printf($string, $values){
    preg_match_all("/\%(\d*)/", $string, $replace);
    return str_replace($replace[0], is_array($values) ? self::escape_array($values) : self::escape_string($values), $string);
  }

  /** Escapes whole array for use in MySQL */
  public static function escape_array(array $array){
    $result=array();
    foreach($array as $key => $value){
       $result[$key] = is_numeric($value) ? $value : ( is_string($value) ? self::escape_string($value) : ( is_array($value) ? self::escape_array($value) : $value ) );
    }
    return $result;
  }

  /** Escapes given string for use in MySQL */
  public static function escape_string($string){
    $string=str_replace('\'', '\\\'' ,$string);
    return is_string($string) ? ( self::is_sql_function($string) ? self::escape_sql_function($string) : "'$string'" ) : $string;
  }

  /** Checks whether a given string is a SQL function or not */
  public static function is_sql_function($string){
    if(is_string($string)){
      $is_plmn = preg_match("/^(\w*)(\+|-)(\w*)/", $string);
      $var = is_string($string) ? strtoupper(preg_replace('/[^a-zA-Z0-9_\(\)]/','',$string)) : false;
      $is_sql = in_array(preg_replace('/\(.*\)/','',$var), self::$sql_functions);
      return ($is_sql || $is_plmn) ? true : false;
    }
    else return false;

  }

  /** Escapes given SQL function  */
  public static function escape_sql_function($sql_function){
    return str_replace(array('("','")'), array('(\'','\')'), $sql_function);
  }

  /** Checks whether a given string is a MySQL 'AS construction' ([SELECT] cars AS vegetables) */
  public static function is_as_construction($string){
    return is_string($string) ? ( preg_match('/(.*) as \w*/i', $string) ? true : false ) :  false;
  }

  /** Escapes (quotes column name with ``) given 'AS construction' */
  public static function escape_as_construction($as_construction){
    $construction=explode(' ', $as_construction);
    $escape = preg_match('/^\w{1,}$/', $construction[0]) ? true : false;
    if($escape){
      $construction[0]="`".$construction[0]."`";
    }
    $as_construction=join(' ', $construction);
    return preg_replace('/(.*) (as) (\w*)/i','$1 AS `$3`',$as_construction);
  }

  public static function nlbr($string){
    $string = str_replace("\n\n", "\n", $string);
    return str_replace("\n","<br>", $string);
  }

  /** Returns content of the file */
  public static function read_file($filename) {
    if (!function_exists('file_get_contents')) {
      $fhandle = fopen($filename, "r");
      $fcontents = fread($fhandle, filesize($filename));
      fclose($fhandle);
    }
    else $content = file_get_contents($filename);

    return $content;
  }

  /** Puts content to the file */
  public static function write_file($filename, $data) {
    if(!function_exists('file_put_contents')){
      $f = @fopen($filename, 'w');
      if (!$f) return false;
      else {
        $content = fwrite($f, $data);
        fclose($f);
      }
    }
    else $content = file_put_contents($filename, $data);

    return $content;
  }

}

?>