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

namespace Neevo;


/**
 * Representation of a row in a result.
 * @author Smasty
 */
class Row implements \ArrayAccess, \Countable, \IteratorAggregate {


	/** @var bool */
	protected $frozen;

	/** @var string */
	protected $primary;

	/** @var array */
	protected $data = array();

	/** @var array */
	protected $modified = array();

	/** @var string */
	protected $table;

	/** @var Connection */
	protected $connection;


	/**
	 * Create a row instance.
	 * @param array $data
	 * @param Result $result
	 * @return void
	 */
	public function __construct(array $data, Result $result){
		$this->data = $data;
		$this->connection = $result->getConnection();
		$this->table = $result->getTable();
		$this->primary = $result->getPrimaryKey();

		if(!$this->table || !$this->primary || !isset($this->data[$this->primary]))
			$this->frozen = true;
	}


	/**
	 * Update corresponding database row if available.
	 * @throws NeevoException
	 * @return int Number of affected rows.
	 */
	public function update(){
		if($this->frozen)
			throw new NeevoException('Update disabled - cannot get primary key or table.');

		if(!empty($this->modified)){
			return Statement::createUpdate($this->connection, $this->table,	$this->modified)
					->where($this->primary, $this->data[$this->primary])->limit(1)->affectedRows();
		}
		return 0;
	}


	/**
	 * Delete corresponding database row if available.
	 * @throws NeevoException
	 * @return int Number of affected rows.
	 */
	public function delete(){
		if($this->frozen)
			throw new NeevoException('Delete disabled - cannot get primary key or table.');

		return Statement::createDelete($this->connection, $this->table)
				->where($this->primary, $this->data[$this->primary])->limit(1)->affectedRows();
	}


	/**
	 * Return values as an array.
	 * @return array
	 */
	public function toArray(){
		return array_merge($this->data, $this->modified);
	}


	/**
	 * If row is not able to update it's state.
	 * @return bool
	 */
	public function isFrozen(){
		return (bool) $this->frozen;
	}


	public function count(){
		return count(array_merge($this->data, $this->modified));
	}


	/**
	 * @return \ArrayIterator
	 */
	public function getIterator(){
		return new \ArrayIterator(array_merge($this->data, $this->modified));
	}


	public function __get($name){
		return array_key_exists($name, $this->modified)
				? $this->modified[$name] : (isset($this->data[$name])
					? $this->data[$name] : null);
	}


	public function __set($name, $value){
			$this->modified[$name] = $value;
	}


	public function __isset($name){
		return isset($this->data[$name]) || isset($this->modified[$name]);
	}


	public function __unset($name){
		$this->modified[$name] = null;
	}


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
