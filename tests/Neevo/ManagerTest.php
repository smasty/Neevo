<?php


/**
 * Tests for Neevo.
 */
class ManagerTest extends PHPUnit_Framework_TestCase {

	/** @var Neevo */
	private $neevo;


	protected function setUp(){
		$this->neevo = new Neevo\Manager('driver=Dummy&lazy=1');
	}


	protected function tearDown(){
		unset($this->neevo);
	}


	public function testConnect(){
		$neevo = new Neevo\Manager('driver=Dummy', new Neevo\Cache\SessionStorage);
		$this->assertInstanceOf('Neevo\\Drivers\\DummyDriver', $neevo->getConnection()->getDriver());
		$this->assertInstanceOf('Neevo\\Cache\\SessionStorage', $neevo->getConnection()->getCache());

		$r = new ReflectionProperty('Neevo\Connection', 'observers');
		$r->setAccessible(true);
		$this->assertTrue($r->getValue($neevo->getConnection())->contains($neevo));
	}


	public function testBeginTransaction(){
		$this->neevo->begin();
		$this->assertEquals(
			Neevo\Drivers\DummyDriver::TRANSACTION_OPEN,
			$this->neevo->getConnection()->getDriver()->transactionState()
		);
	}


	public function testCommitTransaction(){
		$this->neevo->begin();
		$this->neevo->commit();
		$this->assertEquals(
			Neevo\Drivers\DummyDriver::TRANSACTION_COMMIT,
			$this->neevo->getConnection()->getDriver()->transactionState()
		);
	}


	public function testRollbackTransaction(){
		$this->neevo->begin();
		$this->neevo->rollback();
		$this->assertEquals(
			Neevo\Drivers\DummyDriver::TRANSACTION_ROLLBACK,
			$this->neevo->getConnection()->getDriver()->transactionState()
		);
	}


	public function testSelect(){
		$res = $this->neevo->select($c = 'col', $t = 'table');
		$this->assertInstanceOf('Neevo\Result', $res);
		$this->assertEquals(Neevo\Manager::STMT_SELECT, $res->getType());
		$this->assertEquals(array($c), $res->getColumns());
		$this->assertEquals($t, $res->getSource());
		$this->assertTrue($res->getConnection() === $this->neevo->getConnection());
	}


	public function testInsert(){
		$ins = $this->neevo->insert($t = 'table', $v = array('val1', 'val2'));
		$this->assertInstanceOf('Neevo\Statement', $ins);
		$this->assertEquals(Neevo\Manager::STMT_INSERT, $ins->getType());
		$this->assertEquals($t, $ins->getTable());
		$this->assertEquals($v, $ins->getValues());
	}


	public function testUpdate(){
		$upd = $this->neevo->update($t = 'table', $d = array('val1', 'val2'));
		$this->assertInstanceOf('Neevo\Statement', $upd);
		$this->assertEquals(Neevo\Manager::STMT_UPDATE, $upd->getType());
		$this->assertEquals($t, $upd->getTable());
		$this->assertEquals($d, $upd->getValues());
	}


	public function testDelete(){
		$del = $this->neevo->delete($t = 'table');
		$this->assertEquals(Neevo\Manager::STMT_DELETE, $del->getType());
		$this->assertInstanceOf('Neevo\Statement', $del);
		$this->assertEquals($t, $del->getTable());
	}


	public function testAttachObserver(){
		$o = new DummyObserver;
		$this->neevo->attachObserver($o, 1);
		$this->neevo->notifyObservers(1);
		$this->assertTrue($o->isNotified($e));
		$this->assertEquals(1, $e);
		$this->neevo->detachObserver($o);
	}


	public function testUpdateStatus(){
		$r = $this->neevo->select('foo');
		$sql = (string) $r;
		$r->run();
		$this->assertEquals($sql, $this->neevo->getLast());
		$this->assertEquals(1, $this->neevo->getQueries());
	}


	public function testHighlightSql(){
		$this->assertEquals(
			"<pre style=\"color:#555\" class=\"sql-dump\"><strong style=\"color:#e71818\">SELECT</strong> * \n<strong style=\"color:#e71818\">FROM</strong> `table` \n<strong style=\"color:#e71818\">WHERE</strong> <strong style=\"color:#d59401\">RAND</strong>() = <em style=\"color:#008000\">'John Doe'</em>; <em style=\"color:#999\">/* comment */</em></pre>\n",
			$v=Neevo\Manager::highlightSql("SELECT * FROM `table` WHERE RAND() = 'John Doe'; /* comment */")
		);
	}


	public function testLoadFile(){
		$queries = array(
			'SELECT 1 FROM 1;',
			'SELECT 2 FROM 2;',
			'SELECT 3 FROM 3;',
		);
		file_put_contents($f = tempnam(sys_get_temp_dir(), 'Neevo'), implode("\n", $queries));

		$count = $this->neevo->loadFile($f);
		$this->assertEquals($queries, array_map('trim', $this->neevo->getConnection()->getDriver()->performed()));
		$this->assertEquals($count, count($queries));
		unlink($f);
	}


	/**
	 * @expectedException Neevo\NeevoException
	 */
	public function testLoadFileNoFile(){
		$this->neevo->loadFile($f = 'nofile');
	}


}
