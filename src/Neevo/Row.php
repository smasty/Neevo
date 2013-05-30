<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2013 Smasty (http://smasty.net)
 *
 */

namespace Neevo;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Representation of a row in a result.
 * @author Smasty
 */
class Row implements ArrayAccess, Countable, IteratorAggregate
{


    /** @var array */
    protected $data = array();


    /**
     * Creates a row instance.
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }


    /**
     * Returns values as an array.
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }


    public function count()
    {
        return count($this->data);
    }


    /**
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->data);
    }


    public function __get($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }


    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }


    public function __isset($name)
    {
        return isset($this->data[$name]);
    }


    public function __unset($name)
    {
        unset($this->data[$name]);
    }


    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }


    public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }


    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }


    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }
}
