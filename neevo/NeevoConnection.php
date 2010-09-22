<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * @author   Martin Srank (http://smasty.net)
 * @license  http://www.opensource.org/licenses/mit-license.php  MIT license
 * @link     http://neevo.smasty.net/
 * @package  Neevo
 *
 */

/**
 * Neevo driver class
 * @package Neevo
 */
class NeevoConnection{

  private $neevo, $driver, $user, $pswd, $host, $database, $encoding, $table_pefix;


  public function __construct(Neevo $neevo, NeevoDriver $driver, $user = null, $pswd = null, $host = null, $database = null, $encoding = null, $table_prefix = null){
    $this->neevo = $neevo;
    $this->driver = $driver;
    $this->user = $user;
    $this->pswd = $pswd;
    $this->host = $host;
    $this->database = $database;
    $this->encoding = $encoding;
    $this->table_pefix = $table_prefix;
  }

}
?>
