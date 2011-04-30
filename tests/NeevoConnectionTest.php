<?php
/*require_once './mocks/DummyObserver.php';
require_once './mocks/NeevoDriverDummy.php';
require_once './mocks/NeevoDriverDummyParser.php';
require_once './mocks/NeevoDriverWrong.php';*/

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


	/**
	 * Test format of configuration - string.
	 */
	public function testConfigFormatString(){
		$connection = new NeevoConnection('driver=Dummy');

		A::assertInstanceOf('NeevoDriverDummy', $connection->getDriver());
	}


	/**
	 * Test format of configuration - Traversable.
	 */
	public function testConfigFormatTraversable(){
		$config = new ArrayObject(array('driver' => 'Dummy'));
		$connection = new NeevoConnection($config);

		A::assertInstanceOf('NeevoDriverDummy', $connection->getDriver());
	}


	/**
	 * Test format of configuration - other.
	 */
	public function testConfigFormatElse(){
		try{
			$msg = new NeevoConnection(false);
		} catch(InvalidArgumentException $e){
			$msg = $e->getMessage();
		}

		A::assertStringStartsWith('Configuration must be', $msg);
	}


	/**
	 * Automatic driver instantiation.
	 */
	public function testAutoSetDriver(){
		A::assertInstanceOf('NeevoDriverDummy', $this->instance->getDriver());
	}


	/**
	 * Driver instantiation - no file.
	 */
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


	/**
	 * Driver instantiation - not a driver.
	 */
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


	/**
	 * Automatic parser instantiation.
	 */
	public function testAutoSetParser(){
		A::assertEquals($this->instance->getParser(), 'NeevoParser');
	}


	/**
	 * Custom parser instantiation.
	 */
	public function testSetCustomParser(){
		$connection = new NeevoConnection(array(
				'driver' => 'DummyParser'
			));

		A::assertEquals($connection->getParser(), 'NeevoDriverDummyParser');
	}


	/**
	 * Cache instantiaton
	 */
	public function testSetCache(){
		$cache = new NeevoCacheSession;
		$this->instance->setCache($cache);

		A::assertEquals(spl_object_hash($this->instance->getCache()), spl_object_hash($cache));
	}


	/**
	 * Automatic cache instantiation.
	 */
	public function testAutoSetCache(){
		A::assertInstanceOf('NeevoCache', $this->instance->getCache());
	}


	/**
	 * Test config values.
	 */
	public function testSetConfig(){
		A::assertTrue($this->instance->getConfig('testConfig'));
		A::assertInternalType('array', $this->instance->getConfig());
	}


	/**
	 * Observer attaching via attachObserver().
	 */
	public function testAttachObserver(){
		$observer = new DummyObserver;
		$this->instance->attachObserver($observer);
		$this->instance->notifyObservers(1);

		A::assertTrue($observer->isFired());
	}


	/**
	 * Observer attaching via config value.
	 */
	public function testAutoAttachObserver(){
		$observer = new DummyObserver;
		$conn = new NeevoConnection(array(
				'driver' => 'Dummy',
				'observer' => $observer
			));
		$conn->notifyObservers(1);

		A::assertTrue($observer->isFired());
	}


	/**
	 * Observer detaching.
	 */
	public function testDetachObserver(){
		$observer = new DummyObserver;
		$this->instance->attachObserver($observer);
		$this->instance->detachObserver($observer);
		$this->instance->notifyObservers(1);

		A::assertFalse($observer->isFired());
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


}

?>
