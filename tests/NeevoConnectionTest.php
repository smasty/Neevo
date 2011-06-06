 <?php

use PHPUnit_Framework_Assert as A;


/**
 * Tests for NeevoConnection.
 */
class NeevoConnectionTest extends PHPUnit_Framework_TestCase {


	/** @var NeevoConnection */
	protected $instance;


	protected function setUp(){
		$config = array(
			'testConfig' => true,
			'driver' => 'Dummy'
		);
		$this->instance = new NeevoConnection($config);
	}


	protected function tearDown(){
		unset($this->instance);
	}


	public function testConfigFormatString(){
		$connection = new NeevoConnection('driver=Dummy');

		A::assertInstanceOf('NeevoDriverDummy', $connection->getDriver());
	}


	public function testConfigFormatTraversable(){
		$config = new ArrayObject(array('driver' => 'Dummy'));
		$connection = new NeevoConnection($config);

		A::assertInstanceOf('NeevoDriverDummy', $connection->getDriver());
	}


	public function testConfigFormatElse(){
		try{
			$msg = new NeevoConnection(false);
		} catch(InvalidArgumentException $e){
			$msg = $e->getMessage();
		}

		A::assertStringStartsWith('Configuration must be', $msg);
	}


	public function testAutoSetDriver(){
		A::assertInstanceOf('NeevoDriverDummy', $this->instance->getDriver());
	}


	public function testSetDriverNoFile(){
		try{
			$msg = new NeevoConnection(array(
					'driver' => 'Foo'
				));
		} catch(NeevoDriverException $e){
			$msg = $e->getMessage();
		}

		A::assertStringEndsWith('does not exist.', $msg);
	}


	public function testSetDriverNoDriver(){
		try{
			$msg = new NeevoConnection(array(
					'driver' => 'Wrong'
				));
		} catch(NeevoDriverException $e){
			$msg = $e->getMessage();
		}

		A::assertStringStartsWith("Class 'NeevoDriverWrong'", $msg);
	}


	public function testAutoSetParser(){
		A::assertEquals($this->instance->getParser(), 'NeevoParser');
	}


	public function testSetCustomParser(){
		$connection = new NeevoConnection(array(
				'driver' => 'Parser'
			));

		A::assertEquals($connection->getParser(), 'NeevoDriverParser');
	}


	public function testSetCache(){
		$cache = new NeevoCacheSession;
		$this->instance->setCache($cache);

		A::assertEquals(spl_object_hash($this->instance->getCache()), spl_object_hash($cache));
	}


	public function testAutoSetCache(){
		A::assertInstanceOf('NeevoCache', $this->instance->getCache());
	}


	public function testSetConfig(){
		A::assertTrue($this->instance->getConfig('testConfig'));
		A::assertInternalType('array', $this->instance->getConfig());
	}


	public function testAttachObserver(){
		$observer = new DummyObserver;
		$this->instance->attachObserver($observer, 1);
		$this->instance->notifyObservers(1);

		A::assertTrue($observer->isNotified());
	}


	public function testDetachObserver(){
		$observer = new DummyObserver;
		$this->instance->attachObserver($observer, 1);
		$this->instance->detachObserver($observer);
		$this->instance->notifyObservers(1);

		A::assertFalse($observer->isNotified());
	}


	public function testAlias(){
		$config = array(
			'alias' => 'value',
		);
		NeevoConnection::alias($config, 'key', 'alias');

		A::assertEquals('value', $config['key']);
	}


	public function testGetPrefix(){
		A::assertEquals('', $this->instance->getPrefix());
	}


	public function testArrayAccess(){
		A::assertEquals('Dummy', $this->instance['driver'], 'offsetGet()');
		A::assertTrue(isset($this->instance['driver']), 'offsetSet()');
	}


	public function testCloseConnection(){
		$this->instance->attachObserver($o = new DummyObserver, DummyObserver::DISCONNECT);
		$this->instance->__destruct();

		A::assertTrue($this->instance->getDriver()->isClosed());
		A::assertTrue($o->isNotified());
	}


}



/**
 * Class with Neevo driver convention name, but not a driver.
 */
class NeevoDriverWrong {}

?>
