
<?php
// Funções alvo do candidato — deixe como base de trabalho.
function _now() {
  return date('Y-m-d H:i:s');
}

function _log_debug($txt) {
  // $file = __DIR__ . '/debug_upload.txt';
  // $line = "[" . date('Y-m-d H:i:s') . "] " . $txt . "\n";
  // @file_put_contents($file, $line, FILE_APPEND);
}

// --- Geocoding (dev stub) -------------------------------------------------
function _normalize_cep($cep) {
  if (!$cep) return '';
  // remove non digits
  $s = preg_replace('/\D/', '', $cep);
  return $s;
}

function event_exists($chave, $status, $data_hora) {
  $conn = db();
  $sql = "SELECT id FROM eventos WHERE chave = ? AND status = ? AND data_hora = ? LIMIT 1";
  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) return false;
  mysqli_stmt_bind_param($stmt, 'sss', $chave, $status, $data_hora);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_store_result($stmt);
  $exists = (mysqli_stmt_num_rows($stmt) > 0);
  mysqli_stmt_close($stmt);
  return $exists;
}

function geocode_stub_lookup($cep) {
  static $map = null;
  if ($map === null) {
    $path = __DIR__ . '/../sample_data/geocode_stub.json';
    if (is_file($path)) {
      $txt = @file_get_contents($path);
      $map = @json_decode($txt, true);
      if (!is_array($map)) $map = array();
    } else {
      $map = array();
    }
  }
  $n = _normalize_cep($cep);
  if ($n === '') return null;
  if (isset($map[$n])) {
    return $map[$n];
  }
  return null;
}

function update_entrega_geocode($entrega_id, $cep) {
  $coords = geocode_stub_lookup($cep);
  if (!$coords) return false;
  $conn = db();
  $lat = isset($coords['lat']) ? $coords['lat'] : null;
  $lng = isset($coords['lng']) ? $coords['lng'] : null;
  $stmt = mysqli_prepare($conn, "UPDATE entregas SET dest_lat = ?, dest_lng = ? WHERE id = ?");
  // bind as strings to allow null
  mysqli_stmt_bind_param($stmt, 'ddi', $lat, $lng, $entrega_id);
  // Some PHP versions require casting
  if ($lat === null) $lat = null;
  if ($lng === null) $lng = null;
  mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);
  return true;
}

