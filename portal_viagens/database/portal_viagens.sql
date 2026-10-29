-- Portal de Viagens e Roteiros  |  MySQL 8 / MariaDB
-- Senha de TODOS os usuários de exemplo: viagem123
CREATE DATABASE IF NOT EXISTS portal_viagens CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE portal_viagens;
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS migracoes, notificacoes, tokens_email, emails_fila, cupons, chamados, configuracoes, guia_locais, guias, lancamentos, categorias_financeiras, grupos_financeiros, naturezas_financeiras, avaliacoes, favoritos, paises, moedas,
  logs_acesso, reset_senha, historico_precos, pagamentos, lista_espera, reservas, roteiro_itens, roteiros, saidas, passeios, guias_viagem, categorias, usuarios,
  tipos_usuario, status_usuario, status_chamado, status_email, tipos_cupom, escopos, status_reserva, status_pagamento, metodos_pagamento, status_espera;
SET FOREIGN_KEY_CHECKS=1;


-- ===== TABELAS DE DOMÍNIO (valores editáveis, com integridade por chave estrangeira) =====
CREATE TABLE tipos_usuario (id TINYINT PRIMARY KEY, nome VARCHAR(40) NOT NULL);
INSERT INTO tipos_usuario VALUES (1,'Viajante'),(2,'Guia local'),(3,'Administrador'),(4,'Financeiro'),(5,'Atendimento'),(6,'Catálogo');
CREATE TABLE status_usuario (codigo VARCHAR(20) PRIMARY KEY, nome VARCHAR(40) NOT NULL);
INSERT INTO status_usuario VALUES ('ativo','Ativo'),('inativo','Inativo'),('bloqueado','Bloqueado');
CREATE TABLE escopos (codigo VARCHAR(20) PRIMARY KEY, nome VARCHAR(40) NOT NULL);
INSERT INTO escopos VALUES ('nacional','Nacional'),('internacional','Internacional');
CREATE TABLE status_reserva (codigo VARCHAR(30) PRIMARY KEY, nome VARCHAR(40) NOT NULL, ordem TINYINT NOT NULL);
INSERT INTO status_reserva VALUES ('aguardando_pagamento','Aguardando pagamento',1),('em_analise','Pagamento em análise',2),('confirmada','Confirmada',3),('cancelada','Cancelada',4),('expirada','Expirada',5);
CREATE TABLE status_pagamento (codigo VARCHAR(20) PRIMARY KEY, nome VARCHAR(40) NOT NULL);
INSERT INTO status_pagamento VALUES ('informado','Informado pelo cliente'),('confirmado','Confirmado'),('recusado','Recusado');
CREATE TABLE metodos_pagamento (codigo VARCHAR(20) PRIMARY KEY, nome VARCHAR(40) NOT NULL);
INSERT INTO metodos_pagamento VALUES ('pix','PIX'),('transferencia','Transferência bancária');
CREATE TABLE status_chamado (codigo VARCHAR(20) PRIMARY KEY, nome VARCHAR(40) NOT NULL);
INSERT INTO status_chamado VALUES ('aberto','Aberto'),('respondido','Respondido'),('fechado','Fechado');
CREATE TABLE status_email (codigo VARCHAR(20) PRIMARY KEY, nome VARCHAR(40) NOT NULL);
INSERT INTO status_email VALUES ('pendente','Pendente'),('enviado','Enviado'),('falhou','Falhou');
CREATE TABLE tipos_cupom (codigo VARCHAR(20) PRIMARY KEY, nome VARCHAR(40) NOT NULL);
INSERT INTO tipos_cupom VALUES ('percentual','Percentual (%)'),('valor','Valor fixo (R$)');
CREATE TABLE status_espera (codigo VARCHAR(20) PRIMARY KEY, nome VARCHAR(40) NOT NULL);
INSERT INTO status_espera VALUES ('aguardando','Aguardando'),('notificado','Notificado'),('cancelado','Cancelado');

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  telefone VARCHAR(20) NULL,
  tipo_user TINYINT NOT NULL DEFAULT 1,
  senha VARCHAR(255) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ativo',
  bloqueado_ate DATETIME NULL COMMENT 'impede novas reservas (reserva expirada sem pagamento)',
  tema_modo VARCHAR(10) NULL COMMENT 'preferência individual: auto, light ou dark',
  tema_cor VARCHAR(20) NULL COMMENT 'preferência individual de cor do site',
  tema_fonte VARCHAR(10) NULL COMMENT 'preferência individual: normal ou grande',
  totp_secret VARCHAR(64) NULL COMMENT 'segredo do app autenticador (2FA do administrador)',
  totp_ativo TINYINT(1) NOT NULL DEFAULT 0,
  aceite_termos_em DATETIME NULL COMMENT 'quando aceitou Termos e Política de Privacidade',
  email_verificado_em DATETIME NULL COMMENT 'nulo = e-mail ainda não confirmado (não pode reservar)',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tipo_user) REFERENCES tipos_usuario(id),
  FOREIGN KEY (status) REFERENCES status_usuario(codigo)
);
CREATE TABLE guias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL COMMENT 'conta de login do guia (opcional)',
  nome VARCHAR(120) NOT NULL,
  foto VARCHAR(255) NULL COMMENT 'caminho em uploads/ ou URL; vazio = avatar com iniciais',
  email VARCHAR(150) NULL,
  telefone VARCHAR(25) NULL,
  idiomas VARCHAR(120) NULL,
  bio TEXT NULL,
  salario DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'salário mensal (visível só no admin)',
  data_admissao DATE NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);
CREATE TABLE guia_locais (
  id INT AUTO_INCREMENT PRIMARY KEY,
  guia_id INT NOT NULL,
  local VARCHAR(80) NOT NULL COMMENT 'país ou região de atuação',
  FOREIGN KEY (guia_id) REFERENCES guias(id) ON DELETE CASCADE
);
CREATE TABLE configuracoes (
  chave VARCHAR(60) PRIMARY KEY,
  valor VARCHAR(255) NULL,
  rotulo VARCHAR(80) NOT NULL
);
CREATE TABLE emails_fila (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  para VARCHAR(150) NOT NULL,
  assunto VARCHAR(200) NOT NULL,
  corpo TEXT NOT NULL,
  tipo VARCHAR(40) NOT NULL DEFAULT 'geral',
  status VARCHAR(20) NOT NULL DEFAULT 'pendente',
  tentativas INT NOT NULL DEFAULT 0,
  erro VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  enviado_em DATETIME NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  FOREIGN KEY (status) REFERENCES status_email(codigo)
);
CREATE TABLE chamados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL,
  assunto VARCHAR(150) NOT NULL,
  mensagem TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'aberto',
  resposta TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  respondido_em DATETIME NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  FOREIGN KEY (status) REFERENCES status_chamado(codigo)
);
CREATE TABLE categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  icone VARCHAR(8) NOT NULL DEFAULT '🧭'
);
CREATE TABLE passeios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  categoria_id INT NOT NULL,
  guia_id INT NULL,
  titulo VARCHAR(200) NOT NULL,
  destino VARCHAR(120) NOT NULL,
  descricao TEXT,
  duracao_horas DECIMAL(4,1) NOT NULL DEFAULT 4,
  preco DECIMAL(10,2) NOT NULL,
  emoji VARCHAR(30) NOT NULL DEFAULT '🌎' COMMENT 'emojis extras da capa (ex.: 🍕🏛️)',
  motivo VARCHAR(30) NULL COMMENT 'silhueta do ponto turístico da capa',
  imagem VARCHAR(255) NULL COMMENT 'foto: caminho em uploads/ ou URL',
  video_id VARCHAR(20) NULL COMMENT 'ID do vídeo do YouTube',
  hue SMALLINT NOT NULL DEFAULT 180,
  escopo VARCHAR(20) NOT NULL DEFAULT 'nacional',
  pais VARCHAR(80) NOT NULL DEFAULT 'Brasil',
  dias INT NOT NULL DEFAULT 1,
  atracoes TEXT NULL COMMENT 'lista separada por ponto e vírgula',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  FOREIGN KEY (guia_id) REFERENCES guias(id) ON DELETE SET NULL,
  FOREIGN KEY (escopo) REFERENCES escopos(codigo)
);
-- Cada passeio tem várias saídas (datas) com controle de vagas
CREATE TABLE saidas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  passeio_id INT NOT NULL,
  data_saida DATE NOT NULL,
  vagas_total INT NOT NULL,
  vagas_disponiveis INT NOT NULL,
  FOREIGN KEY (passeio_id) REFERENCES passeios(id) ON DELETE CASCADE
);
CREATE TABLE cupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL UNIQUE,
  tipo VARCHAR(20) NOT NULL DEFAULT 'percentual',
  valor DECIMAL(10,2) NOT NULL,
  validade DATE NULL,
  usos_max INT NULL COMMENT 'nulo = ilimitado; conta reservas não canceladas',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT ck_cupom_valor CHECK (valor > 0),
  FOREIGN KEY (tipo) REFERENCES tipos_cupom(codigo)
);
CREATE TABLE reservas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  saida_id INT NOT NULL,
  qtd INT NOT NULL,
  valor_total DECIMAL(10,2) NOT NULL,
  taxa_cancelamento DECIMAL(10,2) NOT NULL DEFAULT 0,
  status VARCHAR(30) NOT NULL DEFAULT 'aguardando_pagamento',
  prazo_pagamento DATETIME NULL,
  cupom_id INT NULL,
  desconto DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'desconto do cupom já abatido em valor_total',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (saida_id) REFERENCES saidas(id),
  FOREIGN KEY (cupom_id) REFERENCES cupons(id) ON DELETE SET NULL,
  FOREIGN KEY (status) REFERENCES status_reserva(codigo)
);
-- Lista de espera quando a saída está lotada (ideia da fila_espera da biblioteca)
CREATE TABLE lista_espera (
  id INT AUTO_INCREMENT PRIMARY KEY,
  saida_id INT NOT NULL,
  usuario_id INT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'aguardando',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (saida_id) REFERENCES saidas(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (status) REFERENCES status_espera(codigo)
);
-- Roteiros: usuario_id NULL = roteiro-modelo da casa
CREATE TABLE roteiros (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  titulo VARCHAR(200) NOT NULL,
  destino VARCHAR(120) NOT NULL,
  dias INT NOT NULL DEFAULT 3,
  data_inicio DATE NULL,
  publico TINYINT(1) NOT NULL DEFAULT 0,
  token_compartilhar VARCHAR(40) NULL UNIQUE COMMENT 'link secreto de leitura (compartilhar roteiro)',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);
CREATE TABLE roteiro_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  roteiro_id INT NOT NULL,
  dia INT NOT NULL DEFAULT 1,
  horario CHAR(5) NULL,
  passeio_id INT NULL,
  nota VARCHAR(255) NULL,
  FOREIGN KEY (roteiro_id) REFERENCES roteiros(id) ON DELETE CASCADE,
  FOREIGN KEY (passeio_id) REFERENCES passeios(id) ON DELETE SET NULL
);
CREATE TABLE guias_viagem (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(200) NOT NULL,
  destino VARCHAR(120) NOT NULL,
  resumo VARCHAR(300) NOT NULL,
  conteudo TEXT NOT NULL,
  emoji VARCHAR(8) NOT NULL DEFAULT '📖',
  hue SMALLINT NOT NULL DEFAULT 200,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE logs_acesso (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  email VARCHAR(150) NOT NULL,
  sucesso TINYINT(1) NOT NULL,
  area VARCHAR(10) NOT NULL DEFAULT 'site' COMMENT 'site ou admin',
  ip VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE pagamentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reserva_id INT NOT NULL,
  metodo VARCHAR(20) NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  comprovante VARCHAR(120) NOT NULL COMMENT 'código/ID da transação informado pelo cliente',
  status VARCHAR(20) NOT NULL DEFAULT 'informado',
  motivo VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  decidido_em DATETIME NULL,
  decidido_por INT NULL,
  FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE CASCADE,
  FOREIGN KEY (metodo) REFERENCES metodos_pagamento(codigo),
  FOREIGN KEY (status) REFERENCES status_pagamento(codigo)
);
CREATE TABLE historico_precos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  passeio_id INT NOT NULL,
  preco_antigo DECIMAL(10,2) NOT NULL,
  preco_novo DECIMAL(10,2) NOT NULL,
  usuario_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (passeio_id) REFERENCES passeios(id) ON DELETE CASCADE
);
CREATE TABLE reset_senha (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira DATETIME NOT NULL,
  usado TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Câmbio e custos de referência usados no quiz "Quanto custa a viagem?" (valores de EXEMPLO — atualize no painel admin)
CREATE TABLE moedas (
  codigo CHAR(3) PRIMARY KEY,
  nome VARCHAR(60) NOT NULL,
  simbolo VARCHAR(8) NOT NULL,
  cotacao_brl DECIMAL(16,8) NOT NULL COMMENT 'quantos reais vale 1 unidade da moeda',
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE paises (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(80) NOT NULL UNIQUE,
  moeda CHAR(3) NOT NULL,
  hospedagem_dia DECIMAL(14,2) NOT NULL COMMENT 'quarto duplo por noite, moeda local, padrão econômico',
  alimentacao_dia DECIMAL(14,2) NOT NULL COMMENT 'por pessoa/dia, moeda local',
  transporte_dia DECIMAL(14,2) NOT NULL COMMENT 'transporte local por pessoa/dia, moeda local',
  lazer_dia DECIMAL(14,2) NOT NULL COMMENT 'passeios e ingressos por pessoa/dia, moeda local',
  seguro_dia_brl DECIMAL(10,2) NOT NULL COMMENT 'seguro viagem por pessoa/dia, em reais',
  passagem_brl DECIMAL(10,2) NOT NULL COMMENT 'passagem aérea ida e volta por pessoa, em reais',
  visto DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'visto/autorização por pessoa, moeda local',
  visto_obs VARCHAR(160) NULL,
  FOREIGN KEY (moeda) REFERENCES moedas(codigo)
);
CREATE TABLE favoritos (
  usuario_id INT NOT NULL,
  passeio_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, passeio_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (passeio_id) REFERENCES passeios(id) ON DELETE CASCADE
);
CREATE TABLE avaliacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  passeio_id INT NOT NULL,
  nota TINYINT NOT NULL,
  comentario VARCHAR(1000) NULL,
  visivel TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'moderação: 0 oculta a avaliação',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_avaliacao (usuario_id, passeio_id),
  CONSTRAINT ck_nota CHECK (nota BETWEEN 1 AND 5),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (passeio_id) REFERENCES passeios(id) ON DELETE CASCADE
);
-- Financeiro: natureza (receita/despesa) e grupo (DRE simplificada) em tabelas de domínio
CREATE TABLE naturezas_financeiras (codigo VARCHAR(20) PRIMARY KEY, nome VARCHAR(40) NOT NULL);
INSERT INTO naturezas_financeiras VALUES ('receita','Receita (ganho)'),('despesa','Despesa (perda)');
CREATE TABLE grupos_financeiros (codigo VARCHAR(20) PRIMARY KEY, nome VARCHAR(60) NOT NULL, ordem TINYINT NOT NULL);
INSERT INTO grupos_financeiros VALUES ('receita','Receita bruta',1),('deducao','Deduções (reembolsos)',2),('custo_direto','Custos diretos',3),('operacional','Despesas operacionais',4);
CREATE TABLE categorias_financeiras (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(80) NOT NULL,
  natureza VARCHAR(20) NOT NULL,
  grupo VARCHAR(20) NOT NULL,
  FOREIGN KEY (natureza) REFERENCES naturezas_financeiras(codigo),
  FOREIGN KEY (grupo) REFERENCES grupos_financeiros(codigo)
);
CREATE TABLE lancamentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data_lanc DATE NOT NULL,
  categoria_id INT NOT NULL,
  descricao VARCHAR(200) NOT NULL,
  valor DECIMAL(12,2) NOT NULL COMMENT 'sempre positivo; o sinal vem da natureza da categoria',
  pago TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = realizado; 0 = previsto (a pagar/receber)',
  reserva_id INT NULL,
  criado_por INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT ck_valor CHECK (valor > 0),
  FOREIGN KEY (categoria_id) REFERENCES categorias_financeiras(id),
  FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE SET NULL
);
INSERT INTO moedas (codigo,nome,simbolo,cotacao_brl) VALUES
('BRL','Real','R$',1),
('USD','Dólar americano','US$',5.4),
('EUR','Euro','€',6.3),
('GBP','Libra esterlina','£',7.2),
('CHF','Franco suíço','CHF',6.7),
('CZK','Coroa tcheca','Kč',0.26),
('TRY','Lira turca','₺',0.13),
('ISK','Coroa islandesa','kr',0.044),
('NOK','Coroa norueguesa','kr',0.53),
('CAD','Dólar canadense','C$',3.95),
('MXN','Peso mexicano','MX$',0.29),
('ARS','Peso argentino','AR$',0.004),
('CLP','Peso chileno','CL$',0.0057),
('PEN','Sol peruano','S/',1.5),
('COP','Peso colombiano','COL$',0.0013),
('UYU','Peso uruguaio','$U',0.13),
('BOB','Boliviano','Bs',0.78),
('CRC','Colón costarriquenho','₡',0.0107),
('JPY','Iene japonês','¥',0.036),
('THB','Baht tailandês','฿',0.165),
('CNY','Yuan chinês','CN¥',0.76),
('INR','Rupia indiana','₹',0.064),
('AED','Dirham dos Emirados','AED',1.47),
('IDR','Rupia indonésia','Rp',0.00033),
('VND','Dong vietnamita','₫',0.000215),
('KRW','Won sul-coreano','₩',0.0039),
('SGD','Dólar de Singapura','S$',4.15),
('NPR','Rupia nepalesa','Rs',0.04),
('JOD','Dinar jordaniano','JD',7.6),
('EGP','Libra egípcia','E£',0.11),
('MAD','Dirham marroquino','MAD',0.59),
('ZAR','Rand sul-africano','R',0.3),
('TZS','Xelim tanzaniano','TSh',0.0021),
('AUD','Dólar australiano','A$',3.55),
('NZD','Dólar neozelandês','NZ$',3.2);
INSERT INTO paises (nome,moeda,hospedagem_dia,alimentacao_dia,transporte_dia,lazer_dia,seguro_dia_brl,passagem_brl,visto,visto_obs) VALUES
('Brasil','BRL',220,110,45,80,65,900,0,'Sem documentação internacional (RG ou CNH bastam)'),
('França','EUR',90.0,41.1,18.9,32.6,27,5300,20,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Itália','EUR',90.0,41.1,18.9,32.6,27,5300,20,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Espanha','EUR',60.0,29.1,12.9,22.3,27,5300,20,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Portugal','EUR',60.0,29.1,12.9,22.3,27,5300,20,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Alemanha','EUR',90.0,41.1,18.9,32.6,27,5300,20,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Grécia','EUR',60.0,29.1,12.9,22.3,27,5300,20,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Holanda','EUR',90.0,41.1,18.9,32.6,27,5300,20,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Suíça','CHF',113,48.4,22.6,40.3,32,5300,19,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Áustria','EUR',90.0,41.1,18.9,32.6,27,5300,20,'ETIAS (Schengen) previsto — confirme no site oficial'),
('República Tcheca','CZK',1450,706,312,540,27,5300,500,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Croácia','EUR',60.0,29.1,12.9,22.3,27,5300,20,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Noruega','NOK',1430,611,285,509,32,5300,230,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Islândia','ISK',17200,7360,3440,6140,32,5300,3000,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Reino Unido','GBP',78.8,36.0,16.5,28.5,32,5300,16,'ETA britânico — confirme exigência atual'),
('Irlanda','EUR',90.0,41.1,18.9,32.6,27,5300,0,'Brasileiros: sem visto para turismo'),
('Turquia','TRY',1740,831,332,665,27,6600,0,'Brasileiros: sem visto para turismo'),
('Estados Unidos','USD',105,48.0,22.0,38.0,49,4900,185,'Visto B1/B2 (taxa consular) — agende com antecedência'),
('Canadá','CAD',144,65.6,30.1,51.9,32,4900,100,'Visto de visitante — confirme elegibilidade para eTA'),
('México','MXN',782,372,149,298,27,3600,0,'Brasileiros: sem visto para turismo'),
('Argentina','ARS',56700,27000,10800,21600,22,2800,0,'Documento de identidade com foto aceito'),
('Chile','CLP',66300,32200,14200,24600,22,2800,0,'Documento de identidade com foto aceito'),
('Peru','PEN',151,72.0,28.8,57.6,22,2800,0,'Documento de identidade com foto aceito'),
('Colômbia','COP',174000,83100,33200,66500,22,2800,0,'Passaporte ou RG; confirme regras'),
('Cuba','USD',42.0,20.0,8.0,16.0,32,3600,25,'Cartão de turista (~US$ 25)'),
('Uruguai','UYU',2910,1410,623,1080,22,2800,0,'Documento de identidade com foto aceito'),
('Bolívia','BOB',180,90.0,34.6,69.2,22,2800,0,'Exige certificado de febre amarela'),
('Equador','USD',42.0,20.0,8.0,16.0,22,2800,0,'Galápagos: taxa de ingresso à parte'),
('Costa Rica','CRC',35300,17200,7570,13100,27,3600,0,'Exige certificado de febre amarela (conforme origem)'),
('Japão','JPY',15800,7200,3300,5700,27,7900,0,'Visto de turista exigido (isento de taxa para brasileiros)'),
('Tailândia','THB',1370,655,262,524,27,7900,0,'Brasileiros: sem visto para turismo'),
('China','CNY',497,242,107,185,27,7900,0,'Isenção para brasileiros — confirme vigência'),
('Índia','INR',2190,1100,422,844,27,7900,3300,'e-Visa de turismo (~US$ 40)'),
('Emirados Árabes Unidos','AED',386,176,80.8,140,27,6600,0,'Brasileiros: sem visto para turismo'),
('Indonésia','IDR',687000,327000,131000,262000,27,7900,500000,'Visto na chegada (~US$ 33) + taxa turística de Bali'),
('Vietnã','VND',653000,327000,126000,251000,27,7900,625000,'e-Visa (~US$ 25)'),
('Coreia do Sul','KRW',96900,47100,20800,36000,27,7900,0,'K-ETA — confirme exigência atual'),
('Singapura','SGD',137,62.5,28.6,49.4,27,7900,0,'Cartão de embarque digital (gratuito)'),
('Maldivas','USD',140,60.0,28.0,50.0,27,8900,0,'Visto grátis na chegada; resorts cobram em dólar'),
('Nepal','NPR',3510,1760,675,1350,27,7900,6600,'Visto na chegada (~US$ 50)'),
('Jordânia','JOD',49.7,24.2,10.7,18.5,27,6600,0,'Jordan Pass pode incluir visto e ingresso de Petra'),
('Egito','EGP',1280,638,245,491,27,6600,1250,'Visto na chegada (~US$ 25)'),
('Marrocos','MAD',384,183,73.2,146,27,6800,0,'Brasileiros: sem visto para turismo'),
('África do Sul','ZAR',1260,612,270,468,27,6800,0,'Exige certificado de febre amarela (conforme origem)'),
('Tanzânia','TZS',180000,87400,38600,66900,32,6800,130000,'Visto (~US$ 50) + certificado de febre amarela'),
('Austrália','AUD',160,73.0,33.5,57.8,32,9800,200,'Visto de visitante (subclasse 600)'),
('Nova Zelândia','NZD',177,81.0,37.1,64.1,32,9800,117,'NZeTA + taxa de turismo (IVL)');
-- Países adicionais (valores de exemplo)
INSERT INTO moedas (codigo,nome,simbolo,cotacao_brl) VALUES
('HUF','Florim húngaro','Ft',0.0158),
('PLN','Zloty polonês','zł',1.48),
('SEK','Coroa sueca','kr',0.57),
('DKK','Coroa dinamarquesa','kr',0.84),
('ILS','Novo shekel','₪',1.5),
('QAR','Rial catari','QR',1.48),
('MYR','Ringgit malaio','RM',1.25),
('PHP','Peso filipino','₱',0.095),
('LKR','Rupia do Sri Lanka','Rs',0.018),
('KES','Xelim queniano','KSh',0.042),
('NAD','Dólar namibiano','N$',0.3),
('MUR','Rupia mauriciana','₨',0.12),
('DOP','Peso dominicano','RD$',0.088),
('JMD','Dólar jamaicano','J$',0.034),
('PYG','Guarani','₲',0.00072),
('FJD','Dólar fijiano','FJ$',2.4),
('TND','Dinar tunisiano','DT',1.75),
('GEL','Lari georgiano','₾',2.0),
('SCR','Rupia seichelense','SR',0.39);
INSERT INTO paises (nome,moeda,hospedagem_dia,alimentacao_dia,transporte_dia,lazer_dia,seguro_dia_brl,passagem_brl,visto,visto_obs) VALUES
('Hungria','HUF',23900,11600,5130,8890,27,5300,8000,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Polônia','PLN',153,73.0,29.2,58.4,27,5300,85,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Suécia','SEK',995,455,208,360,32,5300,220,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Dinamarca','DKK',675,309,141,244,32,5300,150,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Finlândia','EUR',90.0,41.1,18.9,32.6,32,5300,20,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Bélgica','EUR',90.0,41.1,18.9,32.6,27,5300,20,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Malta','EUR',60.0,29.1,12.9,22.3,27,5300,20,'ETIAS (Schengen) previsto — confirme no site oficial'),
('Chipre','EUR',60.0,29.1,12.9,22.3,27,6600,0,'Brasileiros: sem visto para turismo'),
('Israel','ILS',378,173,79.2,137,32,6600,0,'Sem visto para turismo; consulte alertas de viagem'),
('Catar','QAR',383,175,80.3,139,27,6600,0,'Confirme a exigência atual de autorização'),
('Malásia','MYR',181,86.4,34.6,69.1,27,7900,0,'Sem visto para turismo; confirme regras de entrada'),
('Filipinas','PHP',2390,1140,455,909,27,7900,0,'Sem visto para turismo (estadia curta)'),
('Sri Lanka','LKR',7800,3900,1500,3000,27,7900,15000,'Autorização eletrônica (ETA, ~US$ 50)'),
('Camboja','USD',26.0,13.0,5.0,10.0,27,7900,30,'e-Visa (~US$ 30)'),
('Quênia','KES',9000,4370,1930,3340,32,6800,3900,'Autorização eletrônica (eTA)'),
('Namíbia','NAD',1260,612,270,468,27,6800,0,'Sem visto para turismo (estadia curta)'),
('Seychelles','SCR',1940,831,388,692,27,8900,0,'Autorização eletrônica de viagem (sem custo)'),
('Maurício','MUR',4720,2160,990,1710,27,6800,0,'Brasileiros: sem visto para turismo'),
('Panamá','USD',70.0,34.0,15.0,26.0,27,3600,0,'Pode exigir certificado de febre amarela'),
('República Dominicana','DOP',4300,2090,920,1600,27,3600,0,'Brasileiros: sem visto para turismo'),
('Jamaica','JMD',16700,7620,3490,6040,27,3600,0,'Brasileiros: sem visto para turismo'),
('Paraguai','PYG',315000,150000,60000,120000,22,2800,0,'Documento de identidade com foto aceito'),
('Bahamas','USD',140,60.0,28.0,50.0,27,4900,0,'Brasileiros: sem visto para turismo'),
('Fiji','FJD',236,108,49.5,85.5,32,9800,0,'Confirme a exigência de visto'),
('Tunísia','TND',130,61.7,24.7,49.4,27,6800,0,'Confirme a exigência de visto'),
('Geórgia','GEL',113,54.0,21.6,43.2,27,5300,0,'Brasileiros: sem visto para turismo');

