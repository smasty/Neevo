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
 * Class for data manipulation statements (INSERT, UPDATE, DELETE)
 * @author Smasty
 */
class Statement extends BaseStatement {


	/** @var array */
	protected $values = array();

	/** @var int */
	protected $affectedRows;


	/**
	 * Creates UPDATE statement.
	 * @param Connection $connection
	 * @param string $table
	 * @param array|\Traversable $data
	 * @return Statement fluent interface
	 */
	public static function createUpdate(Connection $connection, $table, $data){
		if(!($data instanceof \Traversable || (is_array($data) && !empty($data))))
			throw new \InvalidArgumentException('Data must be a non-empty array or Traversable.');

		$obj = new self($connection);
		$obj->type = Manager::STMT_UPDATE;
		$obj->source = $table;
		$obj->values = $data instanceof \Traversable ? iterator_to_array($data) : $data;
		return $obj;
	}


	/**
	 * Creates INSERT statement.
	 * @param Connection $connection
	 * @param string $table
	 * @param array|\Traversable $values
	 * @return Statement fluent interface
	 */
	public static function createInsert(Connection $connection, $table, array $values){
		if(!($values instanceof \Traversable || (is_array($values) && !empty($values))))
			throw new \InvalidArgumentException('Values must be a non-empty array or Traversable.');

		$obj = new self($connection);
		$obj->type = Manager::STMT_INSERT;
		$obj->source = $table;
		$obj->values = $values instanceof \Traversable ? iterator_to_array($values) : $values;
		return $obj;
	}


	/**
	 * Creates DELETE statement.
	 * @param Connection $connection
	 * @param string $table
	 * @return Statement fluent interface
	 */
	public static function createDelete(Connection $connection, $table){
		$obj = new self($connection);
		$obj->type = Manager::STMT_DELETE;
		$obj->source = $table;
		return $obj;
	}


	public function run(){
		$result = parent::run();

		try{
			$this->affectedRows = $this->connection->getDriver()->getAffectedRows();
		} catch(DriverException $e){
			$this->affectedRows = false;
		}

		return $result;
	}


	/**
	 * Returns the ID generated in the last INSERT statement.
	 * @return int|FALSE
	 * @throws NeevoException on non-INSERT statements.
	 */
	public function insertId(){
		if($this->type !== Manager::STMT_INSERT)
			throw new NeevoException(__METHOD__ . ' can be called only on INSERT statements.');

		$this->performed || $this->run();
		try{
			return $this->connection->getDriver()->getInsertId();
		} catch(ImplementationException $e){
			return false;
		}
	}


	/**
	 * Returns the number of rows affected by the statement.
	 * @return int
	 */
	public function affectedRows(){
		$this->performed || $this->run();
		if($this->affectedRows === false)
			throw new DriverException('Affected rows are not supported by this driver.');
		return $this->affectedRows;
	}


	/**
	 * Returns the values of statement.
	 * @return array
	 */
	public function getValues(){
		return $this->values;
	}


	/**
	 * Resets the state of the statement.
	 */
	public function resetState(){
		parent::resetState();
		$this->affectedRows = null;
	}


}
