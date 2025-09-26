
<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/webhook/nfe-upload' && $method === 'POST') {
  handle_nfe_upload();
} elseif ($uri === '/webhook/evento' && $method === 'POST') {
  handle_evento();
} elseif ($uri === '/api/entregas' && $method === 'GET') {
  api_listar_entregas();
} elseif ($uri === '/api/rastreamento' && $method === 'GET') {
  api_rastreamento();
} elseif ($uri === '/api/metricas/gargalo' && $method === 'GET') {
  api_gargalo();
} else {
  json_out(array('error'=>'route not found','path'=>$uri), 404);
}
?>