-- Tokens de verificação de e-mail, notificações internas e controle de migrações
CREATE TABLE tokens_email (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira DATETIME NOT NULL,
  usado TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);
CREATE TABLE notificacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  titulo VARCHAR(150) NOT NULL,
  mensagem VARCHAR(255) NULL,
  url VARCHAR(255) NULL,
  lida TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);
CREATE TABLE migracoes (
  arquivo VARCHAR(150) PRIMARY KEY,
  aplicada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para as consultas mais frequentes
CREATE INDEX idx_reservas_status_prazo ON reservas (status, prazo_pagamento);
CREATE INDEX idx_reservas_usuario ON reservas (usuario_id);
CREATE INDEX idx_saidas_passeio_data ON saidas (passeio_id, data_saida);
CREATE INDEX idx_lancamentos_data ON lancamentos (data_lanc, pago);
CREATE INDEX idx_pagamentos_status ON pagamentos (status);
CREATE INDEX idx_passeios_escopo_pais ON passeios (escopo, pais);
CREATE INDEX idx_logs_acesso ON logs_acesso (area, ip, created_at);
CREATE INDEX idx_emails_status ON emails_fila (status);
CREATE INDEX idx_chamados_status ON chamados (status);
CREATE INDEX idx_notificacoes_usuario ON notificacoes (usuario_id, lida);

-- ===== DADOS DE EXEMPLO (fictícios) =====
INSERT INTO usuarios (nome,email,telefone,tipo_user,senha) VALUES
('Administrador','admin@portalviagens.com','(35) 99999-0000',3,'$2b$12$5VMSLu8vr889BTnWQNa9b..H2g6rL9r240VFJVXF9pxRaTFWDnjeu'),
('Marina Duarte','marina@portalviagens.com','(24) 98888-1111',2,'$2b$12$5VMSLu8vr889BTnWQNa9b..H2g6rL9r240VFJVXF9pxRaTFWDnjeu'),
('Rafael Nogueira','rafael@portalviagens.com','(67) 97777-2222',2,'$2b$12$5VMSLu8vr889BTnWQNa9b..H2g6rL9r240VFJVXF9pxRaTFWDnjeu'),
('Viajante Teste','viajante@teste.com','(35) 96666-3333',1,'$2b$12$5VMSLu8vr889BTnWQNa9b..H2g6rL9r240VFJVXF9pxRaTFWDnjeu'),
('Ana Beatriz Lima','ana@exemplo.com',NULL,1,'$2b$12$5VMSLu8vr889BTnWQNa9b..H2g6rL9r240VFJVXF9pxRaTFWDnjeu'),
('Carlos Eduardo Souza','carlos@exemplo.com',NULL,1,'$2b$12$5VMSLu8vr889BTnWQNa9b..H2g6rL9r240VFJVXF9pxRaTFWDnjeu'),
('Juliana Prado','juliana@exemplo.com',NULL,1,'$2b$12$5VMSLu8vr889BTnWQNa9b..H2g6rL9r240VFJVXF9pxRaTFWDnjeu'),
('Marcos Vinícius Reis','marcos@exemplo.com',NULL,1,'$2b$12$5VMSLu8vr889BTnWQNa9b..H2g6rL9r240VFJVXF9pxRaTFWDnjeu');

INSERT INTO guias (usuario_id,nome,email,telefone,idiomas,bio,salario,data_admissao) VALUES
(2,'Marina Duarte','marina@portalviagens.com','(24) 98888-1111','Português, Inglês','Guia credenciada, apaixonada por história colonial e pelo litoral fluminense. Conduz grupos pequenos com calma e muitas histórias.',4200,DATE_SUB(CURDATE(), INTERVAL 6 YEAR)),
(3,'Rafael Nogueira','rafael@portalviagens.com','(67) 97777-2222','Português, Espanhol, Inglês','Especialista em ecoturismo e aventura no Centro-Oeste: Bonito, Pantanal e as chapadas. Condutor de ambientes naturais.',4600,DATE_SUB(CURDATE(), INTERVAL 5 YEAR)),
(NULL,'Camila Torres','camila.torres@exemplo.com','(71) 96555-0101','Português, Inglês','Nascida em Salvador, mostra o Nordeste além das praias: cultura, música e gastronomia de cada cidade.',4300,DATE_SUB(CURDATE(), INTERVAL 4 YEAR)),
(NULL,'Henrique Vasconcelos','henrique.v@exemplo.com','(51) 96444-0202','Português, Inglês, Italiano','Guia do Sul e Sudeste: serra gaúcha, litoral catarinense e a região de Curitiba. Especialista em enoturismo.',4400,DATE_SUB(CURDATE(), INTERVAL 7 YEAR)),
(NULL,'Luana Tapajós','luana.t@exemplo.com','(92) 96333-0303','Português, Inglês','Bióloga e guia amazônica. Conduz expedições pela floresta, rios e comunidades ribeirinhas com foco em sustentabilidade.',4500,DATE_SUB(CURDATE(), INTERVAL 3 YEAR)),
(NULL,'Giulia Ferraro','giulia.ferraro@exemplo.com','+39 06 5550 0101','Italiano, Português, Inglês','Romana de nascimento, formada em História da Arte. Faz do Coliseu à mesa de massa uma aula divertida sobre a Itália.',6800,DATE_SUB(CURDATE(), INTERVAL 8 YEAR)),
(NULL,'Étienne Moreau','etienne.moreau@exemplo.com','+33 1 5550 0202','Francês, Inglês, Português','Parisiense e sommelier amador. Leva você dos museus às padarias do bairro, passando pelos castelos do Loire.',7200,DATE_SUB(CURDATE(), INTERVAL 9 YEAR)),
(NULL,'Lucía Fernández','lucia.f@exemplo.com','+34 91 555 0303','Espanhol, Português, Inglês','Madrilenha especialista em Península Ibérica e Rio da Prata. Flamenco, tapas e muita arquitetura.',6200,DATE_SUB(CURDATE(), INTERVAL 6 YEAR)),
(NULL,'Hiro Tanaka','hiro.tanaka@exemplo.com','+81 3 5550 0404','Japonês, Inglês, Português','Cresceu em Tóquio e morou em São Paulo. Apresenta o Japão tradicional e moderno, e também Coreia e China.',7400,DATE_SUB(CURDATE(), INTERVAL 7 YEAR)),
(NULL,'Arun Mehta','arun.mehta@exemplo.com','+91 11 5550 0505','Inglês, Hindi, Português','Guia do Oriente Médio e do Sul da Ásia, de Delhi a Dubai, com foco em culturas, templos e mercados.',5600,DATE_SUB(CURDATE(), INTERVAL 5 YEAR)),
(NULL,'Sophie Williams','sophie.w@exemplo.com','+44 20 5550 0606','Inglês, Português','Londrina com experiência na América do Norte. Guia em Reino Unido, Irlanda, Estados Unidos e Canadá.',7000,DATE_SUB(CURDATE(), INTERVAL 8 YEAR)),
(NULL,'Mateo Quispe','mateo.quispe@exemplo.com','+51 1 555 0707','Espanhol, Quéchua, Português','Cusquenho, guia de trilhas e cultura andina. Conduz de Machu Picchu ao Atacama com respeito às comunidades locais.',5200,DATE_SUB(CURDATE(), INTERVAL 6 YEAR)),
(NULL,'Amina El-Fassi','amina.elfassi@exemplo.com','+212 5 5550 0808','Árabe, Francês, Inglês','Guia do norte da África e do Mediterrâneo oriental: medinas, desertos, pirâmides e culinária local.',5400,DATE_SUB(CURDATE(), INTERVAL 5 YEAR)),
(NULL,'Thabo Nkosi','thabo.nkosi@exemplo.com','+27 21 555 0909','Inglês, Zulu, Português','Guia de safáris credenciado. Especialista em observação de fauna na África austral e oriental e nas ilhas do Índico.',5900,DATE_SUB(CURDATE(), INTERVAL 7 YEAR)),
(NULL,'Mai Nguyen','mai.nguyen@exemplo.com','+84 24 5550 1010','Inglês, Vietnamita, Português','Guia do Sudeste Asiático e da Oceania, de templos e mercados a mergulhos e ilhas paradisíacas.',5100,DATE_SUB(CURDATE(), INTERVAL 4 YEAR)),
(NULL,'Pedro Alvarado','pedro.alvarado@exemplo.com','+52 55 5550 1111','Espanhol, Inglês, Português','Mexicano com anos de estrada pelo Caribe e pela América Central: ruínas, praias e música.',5000,DATE_SUB(CURDATE(), INTERVAL 6 YEAR)),
(NULL,'Katarina Novak','katarina.novak@exemplo.com','+420 2 5550 1212','Tcheco, Alemão, Inglês, Português','Guia da Europa Central e do Norte: cidades históricas, castelos e paisagens nórdicas.',6400,DATE_SUB(CURDATE(), INTERVAL 6 YEAR));
INSERT INTO guia_locais (guia_id,local) VALUES
((SELECT id FROM guias WHERE nome='Marina Duarte'),'Rio de Janeiro'),
((SELECT id FROM guias WHERE nome='Marina Duarte'),'Minas Gerais'),
((SELECT id FROM guias WHERE nome='Marina Duarte'),'Paraty'),
((SELECT id FROM guias WHERE nome='Marina Duarte'),'Ouro Preto'),
((SELECT id FROM guias WHERE nome='Rafael Nogueira'),'Mato Grosso do Sul'),
((SELECT id FROM guias WHERE nome='Rafael Nogueira'),'Mato Grosso'),
((SELECT id FROM guias WHERE nome='Rafael Nogueira'),'Goiás'),
((SELECT id FROM guias WHERE nome='Rafael Nogueira'),'Distrito Federal'),
((SELECT id FROM guias WHERE nome='Camila Torres'),'Bahia'),
((SELECT id FROM guias WHERE nome='Camila Torres'),'Pernambuco'),
((SELECT id FROM guias WHERE nome='Camila Torres'),'Ceará'),
((SELECT id FROM guias WHERE nome='Camila Torres'),'Maranhão'),
((SELECT id FROM guias WHERE nome='Camila Torres'),'Rio Grande do Norte'),
((SELECT id FROM guias WHERE nome='Camila Torres'),'Alagoas'),
((SELECT id FROM guias WHERE nome='Henrique Vasconcelos'),'Rio Grande do Sul'),
((SELECT id FROM guias WHERE nome='Henrique Vasconcelos'),'Santa Catarina'),
((SELECT id FROM guias WHERE nome='Henrique Vasconcelos'),'Paraná'),
((SELECT id FROM guias WHERE nome='Henrique Vasconcelos'),'São Paulo'),
((SELECT id FROM guias WHERE nome='Luana Tapajós'),'Amazonas'),
((SELECT id FROM guias WHERE nome='Luana Tapajós'),'Pará'),
((SELECT id FROM guias WHERE nome='Luana Tapajós'),'Tocantins'),
((SELECT id FROM guias WHERE nome='Giulia Ferraro'),'Itália'),
((SELECT id FROM guias WHERE nome='Giulia Ferraro'),'Malta'),
((SELECT id FROM guias WHERE nome='Giulia Ferraro'),'Grécia'),
((SELECT id FROM guias WHERE nome='Giulia Ferraro'),'Croácia'),
((SELECT id FROM guias WHERE nome='Giulia Ferraro'),'Chipre'),
((SELECT id FROM guias WHERE nome='Étienne Moreau'),'França'),
((SELECT id FROM guias WHERE nome='Étienne Moreau'),'Bélgica'),
((SELECT id FROM guias WHERE nome='Étienne Moreau'),'Suíça'),
((SELECT id FROM guias WHERE nome='Étienne Moreau'),'Holanda'),
((SELECT id FROM guias WHERE nome='Lucía Fernández'),'Espanha'),
((SELECT id FROM guias WHERE nome='Lucía Fernández'),'Portugal'),
((SELECT id FROM guias WHERE nome='Lucía Fernández'),'Argentina'),
((SELECT id FROM guias WHERE nome='Lucía Fernández'),'Uruguai'),
((SELECT id FROM guias WHERE nome='Lucía Fernández'),'Paraguai'),
((SELECT id FROM guias WHERE nome='Hiro Tanaka'),'Japão'),
((SELECT id FROM guias WHERE nome='Hiro Tanaka'),'Coreia do Sul'),
((SELECT id FROM guias WHERE nome='Hiro Tanaka'),'China'),
((SELECT id FROM guias WHERE nome='Arun Mehta'),'Índia'),
((SELECT id FROM guias WHERE nome='Arun Mehta'),'Nepal'),
((SELECT id FROM guias WHERE nome='Arun Mehta'),'Sri Lanka'),
((SELECT id FROM guias WHERE nome='Arun Mehta'),'Emirados Árabes Unidos'),
((SELECT id FROM guias WHERE nome='Arun Mehta'),'Catar'),
((SELECT id FROM guias WHERE nome='Arun Mehta'),'Israel'),
((SELECT id FROM guias WHERE nome='Sophie Williams'),'Reino Unido'),
((SELECT id FROM guias WHERE nome='Sophie Williams'),'Irlanda'),
((SELECT id FROM guias WHERE nome='Sophie Williams'),'Estados Unidos'),
((SELECT id FROM guias WHERE nome='Sophie Williams'),'Canadá'),
((SELECT id FROM guias WHERE nome='Sophie Williams'),'Islândia'),
((SELECT id FROM guias WHERE nome='Mateo Quispe'),'Peru'),
((SELECT id FROM guias WHERE nome='Mateo Quispe'),'Bolívia'),
((SELECT id FROM guias WHERE nome='Mateo Quispe'),'Chile'),
((SELECT id FROM guias WHERE nome='Mateo Quispe'),'Equador'),
((SELECT id FROM guias WHERE nome='Mateo Quispe'),'Colômbia'),
((SELECT id FROM guias WHERE nome='Amina El-Fassi'),'Egito'),
((SELECT id FROM guias WHERE nome='Amina El-Fassi'),'Marrocos'),
((SELECT id FROM guias WHERE nome='Amina El-Fassi'),'Tunísia'),
((SELECT id FROM guias WHERE nome='Amina El-Fassi'),'Turquia'),
((SELECT id FROM guias WHERE nome='Amina El-Fassi'),'Jordânia'),
((SELECT id FROM guias WHERE nome='Thabo Nkosi'),'África do Sul'),
((SELECT id FROM guias WHERE nome='Thabo Nkosi'),'Tanzânia'),
((SELECT id FROM guias WHERE nome='Thabo Nkosi'),'Quênia'),
((SELECT id FROM guias WHERE nome='Thabo Nkosi'),'Namíbia'),
((SELECT id FROM guias WHERE nome='Thabo Nkosi'),'Maurício'),
((SELECT id FROM guias WHERE nome='Thabo Nkosi'),'Seychelles'),
((SELECT id FROM guias WHERE nome='Mai Nguyen'),'Vietnã'),
((SELECT id FROM guias WHERE nome='Mai Nguyen'),'Tailândia'),
((SELECT id FROM guias WHERE nome='Mai Nguyen'),'Camboja'),
((SELECT id FROM guias WHERE nome='Mai Nguyen'),'Indonésia'),
((SELECT id FROM guias WHERE nome='Mai Nguyen'),'Malásia'),
((SELECT id FROM guias WHERE nome='Mai Nguyen'),'Filipinas'),
((SELECT id FROM guias WHERE nome='Mai Nguyen'),'Singapura'),
((SELECT id FROM guias WHERE nome='Mai Nguyen'),'Maldivas'),
((SELECT id FROM guias WHERE nome='Mai Nguyen'),'Austrália'),
((SELECT id FROM guias WHERE nome='Mai Nguyen'),'Nova Zelândia'),
((SELECT id FROM guias WHERE nome='Mai Nguyen'),'Fiji'),
((SELECT id FROM guias WHERE nome='Pedro Alvarado'),'México'),
((SELECT id FROM guias WHERE nome='Pedro Alvarado'),'Cuba'),
((SELECT id FROM guias WHERE nome='Pedro Alvarado'),'Costa Rica'),
((SELECT id FROM guias WHERE nome='Pedro Alvarado'),'Panamá'),
((SELECT id FROM guias WHERE nome='Pedro Alvarado'),'República Dominicana'),
((SELECT id FROM guias WHERE nome='Pedro Alvarado'),'Jamaica'),
((SELECT id FROM guias WHERE nome='Pedro Alvarado'),'Bahamas'),
((SELECT id FROM guias WHERE nome='Katarina Novak'),'Alemanha'),
((SELECT id FROM guias WHERE nome='Katarina Novak'),'Áustria'),
((SELECT id FROM guias WHERE nome='Katarina Novak'),'República Tcheca'),
((SELECT id FROM guias WHERE nome='Katarina Novak'),'Hungria'),
((SELECT id FROM guias WHERE nome='Katarina Novak'),'Polônia'),
((SELECT id FROM guias WHERE nome='Katarina Novak'),'Noruega'),
((SELECT id FROM guias WHERE nome='Katarina Novak'),'Suécia'),
((SELECT id FROM guias WHERE nome='Katarina Novak'),'Dinamarca'),
((SELECT id FROM guias WHERE nome='Katarina Novak'),'Finlândia'),
((SELECT id FROM guias WHERE nome='Katarina Novak'),'Geórgia');
INSERT INTO categorias (nome,icone) VALUES ('Praia','🏖️'),('Aventura','🧗'),('Cultura','🏛️'),('Gastronomia','🍽️'),('Natureza','🌿'),('Histórico','🏰'),('Lazer','🎡'),('Cidades','🌆');

INSERT INTO passeios (categoria_id,guia_id,titulo,destino,descricao,duracao_horas,preco,emoji,hue) VALUES
(1,2,'Circuito das Praias de Paraty de Escuna','Paraty - RJ','Passeio de escuna por ilhas e praias de águas calmas, com parada para mergulho livre e almoço opcional a bordo.',6,189.90,'⛵',195),
(2,3,'Flutuação no Rio Sucuri','Bonito - MS','Flutuação em águas cristalinas com peixes e vegetação aquática. Equipamento incluso e guia credenciado.',4,320.00,'🤿',170),
(3,2,'Centro Histórico e Igrejas Barrocas','Ouro Preto - MG','Caminhada guiada pelas igrejas, ladeiras e casarões do período colonial, com histórias do ciclo do ouro.',3.5,95.00,'⛪',30),
(5,3,'Cachoeiras e Cânions de Capitólio','Capitólio - MG','Passeio de lancha pelo Lago de Furnas e visita às cachoeiras do entorno, com tempo para banho.',5,150.00,'🏞️',160),
(5,2,'Lençóis Maranhenses: Lagoa Azul','Barreirinhas - MA','Travessia de 4x4 pelas dunas até as lagoas de água doce, com pôr do sol sobre as dunas.',7,280.00,'🏜️',45),
(4,2,'Tour Gastronômico no Pelourinho','Salvador - BA','Degustação de acarajé, moqueca e doces baianos em casas tradicionais, com história de cada prato.',3,130.00,'🍤',15),
(2,3,'Trilha ao Pico da Bandeira','Alto Caparaó - MG','Subida guiada ao terceiro pico mais alto do Brasil, com saída de madrugada para ver o nascer do sol.',9,210.00,'⛰️',260),
(3,2,'Cataratas do Iguaçu: Lado Brasileiro','Foz do Iguaçu - PR','Passarelas panorâmicas, trilha das Cataratas e transporte interno. Ingresso do parque não incluso.',4,110.00,'💦',200),
(1,3,'Day Use em Maragogi e Piscinas Naturais','Maragogi - AL','Catamarã até as galés, piscinas naturais com mergulho livre e tempo de praia.',5,175.00,'🐠',185),
(4,2,'Rota do Café e do Queijo Mineiro','Sul de Minas - MG','Visita a fazendas, torra e degustação de cafés especiais e queijos artesanais da região.',6,140.00,'☕',25);

INSERT INTO passeios (categoria_id,guia_id,titulo,destino,descricao,duracao_horas,preco,emoji,hue,escopo,pais,dias,atracoes) VALUES
(5,2,'Lençóis Maranhenses: Circuito Completo','Barreirinhas - MA','Roteiro de 4 dias em Barreirinhas - MA com guia local. Destaques: Lagoa Azul, Lagoa Bonita, Rio Preguiças e Atins, Pôr do sol nas dunas, Vassouras.',96,2890,'🏜️',45,'nacional','Brasil',4,'Lagoa Azul;Lagoa Bonita;Rio Preguiças e Atins;Pôr do sol nas dunas;Vassouras'),
(1,3,'Fernando de Noronha: Ilha Completa','Fernando de Noronha - PE','Roteiro de 5 dias em Fernando de Noronha - PE com guia local. Destaques: Baía do Sancho, Baía dos Porcos, Mergulho com tartarugas, Forte do Boldró, Projeto Tamar.',120,6900,'🐢',190,'nacional','Brasil',5,'Baía do Sancho;Baía dos Porcos;Mergulho com tartarugas;Forte do Boldró;Projeto Tamar'),
(1,2,'Jericoacoara e Pedra Furada','Jijoca de Jericoacoara - CE','Roteiro de 5 dias em Jijoca de Jericoacoara - CE com guia local. Destaques: Pôr do sol na Duna, Pedra Furada, Lagoa do Paraíso, Buggy pelas dunas.',120,3450,'🏄',35,'nacional','Brasil',5,'Pôr do sol na Duna;Pedra Furada;Lagoa do Paraíso;Buggy pelas dunas'),
(2,3,'Chapada Diamantina: Vale do Capão','Lençóis - BA','Roteiro de 5 dias em Lençóis - BA com guia local. Destaques: Cachoeira da Fumaça, Poço Azul, Morro do Pai Inácio, Gruta da Lapa Doce, Vale do Pati.',120,3100,'⛰️',150,'nacional','Brasil',5,'Cachoeira da Fumaça;Poço Azul;Morro do Pai Inácio;Gruta da Lapa Doce;Vale do Pati'),
(5,2,'Chapada dos Veadeiros: Cachoeiras','Alto Paraíso - GO','Roteiro de 4 dias em Alto Paraíso - GO com guia local. Destaques: Cachoeira Santa Bárbara, Cânion Preto, Vale da Lua, Salto 80.',96,2400,'💧',140,'nacional','Brasil',4,'Cachoeira Santa Bárbara;Cânion Preto;Vale da Lua;Salto 80'),
(5,3,'Pantanal: Safári e Pesca','Pantanal - MT/MS','Roteiro de 4 dias em Pantanal - MT/MS com guia local. Destaques: Safári fotográfico, Onças-pintadas, Passeio de barco, Cavalgada, Observação de aves.',96,3600,'🐊',100,'nacional','Brasil',4,'Safári fotográfico;Onças-pintadas;Passeio de barco;Cavalgada;Observação de aves'),
(5,2,'Amazônia: Manaus e Encontro das Águas','Manaus - AM','Roteiro de 5 dias em Manaus - AM com guia local. Destaques: Encontro das Águas, Teatro Amazonas, Lodge na floresta, Pesca de piranha, Arquipélago de Anavilhanas.',120,4200,'🌳',120,'nacional','Brasil',5,'Encontro das Águas;Teatro Amazonas;Lodge na floresta;Pesca de piranha;Arquipélago de Anavilhanas'),
(7,3,'Gramado e Canela: Serra Gaúcha','Gramado - RS','Roteiro de 4 dias em Gramado - RS com guia local. Destaques: Rua Coberta, Lago Negro, Mini Mundo, Parque do Caracol, Fondue.',96,2650,'🏔️',210,'nacional','Brasil',4,'Rua Coberta;Lago Negro;Mini Mundo;Parque do Caracol;Fondue'),
(8,2,'Rio de Janeiro Clássico','Rio de Janeiro - RJ','Roteiro de 5 dias em Rio de Janeiro - RJ com guia local. Destaques: Cristo Redentor, Pão de Açúcar, Escadaria Selarón, Praia de Copacabana, Santa Teresa.',120,2950,'🏙️',15,'nacional','Brasil',5,'Cristo Redentor;Pão de Açúcar;Escadaria Selarón;Praia de Copacabana;Santa Teresa'),
(6,3,'Salvador: Centro Histórico e Praias','Salvador - BA','Roteiro de 4 dias em Salvador - BA com guia local. Destaques: Pelourinho, Elevador Lacerda, Igreja do Bonfim, Farol da Barra, Praia do Flamengo.',96,2300,'🥁',20,'nacional','Brasil',4,'Pelourinho;Elevador Lacerda;Igreja do Bonfim;Farol da Barra;Praia do Flamengo'),
(6,2,'Recife e Olinda Históricos','Recife - PE','Roteiro de 4 dias em Recife - PE com guia local. Destaques: Olinda, Marco Zero, Instituto Ricardo Brennand, Rua do Bom Jesus.',96,2100,'🎭',330,'nacional','Brasil',4,'Olinda;Marco Zero;Instituto Ricardo Brennand;Rua do Bom Jesus'),
(1,3,'Porto de Galinhas: Piscinas Naturais','Ipojuca - PE','Roteiro de 5 dias em Ipojuca - PE com guia local. Destaques: Piscinas naturais, Praia de Muro Alto, Maracaípe, Passeio de jangada.',120,3200,'🐠',185,'nacional','Brasil',5,'Piscinas naturais;Praia de Muro Alto;Maracaípe;Passeio de jangada'),
(1,2,'Natal e Pipa: Dunas e Golfinhos','Natal - RN','Roteiro de 5 dias em Natal - RN com guia local. Destaques: Baía dos Golfinhos, Chapadão, Dunas de Genipabu, Passeio de buggy, Praia do Amor.',120,3050,'🐬',205,'nacional','Brasil',5,'Baía dos Golfinhos;Chapadão;Dunas de Genipabu;Passeio de buggy;Praia do Amor'),
(2,3,'Jalapão: Fervedouros e Dunas','Jalapão - TO','Roteiro de 5 dias em Jalapão - TO com guia local. Destaques: Fervedouros, Cachoeira da Velha, Serra do Espírito Santo, Dunas Douradas.',120,4100,'🌅',40,'nacional','Brasil',5,'Fervedouros;Cachoeira da Velha;Serra do Espírito Santo;Dunas Douradas'),
(1,2,'Florianópolis: Ilha da Magia','Florianópolis - SC','Roteiro de 5 dias em Florianópolis - SC com guia local. Destaques: Praia Mole, Joaquina, Santo Antônio de Lisboa, Lagoa da Conceição.',120,2800,'🏝️',175,'nacional','Brasil',5,'Praia Mole;Joaquina;Santo Antônio de Lisboa;Lagoa da Conceição'),
(1,3,'Ilha Grande e Angra dos Reis','Angra dos Reis - RJ','Roteiro de 4 dias em Angra dos Reis - RJ com guia local. Destaques: Lopes Mendes, Lagoa Azul, Aventureiro, Vila do Abraão.',96,2500,'🛶',165,'nacional','Brasil',4,'Lopes Mendes;Lagoa Azul;Aventureiro;Vila do Abraão'),
(1,2,'Búzios e Arraial do Cabo','Búzios - RJ','Roteiro de 4 dias em Búzios - RJ com guia local. Destaques: Praia da Ferradura, Orla Bardot, Praia do Forno, Prainhas do Pontal.',96,2350,'⛵',200,'nacional','Brasil',4,'Praia da Ferradura;Orla Bardot;Praia do Forno;Prainhas do Pontal'),
(1,3,'Ilhabela: Cachoeiras e Praias','Ilhabela - SP','Roteiro de 4 dias em Ilhabela - SP com guia local. Destaques: Cachoeira do Gato, Praia do Curral, Ponta das Canas, Mergulho.',96,2200,'🌴',150,'nacional','Brasil',4,'Cachoeira do Gato;Praia do Curral;Ponta das Canas;Mergulho'),
(3,2,'Curitiba e Morretes de Trem','Curitiba - PR','Roteiro de 3 dias em Curitiba - PR com guia local. Destaques: Jardim Botânico, Ópera de Arame, Trem Curitiba-Morretes, Paranaguá.',72,1700,'🚂',120,'nacional','Brasil',3,'Jardim Botânico;Ópera de Arame;Trem Curitiba-Morretes;Paranaguá'),
(2,3,'Brotas: Rafting e Cachoeiras','Brotas - SP','Roteiro de 3 dias em Brotas - SP com guia local. Destaques: Rafting, Boia-cross, Cachoeiras, Tirolesa.',72,1500,'🚣',160,'nacional','Brasil',3,'Rafting;Boia-cross;Cachoeiras;Tirolesa'),
(3,2,'São Paulo Cultural','São Paulo - SP','Roteiro de 3 dias em São Paulo - SP com guia local. Destaques: MASP, Avenida Paulista, Mercado Municipal, Beco do Batman, Pinacoteca.',72,1800,'🌆',260,'nacional','Brasil',3,'MASP;Avenida Paulista;Mercado Municipal;Beco do Batman;Pinacoteca'),
(6,3,'Brasília Modernista','Brasília - DF','Roteiro de 3 dias em Brasília - DF com guia local. Destaques: Congresso Nacional, Catedral, Palácio da Alvorada, Lago Paranoá.',72,1650,'🏛️',270,'nacional','Brasil',3,'Congresso Nacional;Catedral;Palácio da Alvorada;Lago Paranoá'),
(6,2,'Tiradentes e Estrada Real','Tiradentes - MG','Roteiro de 3 dias em Tiradentes - MG com guia local. Destaques: Igreja Matriz, Maria Fumaça, Largo das Forras, Chafariz de São José.',72,1550,'🔔',35,'nacional','Brasil',3,'Igreja Matriz;Maria Fumaça;Largo das Forras;Chafariz de São José'),
(6,3,'Diamantina: Cidade Patrimônio','Diamantina - MG','Roteiro de 3 dias em Diamantina - MG com guia local. Destaques: Casa de JK, Mercado Velho, Serra dos Cristais, Casario colonial.',72,1450,'💎',280,'nacional','Brasil',3,'Casa de JK;Mercado Velho;Serra dos Cristais;Casario colonial'),
(5,2,'Pirenópolis: Cachoeiras e Cavalhadas','Pirenópolis - GO','Roteiro de 3 dias em Pirenópolis - GO com guia local. Destaques: Cachoeira do Abade, Santuário Vagafogo, Centro Histórico, Cavalhadas.',72,1400,'🐴',25,'nacional','Brasil',3,'Cachoeira do Abade;Santuário Vagafogo;Centro Histórico;Cavalhadas'),
(1,3,'Alter do Chão: Caribe Amazônico','Santarém - PA','Roteiro de 4 dias em Santarém - PA com guia local. Destaques: Ilha do Amor, Praia do Cajueiro, Floresta Nacional do Tapajós, Lago Verde.',96,2700,'🌊',170,'nacional','Brasil',4,'Ilha do Amor;Praia do Cajueiro;Floresta Nacional do Tapajós;Lago Verde'),
(4,2,'Belém: Ver-o-Peso e Gastronomia','Belém - PA','Roteiro de 3 dias em Belém - PA com guia local. Destaques: Ver-o-Peso, Estação das Docas, Ilha do Combu, Tacacá e pato no tucupi.',72,1900,'🍲',90,'nacional','Brasil',3,'Ver-o-Peso;Estação das Docas;Ilha do Combu;Tacacá e pato no tucupi'),
(6,3,'São Luís: Centro Histórico','São Luís - MA','Roteiro de 3 dias em São Luís - MA com guia local. Destaques: Rua Portugal, Palácio dos Leões, Fonte do Ribeirão, Praia do Calhau.',72,1750,'🎨',50,'nacional','Brasil',3,'Rua Portugal;Palácio dos Leões;Fonte do Ribeirão;Praia do Calhau'),
(1,2,'Fortaleza e Canoa Quebrada','Fortaleza - CE','Roteiro de 5 dias em Fortaleza - CE com guia local. Destaques: Beach Park, Falésias de Morro Branco, Canoa Quebrada, Praia do Futuro.',120,2750,'🌞',30,'nacional','Brasil',5,'Beach Park;Falésias de Morro Branco;Canoa Quebrada;Praia do Futuro'),
(1,3,'Morro de São Paulo e Boipeba','Cairu - BA','Roteiro de 5 dias em Cairu - BA com guia local. Destaques: Piscinas naturais, Segunda Praia, Boipeba, Gamboa.',120,2900,'🏖️',190,'nacional','Brasil',5,'Piscinas naturais;Segunda Praia;Boipeba;Gamboa'),
(1,2,'Trancoso e Caraíva','Porto Seguro - BA','Roteiro de 5 dias em Porto Seguro - BA com guia local. Destaques: Quadrado de Trancoso, Praia dos Coqueiros, Caraíva, Espelho.',120,3150,'🌴',155,'nacional','Brasil',5,'Quadrado de Trancoso;Praia dos Coqueiros;Caraíva;Espelho'),
(5,3,'Abrolhos: Observação de Baleias','Caravelas - BA','Roteiro de 4 dias em Caravelas - BA com guia local. Destaques: Parque Nacional Marinho dos Abrolhos, Baleias jubarte, Mergulho.',96,3900,'🐋',210,'nacional','Brasil',4,'Parque Nacional Marinho dos Abrolhos;Baleias jubarte;Mergulho'),
(5,2,'Cânions de Cambará do Sul','Cambará do Sul - RS','Roteiro de 3 dias em Cambará do Sul - RS com guia local. Destaques: Itaimbezinho, Fortaleza, Serra Geral.',72,1850,'🏞️',110,'nacional','Brasil',3,'Itaimbezinho;Fortaleza;Serra Geral'),
(4,3,'Serra Gaúcha: Rota do Vinho','Bento Gonçalves - RS','Roteiro de 3 dias em Bento Gonçalves - RS com guia local. Destaques: Vale dos Vinhedos, Degustação de vinhos, Maria Fumaça, Caminhos de Pedra.',72,1950,'🍷',340,'nacional','Brasil',3,'Vale dos Vinhedos;Degustação de vinhos;Maria Fumaça;Caminhos de Pedra'),
(7,2,'Beto Carrero World e Penha','Penha - SC','Roteiro de 3 dias em Penha - SC com guia local. Destaques: Parque temático, Shows, Praias de Penha.',72,2100,'🎢',300,'nacional','Brasil',3,'Parque temático;Shows;Praias de Penha'),
(5,3,'Cachoeira do Tabuleiro e Serra do Cipó','Conceição do Mato Dentro - MG','Roteiro de 3 dias em Conceição do Mato Dentro - MG com guia local. Destaques: Cachoeira do Tabuleiro, Parque Nacional da Serra do Cipó, Cânion das Bandeirinhas.',72,1500,'🌊',160,'nacional','Brasil',3,'Cachoeira do Tabuleiro;Parque Nacional da Serra do Cipó;Cânion das Bandeirinhas'),
(5,2,'Serra da Canastra e Casca d''Anta','São Roque de Minas - MG','Roteiro de 3 dias em São Roque de Minas - MG com guia local. Destaques: Salto Casca d''Anta, Nascente do Rio São Francisco, Queijo Canastra.',72,1350,'🏞️',145,'nacional','Brasil',3,'Salto Casca d''Anta;Nascente do Rio São Francisco;Queijo Canastra'),
(6,3,'Ouro Preto e Mariana: Roteiro Completo','Ouro Preto - MG','Roteiro de 3 dias em Ouro Preto - MG com guia local. Destaques: Igreja de São Francisco de Assis, Mina de Ouro, Praça Tiradentes, Trem de Mariana.',72,1600,'⛪',30,'nacional','Brasil',3,'Igreja de São Francisco de Assis;Mina de Ouro;Praça Tiradentes;Trem de Mariana'),
(6,2,'Petrópolis Imperial','Petrópolis - RJ','Roteiro de 2 dias em Petrópolis - RJ com guia local. Destaques: Museu Imperial, Catedral de São Pedro, Casa de Santos Dumont.',48,1100,'🏰',270,'nacional','Brasil',2,'Museu Imperial;Catedral de São Pedro;Casa de Santos Dumont'),
(5,3,'Foz do Iguaçu Completo: Brasil e Argentina','Foz do Iguaçu - PR','Roteiro de 4 dias em Foz do Iguaçu - PR com guia local. Destaques: Cataratas lado brasileiro, Cataratas lado argentino, Parque das Aves, Itaipu, Marco das Três Fronteiras.',96,2600,'💦',200,'nacional','Brasil',4,'Cataratas lado brasileiro;Cataratas lado argentino;Parque das Aves;Itaipu;Marco das Três Fronteiras'),
(2,2,'Bonito: Aventura Completa','Bonito - MS','Roteiro de 4 dias em Bonito - MS com guia local. Destaques: Rio Sucuri, Gruta do Lago Azul, Abismo Anhumas, Estância Mimosa.',96,3300,'🤿',170,'nacional','Brasil',4,'Rio Sucuri;Gruta do Lago Azul;Abismo Anhumas;Estância Mimosa'),
(5,3,'Chapada dos Guimarães','Chapada dos Guimarães - MT','Roteiro de 3 dias em Chapada dos Guimarães - MT com guia local. Destaques: Cachoeira Véu de Noiva, Centro Geodésico, Cidade de Pedra.',72,1700,'🌄',24,'nacional','Brasil',3,'Cachoeira Véu de Noiva;Centro Geodésico;Cidade de Pedra'),
(8,NULL,'França: Paris, Versalhes e Vale do Loire','Paris, Versalhes e Vale do Loire','Pacote terrestre de 8 dias por Paris, Versalhes e Vale do Loire (França), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Torre Eiffel, Museu do Louvre, Palácio de Versalhes, Montmartre, Castelos do Loire.',192,14900,'🗼',330,'internacional','França',8,'Torre Eiffel;Museu do Louvre;Palácio de Versalhes;Montmartre;Castelos do Loire'),
(6,NULL,'Itália: Roma, Florença e Veneza','Roma, Florença e Veneza','Pacote terrestre de 10 dias por Roma, Florença e Veneza (Itália), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Coliseu, Vaticano, Duomo de Florença, Canais de Veneza, Fontana di Trevi.',240,16800,'🍕',15,'internacional','Itália',10,'Coliseu;Vaticano;Duomo de Florença;Canais de Veneza;Fontana di Trevi'),
(8,NULL,'Espanha: Madri, Barcelona e Sevilha','Madri, Barcelona e Sevilha','Pacote terrestre de 9 dias por Madri, Barcelona e Sevilha (Espanha), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Sagrada Família, Parque Güell, Palácio Real, Alhambra, Show de flamenco.',216,13900,'💃',25,'internacional','Espanha',9,'Sagrada Família;Parque Güell;Palácio Real;Alhambra;Show de flamenco'),
(8,NULL,'Portugal: Lisboa, Porto e Sintra','Lisboa, Porto e Sintra','Pacote terrestre de 7 dias por Lisboa, Porto e Sintra (Portugal), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Torre de Belém, Palácio da Pena em Sintra, Vale do Douro, Ribeira do Porto, Pastéis de Belém.',168,10900,'🚋',40,'internacional','Portugal',7,'Torre de Belém;Palácio da Pena em Sintra;Vale do Douro;Ribeira do Porto;Pastéis de Belém'),
(8,NULL,'Reino Unido: Londres, Bath e Stonehenge','Londres, Bath e Stonehenge','Pacote terrestre de 7 dias por Londres, Bath e Stonehenge (Reino Unido), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Big Ben, Torre de Londres, Museu Britânico, Stonehenge, Bath.',168,15800,'💂',230,'internacional','Reino Unido',7,'Big Ben;Torre de Londres;Museu Britânico;Stonehenge;Bath'),
(6,NULL,'Alemanha: Berlim, Munique e Baviera','Berlim, Munique e Baviera','Pacote terrestre de 8 dias por Berlim, Munique e Baviera (Alemanha), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Portão de Brandemburgo, Muro de Berlim, Castelo de Neuschwanstein, Marienplatz.',192,14200,'🥨',50,'internacional','Alemanha',8,'Portão de Brandemburgo;Muro de Berlim;Castelo de Neuschwanstein;Marienplatz'),
(1,NULL,'Grécia: Atenas, Santorini e Mykonos','Atenas, Santorini e Mykonos','Pacote terrestre de 9 dias por Atenas, Santorini e Mykonos (Grécia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Acrópole, Pôr do sol em Oia, Praias de Mykonos, Delfos.',216,15400,'🏛️',205,'internacional','Grécia',9,'Acrópole;Pôr do sol em Oia;Praias de Mykonos;Delfos'),
(8,NULL,'Holanda: Amsterdã, Keukenhof e Zaanse Schans','Amsterdã, Keukenhof e Zaanse Schans','Pacote terrestre de 6 dias por Amsterdã, Keukenhof e Zaanse Schans (Holanda), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Canais de Amsterdã, Museu Van Gogh, Casa de Anne Frank, Campos de tulipas.',144,12600,'🌷',340,'internacional','Holanda',6,'Canais de Amsterdã;Museu Van Gogh;Casa de Anne Frank;Campos de tulipas'),
(5,NULL,'Suíça: Zurique, Lucerna e Alpes','Zurique, Lucerna e Alpes','Pacote terrestre de 7 dias por Zurique, Lucerna e Alpes (Suíça), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Jungfraujoch, Lago Lucerna, Matterhorn, Glacier Express.',168,17900,'🏔️',210,'internacional','Suíça',7,'Jungfraujoch;Lago Lucerna;Matterhorn;Glacier Express'),
(3,NULL,'Áustria: Viena e Salzburgo','Viena e Salzburgo','Pacote terrestre de 6 dias por Viena e Salzburgo (Áustria), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Palácio Schönbrunn, Ópera de Viena, Salzburgo, Hallstatt.',144,12800,'🎻',355,'internacional','Áustria',6,'Palácio Schönbrunn;Ópera de Viena;Salzburgo;Hallstatt'),
(6,NULL,'República Tcheca: Praga e Český Krumlov','Praga e Český Krumlov','Pacote terrestre de 6 dias por Praga e Český Krumlov (República Tcheca), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Ponte Carlos, Castelo de Praga, Relógio Astronômico, Český Krumlov.',144,10400,'🏰',15,'internacional','República Tcheca',6,'Ponte Carlos;Castelo de Praga;Relógio Astronômico;Český Krumlov'),
(3,NULL,'Turquia: Istambul e Capadócia','Istambul e Capadócia','Pacote terrestre de 8 dias por Istambul e Capadócia (Turquia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Hagia Sophia, Mesquita Azul, Passeio de balão, Grande Bazar, Pamukkale.',192,13400,'🎈',10,'internacional','Turquia',8,'Hagia Sophia;Mesquita Azul;Passeio de balão;Grande Bazar;Pamukkale'),
(5,NULL,'Croácia: Dubrovnik, Split e Plitvice','Dubrovnik, Split e Plitvice','Pacote terrestre de 8 dias por Dubrovnik, Split e Plitvice (Croácia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Muralhas de Dubrovnik, Lagos de Plitvice, Palácio de Diocleciano, Ilha de Hvar.',192,12900,'⛵',195,'internacional','Croácia',8,'Muralhas de Dubrovnik;Lagos de Plitvice;Palácio de Diocleciano;Ilha de Hvar'),
(5,NULL,'Irlanda: Dublin, Galway e Penhascos de Moher','Dublin, Galway e Penhascos de Moher','Pacote terrestre de 7 dias por Dublin, Galway e Penhascos de Moher (Irlanda), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Penhascos de Moher, Guinness Storehouse, Anel de Kerry, Galway.',168,13500,'☘️',125,'internacional','Irlanda',7,'Penhascos de Moher;Guinness Storehouse;Anel de Kerry;Galway'),
(5,NULL,'Islândia: Reykjavik e Círculo Dourado','Reykjavik e Círculo Dourado','Pacote terrestre de 7 dias por Reykjavik e Círculo Dourado (Islândia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Aurora boreal, Geysir, Gullfoss, Lagoa Azul, Praia de areia preta.',168,19800,'🌌',175,'internacional','Islândia',7,'Aurora boreal;Geysir;Gullfoss;Lagoa Azul;Praia de areia preta'),
(5,NULL,'Noruega: Oslo, Bergen e Fiordes','Oslo, Bergen e Fiordes','Pacote terrestre de 8 dias por Oslo, Bergen e Fiordes (Noruega), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Fiorde de Geiranger, Trem de Flåm, Bryggen, Ópera de Oslo.',192,21500,'🛳️',215,'internacional','Noruega',8,'Fiorde de Geiranger;Trem de Flåm;Bryggen;Ópera de Oslo'),
(7,NULL,'Estados Unidos: Nova York, Washington e Orlando','Nova York, Washington e Orlando','Pacote terrestre de 10 dias por Nova York, Washington e Orlando (Estados Unidos), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Estátua da Liberdade, Times Square, Casa Branca, Parques de Orlando, Central Park.',240,18900,'🗽',225,'internacional','Estados Unidos',10,'Estátua da Liberdade;Times Square;Casa Branca;Parques de Orlando;Central Park'),
(5,NULL,'Canadá: Toronto, Niágara e Montreal','Toronto, Niágara e Montreal','Pacote terrestre de 9 dias por Toronto, Niágara e Montreal (Canadá), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Cataratas do Niágara, CN Tower, Cidade Velha de Montreal, Quebec.',216,17600,'🍁',0,'internacional','Canadá',9,'Cataratas do Niágara;CN Tower;Cidade Velha de Montreal;Quebec'),
(3,NULL,'México: Cidade do México e Riviera Maya','Cidade do México e Riviera Maya','Pacote terrestre de 8 dias por Cidade do México e Riviera Maya (México), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Teotihuacán, Chichén Itzá, Cenotes, Xochimilco, Cancún.',192,11900,'🌮',130,'internacional','México',8,'Teotihuacán;Chichén Itzá;Cenotes;Xochimilco;Cancún'),
(8,NULL,'Argentina: Buenos Aires e Bariloche','Buenos Aires e Bariloche','Pacote terrestre de 7 dias por Buenos Aires e Bariloche (Argentina), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: La Boca, Recoleta, Show de tango, Lago Nahuel Huapi, Cerro Catedral.',168,8900,'💃',200,'internacional','Argentina',7,'La Boca;Recoleta;Show de tango;Lago Nahuel Huapi;Cerro Catedral'),
(5,NULL,'Chile: Santiago e Deserto do Atacama','Santiago e Deserto do Atacama','Pacote terrestre de 8 dias por Santiago e Deserto do Atacama (Chile), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Valle de la Luna, Gêiseres del Tatio, Cerro San Cristóbal, Vinícolas do Maipo.',192,11400,'🏜️',30,'internacional','Chile',8,'Valle de la Luna;Gêiseres del Tatio;Cerro San Cristóbal;Vinícolas do Maipo'),
(6,NULL,'Peru: Lima, Cusco e Machu Picchu','Lima, Cusco e Machu Picchu','Pacote terrestre de 8 dias por Lima, Cusco e Machu Picchu (Peru), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Machu Picchu, Vale Sagrado, Cusco, Lago Titicaca, Gastronomia de Lima.',192,12400,'🦙',80,'internacional','Peru',8,'Machu Picchu;Vale Sagrado;Cusco;Lago Titicaca;Gastronomia de Lima'),
(8,NULL,'Colômbia: Bogotá, Medellín e Cartagena','Bogotá, Medellín e Cartagena','Pacote terrestre de 8 dias por Bogotá, Medellín e Cartagena (Colômbia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Cidade Amuralhada de Cartagena, Monserrate, Comuna 13, Eixo Cafeeiro.',192,9800,'☕',55,'internacional','Colômbia',8,'Cidade Amuralhada de Cartagena;Monserrate;Comuna 13;Eixo Cafeeiro'),
(1,NULL,'Cuba: Havana e Varadero','Havana e Varadero','Pacote terrestre de 7 dias por Havana e Varadero (Cuba), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Havana Velha, Malecón, Carros clássicos, Praia de Varadero.',168,9900,'🚗',190,'internacional','Cuba',7,'Havana Velha;Malecón;Carros clássicos;Praia de Varadero'),
(1,NULL,'Uruguai: Montevidéu, Colonia e Punta del Este','Montevidéu, Colonia e Punta del Este','Pacote terrestre de 6 dias por Montevidéu, Colonia e Punta del Este (Uruguai), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Colonia del Sacramento, Casapueblo, Rambla de Montevidéu, La Mano de Punta del Este.',144,7900,'🏖️',185,'internacional','Uruguai',6,'Colonia del Sacramento;Casapueblo;Rambla de Montevidéu;La Mano de Punta del Este'),
(5,NULL,'Bolívia: La Paz e Salar de Uyuni','La Paz e Salar de Uyuni','Pacote terrestre de 7 dias por La Paz e Salar de Uyuni (Bolívia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Salar de Uyuni, Lago Titicaca, Ilha do Sol, La Paz.',168,9400,'🧂',200,'internacional','Bolívia',7,'Salar de Uyuni;Lago Titicaca;Ilha do Sol;La Paz'),
(5,NULL,'Equador: Quito e Ilhas Galápagos','Quito e Ilhas Galápagos','Pacote terrestre de 8 dias por Quito e Ilhas Galápagos (Equador), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Ilhas Galápagos, Centro Histórico de Quito, Linha do Equador, Lagoa Quilotoa.',192,17800,'🐢',160,'internacional','Equador',8,'Ilhas Galápagos;Centro Histórico de Quito;Linha do Equador;Lagoa Quilotoa'),
(5,NULL,'Costa Rica: San José, Arenal e Manuel Antonio','San José, Arenal e Manuel Antonio','Pacote terrestre de 8 dias por San José, Arenal e Manuel Antonio (Costa Rica), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Vulcão Arenal, Floresta de Monteverde, Praias de Manuel Antonio, Tirolesa.',192,12200,'🦥',115,'internacional','Costa Rica',8,'Vulcão Arenal;Floresta de Monteverde;Praias de Manuel Antonio;Tirolesa'),
(3,NULL,'Japão: Tóquio, Quioto e Osaka','Tóquio, Quioto e Osaka','Pacote terrestre de 12 dias por Tóquio, Quioto e Osaka (Japão), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Templo Senso-ji, Monte Fuji, Fushimi Inari, Castelo de Osaka, Cruzamento de Shibuya.',288,24900,'⛩️',350,'internacional','Japão',12,'Templo Senso-ji;Monte Fuji;Fushimi Inari;Castelo de Osaka;Cruzamento de Shibuya'),
(3,NULL,'Tailândia: Bangkok, Chiang Mai e Phuket','Bangkok, Chiang Mai e Phuket','Pacote terrestre de 10 dias por Bangkok, Chiang Mai e Phuket (Tailândia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Grande Palácio, Wat Arun, Santuário de elefantes, Ilhas Phi Phi.',240,16900,'🛕',40,'internacional','Tailândia',10,'Grande Palácio;Wat Arun;Santuário de elefantes;Ilhas Phi Phi'),
(6,NULL,'China: Pequim, Xangai e Xi''an','Pequim, Xangai e Xi''an','Pacote terrestre de 11 dias por Pequim, Xangai e Xi''an (China), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Grande Muralha, Cidade Proibida, Guerreiros de Terracota, Bund de Xangai.',264,19500,'🐼',5,'internacional','China',11,'Grande Muralha;Cidade Proibida;Guerreiros de Terracota;Bund de Xangai'),
(6,NULL,'Índia: Delhi, Agra e Jaipur','Delhi, Agra e Jaipur','Pacote terrestre de 9 dias por Delhi, Agra e Jaipur (Índia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Taj Mahal, Forte Vermelho, Palácio dos Ventos, Forte de Amber.',216,14800,'🕌',30,'internacional','Índia',9,'Taj Mahal;Forte Vermelho;Palácio dos Ventos;Forte de Amber'),
(8,NULL,'Emirados Árabes Unidos: Dubai e Abu Dhabi','Dubai e Abu Dhabi','Pacote terrestre de 7 dias por Dubai e Abu Dhabi (Emirados Árabes Unidos), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Burj Khalifa, Safári no deserto, Mesquita Sheikh Zayed, Palm Jumeirah.',168,16700,'🏙️',45,'internacional','Emirados Árabes Unidos',7,'Burj Khalifa;Safári no deserto;Mesquita Sheikh Zayed;Palm Jumeirah'),
(5,NULL,'Indonésia: Bali: Ubud, Uluwatu e Nusa Penida','Bali: Ubud, Uluwatu e Nusa Penida','Pacote terrestre de 9 dias por Bali: Ubud, Uluwatu e Nusa Penida (Indonésia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Terraços de arroz de Tegalalang, Templo de Uluwatu, Nusa Penida, Floresta dos Macacos.',216,15200,'🌺',150,'internacional','Indonésia',9,'Terraços de arroz de Tegalalang;Templo de Uluwatu;Nusa Penida;Floresta dos Macacos'),
(3,NULL,'Vietnã: Hanói, Halong e Ho Chi Minh','Hanói, Halong e Ho Chi Minh','Pacote terrestre de 10 dias por Hanói, Halong e Ho Chi Minh (Vietnã), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Baía de Halong, Hoi An, Túneis de Cu Chi, Mercados de Hanói.',240,13800,'🛶',140,'internacional','Vietnã',10,'Baía de Halong;Hoi An;Túneis de Cu Chi;Mercados de Hanói'),
(3,NULL,'Coreia do Sul: Seul, Busan e Jeju','Seul, Busan e Jeju','Pacote terrestre de 9 dias por Seul, Busan e Jeju (Coreia do Sul), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Palácio Gyeongbokgung, Zona Desmilitarizada, Ilha de Jeju, Vila Bukchon Hanok.',216,17400,'🏯',320,'internacional','Coreia do Sul',9,'Palácio Gyeongbokgung;Zona Desmilitarizada;Ilha de Jeju;Vila Bukchon Hanok'),
(8,NULL,'Singapura: Singapura e Sentosa','Singapura e Sentosa','Pacote terrestre de 6 dias por Singapura e Sentosa (Singapura), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Gardens by the Bay, Marina Bay Sands, Sentosa, Night Safari.',144,13900,'🌃',170,'internacional','Singapura',6,'Gardens by the Bay;Marina Bay Sands;Sentosa;Night Safari'),
(1,NULL,'Maldivas: Resort em Malé e Atóis','Resort em Malé e Atóis','Pacote terrestre de 7 dias por Resort em Malé e Atóis (Maldivas), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Bangalô sobre a água, Mergulho, Snorkel com raias, Passeio de dhoni.',168,23900,'🏝️',185,'internacional','Maldivas',7,'Bangalô sobre a água;Mergulho;Snorkel com raias;Passeio de dhoni'),
(2,NULL,'Nepal: Katmandu e Pokhara','Katmandu e Pokhara','Pacote terrestre de 9 dias por Katmandu e Pokhara (Nepal), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Praça Durbar, Vista do Himalaia, Lago Phewa, Pashupatinath.',216,13200,'🏔️',260,'internacional','Nepal',9,'Praça Durbar;Vista do Himalaia;Lago Phewa;Pashupatinath'),
(6,NULL,'Jordânia: Amã, Petra e Wadi Rum','Amã, Petra e Wadi Rum','Pacote terrestre de 7 dias por Amã, Petra e Wadi Rum (Jordânia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Petra, Wadi Rum, Mar Morto, Jerash.',168,14100,'🐪',25,'internacional','Jordânia',7,'Petra;Wadi Rum;Mar Morto;Jerash'),
(6,NULL,'Egito: Cairo, Luxor e Cruzeiro no Nilo','Cairo, Luxor e Cruzeiro no Nilo','Pacote terrestre de 9 dias por Cairo, Luxor e Cruzeiro no Nilo (Egito), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Pirâmides de Gizé, Vale dos Reis, Templo de Karnak, Cruzeiro no Nilo.',216,15600,'🔺',45,'internacional','Egito',9,'Pirâmides de Gizé;Vale dos Reis;Templo de Karnak;Cruzeiro no Nilo'),
(3,NULL,'Marrocos: Marrakech, Fez e Saara','Marrakech, Fez e Saara','Pacote terrestre de 8 dias por Marrakech, Fez e Saara (Marrocos), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Medina de Marrakech, Deserto do Saara, Fez, Chefchaouen.',192,11800,'🕌',20,'internacional','Marrocos',8,'Medina de Marrakech;Deserto do Saara;Fez;Chefchaouen'),
(5,NULL,'África do Sul: Cidade do Cabo e Safári Kruger','Cidade do Cabo e Safári Kruger','Pacote terrestre de 9 dias por Cidade do Cabo e Safári Kruger (África do Sul), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Table Mountain, Cabo da Boa Esperança, Safári no Kruger, Rota dos Vinhedos.',216,19200,'🦁',40,'internacional','África do Sul',9,'Table Mountain;Cabo da Boa Esperança;Safári no Kruger;Rota dos Vinhedos'),
(5,NULL,'Tanzânia: Safári Serengeti e Zanzibar','Safári Serengeti e Zanzibar','Pacote terrestre de 9 dias por Safári Serengeti e Zanzibar (Tanzânia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Serengeti, Cratera de Ngorongoro, Zanzibar, Migração dos gnus.',216,23500,'🦒',35,'internacional','Tanzânia',9,'Serengeti;Cratera de Ngorongoro;Zanzibar;Migração dos gnus'),
(8,NULL,'Austrália: Sydney, Melbourne e Grande Barreira','Sydney, Melbourne e Grande Barreira','Pacote terrestre de 12 dias por Sydney, Melbourne e Grande Barreira (Austrália), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Opera House, Great Ocean Road, Grande Barreira de Corais, Uluru.',288,27500,'🦘',200,'internacional','Austrália',12,'Opera House;Great Ocean Road;Grande Barreira de Corais;Uluru'),
(5,NULL,'Nova Zelândia: Auckland, Queenstown e Rotorua','Auckland, Queenstown e Rotorua','Pacote terrestre de 12 dias por Auckland, Queenstown e Rotorua (Nova Zelândia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Milford Sound, Hobbiton, Rotorua, Bungee em Queenstown.',288,26800,'🥝',130,'internacional','Nova Zelândia',12,'Milford Sound;Hobbiton;Rotorua;Bungee em Queenstown');
INSERT INTO passeios (categoria_id,guia_id,titulo,destino,descricao,duracao_horas,preco,emoji,hue,escopo,pais,dias,atracoes) VALUES
(6,NULL,'Hungria: Budapeste e Curva do Danúbio','Budapeste e Curva do Danúbio','Pacote terrestre de 6 dias por Budapeste e Curva do Danúbio (Hungria), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Parlamento, Banhos Széchenyi, Bastião dos Pescadores, Cruzeiro no Danúbio.',144,10200,'🏰',10,'internacional','Hungria',6,'Parlamento;Banhos Széchenyi;Bastião dos Pescadores;Cruzeiro no Danúbio'),
(6,NULL,'Polônia: Varsóvia, Cracóvia e Auschwitz','Varsóvia, Cracóvia e Auschwitz','Pacote terrestre de 7 dias por Varsóvia, Cracóvia e Auschwitz (Polônia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Centro histórico de Cracóvia, Mina de Sal de Wieliczka, Memorial de Auschwitz, Castelo de Wawel.',168,10800,'🕯️',355,'internacional','Polônia',7,'Centro histórico de Cracóvia;Mina de Sal de Wieliczka;Memorial de Auschwitz;Castelo de Wawel'),
(8,NULL,'Suécia: Estocolmo e Arquipélago','Estocolmo e Arquipélago','Pacote terrestre de 6 dias por Estocolmo e Arquipélago (Suécia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Gamla Stan, Museu Vasa, Arquipélago de Estocolmo, Museu do ABBA.',144,13900,'🛥️',205,'internacional','Suécia',6,'Gamla Stan;Museu Vasa;Arquipélago de Estocolmo;Museu do ABBA'),
(8,NULL,'Dinamarca: Copenhague e Castelos','Copenhague e Castelos','Pacote terrestre de 6 dias por Copenhague e Castelos (Dinamarca), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Nyhavn, Jardins de Tivoli, Castelo de Kronborg, Pequena Sereia.',144,14100,'🧜',350,'internacional','Dinamarca',6,'Nyhavn;Jardins de Tivoli;Castelo de Kronborg;Pequena Sereia'),
(5,NULL,'Finlândia: Helsinque e Lapônia','Helsinque e Lapônia','Pacote terrestre de 8 dias por Helsinque e Lapônia (Finlândia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Aurora boreal, Vila do Papai Noel, Safári de huskies, Sauna finlandesa.',192,18900,'🦌',195,'internacional','Finlândia',8,'Aurora boreal;Vila do Papai Noel;Safári de huskies;Sauna finlandesa'),
(8,NULL,'Bélgica: Bruxelas, Bruges e Gante','Bruxelas, Bruges e Gante','Pacote terrestre de 6 dias por Bruxelas, Bruges e Gante (Bélgica), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Grand-Place, Canais de Bruges, Chocolates belgas, Castelo dos Condes.',144,12700,'🍫',45,'internacional','Bélgica',6,'Grand-Place;Canais de Bruges;Chocolates belgas;Castelo dos Condes'),
(6,NULL,'Malta: Valeta, Mdina e Gozo','Valeta, Mdina e Gozo','Pacote terrestre de 6 dias por Valeta, Mdina e Gozo (Malta), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Valeta, Mdina, Blue Lagoon em Comino, Gruta Azul.',144,10900,'⚓',210,'internacional','Malta',6,'Valeta;Mdina;Blue Lagoon em Comino;Gruta Azul'),
(1,NULL,'Chipre: Pafos e Limassol','Pafos e Limassol','Pacote terrestre de 7 dias por Pafos e Limassol (Chipre), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Rochas de Afrodite, Tumbas dos Reis, Montes Troodos, Praias de Ayia Napa.',168,11800,'🏖️',190,'internacional','Chipre',7,'Rochas de Afrodite;Tumbas dos Reis;Montes Troodos;Praias de Ayia Napa'),
(6,NULL,'Israel: Jerusalém, Tel Aviv e Mar Morto','Jerusalém, Tel Aviv e Mar Morto','Pacote terrestre de 8 dias por Jerusalém, Tel Aviv e Mar Morto (Israel), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Cidade Antiga de Jerusalém, Muro das Lamentações, Mar Morto, Masada.',192,15400,'🕍',45,'internacional','Israel',8,'Cidade Antiga de Jerusalém;Muro das Lamentações;Mar Morto;Masada'),
(8,NULL,'Catar: Doha e Deserto','Doha e Deserto','Pacote terrestre de 5 dias por Doha e Deserto (Catar), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Museu de Arte Islâmica, Souq Waqif, Safári no deserto, Mar Interior.',120,13200,'🕌',35,'internacional','Catar',5,'Museu de Arte Islâmica;Souq Waqif;Safári no deserto;Mar Interior'),
(8,NULL,'Malásia: Kuala Lumpur, Penang e Langkawi','Kuala Lumpur, Penang e Langkawi','Pacote terrestre de 9 dias por Kuala Lumpur, Penang e Langkawi (Malásia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Torres Petronas, Cavernas Batu, George Town, Praias de Langkawi.',216,13100,'🗼',140,'internacional','Malásia',9,'Torres Petronas;Cavernas Batu;George Town;Praias de Langkawi'),
(1,NULL,'Filipinas: Manila, El Nido e Palawan','Manila, El Nido e Palawan','Pacote terrestre de 10 dias por Manila, El Nido e Palawan (Filipinas), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Lagoas de El Nido, Rio Subterrâneo de Puerto Princesa, Intramuros, Mergulho em Coron.',240,14200,'🏝️',170,'internacional','Filipinas',10,'Lagoas de El Nido;Rio Subterrâneo de Puerto Princesa;Intramuros;Mergulho em Coron'),
(3,NULL,'Sri Lanka: Colombo, Kandy e Sigiriya','Colombo, Kandy e Sigiriya','Pacote terrestre de 9 dias por Colombo, Kandy e Sigiriya (Sri Lanka), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Fortaleza de Sigiriya, Templo do Dente, Plantações de chá, Safári no Yala.',216,13400,'🐘',120,'internacional','Sri Lanka',9,'Fortaleza de Sigiriya;Templo do Dente;Plantações de chá;Safári no Yala'),
(6,NULL,'Camboja: Siem Reap e Phnom Penh','Siem Reap e Phnom Penh','Pacote terrestre de 8 dias por Siem Reap e Phnom Penh (Camboja), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Angkor Wat, Bayon, Ta Prohm, Palácio Real.',192,12600,'🛕',30,'internacional','Camboja',8,'Angkor Wat;Bayon;Ta Prohm;Palácio Real'),
(5,NULL,'Quênia: Nairóbi e Masai Mara','Nairóbi e Masai Mara','Pacote terrestre de 8 dias por Nairóbi e Masai Mara (Quênia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Safári no Masai Mara, Migração dos gnus, Lago Nakuru, Orfanato de elefantes.',192,20800,'🦓',45,'internacional','Quênia',8,'Safári no Masai Mara;Migração dos gnus;Lago Nakuru;Orfanato de elefantes'),
(5,NULL,'Namíbia: Windhoek, Sossusvlei e Etosha','Windhoek, Sossusvlei e Etosha','Pacote terrestre de 9 dias por Windhoek, Sossusvlei e Etosha (Namíbia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Dunas de Sossusvlei, Deadvlei, Parque Etosha, Costa dos Esqueletos.',216,19800,'🏜️',25,'internacional','Namíbia',9,'Dunas de Sossusvlei;Deadvlei;Parque Etosha;Costa dos Esqueletos'),
(1,NULL,'Seychelles: Mahé, Praslin e La Digue','Mahé, Praslin e La Digue','Pacote terrestre de 7 dias por Mahé, Praslin e La Digue (Seychelles), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Anse Source d''Argent, Vallée de Mai, Praia Anse Lazio, Passeio entre ilhas.',168,21500,'🐢',180,'internacional','Seychelles',7,'Anse Source d''Argent;Vallée de Mai;Praia Anse Lazio;Passeio entre ilhas'),
(1,NULL,'Maurício: Grand Baie e Sul da Ilha','Grand Baie e Sul da Ilha','Pacote terrestre de 8 dias por Grand Baie e Sul da Ilha (Maurício), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Le Morne, Terra das Sete Cores, Cachoeira Chamarel, Ilha dos Cervos.',192,17900,'🌴',175,'internacional','Maurício',8,'Le Morne;Terra das Sete Cores;Cachoeira Chamarel;Ilha dos Cervos'),
(8,NULL,'Panamá: Cidade do Panamá e San Blas','Cidade do Panamá e San Blas','Pacote terrestre de 7 dias por Cidade do Panamá e San Blas (Panamá), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Canal do Panamá, Casco Viejo, Ilhas San Blas, Floresta de Soberanía.',168,11600,'🚢',185,'internacional','Panamá',7,'Canal do Panamá;Casco Viejo;Ilhas San Blas;Floresta de Soberanía'),
(1,NULL,'República Dominicana: Punta Cana e Santo Domingo','Punta Cana e Santo Domingo','Pacote terrestre de 7 dias por Punta Cana e Santo Domingo (República Dominicana), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Ilha Saona, Zona Colonial, Praias de Punta Cana, Altos de Chavón.',168,10800,'🏖️',190,'internacional','República Dominicana',7,'Ilha Saona;Zona Colonial;Praias de Punta Cana;Altos de Chavón'),
(1,NULL,'Jamaica: Montego Bay e Negril','Montego Bay e Negril','Pacote terrestre de 7 dias por Montego Bay e Negril (Jamaica), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Praia Seven Mile, Cachoeiras de Dunn''s River, Montanhas Azuis, Casa de Bob Marley.',168,12400,'🎶',120,'internacional','Jamaica',7,'Praia Seven Mile;Cachoeiras de Dunn''s River;Montanhas Azuis;Casa de Bob Marley'),
(8,NULL,'Paraguai: Assunção e Ciudad del Este','Assunção e Ciudad del Este','Pacote terrestre de 5 dias por Assunção e Ciudad del Este (Paraguai), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Centro Histórico de Assunção, Ruínas Jesuíticas, Represa de Itaipu, Compras.',120,6900,'🌆',10,'internacional','Paraguai',5,'Centro Histórico de Assunção;Ruínas Jesuíticas;Represa de Itaipu;Compras'),
(1,NULL,'Bahamas: Nassau e Exumas','Nassau e Exumas','Pacote terrestre de 7 dias por Nassau e Exumas (Bahamas), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Porcos nadadores, Resort Atlantis, Praia Cable Beach, Exuma Cays.',168,16900,'🐷',190,'internacional','Bahamas',7,'Porcos nadadores;Resort Atlantis;Praia Cable Beach;Exuma Cays'),
(1,NULL,'Fiji: Nadi e Ilhas Mamanuca','Nadi e Ilhas Mamanuca','Pacote terrestre de 9 dias por Nadi e Ilhas Mamanuca (Fiji), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Ilhas Mamanuca, Mergulho com tubarões, Jardim do Gigante Adormecido, Aldeias tradicionais.',216,23800,'🌺',165,'internacional','Fiji',9,'Ilhas Mamanuca;Mergulho com tubarões;Jardim do Gigante Adormecido;Aldeias tradicionais'),
(6,NULL,'Tunísia: Túnis, Sidi Bou Said e Saara','Túnis, Sidi Bou Said e Saara','Pacote terrestre de 8 dias por Túnis, Sidi Bou Said e Saara (Tunísia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Cartago, Medina de Túnis, Sidi Bou Said, Dunas do Saara.',192,10600,'🏛️',35,'internacional','Tunísia',8,'Cartago;Medina de Túnis;Sidi Bou Said;Dunas do Saara'),
(3,NULL,'Geórgia: Tbilisi e Cáucaso','Tbilisi e Cáucaso','Pacote terrestre de 8 dias por Tbilisi e Cáucaso (Geórgia), com guia e roteiro de atrações. Passagem aérea não inclusa. Destaques: Cidade Velha de Tbilisi, Kazbegi, Mtskheta, Rota do Vinho de Kakheti.',192,11700,'⛰️',350,'internacional','Geórgia',8,'Cidade Velha de Tbilisi;Kazbegi;Mtskheta;Rota do Vinho de Kakheti');
INSERT INTO roteiros (usuario_id,titulo,destino,dias,publico) VALUES
(NULL,'Fim de semana barroco em Ouro Preto','Ouro Preto - MG',3,1),
(NULL,'4 dias entre mar e história em Paraty','Paraty - RJ',4,1);
INSERT INTO roteiro_itens (roteiro_id,dia,horario,passeio_id,nota) VALUES
(1,1,'14:00',NULL,'Chegada, check-in e jantar com comida mineira'),
(1,2,'09:00',3,NULL),(1,2,'15:00',NULL,'Visita livre às minas e museus'),
(1,3,'10:00',NULL,'Café da manhã e compras de artesanato antes de voltar'),
(2,1,'15:00',NULL,'Chegada e caminhada pelo centro histórico'),
(2,2,'09:00',1,NULL),(2,3,'10:00',NULL,'Dia livre: praia, trilha ou cachoeira'),
(2,4,'09:00',NULL,'Café da manhã e retorno');

INSERT INTO guias_viagem (titulo,destino,resumo,conteudo,emoji,hue) VALUES
('Guia rápido de Ouro Preto','Ouro Preto - MG','O que ver, quando ir e como se locomover pela cidade histórica.','Melhor época: de maio a setembro, com clima seco e dias claros.\n\nComo se locomover: o centro é compacto, mas as ladeiras são íngremes. Leve calçado fechado e confortável.\n\nNão perca: Igreja de São Francisco de Assis, Praça Tiradentes, Museu da Inconfidência e a Mina do Chico Rei.\n\nDica: reserve pelo menos 2 dias para conhecer sem pressa.','🏛️',30),
('Paraty: praias, escunas e centro histórico','Paraty - RJ','Um roteiro enxuto para aproveitar o mar e as ruas de pedra.','O centro histórico é fechado para carros e alaga com a maré alta em alguns dias do ano — vale consultar a tábua de marés.\n\nOs passeios de escuna saem pela manhã e passam por ilhas e praias; leve protetor solar, boné e roupa de banho.\n\nNo fim do dia, caminhe pelo centro e prove a gastronomia caiçara.','⛵',195),
('Como montar um bom roteiro de viagem','Dicas gerais','Passo a passo para organizar dias, deslocamentos e passeios sem correria.','1) Defina o número de dias e o ritmo: poucos passeios por dia rendem mais.\n\n2) Agrupe atividades por região para reduzir deslocamentos.\n\n3) Reserve os passeios concorridos com antecedência e deixe um dia livre.\n\n4) Use o montador de roteiros do portal para organizar tudo por dia e ver o custo estimado.','🗺️',150);

-- Capas (silhueta + emojis) e guia responsável por cada viagem
UPDATE passeios SET motivo='colosseu', emoji='🍕🏛️🍝', guia_id=(SELECT id FROM guias WHERE nome='Giulia Ferraro') WHERE escopo='internacional' AND pais='Itália';
UPDATE passeios SET motivo='eiffel', emoji='🗼🥐🍷', guia_id=(SELECT id FROM guias WHERE nome='Étienne Moreau') WHERE escopo='internacional' AND pais='França';
UPDATE passeios SET motivo='castelo', emoji='💃🥘🌞', guia_id=(SELECT id FROM guias WHERE nome='Lucía Fernández') WHERE escopo='internacional' AND pais='Espanha';
UPDATE passeios SET motivo='castelo', emoji='🚋🥧🐓', guia_id=(SELECT id FROM guias WHERE nome='Lucía Fernández') WHERE escopo='internacional' AND pais='Portugal';
UPDATE passeios SET motivo='castelo', emoji='🥨🍺🏰', guia_id=(SELECT id FROM guias WHERE nome='Katarina Novak') WHERE escopo='internacional' AND pais='Alemanha';
UPDATE passeios SET motivo='partenon', emoji='🏛️🫒🌊', guia_id=(SELECT id FROM guias WHERE nome='Giulia Ferraro') WHERE escopo='internacional' AND pais='Grécia';
UPDATE passeios SET motivo='moinho', emoji='🌷🚲🧀', guia_id=(SELECT id FROM guias WHERE nome='Étienne Moreau') WHERE escopo='internacional' AND pais='Holanda';
UPDATE passeios SET motivo='montanha', emoji='🏔️🧀🍫', guia_id=(SELECT id FROM guias WHERE nome='Étienne Moreau') WHERE escopo='internacional' AND pais='Suíça';
UPDATE passeios SET motivo='igreja', emoji='🎻🏰🍰', guia_id=(SELECT id FROM guias WHERE nome='Katarina Novak') WHERE escopo='internacional' AND pais='Áustria';
UPDATE passeios SET motivo='castelo', emoji='🏰🍺🎭', guia_id=(SELECT id FROM guias WHERE nome='Katarina Novak') WHERE escopo='internacional' AND pais='República Tcheca';
UPDATE passeios SET motivo='veleiro', emoji='⛵🏖️🍋', guia_id=(SELECT id FROM guias WHERE nome='Giulia Ferraro') WHERE escopo='internacional' AND pais='Croácia';
UPDATE passeios SET motivo='fiorde', emoji='🛳️🐟🌲', guia_id=(SELECT id FROM guias WHERE nome='Katarina Novak') WHERE escopo='internacional' AND pais='Noruega';
UPDATE passeios SET motivo='aurora', emoji='🌌🌋♨️', guia_id=(SELECT id FROM guias WHERE nome='Sophie Williams') WHERE escopo='internacional' AND pais='Islândia';
UPDATE passeios SET motivo='ponte', emoji='💂☕🚌', guia_id=(SELECT id FROM guias WHERE nome='Sophie Williams') WHERE escopo='internacional' AND pais='Reino Unido';
UPDATE passeios SET motivo='castelo', emoji='☘️🍀🍺', guia_id=(SELECT id FROM guias WHERE nome='Sophie Williams') WHERE escopo='internacional' AND pais='Irlanda';
UPDATE passeios SET motivo='taj', emoji='🎈🕌🍢', guia_id=(SELECT id FROM guias WHERE nome='Amina El-Fassi') WHERE escopo='internacional' AND pais='Turquia';
UPDATE passeios SET motivo='castelo', emoji='🏰♨️🍲', guia_id=(SELECT id FROM guias WHERE nome='Katarina Novak') WHERE escopo='internacional' AND pais='Hungria';
UPDATE passeios SET motivo='igreja', emoji='🕯️🥟🏰', guia_id=(SELECT id FROM guias WHERE nome='Katarina Novak') WHERE escopo='internacional' AND pais='Polônia';
UPDATE passeios SET motivo='skyline', emoji='🛥️🦌🧊', guia_id=(SELECT id FROM guias WHERE nome='Katarina Novak') WHERE escopo='internacional' AND pais='Suécia';
UPDATE passeios SET motivo='skyline', emoji='🧜🚲🥐', guia_id=(SELECT id FROM guias WHERE nome='Katarina Novak') WHERE escopo='internacional' AND pais='Dinamarca';
UPDATE passeios SET motivo='aurora', emoji='🦌❄️🧖', guia_id=(SELECT id FROM guias WHERE nome='Katarina Novak') WHERE escopo='internacional' AND pais='Finlândia';
UPDATE passeios SET motivo='igreja', emoji='🍫🧇🍺', guia_id=(SELECT id FROM guias WHERE nome='Étienne Moreau') WHERE escopo='internacional' AND pais='Bélgica';
UPDATE passeios SET motivo='castelo', emoji='⚓🏰🌊', guia_id=(SELECT id FROM guias WHERE nome='Giulia Ferraro') WHERE escopo='internacional' AND pais='Malta';
UPDATE passeios SET motivo='praia', emoji='🏖️🏛️🍊', guia_id=(SELECT id FROM guias WHERE nome='Giulia Ferraro') WHERE escopo='internacional' AND pais='Chipre';
UPDATE passeios SET motivo='taj', emoji='🕍🌅🧂', guia_id=(SELECT id FROM guias WHERE nome='Arun Mehta') WHERE escopo='internacional' AND pais='Israel';
UPDATE passeios SET motivo='burj', emoji='🕌🐪🌇', guia_id=(SELECT id FROM guias WHERE nome='Arun Mehta') WHERE escopo='internacional' AND pais='Catar';
UPDATE passeios SET motivo='estatua', emoji='🗽🍔🎢', guia_id=(SELECT id FROM guias WHERE nome='Sophie Williams') WHERE escopo='internacional' AND pais='Estados Unidos';
UPDATE passeios SET motivo='cataratas', emoji='🍁🦫🏔️', guia_id=(SELECT id FROM guias WHERE nome='Sophie Williams') WHERE escopo='internacional' AND pais='Canadá';
UPDATE passeios SET motivo='piramide', emoji='🌮🌵💀', guia_id=(SELECT id FROM guias WHERE nome='Pedro Alvarado') WHERE escopo='internacional' AND pais='México';
UPDATE passeios SET motivo='skyline', emoji='💃🥩🍷', guia_id=(SELECT id FROM guias WHERE nome='Lucía Fernández') WHERE escopo='internacional' AND pais='Argentina';
UPDATE passeios SET motivo='dunas', emoji='🍷🏜️🌌', guia_id=(SELECT id FROM guias WHERE nome='Mateo Quispe') WHERE escopo='internacional' AND pais='Chile';
UPDATE passeios SET motivo='machu', emoji='🦙🏔️🥔', guia_id=(SELECT id FROM guias WHERE nome='Mateo Quispe') WHERE escopo='internacional' AND pais='Peru';
UPDATE passeios SET motivo='igreja', emoji='☕🌴💃', guia_id=(SELECT id FROM guias WHERE nome='Mateo Quispe') WHERE escopo='internacional' AND pais='Colômbia';
UPDATE passeios SET motivo='skyline', emoji='🚗🌴🎺', guia_id=(SELECT id FROM guias WHERE nome='Pedro Alvarado') WHERE escopo='internacional' AND pais='Cuba';
UPDATE passeios SET motivo='praia', emoji='🏖️🥩🧉', guia_id=(SELECT id FROM guias WHERE nome='Lucía Fernández') WHERE escopo='internacional' AND pais='Uruguai';
UPDATE passeios SET motivo='dunas', emoji='🧂🦙🏔️', guia_id=(SELECT id FROM guias WHERE nome='Mateo Quispe') WHERE escopo='internacional' AND pais='Bolívia';
UPDATE passeios SET motivo='vulcao', emoji='🐢🌋🍌', guia_id=(SELECT id FROM guias WHERE nome='Mateo Quispe') WHERE escopo='internacional' AND pais='Equador';
UPDATE passeios SET motivo='vulcao', emoji='🦥🌋🦜', guia_id=(SELECT id FROM guias WHERE nome='Pedro Alvarado') WHERE escopo='internacional' AND pais='Costa Rica';
UPDATE passeios SET motivo='ponte', emoji='🚢🌴🦜', guia_id=(SELECT id FROM guias WHERE nome='Pedro Alvarado') WHERE escopo='internacional' AND pais='Panamá';
UPDATE passeios SET motivo='praia', emoji='🏖️🌴🍹', guia_id=(SELECT id FROM guias WHERE nome='Pedro Alvarado') WHERE escopo='internacional' AND pais='República Dominicana';
UPDATE passeios SET motivo='praia', emoji='🎶🏖️🍹', guia_id=(SELECT id FROM guias WHERE nome='Pedro Alvarado') WHERE escopo='internacional' AND pais='Jamaica';
UPDATE passeios SET motivo='igreja', emoji='🌆🧉🛍️', guia_id=(SELECT id FROM guias WHERE nome='Lucía Fernández') WHERE escopo='internacional' AND pais='Paraguai';
UPDATE passeios SET motivo='veleiro', emoji='🐷⛵🏝️', guia_id=(SELECT id FROM guias WHERE nome='Pedro Alvarado') WHERE escopo='internacional' AND pais='Bahamas';
UPDATE passeios SET motivo='fuji', emoji='⛩️🍣🌸', guia_id=(SELECT id FROM guias WHERE nome='Hiro Tanaka') WHERE escopo='internacional' AND pais='Japão';
UPDATE passeios SET motivo='pagoda', emoji='🛕🐘🍜', guia_id=(SELECT id FROM guias WHERE nome='Mai Nguyen') WHERE escopo='internacional' AND pais='Tailândia';
UPDATE passeios SET motivo='muralha', emoji='🐼🥟🏯', guia_id=(SELECT id FROM guias WHERE nome='Hiro Tanaka') WHERE escopo='internacional' AND pais='China';
UPDATE passeios SET motivo='taj', emoji='🕌🍛🐘', guia_id=(SELECT id FROM guias WHERE nome='Arun Mehta') WHERE escopo='internacional' AND pais='Índia';
UPDATE passeios SET motivo='burj', emoji='🏙️🐪🌇', guia_id=(SELECT id FROM guias WHERE nome='Arun Mehta') WHERE escopo='internacional' AND pais='Emirados Árabes Unidos';
UPDATE passeios SET motivo='pagoda', emoji='🌺🛕🏝️', guia_id=(SELECT id FROM guias WHERE nome='Mai Nguyen') WHERE escopo='internacional' AND pais='Indonésia';
UPDATE passeios SET motivo='veleiro', emoji='🛶🍜🌾', guia_id=(SELECT id FROM guias WHERE nome='Mai Nguyen') WHERE escopo='internacional' AND pais='Vietnã';
UPDATE passeios SET motivo='pagoda', emoji='🏯🍜🎤', guia_id=(SELECT id FROM guias WHERE nome='Hiro Tanaka') WHERE escopo='internacional' AND pais='Coreia do Sul';
UPDATE passeios SET motivo='burj', emoji='🌃🦁🌿', guia_id=(SELECT id FROM guias WHERE nome='Mai Nguyen') WHERE escopo='internacional' AND pais='Singapura';
UPDATE passeios SET motivo='praia', emoji='🏝️🐠🤿', guia_id=(SELECT id FROM guias WHERE nome='Mai Nguyen') WHERE escopo='internacional' AND pais='Maldivas';
UPDATE passeios SET motivo='montanha', emoji='🏔️🙏🥾', guia_id=(SELECT id FROM guias WHERE nome='Arun Mehta') WHERE escopo='internacional' AND pais='Nepal';
UPDATE passeios SET motivo='partenon', emoji='🐪🏜️🏛️', guia_id=(SELECT id FROM guias WHERE nome='Amina El-Fassi') WHERE escopo='internacional' AND pais='Jordânia';
UPDATE passeios SET motivo='burj', emoji='🗼🌴🍜', guia_id=(SELECT id FROM guias WHERE nome='Mai Nguyen') WHERE escopo='internacional' AND pais='Malásia';
UPDATE passeios SET motivo='praia', emoji='🏝️🐠🥥', guia_id=(SELECT id FROM guias WHERE nome='Mai Nguyen') WHERE escopo='internacional' AND pais='Filipinas';
UPDATE passeios SET motivo='pagoda', emoji='🐘🍵🛕', guia_id=(SELECT id FROM guias WHERE nome='Arun Mehta') WHERE escopo='internacional' AND pais='Sri Lanka';
UPDATE passeios SET motivo='pagoda', emoji='🛕🌅🐘', guia_id=(SELECT id FROM guias WHERE nome='Mai Nguyen') WHERE escopo='internacional' AND pais='Camboja';
UPDATE passeios SET motivo='montanha', emoji='⛰️🍷🧀', guia_id=(SELECT id FROM guias WHERE nome='Katarina Novak') WHERE escopo='internacional' AND pais='Geórgia';
UPDATE passeios SET motivo='piramide', emoji='🔺🐪☀️', guia_id=(SELECT id FROM guias WHERE nome='Amina El-Fassi') WHERE escopo='internacional' AND pais='Egito';
UPDATE passeios SET motivo='taj', emoji='🕌🐪🍵', guia_id=(SELECT id FROM guias WHERE nome='Amina El-Fassi') WHERE escopo='internacional' AND pais='Marrocos';
UPDATE passeios SET motivo='partenon', emoji='🏛️🏜️🍊', guia_id=(SELECT id FROM guias WHERE nome='Amina El-Fassi') WHERE escopo='internacional' AND pais='Tunísia';
UPDATE passeios SET motivo='safari', emoji='🦁🍷🌅', guia_id=(SELECT id FROM guias WHERE nome='Thabo Nkosi') WHERE escopo='internacional' AND pais='África do Sul';
UPDATE passeios SET motivo='safari', emoji='🦒🦓🌍', guia_id=(SELECT id FROM guias WHERE nome='Thabo Nkosi') WHERE escopo='internacional' AND pais='Tanzânia';
UPDATE passeios SET motivo='safari', emoji='🦓🦁🌅', guia_id=(SELECT id FROM guias WHERE nome='Thabo Nkosi') WHERE escopo='internacional' AND pais='Quênia';
UPDATE passeios SET motivo='dunas', emoji='🏜️🦒🌌', guia_id=(SELECT id FROM guias WHERE nome='Thabo Nkosi') WHERE escopo='internacional' AND pais='Namíbia';
UPDATE passeios SET motivo='praia', emoji='🌴🏝️🐠', guia_id=(SELECT id FROM guias WHERE nome='Thabo Nkosi') WHERE escopo='internacional' AND pais='Maurício';
UPDATE passeios SET motivo='praia', emoji='🐢🌴🏝️', guia_id=(SELECT id FROM guias WHERE nome='Thabo Nkosi') WHERE escopo='internacional' AND pais='Seychelles';
UPDATE passeios SET motivo='opera', emoji='🦘🏄🐨', guia_id=(SELECT id FROM guias WHERE nome='Mai Nguyen') WHERE escopo='internacional' AND pais='Austrália';
UPDATE passeios SET motivo='fiorde', emoji='🥝🐑🏔️', guia_id=(SELECT id FROM guias WHERE nome='Mai Nguyen') WHERE escopo='internacional' AND pais='Nova Zelândia';
UPDATE passeios SET motivo='praia', emoji='🌺🏝️🤿', guia_id=(SELECT id FROM guias WHERE nome='Mai Nguyen') WHERE escopo='internacional' AND pais='Fiji';
UPDATE passeios SET motivo='dunas', emoji='🏜️💧🌅' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Lençóis Maranhenses%';
UPDATE passeios SET motivo='praia', emoji='🐢🌊🤿' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Fernando de Noronha%';
UPDATE passeios SET motivo='dunas', emoji='🏄🌅🪁' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Jericoacoara%';
UPDATE passeios SET motivo='cataratas', emoji='⛰️💧🥾' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Chapada Diamantina%';
UPDATE passeios SET motivo='cataratas', emoji='💧🌿🦋' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Veadeiros%';
UPDATE passeios SET motivo='floresta', emoji='🐊🦜🛶' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Pantanal%';
UPDATE passeios SET motivo='floresta', emoji='🌳🐒🛶' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Amazônia%';
UPDATE passeios SET motivo='igreja', emoji='🍫🎄🧀' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Gramado%';
UPDATE passeios SET motivo='cristo', emoji='🏙️🏖️⚽' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Rio de Janeiro%';
UPDATE passeios SET motivo='igreja', emoji='🥁🍤⛪' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Salvador%';
UPDATE passeios SET motivo='igreja', emoji='🎭🥁🏖️' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Recife%';
UPDATE passeios SET motivo='praia', emoji='🐠🏖️🥥' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Porto de Galinhas%';
UPDATE passeios SET motivo='dunas', emoji='🐬🏖️🌅' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Natal%';
UPDATE passeios SET motivo='dunas', emoji='🌅💧🏜️' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Jalapão%';
UPDATE passeios SET motivo='praia', emoji='🏄🦪🏖️' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Florianópolis%';
UPDATE passeios SET motivo='praia', emoji='🛶🏝️🐢' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Ilha Grande%';
UPDATE passeios SET motivo='veleiro', emoji='⛵🏖️🍹' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Búzios%';
UPDATE passeios SET motivo='praia', emoji='🌴🏄💦' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Ilhabela%';
UPDATE passeios SET motivo='floresta', emoji='🚂🌳🏛️' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Curitiba%';
UPDATE passeios SET motivo='cataratas', emoji='🚣💦🌿' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Brotas%';
UPDATE passeios SET motivo='skyline', emoji='🌆🍕🎨' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%São Paulo%';
UPDATE passeios SET motivo='skyline', emoji='🏛️✈️🌇' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Brasília%';
UPDATE passeios SET motivo='igreja', emoji='🔔⛪🚂' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Tiradentes%';
UPDATE passeios SET motivo='igreja', emoji='💎⛪🎶' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Diamantina%';
UPDATE passeios SET motivo='cataratas', emoji='🐴💧⛪' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Pirenópolis%';
UPDATE passeios SET motivo='praia', emoji='🌊🌳🛶' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Alter do Chão%';
UPDATE passeios SET motivo='floresta', emoji='🍲🌳⛵' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Belém%';
UPDATE passeios SET motivo='igreja', emoji='🎨⛪🥁' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%São Luís%';
UPDATE passeios SET motivo='praia', emoji='🌞🏖️🦐' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Fortaleza%';
UPDATE passeios SET motivo='praia', emoji='🏖️🐠🥥' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Morro de São Paulo%';
UPDATE passeios SET motivo='praia', emoji='🌴🏖️🍹' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Trancoso%';
UPDATE passeios SET motivo='veleiro', emoji='🐋⛵🤿' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Abrolhos%';
UPDATE passeios SET motivo='montanha', emoji='🏞️🥾🌄' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Cambará%';
UPDATE passeios SET motivo='montanha', emoji='🍷🍇🚂' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Serra Gaúcha%';
UPDATE passeios SET motivo='roda', emoji='🎢🎡🎠' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Beto Carrero%';
UPDATE passeios SET motivo='cataratas', emoji='🌊🥾🌿' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Tabuleiro%';
UPDATE passeios SET motivo='cataratas', emoji='🧀🏞️🥾' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Canastra%';
UPDATE passeios SET motivo='igreja', emoji='⛪💰🔔' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Ouro Preto%';
UPDATE passeios SET motivo='castelo', emoji='🏰👑🌹' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Petrópolis%';
UPDATE passeios SET motivo='cataratas', emoji='💦🌈🦜' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Foz do Iguaçu%';
UPDATE passeios SET motivo='cataratas', emoji='🤿🐟💧' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Bonito%';
UPDATE passeios SET motivo='montanha', emoji='🌄🏞️🥾' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Guimarães%';
UPDATE passeios SET motivo='veleiro', emoji='⛵🏖️🍹' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Paraty%';
UPDATE passeios SET motivo='floresta', emoji='🤿🐟🌿' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Sucuri%';
UPDATE passeios SET motivo='igreja', emoji='⛪🔔🎨' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Igrejas Barrocas%';
UPDATE passeios SET motivo='cataratas', emoji='🚤💧🌅' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Capitólio%';
UPDATE passeios SET motivo='dunas', emoji='🏜️💧🌅' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Lagoa Azul%';
UPDATE passeios SET motivo='igreja', emoji='🍤🥁⛪' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Pelourinho%';
UPDATE passeios SET motivo='montanha', emoji='⛰️🌄🥾' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Pico da Bandeira%';
UPDATE passeios SET motivo='cataratas', emoji='💦🌈🦜' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Cataratas do Iguaçu%';
UPDATE passeios SET motivo='praia', emoji='🐠🏖️🌴' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Maragogi%';
UPDATE passeios SET motivo='montanha', emoji='☕🧀🌄' WHERE escopo='nacional' AND motivo IS NULL AND titulo LIKE '%Rota do Café%';
UPDATE passeios SET motivo='praia', emoji='🏖️🌴🐠' WHERE motivo IS NULL;
UPDATE passeios SET guia_id=(SELECT id FROM guias WHERE nome='Marina Duarte') WHERE escopo='nacional' AND destino REGEXP ' - (RJ|MG)';
UPDATE passeios SET guia_id=(SELECT id FROM guias WHERE nome='Rafael Nogueira') WHERE escopo='nacional' AND destino REGEXP ' - (MS|MT|GO|DF)';
UPDATE passeios SET guia_id=(SELECT id FROM guias WHERE nome='Camila Torres') WHERE escopo='nacional' AND destino REGEXP ' - (BA|PE|CE|MA|RN|AL)';
UPDATE passeios SET guia_id=(SELECT id FROM guias WHERE nome='Henrique Vasconcelos') WHERE escopo='nacional' AND destino REGEXP ' - (RS|SC|PR|SP)';
UPDATE passeios SET guia_id=(SELECT id FROM guias WHERE nome='Luana Tapajós') WHERE escopo='nacional' AND destino REGEXP ' - (AM|PA|TO)';

-- Saídas (2 por passeio, internacionais com 20 vagas) — sempre no futuro
INSERT INTO saidas (passeio_id,data_saida,vagas_total,vagas_disponiveis)
SELECT id, DATE_ADD(CURDATE(), INTERVAL 7+id*2 DAY), IF(escopo='internacional',20,12), IF(escopo='internacional',20,12) FROM passeios;
INSERT INTO saidas (passeio_id,data_saida,vagas_total,vagas_disponiveis)
SELECT id, DATE_ADD(CURDATE(), INTERVAL 45+id*2 DAY), IF(escopo='internacional',20,12), IF(escopo='internacional',20,12) FROM passeios;
-- Uma saída lotada para demonstrar a lista de espera
UPDATE saidas SET vagas_disponiveis=0 WHERE passeio_id=1 AND data_saida=DATE_ADD(CURDATE(), INTERVAL 9 DAY);

-- Reserva de exemplo com pagamento informado (aparece como notificação no admin)
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,status,prazo_pagamento)
SELECT 4, s.id, 2, p.preco*2, 'em_analise', DATE_ADD(NOW(), INTERVAL 20 HOUR) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE p.id=3 ORDER BY s.data_saida LIMIT 1;
SET @rid = LAST_INSERT_ID();
UPDATE saidas s JOIN reservas r ON r.saida_id=s.id SET s.vagas_disponiveis=s.vagas_disponiveis-r.qtd WHERE r.id=@rid;
INSERT INTO pagamentos (reserva_id,metodo,valor,comprovante,status) SELECT id,'pix',valor_total,'E00000DEMO123','informado' FROM reservas WHERE id=@rid;

