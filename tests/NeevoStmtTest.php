<?php

use PHPUnit_Framework_Assert as A;


/**
 * Tests for NeevoStmtBase.
 */
class NeevoStmtTest extends PHPUnit_Framework_TestCase {


	/** @var NeevoConnection */
	private $c;


	protected function setUp(){
		$this->c = new NeevoConnection(array(
				'driver' => 'Dummy'
			));
	}


	protected function tearDown(){
		unset($this->stmt);
	}


	/**
	 * Test NeevoStmt::createUpdate().
	 */
	public function testCreateUpdate(){
		$stmt = NeevoStmt::createUpdate($this->c, $s = 'table', $d = array('column' => 'value'));
		A::assertEquals($d, $stmt->getValues());
		A::assertEquals($s, $stmt->getTable());
		A::assertEquals(Neevo::STMT_UPDATE, $stmt->getType());
	}


	/**
	 * Test NeevoStmt::createInsert().
	 */
	public function testCreateInsert(){
		$stmt = NeevoStmt::createInsert($this->c, $s = 'table', $d = array('column' => 'value'));
		A::assertEquals($d, $stmt->getValues());
		A::assertEquals($s, $stmt->getTable());
		A::assertEquals(Neevo::STMT_INSERT, $stmt->getType());
	}


	/**
	 * Test NeevoStmt::createDelete().
	 */
	public function testCreateDelete(){
		$stmt = NeevoStmt::createDelete($this->c, $s = 'table');
		A::assertEquals($s, $stmt->getTable());
		A::assertEquals(Neevo::STMT_DELETE, $stmt->getType());
	}


	/**
	 * Test run() method.
	 */
	public function testRun(){
		$stmt = NeevoStmt::createDelete($this->c, 'table');
		A::assertTrue($stmt->run());
	}


	/**
	 * Test InsertId() method.
	 */
	public function testInsertId(){
		$stmt = NeevoStmt::createInsert($this->c, 'table', array('column', 'value'));
		A::assertEquals(4, $stmt->insertId());
	}


	/**
	 * Test InsertId() method on non-INSERT query.
	 */
	public function testInsertIdException(){
		$stmt = NeevoStmt::createDelete($this->c, 'table');
		try{
			$stmt->insertId();
			$exc = false;
		} catch(NeevoException $e){
			$exc = true;
		}

		A::assertTrue($exc);
	}


	/**
	 * Test affectedRows() method.
	 */
	public function testAffectedRows(){
		$stmt = NeevoStmt::createDelete($this->c, 'table');
		A::assertEquals(1, $stmt->affectedRows());
	}


	/**
	 * Test resetState() method.
	 */
	public function testResetState(){
		$stmt = NeevoStmt::createDelete($this->c, 'table');
		$stmt->affectedRows();
		$stmt->resetState();

		$aff = new ReflectionProperty('NeevoStmt', 'affectedRows');
		$aff->setAccessible(true);
		A::assertNull($aff->getValue($stmt));
	}


}
