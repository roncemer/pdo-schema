<?php
// ddldbdiff.php
// Copyright (c) 2011-2017 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

if (!class_exists('AbstractINIMultiDatabasePDOFactory', false)) include __DIR__.'/AbstractINIMultiDatabasePDOFactory.class.php';
if (!class_exists('DDL', false)) include __DIR__.'/DDL.class.php';

function usage() {
	global $argv;

	fputs(
		STDERR,
		"Usage: php ".basename($argv[0])." [options] <database-ini-file> <ddl-dir> [<connection-name>] [<table-name> [<table-name> ...]]\n".
		"Compare a database to DDL file(s); generate SQL commands to update database.\n".
		"    database-ini-file - The path to the database.ini file which contains connection configurations.\n".
		"    ddl-dir           - The path to the ddl directory where all of the YAML DDL files exist.\n".
		"    connection-name   - The connection name, or an empty string (or omit) to use the default connection.\n".
		"    table-name        - Optional table names which, if specified, will be the only tables processed.\n".
		"                        If omitted, all tables will be processed.\n".
		"Options:\n".
		"    -allow-drop-table  - Drop tables which exist in the database but don't exist\n".
		"                         in any of the DDL files.\n".
		"    -allow-drop-column - Drop columns which exist in the database but don't exist\n".
		"                         in the table schema within the DDL files.\n".
		"    -allow-drop-index  - Drop indexes which exist in the database but don't exist\n".
		"                         in the table schema within the DDL files.\n"
	);
	exit(1);
} // usage()

$allowDropTable = false;
$allowDropColumn = false;
$allowDropIndex = false;

$databaseIniFile = '';
$ddlDir = '';
$connectionName = '';
$allowedTableNames = array();

$argState = 0;
for ($ai = 1; $ai < $argc; $ai++) {
	$arg = $argv[$ai];
	if ( (strlen($arg) > 0) && ($arg[0] == '-') ) {
		switch ($arg) {
		case '-allow-drop-table':
			$allowDropTable = true;
			break;
		case '-allow-drop-column':
			$allowDropColumn = true;
			break;
		case '-allow-drop-index':
			$allowDropIndex = true;
			break;
		case '-help':
		case '--help':
		case '-?':
			usage();
		default:
			fprintf(STDERR, "Unrecognized command line switch: %s.\n", $arg);
			usage();
		}
		continue;
	}	// if ( (strlen($arg) > 0) && ($arg[0] == '-') )
	switch ($argState) {
	case 0: $databaseIniFile = $arg; $argState++; break;
	case 1: $ddlDir = $arg; $argState++; break;
	case 2: $connectionName = $arg; $argState++; break;
	case 3: $allowedTableNames[] = $arg;		// remain in this state.
	}
}

if ($databaseIniFile == '') {
	fprintf(STDERR, "No database.ini file specified.\n");
	usage();
}
if ($ddlDir == '') {
	fprintf(STDERR, "No DDL directory specified.\n");
	usage();
}

$databaseAllowedTableNames = array_slice($allowedTableNames, 0);

// Build a connection factory which works witht the specifed database.ini file.
class MyPDOFactory extends AbstractINIMultiDatabasePDOFactory {
}
MyPDOFactory::$INI_FILE = $databaseIniFile;

$connectionParamsByName = MyPDOFactory::getPDOParamsByName();
$cp = MyPDOFactory::getPDOParams($connectionName, null, null, $connectionParamsByName);
if (empty($cp)) {
	fprintf(STDERR, "Invalid connection name: %s\n", $connectionName);
	exit(30);
}
$connectionParams = MyPDOFactory::getPDOParams($connectionName);
$dbmap = (isset($connectionParams['tableToDatabaseMap']) && ($connectionParams['tableToDatabaseMap'] != '')) ?
	new DDLTableToDatabaseMap($connectionParams['tableToDatabaseMap']) : null;

// Get list of table names for this connection which are mapped from other connections;
// merge with allowed table names to get final table name list.
$allCurrentDDL = new DDL();
if (($res = YAMLDDLParser::loadAllDDLFiles($ddlDir, $allCurrentDDL)) != 0) exit($res);
$mapTargetTableNames = MyPDOFactory::getMapTargetTableNames($connectionName, $connectionParamsByName, $allCurrentDDL);
unset($allCurrentDDL, $res);
if (!empty($mapTargetTableNames)) {
	if (!empty($allowedTableNames)) {
		$allowedTableNames = array_intersect($allowedTableNames, $mapTargetTableNames);
	} else {
		$allowedTableNames = $mapTargetTableNames;
	}
}

// Load the DDL from the database, filtering out all non-allowed tables.
$db = MyPDOFactory::getPDO($connectionName);
$dialect = $db->dialect;
$loader = new PDODDLLoader();
$databaseDDL = $loader->loadDDL($db, false, $databaseAllowedTableNames);
$db = null;

// Load the current DDL from the DDL files.
$currentDDL = new DDL(array());
if (($res = YAMLDDLParser::loadAllDDLFiles($ddlDir, $currentDDL, $allowedTableNames, $dbmap)) != 0) exit($res);

// If we have map-target tables, remove all foreign keys from the current DDL which
// reference tables which are not map targets (because those tables should not exist
// in this database).
if (!empty($mapTargetTableNames)) {
	$anyDeleted = false;
	for ($i = 0, $n = count($currentDDL->topLevelEntities); $i < $n; $i++) {
		if (($currentDDL->topLevelEntities[$i] instanceof DDLForeignKey) &&
			(!in_array($currentDDL->topLevelEntities[$i]->foreignTableName, $mapTargetTableNames))) {
			unset($currentDDL->topLevelEntities[$i]);
			$anyDeleted = true;
		}
	}
	if ($anyDeleted) $currentDDL->topLevelEntities = array_slice($currentDDL->topLevelEntities, 0);
	unset($i, $n, $anyDeleted);
}

// Generate SQL to update the DDL in the database to match the current DDL from the DDL files.
$updater = new SQLDDLUpdater();
$sqlStatements = $updater->generateSQLUpdates(
	$databaseDDL,		// $oldDDL
	$currentDDL,		// $newDDL
	$allowDropTable,	// $allowDropTable
	$allowDropColumn,	// $allowDropColumn
	$allowDropIndex,	// $allowDropIndex
	$dialect,			// $dialect
	$dbmap,				// $dbmap
	null,				// $localDBName
	$ddlDir				// $basepath
);

if (($dialect == 'mysql') && (!empty($sqlStatements))) fputs(STDOUT, "set foreign_key_checks = 0;\n");
foreach ($sqlStatements as $stmt) {
	fprintf(STDOUT, "%s;\n", $stmt);
}
if (($dialect == 'mysql') && (!empty($sqlStatements))) fputs(STDOUT, "set foreign_key_checks = 1;\n");

exit(0);
