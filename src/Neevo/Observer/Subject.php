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

namespace Neevo\Observer;


/**
 * Neevo observable interface.
 * @author Martin Srank
 */
interface Subject {


	/**
	 * Attach given observer to given event.
	 * @param Observer $observer
	 * @param int $event
	 * @return void
	 */
	public function attachObserver(Observer $observer, $event);


	/**
	 * Detach given observer.
	 * @param Observer $observer
	 * @return void
	 */
	public function detachObserver(Observer $observer);


	/**
	 * Notify all observers attached to given event.
	 * @param int $event
	 * @return void
	 */
	public function notifyObservers($event);


}
