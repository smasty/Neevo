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
 * Neevo observable interface.
 * @author Martin Srank
 * @package Neevo
 */
interface INeevoObservable {


	/**
	 * Attach given observer to given event.
	 * @param INeevoObserver $observer
	 * @param int $event
	 * @return void
	 */
	public function attachObserver(INeevoObserver $observer, $event);


	/**
	 * Detach given observer.
	 * @param INeevoObserver $observer
	 * @return void
	 */
	public function detachObserver(INeevoObserver $observer);


	/**
	 * Notify all observers attached to given event.
	 * @param int $event
	 * @return void
	 */
	public function notifyObservers($event);


}
