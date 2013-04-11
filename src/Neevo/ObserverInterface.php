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


/**
 * Neevo observer interface.
 * @author Smasty
 */
interface ObserverInterface {


	// Event types
	const CONNECT = 2,
	SELECT = 4,
	INSERT = 8,
	UPDATE = 16,
	DELETE = 32,
	QUERY = 60, // SELECT, INSERT, UPDATE, DELETE

	BEGIN = 64,
	COMMIT = 128,
	ROLLBACK = 256,
	TRANSACTION = 448, // BEGIN, COMMIT, ROLLBACK

	EXCEPTION = 512,
	DISCONNECT =1024,
	ALL = 2046;


	/**
	 * Receives update from observable subject.
	 * @param ObservableInterface $subject
	 * @param int $event Event type
	 */
	public function updateStatus(ObservableInterface $subject, $event);


}