-- Categorias financeiras e lançamentos de exemplo (últimos 6 meses)
INSERT INTO categorias_financeiras (id,nome,natureza,grupo) VALUES
(1,'Venda de viagens','receita','receita'),
(2,'Outras receitas','receita','receita'),
(3,'Reembolso a cliente','despesa','deducao'),
(4,'Pagamento do guia','despesa','custo_direto'),
(5,'Fornecedores e ingressos','despesa','custo_direto'),
(6,'Marketing e anúncios','despesa','operacional'),
(7,'Sistema e hospedagem do site','despesa','operacional'),
(8,'Impostos e taxas','despesa','operacional'),
(9,'Pró-labore e salários','despesa','operacional'),
(10,'Outras despesas','despesa','operacional');
INSERT INTO lancamentos (data_lanc,categoria_id,descricao,valor,pago) VALUES
(DATE_SUB(CURDATE(), INTERVAL 5 DAY),2,'Comissão de parceiros',425.00,1),
(DATE_SUB(CURDATE(), INTERVAL 21 DAY),5,'Ingressos e fornecedores locais',430.00,1),
(DATE_SUB(CURDATE(), INTERVAL 28 DAY),5,'Ingressos e fornecedores locais',1482.00,1),
(DATE_SUB(CURDATE(), INTERVAL 4 DAY),6,'Anúncios (redes sociais)',360.00,1),
(DATE_SUB(CURDATE(), INTERVAL 5 DAY),7,'Hospedagem do site e domínio',89.90,1),
(DATE_SUB(CURDATE(), INTERVAL 10 DAY),8,'Impostos (Simples Nacional)',436.00,1),
(DATE_SUB(CURDATE(), INTERVAL 15 DAY),9,'Pró-labore',1800.00,1),
(DATE_SUB(CURDATE(), INTERVAL 9 DAY),10,'Material e despesas gerais',233.00,1),
(DATE_SUB(CURDATE(), INTERVAL 48 DAY),5,'Ingressos e fornecedores locais',991.00,1),
(DATE_SUB(CURDATE(), INTERVAL 32 DAY),5,'Ingressos e fornecedores locais',543.00,1),
(DATE_SUB(CURDATE(), INTERVAL 50 DAY),6,'Anúncios (redes sociais)',410.00,1),
(DATE_SUB(CURDATE(), INTERVAL 35 DAY),7,'Hospedagem do site e domínio',89.90,1),
(DATE_SUB(CURDATE(), INTERVAL 40 DAY),8,'Impostos (Simples Nacional)',810.00,1),
(DATE_SUB(CURDATE(), INTERVAL 45 DAY),9,'Pró-labore',1800.00,1),
(DATE_SUB(CURDATE(), INTERVAL 41 DAY),10,'Material e despesas gerais',178.00,1),
(DATE_SUB(CURDATE(), INTERVAL 73 DAY),2,'Comissão de parceiros',157.00,1),
(DATE_SUB(CURDATE(), INTERVAL 62 DAY),5,'Ingressos e fornecedores locais',522.00,1),
(DATE_SUB(CURDATE(), INTERVAL 76 DAY),5,'Ingressos e fornecedores locais',1397.00,1),
(DATE_SUB(CURDATE(), INTERVAL 67 DAY),6,'Anúncios (redes sociais)',872.00,1),
(DATE_SUB(CURDATE(), INTERVAL 65 DAY),7,'Hospedagem do site e domínio',89.90,1),
(DATE_SUB(CURDATE(), INTERVAL 70 DAY),8,'Impostos (Simples Nacional)',592.00,1),
(DATE_SUB(CURDATE(), INTERVAL 75 DAY),9,'Pró-labore',1800.00,1),
(DATE_SUB(CURDATE(), INTERVAL 68 DAY),10,'Material e despesas gerais',267.00,1),
(DATE_SUB(CURDATE(), INTERVAL 98 DAY),2,'Comissão de parceiros',351.00,1),
(DATE_SUB(CURDATE(), INTERVAL 106 DAY),5,'Ingressos e fornecedores locais',928.00,1),
(DATE_SUB(CURDATE(), INTERVAL 92 DAY),5,'Ingressos e fornecedores locais',1982.00,1),
(DATE_SUB(CURDATE(), INTERVAL 111 DAY),6,'Anúncios (redes sociais)',636.00,1),
(DATE_SUB(CURDATE(), INTERVAL 95 DAY),7,'Hospedagem do site e domínio',89.90,1),
(DATE_SUB(CURDATE(), INTERVAL 100 DAY),8,'Impostos (Simples Nacional)',703.00,1),
(DATE_SUB(CURDATE(), INTERVAL 105 DAY),9,'Pró-labore',1800.00,1),
(DATE_SUB(CURDATE(), INTERVAL 94 DAY),10,'Material e despesas gerais',103.00,1),
(DATE_SUB(CURDATE(), INTERVAL 132 DAY),2,'Comissão de parceiros',266.00,1),
(DATE_SUB(CURDATE(), INTERVAL 137 DAY),5,'Ingressos e fornecedores locais',1636.00,1),
(DATE_SUB(CURDATE(), INTERVAL 124 DAY),5,'Ingressos e fornecedores locais',1263.00,1),
(DATE_SUB(CURDATE(), INTERVAL 123 DAY),6,'Anúncios (redes sociais)',406.00,1),
(DATE_SUB(CURDATE(), INTERVAL 125 DAY),7,'Hospedagem do site e domínio',89.90,1),
(DATE_SUB(CURDATE(), INTERVAL 130 DAY),8,'Impostos (Simples Nacional)',511.00,1),
(DATE_SUB(CURDATE(), INTERVAL 135 DAY),9,'Pró-labore',1800.00,1),
(DATE_SUB(CURDATE(), INTERVAL 130 DAY),10,'Material e despesas gerais',141.00,1),
(DATE_SUB(CURDATE(), INTERVAL 159 DAY),2,'Comissão de parceiros',533.00,1),
(DATE_SUB(CURDATE(), INTERVAL 163 DAY),5,'Ingressos e fornecedores locais',1487.00,1),
(DATE_SUB(CURDATE(), INTERVAL 170 DAY),5,'Ingressos e fornecedores locais',669.00,1),
(DATE_SUB(CURDATE(), INTERVAL 154 DAY),6,'Anúncios (redes sociais)',670.00,1),
(DATE_SUB(CURDATE(), INTERVAL 155 DAY),7,'Hospedagem do site e domínio',89.90,1),
(DATE_SUB(CURDATE(), INTERVAL 160 DAY),8,'Impostos (Simples Nacional)',541.00,1),
(DATE_SUB(CURDATE(), INTERVAL 165 DAY),9,'Pró-labore',1800.00,1);
INSERT INTO avaliacoes (usuario_id,passeio_id,nota,comentario) VALUES
(5,1,5,'Paisagens lindas e roteiro bem planejado.'),
(6,1,4,'Ótimo custo-benefício. Voltaria com certeza.'),
(7,1,5,'Guia muito simpático e conhecedor do local.'),
(5,2,5,'Experiência inesquecível com a família toda.'),
(6,2,3,'Bom passeio, mas o grupo estava grande demais.'),
(7,2,5,'Passeio incrível, guia atencioso e tudo no horário.'),
(8,2,5,'Valeu cada centavo! Recomendo para quem gosta de aventura.'),
(5,3,5,'Valeu cada centavo! Recomendo para quem gosta de aventura.'),
(6,3,4,'Organização excelente, só achei a saída muito cedo.'),
(5,4,4,'Ótimo custo-benefício. Voltaria com certeza.'),
(6,4,5,'Guia muito simpático e conhecedor do local.'),
(7,4,5,'Experiência inesquecível com a família toda.'),
(5,5,3,'Bom passeio, mas o grupo estava grande demais.'),
(6,5,5,'Passeio incrível, guia atencioso e tudo no horário.'),
(7,5,5,'Valeu cada centavo! Recomendo para quem gosta de aventura.'),
(8,5,4,'Organização excelente, só achei a saída muito cedo.'),
(5,6,4,'Organização excelente, só achei a saída muito cedo.'),
(6,6,5,'Paisagens lindas e roteiro bem planejado.'),
(5,7,5,'Guia muito simpático e conhecedor do local.'),
(6,7,5,'Experiência inesquecível com a família toda.'),
(7,7,3,'Bom passeio, mas o grupo estava grande demais.'),
(5,8,5,'Passeio incrível, guia atencioso e tudo no horário.'),
(6,8,5,'Valeu cada centavo! Recomendo para quem gosta de aventura.'),
(7,8,4,'Organização excelente, só achei a saída muito cedo.'),
(8,8,5,'Paisagens lindas e roteiro bem planejado.'),
(5,9,5,'Paisagens lindas e roteiro bem planejado.'),
(6,9,4,'Ótimo custo-benefício. Voltaria com certeza.'),
(5,10,5,'Experiência inesquecível com a família toda.'),
(6,10,3,'Bom passeio, mas o grupo estava grande demais.'),
(7,10,5,'Passeio incrível, guia atencioso e tudo no horário.'),
(5,11,5,'Valeu cada centavo! Recomendo para quem gosta de aventura.'),
(6,11,4,'Organização excelente, só achei a saída muito cedo.'),
(7,11,5,'Paisagens lindas e roteiro bem planejado.'),
(8,11,4,'Ótimo custo-benefício. Voltaria com certeza.'),
(5,12,4,'Ótimo custo-benefício. Voltaria com certeza.'),
(6,12,5,'Guia muito simpático e conhecedor do local.');

