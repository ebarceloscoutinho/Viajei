# Roteira — Monte, reserve, viaje.

Site de viagens em **PHP 7.4+ / MySQL (PDO) / Bootstrap 5**, mobile-first e instalável (PWA).
> "Roteira" é um nome sugerido: pesquise marca (INPI) e domínio antes de usar. Para renomear, edite `SITE_NOME`/`SITE_SLOGAN` em `inc/app.php`.

## Como rodar (XAMPP/Laragon)
1. Copie `portal_viagens` para `htdocs`.
2. Importe `database/portal_viagens.sql` no phpMyAdmin (**recria o banco**, apagando o `portal_viagens` anterior).
3. Ajuste usuário/senha em `inc/conexao.php`.
4. Site: `http://localhost/portal_viagens/` · Admin: `/admin/login.php` · Guia: `/guia/login.php`
5. Dê permissão de escrita às pastas `uploads/passeios` e `uploads/guias`.
6. (Opcional) Rode os testes: `php tests/run.php`

Senha de todos os usuários de exemplo: `viagem123` — viajante `viajante@teste.com`; admin `admin@portalviagens.com`; cargos do painel `financeiro@`, `atendimento@` e `catalogo@portalviagens.com`; guias `marina@` e `rafael@portalviagens.com`. Cupons de exemplo: `BEMVINDO10` e `VERAO100`.
Para testar avaliações logo após reservar: `AVALIACAO_EXIGE_VIAGEM_REALIZADA = false` em `inc/app.php` (o viajante de teste já tem 2 viagens realizadas para avaliar).

## Configurações (Admin → Configurações)
Só o cargo Administrador. Nome e slogan do site, tema padrão, aviso no topo, prazos e taxas de reserva, dados do PIX, cadastro aberto/fechado, envio de e-mails e **modo manutenção**; inclui o checklist "Antes de publicar". Os valores ficam na tabela `configuracoes` (chaves `sys_*`) e, sem valor salvo, valem os padrões de `inc/ajustes.php`. Para criar um ajuste novo, acrescente uma linha em `ajustesSchema()` e leia com `ajuste('sys_minha_chave')`. Não precisa de migração: a tabela já existe.

## Capas ilustradas
Cada ponto turístico é um SVG em `inc/motivos.php`; as cores dele ficam em `inc/paletas.php` (principal, detalhes, traços, tronco e chão: grama/areia/mar/neve). Para criar um novo, adicione a ilustração em `motivos.php` (classes `d` = detalhe, `s` = traço, `t` = tronco) e uma linha com a mesma chave em `paletas.php`.
O CSS é carregado com `?v=<data do arquivo>` e o service worker busca CSS/JS na rede primeiro, então mudanças de visual aparecem sem precisar limpar o cache.

## Três áreas separadas (login e sessão próprios)
**Site** (viajantes) · **Admin** (`/admin`, com 2FA opcional e QR Code) · **Guia** (`/guia`: próximas saídas, passageiros confirmados, avaliações e perfil).

**Cargos do painel admin:** Administrador (tudo) · Financeiro (pagamentos, financeiro, preços, cupons, reservas, salários) · Atendimento (suporte, reservas, avaliações, e-mails) · Catálogo (viagens, guias sem salário, preços, cupons, câmbio, avaliações). A matriz fica em `inc/admin.php` (`PERM`).

## Recursos
- Viagens nacionais (42) e internacionais (72 países), capas ilustradas com ponto turístico + emojis (ou foto real enviada pelo admin), vídeo do YouTube incorporado, guia responsável, avaliações, favoritos, cupons.
- **Pagamento:** vaga garantida por 24h → cliente paga por PIX (copia e cola com valor exato) ou transferência → informa o código → admin confirma → voucher imprimível. Sem pagamento: reserva expira e bloqueia novas reservas por 72h.
- **E-mails automáticos** (fila `emails_fila`): boas-vindas, reserva criada, pagamento confirmado/recusado, reserva expirada, vaga liberada, resposta do suporte, redefinição de senha. Se o servidor não tem e-mail configurado, ficam como "falhou" em Admin → E-mails e podem ser reenviados.
- **Quiz "Quanto custa a viagem?"** com câmbio; cotações atualizáveis pela internet (Admin → Câmbio) ou manualmente.
- **Admin:** dashboard, pagamentos (com notificações), financeiro (lucro bruto/líquido, gráficos, CSV), preços, viagens (imagem/vídeo/guia), guias (foto, salário, locais, criação de acesso), reservas (CSV), avaliações, suporte, cupons, usuários, câmbio, e-mails, segurança (2FA e senha).
- **Conta do viajante:** dados, senha, **confirmação de e-mail (necessária para reservar)**, baixar meus dados (LGPD), pedir exclusão, **notificações dentro do site** (🔔), comparador de viagens, roteiro com **link de compartilhamento**, botão "Ver no mapa".
- **QR Code** gerado em PHP puro (sem bibliotecas) no PIX, no voucher e na configuração do 2FA.
- **Webhook de pagamento** assinado (HMAC) para confirmar PIX automaticamente via gateway/automação — veja `docs/integracao_pagamento.md`.
- **Migrações** de banco (`database/migrations`, Admin → Migrações) e **backup** do banco em um clique (Admin → Backup).
- **Qualidade:** tema individual salvo na conta, PWA, SEO básico (meta description, sitemap, robots, 404), índices no banco, paginação, redimensionamento de imagens, cabeçalhos de segurança.

## Para colocar no ar
- HTTPS; `MODO_DEMO_EMAIL = false`; troque as senhas de exemplo, a chave PIX e os dados bancários (`inc/app.php`); configure e-mail (SMTP/sendmail) e `site_url` (Admin → Suporte → Contatos).
- Agende `cron/expirar_reservas.php` (a cada 10 min) e, se quiser, `cron/atualizar_cambio.php` (diário).
- As pastas `inc`, `database`, `docs`, `tests`, `cron` têm `.htaccess` que bloqueia acesso pelo navegador (Apache). Em Nginx, bloqueie-as na configuração.
- Revise com um advogado `termos.php` e `privacidade.php` (são modelos).

## Atualizando uma instalação anterior sem perder dados
Faça backup (Admin → Backup) e aplique as migrações pendentes em Admin → Migrações (ou `php cron/migrar.php`).

## Limites conhecidos
Sem integração pronta com um gateway específico (o webhook é o ponto de ligação; cada gateway exige conta e adaptador), sem notificações push no celular, sem edição colaborativa de roteiros em tempo real. Preços, cotações, custos, salários, contatos e vistos são dados de exemplo.
Documentação: `docs/banco_de_dados.md` e `docs/analise_concorrentes.md`.
