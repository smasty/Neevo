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
 * @license  http://neevo.smasty.net/license MIT license
 * @link     http://neevo.smasty.net/
 *
 */


/**
 * Autoloader responsible for loading Neevo classes and interfaces.
 * @author Martin Srank
 * @package Neevo
 */
class NeevoLoader {


    /** @var array */
    private $list = array(
       'ineevocache' => '/NeevoCache.php',
       'ineevodriver' => '/INeevoDriver.php',
       'ineevoobservable' => '/NeevoObserver.php',
       'ineevoobserver' => '/NeevoObserver.php',
       'neevo' => '/Neevo.php',
       'neevocache' => '/NeevoCache.php',
       'neevocacheapc' => '/NeevoCache.php',
       'neevocachedb' => '/NeevoCache.php',
       'neevocachefile' => '/NeevoCache.php',
       'neevocacheinclude' => '/NeevoCache.php',
       'neevocachememcache' => '/NeevoCache.php',
       'neevocachenette' => '/NeevoCache.php',
       'neevocachesession' => '/NeevoCache.php',
       'neevoconnection' => '/NeevoConnection.php',
       'neevodriverexception' => '/NeevoException.php',
       'neevoexception' => '/NeevoException.php',
       'neevoimplementationexception' => '/NeevoException.php',
       'neevoliteral' => '/Neevo.php',
       'neevoobserver' => '/NeevoObserver.php',
       'neevoresult' => '/NeevoResult.php',
       'neevoresultiterator' => '/NeevoResultIterator.php',
       'neevorow' => '/NeevoRow.php',
       'neevostmt' => '/NeevoStmt.php',
       'neevostmtbase' => '/NeevoStmtBase.php',
       'neevostmtparser' => '/NeevoStmtParser.php',
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
     * Try load Neevo class/interface
     * @param string $type
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