-- Contatos do suporte (EXEMPLO — edite em Admin → Suporte)
INSERT INTO configuracoes (chave,valor,rotulo) VALUES
('suporte_email','seuemail@gmail.com','E-mail de suporte (Gmail)'),
('suporte_telefone','(35) 3000-0000','Telefone fixo'),
('suporte_whatsapp','(35) 99999-0000','WhatsApp'),
('suporte_horario','Seg a sex, 8h às 18h · Sáb, 9h às 13h','Horário de atendimento'),
('suporte_endereco','Rua das Viagens, 100 — Centro, Campanha/MG','Endereço'),
('suporte_instagram','@roteira.viagens','Instagram');
INSERT INTO chamados (usuario_id,nome,email,assunto,mensagem,status,resposta,respondido_em) VALUES
(4,'Viajante Teste','viajante@teste.com','Dúvida sobre o pagamento por PIX','Posso pagar a reserva em duas partes?','respondido','Olá! O pagamento deve ser feito de uma vez em até 24h. Qualquer dúvida, estamos à disposição.',NOW()),
(NULL,'Visitante','visitante@exemplo.com','Vocês fazem roteiros sob medida?','Gostaria de um roteiro para 10 dias na Itália com crianças.','aberto',NULL,NULL);

-- ===== Histórico de demonstração: viagens já realizadas, pagamentos confirmados e financeiro derivado =====
INSERT INTO saidas (passeio_id,data_saida,vagas_total,vagas_disponiveis) SELECT id, DATE_SUB(CURDATE(), INTERVAL 8+id*7 DAY), 12, 12 FROM passeios WHERE id<=12;
INSERT INTO saidas (passeio_id,data_saida,vagas_total,vagas_disponiveis) SELECT id, DATE_SUB(CURDATE(), INTERVAL 63+id*7 DAY), 12, 12 FROM passeios WHERE id<=12;
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 5, s.id, 3, p.preco*3, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 44 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=1 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 15 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 2, p.preco*2, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 40 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=1 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 15 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 7, s.id, 3, p.preco*3, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 38 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=1 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 15 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 5, s.id, 2, p.preco*2, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 46 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=2 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 22 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 3, p.preco*3, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 52 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=2 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 22 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 7, s.id, 3, p.preco*3, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 34 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=2 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 22 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 8, s.id, 3, p.preco*3, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 48 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=2 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 22 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 5, s.id, 3, p.preco*3, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 42 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=3 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 29 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 46 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=3 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 29 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 5, s.id, 2, p.preco*2, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 54 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=4 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 36 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 51 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=4 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 36 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 7, s.id, 3, p.preco*3, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 55 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=4 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 36 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 5, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 69 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=5 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 43 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 66 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=5 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 43 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 7, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 71 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=5 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 43 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 8, s.id, 2, p.preco*2, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 66 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=5 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 43 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 5, s.id, 2, p.preco*2, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 78 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=6 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 50 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 70 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=6 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 50 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 5, s.id, 2, p.preco*2, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 83 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=7 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 57 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 3, p.preco*3, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 72 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=7 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 57 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 7, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 87 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=7 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 57 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 5, s.id, 3, p.preco*3, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 87 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=8 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 64 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 85 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=8 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 64 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 7, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 77 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=8 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 64 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 8, s.id, 3, p.preco*3, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 89 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=8 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 64 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 5, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 85 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=9 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 71 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 2, p.preco*2, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 89 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=9 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 71 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 5, s.id, 2, p.preco*2, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 100 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=10 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 78 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 106 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=10 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 78 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 7, s.id, 2, p.preco*2, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 101 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=10 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 78 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 5, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 101 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=11 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 85 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 107 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=11 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 85 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 7, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 105 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=11 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 85 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 8, s.id, 3, p.preco*3, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 114 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=11 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 85 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 5, s.id, 3, p.preco*3, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 106 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=12 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 92 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 2, p.preco*2, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 113 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=12 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 92 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 4, s.id, 2, p.preco*2, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 51 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=3 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 29 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 4, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 57 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=4 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 36 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 5, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 101 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=3 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 84 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 4, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 105 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=4 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 91 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 121 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=6 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 105 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 8, s.id, 3, p.preco*3, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 161 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=11 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 140 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 5, s.id, 2, p.preco*2, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 139 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=7 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 112 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 4, s.id, 2, p.preco*2, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 122 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=6 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 105 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 2, p.preco*2, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 90 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=2 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 77 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 3, p.preco*3, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 147 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=10 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 133 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 4, s.id, 3, p.preco*3, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 162 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=10 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 133 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 3, p.preco*3, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 143 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=8 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 119 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 2, p.preco*2, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 97 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=3 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 84 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 7, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 166 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=12 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 147 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 4, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 121 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=5 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 98 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 6, s.id, 1, p.preco*1, 0, 'confirmada', DATE_SUB(NOW(), INTERVAL 132 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=7 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 112 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 7, s.id, 2, p.preco*2, ROUND(p.preco*2*0.2,2), 'cancelada', DATE_SUB(NOW(), INTERVAL 124 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=5 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 98 DAY);
INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,taxa_cancelamento,status,created_at) SELECT 5, s.id, 2, p.preco*2, ROUND(p.preco*2*0.2,2), 'cancelada', DATE_SUB(NOW(), INTERVAL 151 DAY) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.passeio_id=9 AND s.data_saida=DATE_SUB(CURDATE(), INTERVAL 126 DAY);
UPDATE saidas s SET s.vagas_disponiveis = s.vagas_total - COALESCE((SELECT SUM(r.qtd) FROM reservas r WHERE r.saida_id=s.id AND r.status IN ('aguardando_pagamento','em_analise','confirmada')),0) WHERE s.data_saida < CURDATE();
INSERT INTO pagamentos (reserva_id,metodo,valor,comprovante,status,created_at,decidido_em) SELECT r.id,'pix',r.valor_total,CONCAT('E2026DEMO',LPAD(r.id,6,'0')),'confirmado',r.created_at,r.created_at FROM reservas r WHERE r.status IN ('confirmada','cancelada');
INSERT INTO lancamentos (data_lanc,categoria_id,descricao,valor,pago,reserva_id) SELECT DATE(r.created_at),1,CONCAT('Venda – ',p.titulo,' (reserva #',r.id,')'),r.valor_total,1,r.id FROM reservas r JOIN saidas s ON s.id=r.saida_id JOIN passeios p ON p.id=s.passeio_id WHERE r.status IN ('confirmada','cancelada');
INSERT INTO lancamentos (data_lanc,categoria_id,descricao,valor,pago,reserva_id) SELECT s.data_saida,4,CONCAT('Repasse ao guia – reserva #',r.id),ROUND(r.valor_total*0.30,2),IF(s.data_saida < CURDATE(),1,0),r.id FROM reservas r JOIN saidas s ON s.id=r.saida_id JOIN passeios p ON p.id=s.passeio_id WHERE r.status='confirmada' AND p.guia_id IS NOT NULL;
INSERT INTO lancamentos (data_lanc,categoria_id,descricao,valor,pago,reserva_id) SELECT DATE_ADD(DATE(r.created_at), INTERVAL 3 DAY),3,CONCAT('Reembolso – reserva #',r.id,' (descontada a taxa)'),r.valor_total-r.taxa_cancelamento,1,r.id FROM reservas r WHERE r.status='cancelada';

