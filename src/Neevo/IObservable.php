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
 * Neevo observable interface.
 * @author Smasty
 */
interface IObservable {


	/**
	 * Attaches given observer to given event.
	 * @param Observer $observer
	 * @param int $event
	 * @return void
	 */
	public function attachObserver(IObserver $observer, $event);


	/**
	 * Detaches given observer.
	 * @param Observer $observer
	 * @return void
	 */
	public function detachObserver(IObserver $observer);


	/**
	 * Notifies all observers attached to given event.
	 * @param int $event
	 * @return void
	 */
	public function notifyObservers($event);


}
