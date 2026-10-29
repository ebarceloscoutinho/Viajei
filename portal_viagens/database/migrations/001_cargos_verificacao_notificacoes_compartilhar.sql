-- Migração 001 — atualiza um banco criado pela versão anterior (idempotente: pode rodar de novo sem estragar).
-- Cargos do admin, verificação de e-mail, notificações internas, compartilhar roteiro e precisão do câmbio.
INSERT IGNORE INTO tipos_usuario VALUES (4,'Financeiro'),(5,'Atendimento'),(6,'Catálogo');
ALTER TABLE usuarios ADD COLUMN email_verificado_em DATETIME NULL COMMENT 'nulo = e-mail ainda não confirmado (não pode reservar)';
UPDATE usuarios SET email_verificado_em = NOW() WHERE email_verificado_em IS NULL;
ALTER TABLE roteiros ADD COLUMN token_compartilhar VARCHAR(40) NULL COMMENT 'link secreto de leitura (compartilhar roteiro)';
ALTER TABLE roteiros ADD UNIQUE KEY uq_roteiro_token (token_compartilhar);
CREATE TABLE IF NOT EXISTS tokens_email (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira DATETIME NOT NULL,
  usado TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS notificacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  titulo VARCHAR(150) NOT NULL,
  mensagem VARCHAR(255) NULL,
  url VARCHAR(255) NULL,
  lida TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);
CREATE INDEX idx_notificacoes_usuario ON notificacoes (usuario_id, lida);
ALTER TABLE moedas MODIFY cotacao_brl DECIMAL(16,8) NOT NULL COMMENT 'quantos reais vale 1 unidade da moeda';
