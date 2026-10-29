# Migrações

Para **evoluir o banco sem perder dados**, coloque aqui arquivos `NNN_descricao.sql` (numere em ordem). O sistema aplica os pendentes em
**Admin → Migrações** (ou `php cron/migrar.php`) e registra cada um na tabela `migracoes`.

- Cada arquivo roda instrução por instrução; erros de "já existe" (coluna, chave, tabela) são ignorados, então os arquivos podem ser re-executados.
- Se uma instrução falha por outro motivo, a migração para e **não** é registrada (corrija e rode de novo).
- DDL (CREATE/ALTER) não é transacional no MySQL: **faça backup antes** (Admin → Backup).
- Instalação nova usa `portal_viagens.sql` (já inclui tudo e registra as migrações existentes).
