<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(array('error'=>'method not allowed'), 405);
}

handle_nfe_upload();