function handle_nfe_upload() {
  // validar upload
  $xml = null;
  // debug: log incoming globals
  try {
    _log_debug("\n-- REQUEST START --");
    _log_debug("_FILES: " . var_export(isset($_FILES) ? $_FILES : array(), true));
    _log_debug("_POST: " . var_export(isset($_POST) ? $_POST : array(), true));
    $rawdebug = @file_get_contents('php://input');
    _log_debug("RAW_INPUT(len=" . strlen($rawdebug) . "): " . substr($rawdebug, 0, 2000));
  } catch (Exception $e) {
    // ignore logging errors
  }
  // 1) multipart upload (file)
  if (isset($_FILES) && isset($_FILES['xml']) && is_uploaded_file($_FILES['xml']['tmp_name'])) {
    $f = $_FILES['xml'];
    $content = @file_get_contents($f['tmp_name']);
    if ($content !== false) {
      // remove UTF-8 BOM if present
      $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
      $content = trim($content, "\0\xB" );
      $xml = @simplexml_load_string($content);
      if (!$xml) {
        // try to extract <?xml ... </NFe> block inside content
        $start = strpos($content, '<?xml');
        if ($start !== false) {
          $sub = substr($content, $start);
          $endPos = strrpos($sub, '</NFe>');
          if ($endPos !== false) {
            $sub = substr($sub, 0, $endPos + strlen('</NFe>'));
          }
          $xml = @simplexml_load_string($sub);
        }
      }
    }
  }
  // 2) form field xml (text) or base64
  if (!$xml && isset($_POST['xml_base64']) && $_POST['xml_base64'] !== '') {
    $decoded = @base64_decode($_POST['xml_base64'], true);
    if ($decoded !== false) {
      $decoded = preg_replace('/^\xEF\xBB\xBF/', '', $decoded);
      $xml = @simplexml_load_string($decoded);
    }
  }
  if (!$xml && isset($_POST['xml']) && $_POST['xml'] !== '') {
    $txt = $_POST['xml'];
    $txt = preg_replace('/^\xEF\xBB\xBF/', '', $txt);
    $xml = @simplexml_load_string($txt);
  }
  // 3) raw body
  if (!$xml) {
    $raw = @file_get_contents('php://input');
    if ($raw) {
      $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
      // try direct
      $xml = @simplexml_load_string($raw);
      if (!$xml) {
        $pos = strpos($raw, '<?xml');
        if ($pos !== false) {
          $s = substr($raw, $pos);
          // try to cut at last closing NFe tag if present
          $endPos = strrpos($s, '</NFe>');
          if ($endPos !== false) {
            $s = substr($s, 0, $endPos + strlen('</NFe>'));
          }
          $xml = @simplexml_load_string($s);
        }
      }
    }
  }
  if (!$xml) {
    _log_debug("PARSE FAILED");
    json_out(array('error' => 'invalid xml or missing upload (see backend/debug_upload.txt for details)'), 400);
  }

  // extrair campos mínimos (compatível com o sample)
  $inf = null;
  if (isset($xml->infNFe)) {
    $inf = $xml->infNFe;
  } elseif (isset($xml->NFe->infNFe)) {
    $inf = $xml->NFe->infNFe;
  } else {
    // tentar raiz
    $inf = $xml->infNFe;
  }

  if (!$inf) {
    json_out(array('error' => 'infNFe not found'), 400);
  }

  // chave: Id="NFe3519..." -> remover prefixo 'NFe' se existir
  $attrs = $inf->attributes();
  $idAttr = (string) $attrs['Id'];
  if (!$idAttr) {
    // tentar pegar de outra forma
    $idAttr = (string) $inf['Id'];
  }
  $chave = $idAttr;
  if (strpos($chave, 'NFe') === 0) {
    $chave = substr($chave, 3);
  }
  $emit_cnpj = (string) $inf->emit->CNPJ;
  $dest_cnpj = (string) $inf->dest->CNPJ;
  $dest_nome = (string) $inf->dest->xNome;
  $dest_cep = (string) $inf->dest->enderDest->CEP;
  $valor = (string) $inf->total->ICMSTot->vNF;
  $dhEmi = (string) $inf->ide->dhEmi;
  // normalizar data para YYYY-MM-DD HH:MM:SS (remover timezone se existir)
  $data_emissao = null;
  if ($dhEmi) {
    // tentar substr
    $data_emissao = str_replace('T', ' ', $dhEmi);
    $data_emissao = preg_replace('/(-\d{2}:?\d{2})$/', '', $data_emissao);
  } else {
    $data_emissao = _now();
  }

  $conn = db();

  // verificar existência
  $chave_esc = mysqli_real_escape_string($conn, $chave);
  $sqlCheck = "SELECT id FROM entregas WHERE chave = '".$chave_esc."' LIMIT 1";
  $res = mysqli_query($conn, $sqlCheck);
  if ($res && mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_assoc($res);
    $entrega_id = $row['id'];
    // atualizar campos básicos
    $upd = "UPDATE entregas SET emitente_cnpj='".mysqli_real_escape_string($conn,$emit_cnpj)."', destinatario_cnpj='".mysqli_real_escape_string($conn,$dest_cnpj)."', destinatario_nome='".mysqli_real_escape_string($conn,$dest_nome)."', destinatario_cep='".mysqli_real_escape_string($conn,$dest_cep)."', valor_nota='".mysqli_real_escape_string($conn,$valor)."', data_emissao='".mysqli_real_escape_string($conn,$data_emissao)."' WHERE id='".intval($entrega_id)."'";
    mysqli_query($conn, $upd);
    // tentar geocode via stub (dev)
    @update_entrega_geocode($entrega_id, $dest_cep);
  } else {
    // inserir
    $stmt = mysqli_prepare($conn, "INSERT INTO entregas (chave, emitente_cnpj, destinatario_cnpj, destinatario_nome, destinatario_cep, valor_nota, data_emissao, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $created = _now();
    mysqli_stmt_bind_param($stmt, 'ssssssss', $chave, $emit_cnpj, $dest_cnpj, $dest_nome, $dest_cep, $valor, $data_emissao, $created);
    mysqli_stmt_execute($stmt);
    $entrega_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
  }

  // registrar evento NFE_RECEBIDA (evitar duplicados)
  $status = 'NFE_RECEBIDA';
  $lat = null; $lng = null; $observacao = 'NF-e importada';
  if (!event_exists($chave, $status, $data_emissao)) {
    $stmt2 = mysqli_prepare($conn, "INSERT INTO eventos (chave, status, lat, lng, observacao, data_hora) VALUES (?, ?, ?, ?, ?, ?)");
    // usar strings vazios para lat/lng nulos (colunas DECIMAL aceitam NULL) -> bind como s
    mysqli_stmt_bind_param($stmt2, 'ssssss', $chave, $status, $lat, $lng, $observacao, $data_emissao);
    mysqli_stmt_execute($stmt2);
    mysqli_stmt_close($stmt2);
  }

  // tentar geocode via stub (dev) se inserido
  @update_entrega_geocode($entrega_id, $dest_cep);

  json_out(array('ok' => true, 'chave' => $chave, 'entrega_id' => intval($entrega_id)));
}

function handle_evento() {
  $body = @file_get_contents('php://input');
  $data = @json_decode($body, true);
  if (!$data || !is_array($data)) {
    json_out(array('error' => 'invalid json'), 400);
  }

  // Verifica se é um array de eventos ou um único evento
  $events = [];
  if (isset($data[0]) && is_array($data[0])) {
      $events = $data; // É um array de eventos
  } else {
      $events[] = $data; // É um único evento, coloque-o em um array para processar
  }

  $last_chave = null;

  foreach ($events as $event_data) {
    $required = array('chave', 'status');
    foreach ($required as $r) {
      if (!isset($event_data[$r]) || $event_data[$r] === '') {
        json_out(array('error' => 'missing '.$r.' in one of the events'), 400);
      }
    }
    $chave = $event_data['chave'];
    $status = $event_data['status'];
    $lat = isset($event_data['lat']) ? $event_data['lat'] : null;
    $lng = isset($event_data['lng']) ? $event_data['lng'] : null;
    $observacao = isset($event_data['observacao']) ? $event_data['observacao'] : null;
    $data_hora = isset($event_data['data_hora']) ? $event_data['data_hora'] : _now();

    $conn = db();
    // inserir evento (evitar duplicados)
    if (!event_exists($chave, $status, $data_hora)) {
      $stmt = mysqli_prepare($conn, "INSERT INTO eventos (chave, status, lat, lng, observacao, data_hora) VALUES (?, ?, ?, ?, ?, ?)");
      mysqli_stmt_bind_param($stmt, 'ssssss', $chave, $status, $lat, $lng, $observacao, $data_hora);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);
    }

    // atualizar entregas.status_atual quando terminal
    $terminal = array('ENTREGUE', 'DEVOLVIDA');
    if (in_array($status, $terminal)) {
      $stmt2 = mysqli_prepare($conn, "UPDATE entregas SET status_atual = ? WHERE chave = ?");
      mysqli_stmt_bind_param($stmt2, 'ss', $status, $chave);
      mysqli_stmt_execute($stmt2);
      mysqli_stmt_close($stmt2);
    }
    $last_chave = $chave;
  }

  json_out(array('ok' => true, 'processed_events' => count($events), 'last_chave' => $last_chave));
}

