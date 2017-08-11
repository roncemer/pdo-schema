<?php
// parseini_multi.php
// Copyright (c) 2011-2017 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

if ($argc != 2) {
	fprintf(STDERR, "Please specify a single INI filename.\n");
	exit(1);
}
if (@file_exists($argv[1])) {
	$cfg = parse_ini_file($argv[1]);
	if (($cfg === false) || (!is_array($cfg))) $cfg = array();
} else {
	$cfg = array();
}
foreach ($cfg as $key=>$val) {
	echo "ini_$key=".escapeshellarg($val)."\n";
}
exit(0);