-- Cupons e configurações extras
INSERT INTO cupons (codigo,tipo,valor,validade,usos_max) VALUES ('BEMVINDO10','percentual',10,DATE_ADD(CURDATE(), INTERVAL 1 YEAR),200),('VERAO100','valor',100,DATE_ADD(CURDATE(), INTERVAL 6 MONTH),50);
INSERT INTO configuracoes (chave,valor,rotulo) VALUES ('email_remetente','nao-responder@exemplo.com.br','E-mail remetente das notificações'),('site_url','','Endereço público do site (links nos e-mails; vazio = automático)');

-- Cargos do painel administrativo (senha: viagem123) e e-mails já confirmados nos dados de exemplo
INSERT INTO usuarios (nome,email,telefone,tipo_user,senha) VALUES
('Fernanda Financeiro','financeiro@portalviagens.com',NULL,4,'$2b$12$5VMSLu8vr889BTnWQNa9b..H2g6rL9r240VFJVXF9pxRaTFWDnjeu'),
('Atendimento Roteira','atendimento@portalviagens.com',NULL,5,'$2b$12$5VMSLu8vr889BTnWQNa9b..H2g6rL9r240VFJVXF9pxRaTFWDnjeu'),
('Catálogo Roteira','catalogo@portalviagens.com',NULL,6,'$2b$12$5VMSLu8vr889BTnWQNa9b..H2g6rL9r240VFJVXF9pxRaTFWDnjeu');
UPDATE usuarios SET email_verificado_em = NOW() WHERE email_verificado_em IS NULL;
INSERT INTO notificacoes (usuario_id,titulo,mensagem,url) VALUES
(4,'Bem-vindo(a) à Roteira','Explore viagens, monte roteiros e calcule o custo da sua viagem.','passeios.php'),
(4,'Pagamento em análise','Recebemos o código do seu pagamento. Sua vaga continua reservada.','minhas_reservas.php');
-- Migrações já incluídas neste arquivo (instalação nova não precisa rodá-las)
INSERT INTO migracoes (arquivo) VALUES ('001_cargos_verificacao_notificacoes_compartilhar.sql');