function api_listar_entregas() {
  $conn = db();
  $status = isset($_GET['status']) ? $_GET['status'] : '';
  $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
  $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
  if ($limit <= 0) $limit = 20;
  $offset = ($page - 1) * $limit;
  $where = array();
  if ($status !== '') {
    $where[] = "status_atual = '".mysqli_real_escape_string($conn, $status)."'";
  }
  if (isset($_GET['de'])) {
    $de = mysqli_real_escape_string($conn, $_GET['de']);
    $where[] = "criado_em >= '".$de."'";
  }
  if (isset($_GET['ate'])) {
    $ate = mysqli_real_escape_string($conn, $_GET['ate']);
    $where[] = "criado_em <= '".$ate."'";
  }
  $sql = "SELECT SQL_CALC_FOUND_ROWS id, chave, emitente_cnpj, destinatario_nome, destinatario_cep, valor_nota, data_emissao, status_atual, dest_lat, dest_lng, criado_em FROM entregas";
  if (count($where) > 0) $sql .= ' WHERE '.implode(' AND ', $where);
  $sql .= " ORDER BY criado_em DESC LIMIT ".intval($limit)." OFFSET ".intval($offset);
  $res = mysqli_query($conn, $sql);
  $items = array();
  while ($row = mysqli_fetch_assoc($res)) {
    $items[] = $row;
  }
  $totalRes = mysqli_query($conn, "SELECT FOUND_ROWS() as total");
  $totalRow = mysqli_fetch_assoc($totalRes);
  $total = intval($totalRow['total']);
  json_out(array('data' => $items, 'page' => $page, 'total' => $total));
}

