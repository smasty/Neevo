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
if(version_compare(PHP_VERSION, '5.1.2', '<')){
  trigger_error('Neevo requires PHP version 5.1.2 or newer', E_USER_ERROR);
}


// Nette Framework compatibility
if(interface_exists('Nette\IDebugPanel')){
  class_alias('Nette\IDebugPanel', 'IDebugPanel');
}
if(!interface_exists('IDebugPanel')){
  /** Nette Framework compatibility. */
  interface IDebugPanel{}
}

@set_magic_quotes_runtime(FALSE);

NeevoLoader::getInstance()->register();


/**
 * Autoloader responsible for loading Neevo classes and interfaces.
 * @author Martin Srank
 * @package Neevo
 */
class NeevoLoader {

  /** @var array */
  private $list = array(
    'neevo' => '/neevo/Neevo.php',
    'neevoliteral' => '/neevo/Neevo.php',
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
    'neevocachenette' => '/neevo/NeevoCache.php',
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

  /** @var NeevoLoader */
  private static $instance;

  private function __construct(){}

  /**
   * Get the signleton instance.
   * @return NeevoLoader
   */
  public static function getInstance(){
    if(self::$instance === null){
      self::$instance = new self;
    }
    return self::$instance;
  }

  /**
   * Register the autoloader.
   * @return void
   */
  public function register(){
    spl_autoload_register(array($this, 'tryLoad'));
  }

  /**
   *
   * @param <type> $type
   * @return bool
   */
  public function tryLoad($type){
    $type = trim(strtolower($type), '\\');

    if(isset($this->list[$type])){
      return include_once dirname(__FILE__) . $this->list[$type];
    }
    return false;
  }
}
