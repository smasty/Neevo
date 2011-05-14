<?php


/**
 * Dummy Neevo observer.
 */
class DummyObserver implements INeevoObserver {


	private $fired = false;


	public function updateStatus(INeevoObservable $observable, $event){
		$this->fired = true;
	}


	public function isFired(){
		return (bool) $this->fired;
	}


}