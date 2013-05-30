<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2012 Smasty (http://smasty.net)
 *
 */

namespace Neevo\Test;

use DummyObserver;
use Exception;
use Neevo\NeevoException;

class NeevoExceptionTest extends \PHPUnit_Framework_TestCase
{


    public function testConstructor()
    {
        $e = new NeevoException($m = 'error', $c = 0, $s = 'SELECT * FROM error', $p = new Exception);
        $this->assertEquals($m, $e->getMessage());
        $this->assertEquals($c, $e->getCode());
        $this->assertEquals($p, $e->getPrevious());
        $this->assertEquals($s, $e->getSql());
    }


    public function testToString()
    {
        $e = new NeevoException(null, 0, $sql = 'SELECT * FROM error');
        $this->assertContains("\nSQL: $sql", (string) $e);
    }


    public function testObservable()
    {
        $e = new NeevoException;
        $observer = new Mocks\DummyObserver;

        $e->attachObserver($observer, $event = Mocks\DummyObserver::EXCEPTION);
        $e->notifyObservers($event);
        $this->assertTrue($observer->isNotified($firedEvent));
        $this->assertEquals($event, $firedEvent);

        $observer->reset();
        $e->detachObserver($observer);
        $e->notifyObservers($event);
        $this->assertFalse($observer->isNotified());
    }
}
