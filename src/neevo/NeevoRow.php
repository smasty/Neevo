<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2011 Martin Srank (http://smasty.net)
 *
 */


/**
 * Representation of a row in a result.
 * @author Martin Srank
 * @package Neevo
 */
class NeevoRow implements ArrayAccess, Countable, IteratorAggregate {


	/** @var bool */
	protected $freeze;

	/** @var string */
	protected $primaryKey;

	/** @var array */
	protected $data = array();

	/** @var array */
	protected $modified = array();

	/** @var array */
	protected $iterable = array();

	/** @var NeevoResult */
	protected $result;


	/**
	 * Create a row instance.
	 * @param array $data
	 * @param NeevoResult $result
	 * @return void
	 */
	public function __construct(array $data, NeevoResult $result){
		$this->data = $data;
		$this->iterable = $this->data;
		$this->result = $result;
		$this->primaryKey = $result->getPrimaryKey();

		if($this->primaryKey === null || !isset($this->data[$this->primaryKey]))
			$this->freeze = true;
	}


	/**
	 * Update corresponding database row if available.
	 * @throws NeevoException
	 * @return int Number of affected rows.
	 */
	public function update(){
		if($this->freeze)
			throw new NeevoException('Update disabled - cannot get primary key.');

		if(!empty($this->modified) && $this->data != $this->iterable){
			return NeevoStmt::createUpdate($this->result->getConnection(), $this->result->getTable(),
					$this->modified)
					->where($this->primaryKey, $this->data[$this->primaryKey])
					->limit(1)->affectedRows();
		}
	}


	/**
	 * Delete corresponding database row if available.
	 * @throws NeevoException
	 * @return int Number of affected rows.
	 */
	public function delete(){
		if($this->freeze)
			throw new NeevoException('Delete disabled - cannot get primary key.');

		return NeevoStmt::createDelete($this->result->getConnection(), $this->result->getTable())
				->where($this->primaryKey, $this->data[$this->primaryKey])
				->limit(1)->affectedRows();
	}


	/**
	 * Return object as an array.
	 * @return array
	 */
	public function toArray(){
		return $this->iterable;
	}


	/*  ************  Implementation of Countable  ************  */


	public function count(){
		return count($this->iterable);
	}


	/*  ************  Implementation of IteratorAggregate  ************  */


	public function getIterator(){
		return new ArrayIterator($this->iterable);
	}


	/*  ************  Magic methods  ************  */


	public function __get($name){
		return array_key_exists($name, $this->modified)
				? $this->modified[$name] : (isset($this->data[$name])
					? $this->data[$name] : null);
	}


	public function __set($name, $value){
			$this->modified[$name] = $value;
			$this->iterable = array_merge($this->data, $this->modified);
	}


	public function __isset($name){
		return isset($this->data[$name]);
	}


	public function __unset($name){
		$this->modified[$name] = null;
		$this->iterable = array_merge($this->data, $this->modified);
	}


	/*  ************  Implementation of ArrayAccess  ************  */


	public function offsetGet($offset){
		return $this->__get($offset);
	}


	public function offsetSet($offset, $value){
		$this->__set($offset, $value);
	}


	public function offsetExists($offset){
		return $this->__isset($offset);
	}


	public function offsetUnset($offset){
		$this->__unset($offset);
	}


}
