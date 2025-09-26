
<?php
// PHP 5.2/5.3 compatível
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'mapcloud';

function db() {
  static $conn = null;
  if ($conn) return $conn;
  $conn = mysqli_connect($GLOBALS['DB_HOST'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASS'], $GLOBALS['DB_NAME']);
  if (!$conn) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(array('error' => 'DB connection failed'));
    exit;
  }
  mysqli_set_charset($conn, 'utf8');
  return $conn;
}

function json_out($data, $code = 200) {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code($code);
  echo json_encode($data);
  exit;
}
?>
