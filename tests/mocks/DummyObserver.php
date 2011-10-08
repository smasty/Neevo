<?php


/**
 * Dummy Neevo observer.
 */
class DummyObserver implements Neevo\IObserver {


	private $notified = false;


	public function updateStatus(Neevo\IObservable $observable, $event){
		$this->notified = $event;
	}


	public function isNotified(& $event = null){
		return (bool) $event = $this->notified;
	}

	public function reset(){
		$this->notified = false;
	}


}