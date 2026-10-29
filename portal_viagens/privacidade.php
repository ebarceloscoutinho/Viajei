<?php
require_once __DIR__ . '/inc/app.php';
$GLOBALS['descricao'] = 'Como a ' . SITE_NOME . ' coleta, usa e protege seus dados pessoais (LGPD).';
topo('Política de Privacidade'); $mail = e(config('suporte_email'));
?>
<div class="row justify-content-center"><div class="col-lg-8">
<div class="alert alert-warning small"><strong>Modelo de partida.</strong> Este texto foi escrito como base para o projeto e <u>deve ser revisado por um advogado</u> e adaptado à realidade da empresa antes de uso comercial.</div>
<h1 class="h3">Política de Privacidade</h1><p class="text-body-secondary">Atualizada em <?= date('d/m/Y') ?>.</p>
<h2 class="h5 mt-4">1. Quem somos</h2><p><?= e(SITE_NOME) ?> é a controladora dos dados tratados neste site. Contato do encarregado/privacidade: <a href="mailto:<?= $mail ?>"><?= $mail ?></a>.</p>
<h2 class="h5 mt-4">2. Dados que coletamos e para quê</h2>
<ul><li><strong>Cadastro:</strong> nome, e-mail, telefone (opcional) e senha (guardada apenas em forma criptografada) — para criar e proteger sua conta.</li>
<li><strong>Reservas e pagamentos:</strong> viagem escolhida, quantidade de pessoas, valores, código de transação informado — para executar o contrato, confirmar pagamentos e emitir voucher.</li>
<li><strong>Suporte:</strong> mensagens e respostas — para atendê-lo.</li>
<li><strong>Avaliações, favoritos e roteiros:</strong> conteúdo que você cria no site.</li>
<li><strong>Segurança:</strong> registros de acesso (e-mail, IP e horário) — para prevenir fraudes e abusos.</li></ul>
<h2 class="h5 mt-4">3. Bases legais (LGPD, art. 7º)</h2><p>Execução de contrato (reservas e pagamentos), cumprimento de obrigação legal (registros fiscais e financeiros), legítimo interesse (segurança e prevenção a fraudes) e consentimento (aceite destes termos no cadastro).</p>
<h2 class="h5 mt-4">4. Com quem compartilhamos</h2><p>O guia responsável pela sua saída vê apenas nome, quantidade de pessoas e telefone dos passageiros confirmados, para operar a viagem. Provedores de hospedagem e de e-mail processam dados em nosso nome. Não vendemos seus dados.</p>
<h2 class="h5 mt-4">5. Cookies e armazenamento local</h2><p>Usamos apenas o cookie de sessão (necessário para login e segurança) e o armazenamento do navegador para guardar suas preferências de tema. Não usamos cookies de publicidade ou rastreamento. Vídeos do YouTube são incorporados no modo de privacidade aprimorado (youtube-nocookie.com), mas o YouTube pode tratar dados ao reproduzi-los.</p>
<h2 class="h5 mt-4">6. Por quanto tempo guardamos</h2><p>Enquanto a conta existir e pelo prazo exigido por lei para dados fiscais e financeiros. Registros de acesso são mantidos pelo período mínimo legal.</p>
<h2 class="h5 mt-4">7. Seus direitos (art. 18)</h2><p>Você pode confirmar a existência de tratamento, acessar, corrigir, portar e pedir a exclusão dos seus dados, revogar consentimento e obter informações sobre compartilhamento. Em <a href="minha_conta.php">Minha conta</a> você baixa seus dados e pede a exclusão; também pode escrever para <a href="mailto:<?= $mail ?>"><?= $mail ?></a>. Você ainda pode reclamar à ANPD.</p>
<h2 class="h5 mt-4">8. Segurança</h2><p>Adotamos senhas com hash, proteção contra CSRF, verificação em duas etapas para administradores, controle de acesso por área e backups. Nenhum sistema é totalmente imune; em caso de incidente relevante, comunicaremos os titulares e a ANPD conforme a lei.</p>
</div></div>
<?php rodape();
