<?php
// AbstractINIPDOFactory.class.php
// Copyright (c) 2010-2017 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

abstract class AbstractINIPDOFactory {
	// This must be set in the implementing class.
	public static $INI_FILE;

	public static function getPDO() {
		if (@file_exists(self::$INI_FILE)) {
			$cfg = parse_ini_file(self::$INI_FILE);
			if (($cfg === false) || (!is_array($cfg))) $cfg = array();
		} else {
			$cfg = array();
		}

		$server = isset($cfg['server']) ? (string)$cfg['server'] : 'localhost';
		if (($colonidx = strpos($server, ':')) !== false) {
			$host = substr($server, 0, $colonidx);
			$port = (int)substr($server, $colonidx+1);
		} else {
			$host = $server;
			$port = -1;
		}
		unset($colonidx);
		$username = isset($cfg['username']) ? (string)$cfg['username'] : 'root';
		$password = isset($cfg['password']) ? (string)$cfg['password'] : '';
		$database = isset($cfg['database']) ? (string)$cfg['database'] : '';
		$dialect = isset($cfg['dialect']) ? (string)$cfg['dialect'] : 'mysql';
		switch ($dialect) {
		case 'mysql':
			if ($port < 0) $port = 3306;
			$con = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s', rawurlencode($host), $port, rawurlencode($database)), $username, $password);
			$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			$db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
			$db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
			break;
		case 'pgsql':
			if ($port < 0) $port = 5432;
			$con = new PDO(sprintf('pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s', rawurlencode($host), $port, rawurlencode($database), rawurlencode($username), rawurlencode($password)));
			$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			$db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
			break;
		default:
			throw new Exception(sprintf('Invalid dialect "%s" in connection parameters', $dialect));
		}
		$con->dialect = $dialect;
		$con->connectionName = '';
		return $con;
	}
}
