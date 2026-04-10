<?php

require_once __DIR__ . '/index.php';

$results = searchPathoByKeyword('faim');
print_r(array_slice($results, 0, 2));