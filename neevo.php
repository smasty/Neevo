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
 * @link       http://labs.smasty.net
 * @package    neevo
 * @version    0.03dev
 */


/** Main Neevo layer class
 * @package neevo
 */
class Neevo{

  var $resource_ID;
  var $queries = 0;
  var $last = '';
  var $error_reporting = 1;
  var $table_prefix = '';

  function __construct(array $opts){
    $connect = $this->connect($opts);
    $encoding = $this->set_encoding($opts['encoding']);
    $select_db = $this->select_db($opts['database']);
    if($opts['table_prefix']) $this->table_prefix = $opts['table_prefix'];
  }

  protected function connect(array $opts){
    $connection = @mysql_connect($opts['host'], $opts['username'], $opts['password']);
    $this->resource_ID = $connection;
    return (bool) $connection or self::error("Connection to host '".$opts['host']."' failed");
  }

  protected function set_encoding($encoding){
    if($encoding){
      $query = $this->query("SET NAMES $encoding", false);
      return (bool) $query;
    } else return true;
  }

  protected function select_db($db_name){
    $select = @mysql_select_db($db_name, $this->resource_ID);
    return (bool) $select or $this->error("Failed selecting database '$db_name'");
  }

  public function prefix($prefix = null){
    if(isset($prefix)) return $this->table_prefix = $prefix;
    else return $this->table_prefix;
  }

  public function error_reporting($value = null){
    if(isset($value)) return $this->error_reporting = $value;
    else return $this->error_reporting;
  }
  
  public final function query($query, $count = true){
    $q=@mysql_query($query, $this->resource_ID);
    $count ? $this->queries++ : false;
    $this->last=$query;
    return $q ? $q : $this->error("Query failed");
  }

  protected function error($err_neevo){
    $err_string=mysql_error();
    $err_no=mysql_errno();
    $err="<b>Neevo error</b> ($err_no) - ";
    $err.=$err_neevo;
    $err_string=str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use', 'Syntax error', $err_string);
    $err.=". $err_string";
    if($this->error_reporting) trigger_error($err, E_USER_WARNING);
    return false;
  }
}
?>