<?php
// parseini_multi.php
// Copyright (c) 2011-2017 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

include __DIR__.'/AbstractINIMultiDatabasePDOFactory.class.php';
include __DIR__.'/DDL.class.php';

if (($argc < 3) || ($argc > 4)) {
	fprintf(STDERR, "Please specify a single INI filename, the path to the ddl directory where the YAML schema files exist, and an optional secondary database name.\n");
	exit(1);
}

$connectionParamsByName = AbstractINIMultiDatabasePDOFactory::loadDatabaseIniFile($argv[1]);
$ddldir = $argv[2];
$connectionName = ($argc >= 4) ? trim($argv[3]) : '';

$aggregateDDL = new DDL();
if (($res = YAMLDDLParser::loadAllDDLFiles(realpath($ddldir), $aggregateDDL)) != 0) {
	return $res;
}

$errorMsgs = AbstractINIMultiDatabasePDOFactory::validateDatabaseIniConfiguration($connectionParamsByName, $aggregateDDL);
if (!empty($errorMsgs)) {
	foreach ($errorMsgs as $errorMsg) {
		fputs(STDERR, $errorMsg);
		fputs(STDERR, "\n");
	}
	exit(20);
}

$params = AbstractINIMultiDatabasePDOFactory::getPDOParams($connectionName, null, null, $connectionParamsByName);
foreach ($params as $key=>$val) {
	echo "ini_${key}=".escapeshellarg($val)."\n";
}
echo 'ini_allConnectionNames='.implode(',', array_keys($connectionParamsByName))."\n";

exit(0);
