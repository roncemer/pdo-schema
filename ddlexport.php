<?php
// ddlexport.php
// Copyright (c) 2011-2017 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

if (!class_exists('DDL', false)) include __DIR__.'/DDL.class.php';

function usage() {
	global $argv;

	fputs(
		STDERR,
		"Usage: php ".basename($argv[0])." [options] <dbServer> <dbUsername> <dbPassword> <dbDatabase> [<tableName> [<tableName> ...]\n".
		"Analyze the tables in an existing database; export DDL in yaml format.\n".
		"    dbServer   - The host name or IP address of the database server.\n".
		"    dbUsername - The user name to use when logging into the database server.\n".
		"    dbPassword - The password to use when logging into the database server.\n".
		"    dbDatabase - The name of the database to use.\n".
		"    tableName  - Optional table name(s) to process.\n".
		"                 If specified, only these tables will be processed;\n".
		"                 otherwise, all tables in the database will be processed.\n".
		"Options:\n".
		"    -dialect <dialect> - Select database dialect. Default dialect is mysql.\n".
		"                         Supported dialects: ".
					implode(', ', DDL::$SUPPORTED_DIALECTS).".\n".
		"    -nodata    - Do not generate inserts to populate the tables with data.\n"
	);
}

$dbServer = '';
$dbUsername = '';
$dbPassword = '';
$dbDatabase = '';
$dialect = 'mysql';
$allowedTableNames = array();
$generateInserts = true;

$argState = 0;
for ($ai = 1; $ai < $argc; $ai++) {
	$arg = $argv[$ai];
	if ( (strlen($arg) > 0) && ($arg[0] == '-') ) {
		switch ($arg) {
		case '-dialect':
			$ai++;
			if ($ai >= $argc) {
				fprintf(STDERR, "Missing database dialect.\n");
				usage();
				exit(1);
			}
			$arg = $argv[$ai];
			if (!in_array($arg, DDL::$SUPPORTED_DIALECTS)) {
				fprintf(STDERR, "Invalid database dialect.\n");
				usage();
				exit(1);
			}
			$dialect = $arg;
			break;
		case '-nodata':
			$generateInserts = false;
			break;
		case '-help':
		case '--help':
		case '-?':
			usage();
			exit(1);
		default:
			fprintf(STDERR, "Unrecognized command line switch: %s.\n", $arg);
			usage();
			exit(1);
		}
		continue;
	}	// if ( (strlen($arg) > 0) && ($arg[0] == '-') )
	switch ($argState) {
	case 0: $dbServer = $arg; $argState++; break;
	case 1: $dbUsername = $arg; $argState++; break;
	case 2: $dbPassword = $arg; $argState++; break;
	case 3: $dbDatabase = $arg; $argState++; break;
	case 4: $allowedTableNames[] = $arg; break;		// remain in this state
	}
}
if ($argState != 4) {
	usage();
	exit(1);
}

if (($colonidx = strpos($dbServer, ':')) !== false) {
	$dbHost = substr($dbServer, 0, $colonidx);
	$dbPort = (int)substr($dbServer, $colonidx+1);
} else {
	$dbHost = $dbServer;
	$dbPort = -1;
}
unset($colonidx);

switch ($dialect) {
case 'mysql':
	if ($dbPort < 0) $dbPort = 3306;
	$db = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s', rawurlencode($dbHost), $dbPort, rawurlencode($dbDatabase)), $dbUsername, $dbPassword);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	$db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
	$db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
	break;
case 'pgsql':
	if ($dbPort < 0) $dbPort = 5432;
	$db = new PDO(sprintf('pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s', rawurlencode($dbHost), $dbPort, rawurlencode($dbDatabase), rawurlencode($dbUsername), rawurlencode($dbPassword)));
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	$db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
	break;
default:
	fprintf(STDERR, "Unsupported dialect: %s\n", $dialect);
}
$db->dialect = $dialect;
$db->connectionName = '';

$loader = new PDODDLLoader();
$ddl = $loader->loadDDL($db, $generateInserts, $allowedTableNames);
///print_r($ddl);
$db = null;
unset($db);

$serializer = new YAMLDDLSerializer();
echo $serializer->serialize($ddl);

exit(0);
