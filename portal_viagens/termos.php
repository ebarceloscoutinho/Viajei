<?php
require_once __DIR__ . '/inc/app.php';
$GLOBALS['descricao'] = 'Termos de uso, reservas, pagamentos e cancelamento da ' . SITE_NOME . '.';
topo('Termos de uso'); $mail = e(config('suporte_email'));
?>
<div class="row justify-content-center"><div class="col-lg-8">
<div class="alert alert-warning small"><strong>Modelo de partida.</strong> Revise com um advogado antes de usar comercialmente (em especial cancelamento, reembolso e responsabilidade por terceiros).</div>
<h1 class="h3">Termos de uso</h1><p class="text-body-secondary">Atualizados em <?= date('d/m/Y') ?>.</p>
<h2 class="h5 mt-4">1. O serviço</h2><p><?= e(SITE_NOME) ?> divulga e vende passeios e pacotes terrestres de viagem, oferece montagem de roteiros e ferramentas de planejamento. Passagens aéreas não estão incluídas nos pacotes internacionais, salvo indicação expressa.</p>
<h2 class="h5 mt-4">2. Conta</h2><p>Você deve fornecer dados verdadeiros e manter sua senha em sigilo. Podemos suspender contas usadas para fraude ou abuso.</p>
<h2 class="h5 mt-4">3. Reserva e pagamento</h2><p>Ao reservar, a vaga fica garantida por <?= PRAZO_PAGAMENTO_HORAS ?> horas. O pagamento é feito por PIX ou transferência e a reserva só é confirmada após a conferência pela equipe. Sem pagamento nem cancelamento no prazo, a reserva expira, a vaga é liberada e novas reservas ficam bloqueadas por <?= BLOQUEIO_HORAS ?> horas. Cupons têm validade e limite de uso e não são acumulativos.</p>
<h2 class="h5 mt-4">4. Cancelamento e arrependimento</h2><p>Compras feitas pela internet têm direito de arrependimento de <?= DIAS_ARREPENDIMENTO ?> dias contados da compra (art. 49 do Código de Defesa do Consumidor), com reembolso integral. Fora desse prazo, o cancelamento é gratuito até <?= DIAS_SEM_TAXA ?> dias antes da saída; depois, cobramos <?= (int)(TAXA_CANCELAMENTO * 100) ?>% do valor pago. Reembolsos são feitos pelo mesmo meio de pagamento, após análise da equipe.</p>
<h2 class="h5 mt-4">5. Alterações e cancelamento pela agência</h2><p>Se uma saída for cancelada ou alterada de forma relevante por nós, você poderá escolher nova data, crédito ou reembolso integral. Eventos fora do nosso controle (clima, greves, fechamentos de atrativos) podem exigir ajustes no roteiro.</p>
<h2 class="h5 mt-4">6. Documentos e saúde</h2><p>Passaporte, vistos, vacinas e seguro-viagem são responsabilidade do viajante. As informações de documentação exibidas no site são estimativas de referência e <u>não substituem</u> a consulta aos órgãos oficiais.</p>
<h2 class="h5 mt-4">7. Avaliações e conteúdo</h2><p>Avaliações só podem ser feitas por quem realizou a viagem e devem ser respeitosas; podemos ocultar conteúdo ofensivo ou ilegal. Textos, marcas e ilustrações do site pertencem à <?= e(SITE_NOME) ?> ou a seus licenciantes.</p>
<h2 class="h5 mt-4">8. Responsabilidade</h2><p>Atuamos com diligência, mas não respondemos por danos decorrentes de serviços de terceiros fora do pacote contratado nem por informações de terceiros (como vídeos incorporados). Nada aqui limita direitos garantidos por lei ao consumidor.</p>
<h2 class="h5 mt-4">9. Contato e foro</h2><p>Dúvidas: <a href="mailto:<?= $mail ?>"><?= $mail ?></a>. Fica eleito o foro do domicílio do consumidor.</p>
</div></div>
<?php rodape();
