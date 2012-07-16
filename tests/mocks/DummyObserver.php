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

use Neevo\ObservableInterface;
use Neevo\ObserverInterface;


/**
 * Dummy Neevo observer.
 */
class DummyObserver implements ObserverInterface {


	private $notified = false;


	public function updateStatus(ObservableInterface $observable, $event){
		$this->notified = $event;
	}


	public function isNotified(& $event = null){
		return (bool) $event = $this->notified;
	}

	public function reset(){
		$this->notified = false;
	}


}