
-- Schema mínimo para a prova (ajuste se precisar)
CREATE TABLE IF NOT EXISTS entregas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(60) UNIQUE,
  emitente_cnpj VARCHAR(20),
  destinatario_cnpj VARCHAR(20),
  destinatario_nome VARCHAR(120),
  destinatario_cep VARCHAR(12),
  valor_nota DECIMAL(12,2),
  data_emissao DATETIME,
  status_atual VARCHAR(20) DEFAULT 'RECEBIDA',
  dest_lat DECIMAL(10,7),
  dest_lng DECIMAL(10,7),
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS eventos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(60),
  status VARCHAR(20),
  lat DECIMAL(10,7),
  lng DECIMAL(10,7),
  observacao VARCHAR(255),
  data_hora DATETIME,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (chave),
  INDEX (status),
  INDEX (data_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Índices úteis
ALTER TABLE eventos ADD INDEX idx_chave_data (chave, data_hora);

-- Índice de unicidade para evitar eventos duplicados
ALTER TABLE eventos ADD UNIQUE INDEX idx_evento_unico (chave, status, data_hora);