function api_rastreamento() {
  if (!isset($_GET['chave']) || $_GET['chave'] === '') {
    json_out(array('error' => 'missing chave'), 400);
  }
  $chave = $_GET['chave'];
  $conn = db();
  $ch = mysqli_real_escape_string($conn, $chave);
  $res = mysqli_query($conn, "SELECT * FROM entregas WHERE chave = '".$ch."' LIMIT 1");
  $entrega = null;
  if ($res && mysqli_num_rows($res) > 0) {
    $entrega = mysqli_fetch_assoc($res);
  }
  $events = array();
  $res2 = mysqli_query($conn, "SELECT * FROM eventos WHERE chave = '".$ch."' ORDER BY data_hora ASC");
  while ($r = mysqli_fetch_assoc($res2)) {
    $events[] = $r;
  }
  json_out(array('chave' => $chave, 'entrega' => $entrega, 'eventos' => $events));
}

function api_gargalo() {
  
  $conn = db();
  
  
  $status_terminais = "'ENTREGUE', 'DEVOLVIDA'";
  
  $sql = "SELECT status_atual, COUNT(*) as cnt 
          FROM entregas 
          WHERE status_atual NOT IN (" . $status_terminais . ")
          GROUP BY status_atual 
          ORDER BY cnt DESC";

  $res = mysqli_query($conn, $sql);
  $counts = array();
  while ($r = mysqli_fetch_assoc($res)) {
    $counts[$r['status_atual']] = intval($r['cnt']);
  }
  
  $gargalo = null;
  if (count($counts) > 0) {
    reset($counts);
    $gargalo = key($counts);
  }
  
  // Para manter a compatibilidade com o front-end, vamos buscar a contagem total de entregues.
  $res_entregues = mysqli_query($conn, "SELECT COUNT(*) as total FROM entregas WHERE status_atual = 'ENTREGUE'");
  $entregues_row = mysqli_fetch_assoc($res_entregues);
  $counts['ENTREGUE'] = intval($entregues_row['total']);

  $res_total = mysqli_query($conn, "SELECT COUNT(*) as total FROM entregas");
  $total_row = mysqli_fetch_assoc($res_total);
  $total_entregas = intval($total_row['total']);

  json_out(array(
    'gargalo' => $gargalo, 
    'counts' => $counts,
    'total_entregas' => $total_entregas
  ));
}
?>
