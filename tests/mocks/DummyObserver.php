<?php


/**
 * Dummy Neevo observer.
 */
class DummyObserver implements INeevoObserver {


	private $notified = false;


	public function updateStatus(INeevoObservable $observable, $event){
		$this->notified = $event;
	}


	public function isNotified(& $event = null){
		return (bool) $event = $this->notified;
	}

	public function reset(){
		$this->notified = false;
	}


}