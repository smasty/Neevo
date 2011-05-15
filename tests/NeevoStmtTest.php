<?php

use PHPUnit_Framework_Assert as A;


/**
 * Tests for NeevoStmt.
 */
class NeevoStmtTest extends PHPUnit_Framework_TestCase {


	/** @var NeevoConnection */
	private $connection;


	protected function setUp(){
		$this->connection = new NeevoConnection(array(
				'driver' => 'Dummy'
			));
	}


	protected function tearDown(){
		unset($this->stmt);
	}


	public function testCreateUpdate(){
		$stmt = NeevoStmt::createUpdate($this->connection, $s = 'table', $d = array('column' => 'value'));
		A::assertEquals($d, $stmt->getValues());
		A::assertEquals($s, $stmt->getTable());
		A::assertEquals(Neevo::STMT_UPDATE, $stmt->getType());
	}


	public function testCreateInsert(){
		$stmt = NeevoStmt::createInsert($this->connection, $s = 'table', $d = array('column' => 'value'));
		A::assertEquals($d, $stmt->getValues());
		A::assertEquals($s, $stmt->getTable());
		A::assertEquals(Neevo::STMT_INSERT, $stmt->getType());
	}


	public function testCreateDelete(){
		$stmt = NeevoStmt::createDelete($this->connection, $s = 'table');
		A::assertEquals($s, $stmt->getTable());
		A::assertEquals(Neevo::STMT_DELETE, $stmt->getType());
	}


	public function testRun(){
		$stmt = NeevoStmt::createDelete($this->connection, 'table');
		A::assertInstanceOf('DummyResult', $stmt->run());
	}


	public function testInsertId(){
		$stmt = NeevoStmt::createInsert($this->connection, 'table', array('column', 'value'));
		A::assertEquals(4, $stmt->insertId());
	}


	/**
	 * @expectedException NeevoException
	 */
	public function testInsertIdException(){
		$stmt = NeevoStmt::createDelete($this->connection, 'table');
		$stmt->insertId();
	}


	public function testAffectedRows(){
		$stmt = NeevoStmt::createDelete($this->connection, 'table');
		A::assertEquals(1, $stmt->affectedRows());
	}


	public function testResetState(){
		$stmt = NeevoStmt::createDelete($this->connection, 'table');
		$stmt->affectedRows();
		$stmt->resetState();

		$aff = new ReflectionProperty('NeevoStmt', 'affectedRows');
		$aff->setAccessible(true);
		A::assertNull($aff->getValue($stmt));
	}


}
