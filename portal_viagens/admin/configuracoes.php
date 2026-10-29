<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
exigirAdmin('configuracoes');
$schema = ajustesSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar();
    $g = $_POST['grupo'] ?? ''; $acao = $_POST['acao'] ?? 'salvar';
    if (isset($schema[$g])) {
        if ($acao === 'padrao') {   // volta a seção inteira ao padrão de fábrica
            $d = $conn->prepare('DELETE FROM configuracoes WHERE chave=?');
            foreach (array_keys($schema[$g][3]) as $k) $d->execute([$k]);
            flash('"' . $schema[$g][1] . '" voltou aos valores padrão.');
        } else {
            $u = $conn->prepare('INSERT INTO configuracoes (chave,valor,rotulo) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)');
            $ajustados = [];
            foreach ($schema[$g][3] as $k => $d) {
                $bruto = $_POST['cfg'][$k] ?? ''; $limpo = ajusteLimpar($d, $bruto);
                if ($d['tipo'] === 'numero' && (!is_numeric(str_replace(',', '.', (string)$bruto)) || (float)str_replace(',', '.', (string)$bruto) != (float)$limpo)) $ajustados[] = $d['rotulo'] . ' → ' . $limpo . ' ' . $d['sufixo'];
                $u->execute([$k, $limpo, mb_substr($d['rotulo'], 0, 80)]);
            }
            flash('"' . $schema[$g][1] . '" salvo. As mudanças já valem no site.' . ($ajustados ? ' Ajustei ao limite permitido: ' . implode('; ', $ajustados) . '.' : ''), $ajustados ? 'warning' : 'success');
        }
    }
    redirecionar('configuracoes.php#' . urlencode($g));
}

/** Um campo do formulário, conforme o tipo definido em inc/ajustes.php. */
function campoAjuste($k, $d) {
    $v = ajuste($k); $id = 'c-' . $k; $h = !empty($d['ajuda']) ? '<div class="form-text">' . e($d['ajuda']) . '</div>' : '';
    if ($d['tipo'] === 'chave')
        return '<div class="form-check form-switch"><input type="hidden" name="cfg[' . e($k) . ']" value="0"><input class="form-check-input" type="checkbox" role="switch" id="' . e($id) . '" name="cfg[' . e($k) . ']" value="1"' . ($v === '1' ? ' checked' : '') . '>'
             . '<label class="form-check-label fw-semibold" for="' . e($id) . '">' . e($d['rotulo']) . '</label>' . $h . '</div>';
    $lab = '<label class="form-label small fw-semibold mb-1" for="' . e($id) . '">' . e($d['rotulo']) . '</label>';
    if ($d['tipo'] === 'escolha') {
        $o = ''; foreach ($d['opcoes'] as $ov => $ot) $o .= '<option value="' . e($ov) . '"' . ($ov === $v ? ' selected' : '') . '>' . e($ot) . '</option>';
        return '<div>' . $lab . '<select class="form-select" id="' . e($id) . '" name="cfg[' . e($k) . ']">' . $o . '</select>' . $h . '</div>';
    }
    if ($d['tipo'] === 'numero')
        return '<div>' . $lab . '<div class="input-group"><input class="form-control" type="number" inputmode="decimal" id="' . e($id) . '" name="cfg[' . e($k) . ']" value="' . e($v) . '" min="' . $d['min'] . '" max="' . $d['max'] . '" step="any">'
             . '<span class="input-group-text">' . e($d['sufixo']) . '</span></div>' . $h . '</div>';
    return '<div>' . $lab . '<input class="form-control" id="' . e($id) . '" name="cfg[' . e($k) . ']" value="' . e($v) . '" maxlength="' . (int)($d['max'] ?? 255) . '">' . $h . '</div>';
}

