<?php


/**
 * Tests for Neevo\Statement.
 */
class StatementTest extends PHPUnit_Framework_TestCase {


	/** @var Neevo\Connection */
	private $connection;


	protected function setUp(){
		$this->connection = new Neevo\Connection(array(
				'driver' => 'Dummy'
			));
	}


	protected function tearDown(){
		unset($this->stmt);
	}


	public function testCreateUpdate(){
		$stmt = Neevo\Statement::createUpdate($this->connection, $s = 'table', $d = array('column' => 'value'));
		$this->assertEquals($d, $stmt->getValues());
		$this->assertEquals($s, $stmt->getTable());
		$this->assertEquals(Neevo\Manager::STMT_UPDATE, $stmt->getType());
	}


	public function testCreateInsert(){
		$stmt = Neevo\Statement::createInsert($this->connection, $s = 'table', $d = array('column' => 'value'));
		$this->assertEquals($d, $stmt->getValues());
		$this->assertEquals($s, $stmt->getTable());
		$this->assertEquals(Neevo\Manager::STMT_INSERT, $stmt->getType());
	}


	public function testCreateDelete(){
		$stmt = Neevo\Statement::createDelete($this->connection, $s = 'table');
		$this->assertEquals($s, $stmt->getTable());
		$this->assertEquals(Neevo\Manager::STMT_DELETE, $stmt->getType());
	}


	public function testRun(){
		$stmt = Neevo\Statement::createDelete($this->connection, 'table');
		$this->assertInstanceOf('DummyResult', $stmt->run());
	}


	public function testInsertId(){
		$stmt = Neevo\Statement::createInsert($this->connection, 'table', array('column', 'value'));
		$this->assertEquals(4, $stmt->insertId());
	}


	/**
	 * @expectedException Neevo\NeevoException
	 */
	public function testInsertIdException(){
		$stmt = Neevo\Statement::createDelete($this->connection, 'table');
		$stmt->insertId();
	}


	public function testInsertIdNotSupported(){
		$stmt = Neevo\Statement::createInsert($this->connection, 'table', array('column', 'value'));
		$this->connection->getDriver()->setError('insert-id');
		$this->assertFalse($stmt->insertId());
	}


	public function testAffectedRows(){
		$stmt = Neevo\Statement::createDelete($this->connection, 'table');
		$this->assertEquals(1, $stmt->affectedRows());
	}



	public function testAffectedRowsError(){
		$this->connection->getDriver()->setError('affected-rows');
		$stmt = Neevo\Statement::createDelete($this->connection, 'table');
		$stmt->run();
		$r = new ReflectionProperty($stmt, 'affectedRows');
		$r->setAccessible(true);
		$this->assertFalse($r->getValue($stmt));
	}


	public function testResetState(){
		$stmt = Neevo\Statement::createDelete($this->connection, 'table');
		$stmt->affectedRows();

		$res = new ReflectionMethod($stmt, 'resetState');
		$res->setAccessible(true);
		$res->invoke($stmt);

		$aff = new ReflectionProperty('Neevo\Statement', 'affectedRows');
		$aff->setAccessible(true);
		$this->assertNull($aff->getValue($stmt));
	}


}
