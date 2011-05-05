<?php


/**
 * Dummy Neevo statement.
 */
class DummyStmt extends NeevoStmtBase {


	public function _validateConditions(){
		return parent::_validateConditions();
	}


	public function setType($type){
		$this->type = $type;
	}

	public function setSource($source){
		$this->source = $source;
	}


}
