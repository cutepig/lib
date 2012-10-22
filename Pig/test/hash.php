<?php

include '../../Pig.php';
include '../Autoloader.php';

$id = mt_rand();
echo "Original id: $id\n\n";

$hash = Pig::hash($id);
echo "Hashed id: $hash\n\n";

$unhash = Pig::unhash($hash);
echo "Unhashed id: $unhash\n";