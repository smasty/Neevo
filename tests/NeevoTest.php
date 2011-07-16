<?php


/**
 * Tests for Neevo.
 */
class NeevoTest extends PHPUnit_Framework_TestCase {

	/** @var Neevo */
	private $neevo;


	protected function setUp(){
		$this->neevo = new Neevo('driver=Dummy&lazy=1');
	}


	protected function tearDown(){
		unset($this->neevo);
	}


	public function testConnect(){
		$neevo = new Neevo('driver=Dummy', new NeevoCacheSession);
		$this->assertInstanceOf('NeevoDriverDummy', $neevo->getConnection()->getDriver());
		$this->assertInstanceOf('NeevoCacheSession', $neevo->getConnection()->getCache());

		$r = new ReflectionProperty('NeevoConnection', 'observers');
		$r->setAccessible(true);
		$this->assertTrue($r->getValue($neevo->getConnection())->contains($neevo));
	}


	public function testBeginTransaction(){
		$this->neevo->begin();
		$this->assertEquals(NeevoDriverDummy::TRANSACTION_OPEN, $this->neevo->getConnection()->getDriver()->transactionState());
	}


	public function testCommitTransaction(){
		$this->neevo->begin();
		$this->neevo->commit();
		$this->assertEquals(NeevoDriverDummy::TRANSACTION_COMMIT, $this->neevo->getConnection()->getDriver()->transactionState());
	}


	public function testRollbackTransaction(){
		$this->neevo->begin();
		$this->neevo->rollback();
		$this->assertEquals(NeevoDriverDummy::TRANSACTION_ROLLBACK, $this->neevo->getConnection()->getDriver()->transactionState());
	}


	public function testSelect(){
		$res = $this->neevo->select($c = 'col', $t = 'table');
		$this->assertInstanceOf('NeevoResult', $res);
		$this->assertEquals(Neevo::STMT_SELECT, $res->getType());
		$this->assertEquals(array($c), $res->getColumns());
		$this->assertEquals($t, $res->getSource());
		$this->assertTrue($res->getConnection() === $this->neevo->getConnection());
	}


	public function testInsert(){
		$ins = $this->neevo->insert($t = 'table', $v = array('val1', 'val2'));
		$this->assertInstanceOf('NeevoStmt', $ins);
		$this->assertEquals(Neevo::STMT_INSERT, $ins->getType());
		$this->assertEquals($t, $ins->getTable());
		$this->assertEquals($v, $ins->getValues());
	}


	public function testUpdate(){
		$upd = $this->neevo->update($t = 'table', $d = array('val1', 'val2'));
		$this->assertInstanceOf('NeevoStmt', $upd);
		$this->assertEquals(Neevo::STMT_UPDATE, $upd->getType());
		$this->assertEquals($t, $upd->getTable());
		$this->assertEquals($d, $upd->getValues());
	}


	public function testDelete(){
		$del = $this->neevo->delete($t = 'table');
		$this->assertEquals(Neevo::STMT_DELETE, $del->getType());
		$this->assertInstanceOf('NeevoStmt', $del);
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
			$v=Neevo::highlightSql("SELECT * FROM `table` WHERE RAND() = 'John Doe'; /* comment */")
		);
	}


}
