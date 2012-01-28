<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2012 Smasty (http://smasty.net)
 *
 */

namespace Neevo;


/**
 * Main Neevo exception.
 * @author Smasty
 */
class NeevoException extends \Exception implements IObservable {


	/** @var string */
	protected $sql;

	/** @var \SplObjectStorage */
	protected static $observers;


	/**
	 * Construct exception.
	 * @param string $message
	 * @param int $code
	 * @param string $sql Optional SQL command
	 * @param Exception $previous
	 * @return void
	 */
	public function __construct($message = '', $code = 0, $sql = null, \Exception $previous = null){

		parent::__construct($message, (int) $code, $previous);
		$this->sql = $sql;
		if(self::$observers === null)
			self::$observers = new \SplObjectStorage;
		$this->notifyObservers(IObserver::EXCEPTION);
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


	/**
	 * Attach given observer to given event.
	 * @param IObserver $observer
	 * @param int $event
	 * @return void
	 */
	public function attachObserver(IObserver $observer, $event){
		self::$observers->attach($observer, $event);
	}


	/**
	 * Detach given observer.
	 * @param IObserver $observer
	 * @return void
	 */
	public function detachObserver(IObserver $observer){
		self::$observers->detach($observer);
	}


	/**
	 * Notify all observers attached to given event.
	 * @param int $event
	 * @return void
	 */
	public function notifyObservers($event){
		foreach(self::$observers as $observer){
			if($event & self::$observers->getInfo())
				$observer->updateStatus($this, $event);
		}
	}


}


/**
 * Neevo driver exception.
 * @author Smasty
 */
class DriverException extends NeevoException {

}


/**
 * Exception for features not implemented by the driver.
 * @author Smasty
 */
class ImplementationException extends NeevoException {

}