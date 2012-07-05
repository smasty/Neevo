<?php

use Neevo\Observable\ObserverInterface;
use Neevo\Observable\SubjectInterface;


/**
 * Dummy Neevo observer.
 */
class DummyObserver implements ObserverInterface {


	private $notified = false;


	public function updateStatus(SubjectInterface $observable, $event){
		$this->notified = $event;
	}


	public function isNotified(& $event = null){
		return (bool) $event = $this->notified;
	}

	public function reset(){
		$this->notified = false;
	}


}