// "Antes de publicar": verificações rápidas de itens que costumam ficar com valor de exemplo
$padrao = 0;
foreach ($conn->query('SELECT senha FROM usuarios WHERE tipo_user >= 2 LIMIT 40') as $x) if (password_verify('viagem123', $x['senha'])) $padrao++;
$verif = [
    [$padrao === 0, $padrao === 0 ? 'Nenhuma conta da equipe usa a senha de exemplo.' : $padrao . ' conta(s) da equipe ainda usam a senha de exemplo (viagem123).', $padrao ? ['usuarios.php', 'Trocar senhas'] : null],
    [!MODO_DEMO_EMAIL, !MODO_DEMO_EMAIL ? 'Modo demonstração desligado.' : 'Modo demonstração ligado: o link de redefinir senha aparece na tela. Desligue em produção (MODO_DEMO_EMAIL em inc/app.php).', null],
    [stripos(PIX_CHAVE, 'exemplo') === false, stripos(PIX_CHAVE, 'exemplo') === false ? 'Chave PIX personalizada.' : 'A chave PIX ainda é a de exemplo. Edite em "Dados de pagamento".', null],
    [!in_array(config('suporte_email'), ['', 'seuemail@gmail.com'], true), !in_array(config('suporte_email'), ['', 'seuemail@gmail.com'], true) ? 'E-mail de suporte personalizado.' : 'O e-mail de suporte ainda é o de exemplo.', in_array(config('suporte_email'), ['', 'seuemail@gmail.com'], true) ? ['suporte.php?aba=contatos', 'Editar contatos'] : null],
    [EMAIL_ATIVO, EMAIL_ATIVO ? 'Envio de e-mails ligado.' : 'Envio de e-mails desligado: as mensagens ficam só na fila.', null],
    [!empty($_SERVER['HTTPS']), !empty($_SERVER['HTTPS']) ? 'Site acessado por HTTPS.' : 'Site em HTTP. Em produção use HTTPS (certificado grátis na hospedagem).', null],
];
$okN = count(array_filter($verif, fn($v) => $v[0]));
admTopo('Configurações', 'configuracoes');
?>
<div class="d-flex flex-wrap align-items-end gap-3 mb-4">
  <div class="me-auto"><h1 class="h3 mb-1">Configurações do site</h1><div class="text-body-secondary">Ajuste o site sem mexer em código. Cada seção tem o seu botão Salvar.</div></div>
  <?php if (ajuste('sys_manutencao') === '1'): ?><span class="badge text-bg-warning fs-6">🚧 Modo manutenção ligado</span><?php endif; ?>
</div>

<div class="row g-4">
  <div class="col-12">
    <div class="card card-body">
      <div class="d-flex align-items-center gap-2 mb-1"><span class="cfg-ico">✅</span><h2 class="h5 mb-0">Antes de publicar</h2><span class="badge <?= $okN === count($verif) ? 'text-bg-success' : 'text-bg-warning' ?> ms-auto"><?= $okN ?> de <?= count($verif) ?> ok</span></div>
      <ul class="list-unstyled cfg-check mb-0 mt-2">
        <?php foreach ($verif as [$ok, $txt, $lnk]): ?><li><span><?= $ok ? '✅' : '⚠️' ?></span><span class="flex-grow-1"><?= e($txt) ?></span><?php if ($lnk): ?><a class="small text-nowrap" href="<?= e($lnk[0]) ?>"><?= e($lnk[1]) ?> →</a><?php endif; ?></li><?php endforeach; ?>
      </ul>
    </div>
  </div>

  <?php foreach ($schema as $g => [$ico, $titulo, $desc, $campos]): ?>
  <div class="col-xl-6" id="<?= e($g) ?>">
    <form method="post" class="card card-body cfg-card h-100 vstack gap-3">
      <?= csrfCampo() ?><input type="hidden" name="grupo" value="<?= e($g) ?>">
      <div class="d-flex gap-2 align-items-start"><span class="cfg-ico"><?= $ico ?></span><div><h2 class="h5 mb-0"><?= e($titulo) ?></h2><div class="small text-body-secondary"><?= e($desc) ?></div></div></div>
      <?php foreach ($campos as $k => $d) echo campoAjuste($k, $d); ?>
      <div class="d-flex gap-2 align-items-center mt-auto pt-2">
        <button class="btn btn-primary" name="acao" value="salvar">Salvar</button>
        <button class="btn btn-link btn-sm text-body-secondary ms-auto" name="acao" value="padrao" formnovalidate onclick="return confirm('Voltar esta seção aos valores padrão?')">Restaurar padrão</button>
      </div>
    </form>
  </div>
  <?php endforeach; ?>

  <div class="col-12">
    <div class="card card-body">
      <h2 class="h5 mb-1">Outras configurações</h2><div class="small text-body-secondary mb-3">Ajustes que têm tela própria.</div>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ([['suporte', 'suporte.php?aba=contatos', '📞', 'Contatos e horário do suporte'], ['cambio', 'cambio.php', '💱', 'Câmbio e custos'], ['emails', 'emails.php', '✉️', 'E-mails enviados'], ['usuarios', 'usuarios.php', '👥', 'Usuários e cargos'], ['seguranca', 'seguranca.php', '🔐', 'Segurança e 2FA'], ['backup', 'backup.php', '💾', 'Backup']] as [$perm, $url, $i, $t])
          if (adminPode($perm)) echo '<a class="chip" href="' . $url . '">' . $i . ' ' . $t . '</a>'; ?>
      </div>
    </div>
  </div>
</div>
<?php admRodape(); ?>
