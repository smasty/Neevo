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
 * Neevo observer interface.
 * @author Martin Srank
 * @package Neevo
 */
interface INeevoObserver {

	// Event types
	const CONNECT = 2;

	const SELECT = 4;
	const INSERT = 8;
	const UPDATE = 16;
	const DELETE = 32;
	const QUERY = 60; // SELECT, INSERT, UPDATE, DELETE

	const BEGIN = 64;
	const COMMIT = 128;
	const ROLLBACK = 256;
	const TRANSACTION = 448; // BEGIN, COMMIT, ROLLBACK

	const EXCEPTION = 512;
	const ALL = 1022;


	/**
	 * Receive update from observable.
	 * @param INeevoObservable $observable
	 * @param int $event Event type
	 * @return void
	 */
	public function updateStatus(INeevoObservable $observable, $event);


}
