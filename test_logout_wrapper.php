<?php
ob_start();
require 'logout.php';
$output = ob_get_clean();
var_dump(headers_list());
