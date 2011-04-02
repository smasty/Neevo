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
 * Main Neevo exception.
 * @author Martin Srank
 * @package NeevoExceptions
 */
class NeevoException extends Exception implements IDebugPanel, INeevoObservable {


	/** @var string */
	protected $sql;

	/** @var SplObjectStorage */
	protected static $observers;


	/**
	 * Construct exception.
	 * @param string $message
	 * @param int $code
	 * @param string $sql Optional SQL command
	 * @return void
	 */
	public function __construct($message = '', $code = 0, $sql = null){

		parent::__construct($message, (int) $code);
		$this->sql = $sql;
		$this->notifyObservers(INeevoObserver::EXCEPTION);
	}


	/**
	 * String representation of exception.
	 * @return string
	 */
	public function __toString(){
		return parent::__toString() . ($this->sql ? "\nSQL: $this->sql" : '');
	}


	/**
	 * Get given SQL command.
	 * @return string
	 */
	public function getSql(){
		return $this->sql;
	}


	/*  ============  Implementation of INeevoObservable  ============  */


	/**
	 * Attach given observer. Static for attachObserver().
	 * @param INeevoObserver $observer
	 * @return void
	 */
	public static function attach(INeevoObserver $observer){
		if(!self::$observers){
			self::$observers = new SplObjectStorage;
		}
		self::$observers->attach($observer);
	}


	/**
	 * Detach given observer. Static for detachObserver().
	 * @param INeevoObserver $observer
	 * @return void
	 */
	public static function detach(INeevoObserver $observer){
		if(!self::$observers){
			return;
		}
		self::$observers->detach($observer);
	}


	public function attachObserver(INeevoObserver $observer){
		self::attach($observer);
	}


	public function detachObserver(INeevoObserver $observer){
		self::detach($observer);
	}


	/**
	 * Notify attached observers.
	 * @param int $event
	 * @return void
	 */
	public function notifyObservers($event){
		if(!self::$observers){
			return;
		}
		foreach(self::$observers as $observer){
			$observer->updateStatus($this, $event);
		}
	}


	/*  ============  Implementation of Nette\IDebugPanel  ============  */


	public function getId(){
		return __CLASS__;
	}


	public function getTab(){
		return 'SQL';
	}


	public function getPanel(){
		if($this->sql === null){
			return;
		}
		return Neevo::highlightSql($this->sql);
	}


}



/**
 * Exception for features not implemented by the driver,
 * @author Martin Srank
 * @package NeevoExceptions
 */
class NeevoImplementationException extends NeevoException {}



/**
 * Neevo driver exception.
 * @author Martin Srank
 * @package NeevoExceptions
 */
class NeevoDriverException extends NeevoException {}
