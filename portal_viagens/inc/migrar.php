<?php
/** Migrações de banco: aplica database/migrations/*.sql pendentes e registra em `migracoes`. Erros de "já existe" são ignorados (idempotente). */
const MIGRACAO_IGNORAR = [1050, 1060, 1061, 1062, 1091];   // tabela/coluna/chave já existe, duplicata, chave inexistente
function migracoesGarantirTabela(PDO $c) { $c->exec('CREATE TABLE IF NOT EXISTS migracoes (arquivo VARCHAR(150) PRIMARY KEY, aplicada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP)'); }
function migracoesStatus(PDO $c, $dir) {
    migracoesGarantirTabela($c);
    $feitas = $c->query('SELECT arquivo, aplicada_em FROM migracoes')->fetchAll(PDO::FETCH_KEY_PAIR);
    $arqs = glob(rtrim($dir, '/\\') . '/*.sql') ?: []; sort($arqs); $l = [];
    foreach ($arqs as $a) { $n = basename($a); $l[] = ['arquivo' => $n, 'aplicada_em' => $feitas[$n] ?? null]; }
    return $l;
}
/** Retorna ['arquivo' => ['ok'=>true] | ['erro'=>msg]]; para na primeira que falhar. */
function aplicarMigracoes(PDO $c, $dir) {
    $res = [];
    foreach (migracoesStatus($c, $dir) as $m) {
        if ($m['aplicada_em']) continue;
        foreach (sqlDividir(file_get_contents(rtrim($dir, '/\\') . '/' . $m['arquivo'])) as $sql) {
            try { $c->exec($sql); }
            catch (PDOException $e) {
                if (!in_array((int)($e->errorInfo[1] ?? 0), MIGRACAO_IGNORAR, true)) { $res[$m['arquivo']] = ['erro' => $e->getMessage()]; return $res; }
            }
        }
        $c->prepare('INSERT INTO migracoes (arquivo) VALUES (?)')->execute([$m['arquivo']]); $res[$m['arquivo']] = ['ok' => true];
    }
    return $res;
}
