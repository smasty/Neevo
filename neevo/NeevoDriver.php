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
class NeevoDriver{

  private $driver, $driver_class, $neevo;

  public function __construct($driver, $neevo){
    switch (strtolower($driver)) {
      case "mysql":
        $this->driver_class = new NeevoDriverMySQL($this);
        break;

      default:
        throw new NeevoException("Driver $driver not supported.");
        break;
    }
    $this->driver = $driver;
  }


  private function driver(){
    return $this->driver_class;
  }


}
?>
