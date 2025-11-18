<?php
// admin/novagrafico.php
require_once __DIR__ . '/../api/functions.php';

require_login();
$pdo = db();
$lang = get_current_language($pdo); // se quiser usar depois

// CSRFs para cada formulário
$csrfGrafico = dso_csrf_ensure('csrf_grafico');
$csrfJoin = dso_csrf_ensure('csrf_join');
$csrfModel = dso_csrf_ensure('csrf_model');

// Descobre banco atual e tabelas do cliente
$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
$tables = [];
if ($dbName) {
  $st = $pdo->prepare("
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = :db
        ORDER BY TABLE_NAME
    ");
  $st->execute([':db' => $dbName]);
  $tables = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'TABLE_NAME');
}

// Trata POSTs dos 3 forms
// Trata POSTs dos 3 forms (métrica, join, model)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';

    switch ($form) {
        case 'grafico':
            dso_handle_grafico_save($pdo);
            break;

        case 'delete_grafico':
            dso_handle_grafico_delete($pdo);
            break;

        case 'join':
            dso_handle_join_save($pdo);
            break;

        case 'delete_join':
            dso_handle_join_delete($pdo);
            break;

        case 'model':
            dso_handle_model_save($pdo);
            break;

        case 'delete_model':
            dso_handle_model_delete($pdo);
            break;

        default:
            // form desconhecido -> só volta com erro genérico
            push_toast('danger', 'Erro', 'Ação inválida no formulário.', 3500);
            dso_redirect_novagrafico();
    }
}

/*
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $form = $_POST['form'] ?? 'grafico';

  // ---------- FORM MÉTRICA (dso_grafico) ----------
  if ($form === 'grafico') {
    if (!isset($_POST['_csrf']) || !dso_csrf_check($_POST['_csrf'], 'csrf_grafico')) {
      push_toast('danger', 'Erro', 'Token CSRF inválido. Atualize a página e tente novamente.', 3500);
      header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
      exit;
    }

    $id = (int) ($_POST['id'] ?? 0); // 0 = novo, >0 = edição
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $descricao = trim((string) ($_POST['descricao'] ?? ''));
    $tabela = trim((string) ($_POST['tabela'] ?? ''));
    $agregacao = (string) ($_POST['agregacao'] ?? 'COUNT');
    $filtro = trim((string) ($_POST['filtro_where'] ?? ''));
    $gruposArray = $_POST['colunas_grupo'] ?? [];
    $colunaValor = trim((string) ($_POST['coluna_valor'] ?? ''));

    if ($nome === '' || $tabela === '') {
      push_toast('danger', 'Campos obrigatórios', 'Preencha pelo menos Nome e Tabela.', 3500);
      header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
      exit;
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $tabela)) {
      push_toast('danger', 'Tabela inválida', 'Nome de tabela contém caracteres não permitidos.', 3500);
      header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
      exit;
    }

    // Colunas de agrupamento
    $coluna_grupo = null;
    if (!empty($gruposArray) && is_array($gruposArray)) {
      $limpas = [];
      foreach ($gruposArray as $g) {
        $g = trim((string) $g);
        if ($g === '')
          continue;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $g)) {
          push_toast('danger', 'Coluna inválida', 'Uma das colunas de agrupamento é inválida.', 3500);
          header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
          exit;
        }
        $limpas[] = $g;
      }
      if ($limpas) {
        $coluna_grupo = implode(',', $limpas);
      }
    }

    // Coluna de valor
    $coluna_valor = null;
    if ($colunaValor !== '') {
      if (!preg_match('/^[A-Za-z0-9_ ]+$/', $colunaValor)) {
        push_toast('danger', 'Expressão inválida', 'Coluna de valor contém caracteres não permitidos.', 3500);
        header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
        exit;
      }
      $coluna_valor = $colunaValor;
    }

    $filtro_where = $filtro !== '' ? $filtro : null;

    try {
      if ($id > 0) {
        // UPDATE
        $sql = "
                    UPDATE dso_grafico
                       SET nome = :nome,
                           descricao = :descricao,
                           tabela = :tabela,
                           coluna_grupo = :coluna_grupo,
                           coluna_valor = :coluna_valor,
                           agregacao = :agregacao,
                           filtro_where = :filtro_where
                     WHERE id = :id
                ";
        $st = $pdo->prepare($sql);
        $st->execute([
          ':id' => $id,
          ':nome' => $nome,
          ':descricao' => $descricao !== '' ? $descricao : null,
          ':tabela' => $tabela,
          ':coluna_grupo' => $coluna_grupo,
          ':coluna_valor' => $coluna_valor,
          ':agregacao' => $agregacao,
          ':filtro_where' => $filtro_where,
        ]);
        push_toast('success', 'Métrica atualizada', 'Configuração de métrica atualizada com sucesso.', 3000);
      } else {
        // INSERT
        $sql = "
                    INSERT INTO dso_grafico
                    (nome, descricao, tabela, coluna_grupo, coluna_valor, agregacao, filtro_where, ativo)
                    VALUES
                    (:nome, :descricao, :tabela, :coluna_grupo, :coluna_valor, :agregacao, :filtro_where, 1)
                ";
        $st = $pdo->prepare($sql);
        $st->execute([
          ':nome' => $nome,
          ':descricao' => $descricao !== '' ? $descricao : null,
          ':tabela' => $tabela,
          ':coluna_grupo' => $coluna_grupo,
          ':coluna_valor' => $coluna_valor,
          ':agregacao' => $agregacao,
          ':filtro_where' => $filtro_where,
        ]);
        push_toast('success', 'Métrica criada', 'Configuração de métrica salva com sucesso.', 3000);
      }
    } catch (Throwable $e) {
      push_toast('danger', 'Erro ao salvar', $e->getMessage(), 4000);
    }

    header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
    exit;
  }

  // ---------- DELETE MÉTRICA ----------
  if ($form === 'delete_grafico') {
    if (!isset($_POST['_csrf']) || !dso_csrf_check($_POST['_csrf'], 'csrf_grafico')) {
      push_toast('danger', 'Erro', 'Token CSRF inválido.', 3500);
      header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
      exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
      try {
        $st = $pdo->prepare("DELETE FROM dso_grafico WHERE id = :id");
        $st->execute([':id' => $id]);
        // modelos/join devem estar em CASCADE; se não, vai dar erro aqui
        push_toast('success', 'Métrica removida', 'Métrica e modelos associados foram removidos.', 3000);
      } catch (Throwable $e) {
        push_toast('danger', 'Erro ao excluir métrica', $e->getMessage(), 4000);
      }
    }
    header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
    exit;
  }

  // ---------- FORM JOIN (dso_grafico_join) ----------
  if ($form === 'join') {
    if (!isset($_POST['_csrf']) || !dso_csrf_check($_POST['_csrf'], 'csrf_join')) {
      push_toast('danger', 'Erro', 'Token CSRF inválido.', 3500);
      header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
      exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    $grafico_id = (int) ($_POST['grafico_id'] ?? 0);
    $tabela = trim((string) ($_POST['tabela_join'] ?? ''));
    $alias = trim((string) ($_POST['alias'] ?? ''));
    $tipo_join = (string) ($_POST['tipo_join'] ?? 'INNER');
    $on_expr = trim((string) ($_POST['on_expr'] ?? ''));

    if ($grafico_id <= 0 || $tabela === '' || $alias === '' || $on_expr === '') {
      push_toast('danger', 'Campos obrigatórios', 'Preencha todos os campos do JOIN.', 3500);
      header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
      exit;
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $tabela)) {
      push_toast('danger', 'Tabela inválida', 'Nome de tabela de JOIN inválido.', 3500);
      header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
      exit;
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $alias)) {
      push_toast('danger', 'Alias inválido', 'Alias deve conter apenas letras, números e _.', 3500);
      header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
      exit;
    }

    $tipo_ok = ['INNER', 'LEFT', 'RIGHT'];
    if (!in_array($tipo_join, $tipo_ok, true)) {
      $tipo_join = 'INNER';
    }

    try {
      if ($id > 0) {
        $sql = "
                    UPDATE dso_grafico_join
                       SET grafico_id = :grafico_id,
                           tabela     = :tabela,
                           alias      = :alias,
                           tipo_join  = :tipo_join,
                           on_expr    = :on_expr
                     WHERE id = :id
                ";
        $st = $pdo->prepare($sql);
        $st->execute([
          ':id' => $id,
          ':grafico_id' => $grafico_id,
          ':tabela' => $tabela,
          ':alias' => $alias,
          ':tipo_join' => $tipo_join,
          ':on_expr' => $on_expr,
        ]);
        push_toast('success', 'JOIN atualizado', 'JOIN atualizado com sucesso.', 3000);
      } else {
        $sql = "
                    INSERT INTO dso_grafico_join
                    (grafico_id, tabela, alias, tipo_join, on_expr, ativo)
                    VALUES
                    (:grafico_id, :tabela, :alias, :tipo_join, :on_expr, 1)
                ";
        $st = $pdo->prepare($sql);
        $st->execute([
          ':grafico_id' => $grafico_id,
          ':tabela' => $tabela,
          ':alias' => $alias,
          ':tipo_join' => $tipo_join,
          ':on_expr' => $on_expr,
        ]);
        push_toast('success', 'JOIN criado', 'JOIN adicionado à métrica.', 3000);
      }
    } catch (Throwable $e) {
      push_toast('danger', 'Erro ao salvar JOIN', $e->getMessage(), 4000);
    }

    header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
    exit;
  }

  // ---------- DELETE JOIN ----------
  if ($form === 'delete_join') {
    if (!isset($_POST['_csrf']) || !dso_csrf_check($_POST['_csrf'], 'csrf_join')) {
      push_toast('danger', 'Erro', 'Token CSRF inválido.', 3500);
      header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
      exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
      try {
        $st = $pdo->prepare("DELETE FROM dso_grafico_join WHERE id = :id");
        $st->execute([':id' => $id]);
        push_toast('success', 'JOIN removido', 'JOIN removido com sucesso.', 3000);
      } catch (Throwable $e) {
        push_toast('danger', 'Erro ao excluir JOIN', $e->getMessage(), 4000);
      }
    }
    header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
    exit;
  }

  // ---------- FORM MODEL (dso_model_grafico) ----------
  if ($form === 'model') {
    if (!isset($_POST['_csrf']) || !dso_csrf_check($_POST['_csrf'], 'csrf_model')) {
      push_toast('danger', 'Erro', 'Token CSRF inválido.', 3500);
      header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
      exit;
    }

    $grafico_id = (int) ($_POST['grafico_id'] ?? 0);
    $nome_model = trim((string) ($_POST['nome_model'] ?? ''));
    $descricao_model = trim((string) ($_POST['descricao_model'] ?? ''));
    $icone = trim((string) ($_POST['icone'] ?? ''));
    $info = (string) ($_POST['info'] ?? 'INFO');
    $tipo = (string) ($_POST['tipo'] ?? 'BARRA');
    $exibicao = (string) ($_POST['exibicao'] ?? 'CARD');
    $cor_principal = trim((string) ($_POST['cor_principal'] ?? ''));
    $cor_secundaria = trim((string) ($_POST['cor_secundaria'] ?? ''));
    $ordem = (int) ($_POST['ordem'] ?? 1);

    if ($grafico_id <= 0 || $nome_model === '') {
      push_toast('danger', 'Campos obrigatórios', 'Selecione a métrica e informe o nome do modelo.', 3500);
      header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
      exit;
    }

    // tipos válidos agora
    $tipos_ok = ['KPI-1', 'KPI-2', 'BARRA', 'LINHA', 'DONUT', 'AREA'];
    if (!in_array($tipo, $tipos_ok, true)) {
      $tipo = 'BARRA';
    }

    // info / cor de fundo do KPI-1 (ou só metadata pro dashboard)
    $infos_ok = ['INFO', 'ALERT', 'SUCCESS', 'DANGER'];
    if (!in_array($info, $infos_ok, true)) {
      $info = 'INFO';
    }

    $exib_ok = ['CARD', 'FULL', 'GRANDE', 'MEDIO', 'PEQUENO', 'MINI', 'MICRO'];
    if (!in_array($exibicao, $exib_ok, true)) {
      $exibicao = 'CARD';
    }

    if ($ordem <= 0)
      $ordem = 1;

    try {
      $sql = "
            INSERT INTO dso_model_grafico
            (nome, descricao, grafico_id, icone, info, tipo,
             cor_principal, cor_secundaria, exibicao, ordem, ativo)
            VALUES
            (:nome, :descricao, :grafico_id, :icone, :info, :tipo,
             :cor_principal, :cor_secundaria, :exibicao, :ordem, 1)
        ";
      $st = $pdo->prepare($sql);
      $st->execute([
        ':nome' => $nome_model,
        ':descricao' => $descricao_model !== '' ? $descricao_model : null,
        ':grafico_id' => $grafico_id,
        ':icone' => $icone !== '' ? $icone : null,
        ':info' => $info,
        ':tipo' => $tipo,
        ':cor_principal' => $cor_principal !== '' ? $cor_principal : null,
        ':cor_secundaria' => $cor_secundaria !== '' ? $cor_secundaria : null,
        ':exibicao' => $exibicao,
        ':ordem' => $ordem,
      ]);

      push_toast('success', 'Modelo criado', 'Modelo de gráfico/KPI salvo com sucesso.', 3000);
    } catch (Throwable $e) {
      push_toast('danger', 'Erro ao salvar modelo', $e->getMessage(), 4000);
    }

    header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
    exit;
  }
  // ---------- DELETE MODEL ----------
  if ($form === 'delete_model') {
    if (!isset($_POST['_csrf']) || !dso_csrf_check($_POST['_csrf'], 'csrf_model')) {
      push_toast('danger', 'Erro', 'Token CSRF inválido.', 3500);
      header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
      exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
      try {
        $st = $pdo->prepare("DELETE FROM dso_model_grafico WHERE id = :id");
        $st->execute([':id' => $id]);
        push_toast('success', 'Modelo removido', 'Modelo de gráfico/KPI removido.', 3000);
      } catch (Throwable $e) {
        push_toast('danger', 'Erro ao excluir modelo', $e->getMessage(), 4000);
      }
    }
    header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
    exit;
  }
}
 */

// Carrega listas para exibir
// Métricas para selects
$graficosOpts = [];
$st = $pdo->query("SELECT id, nome FROM dso_grafico ORDER BY nome");
$graficosOpts = $st->fetchAll(PDO::FETCH_ASSOC);

// Métricas p/ tabela da direita
$graficos = [];
$st = $pdo->query("SELECT id, nome, tabela, coluna_grupo, coluna_valor, agregacao, ativo FROM dso_grafico ORDER BY id DESC");
$graficos = $st->fetchAll(PDO::FETCH_ASSOC);

// Joins já cadastrados
$joins = [];
$st = $pdo->query("
    SELECT j.*, g.nome AS grafico_nome
    FROM dso_grafico_join j
    JOIN dso_grafico g ON g.id = j.grafico_id
    ORDER BY g.nome, j.id
");
$joins = $st->fetchAll(PDO::FETCH_ASSOC);

// Models já cadastrados
$models = [];
$st = $pdo->query("
    SELECT m.*, g.nome AS grafico_nome
    FROM dso_model_grafico m
    JOIN dso_grafico g ON g.id = m.grafico_id
    ORDER BY g.nome, m.ordem, m.id
");
$models = $st->fetchAll(PDO::FETCH_ASSOC);

// ----- Registros em edição (via GET) -----
$editGrafico = null;
if (isset($_GET['edit_grafico'])) {
  $id = (int) $_GET['edit_grafico'];
  if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM dso_grafico WHERE id = :id");
    $st->execute([':id' => $id]);
    $editGrafico = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }
}

$editJoin = null;
if (isset($_GET['edit_join'])) {
  $id = (int) $_GET['edit_join'];
  if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM dso_grafico_join WHERE id = :id");
    $st->execute([':id' => $id]);
    $editJoin = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }
}

$editModel = null;
if (isset($_GET['edit_model'])) {
  $id = (int) $_GET['edit_model'];
  if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM dso_model_grafico WHERE id = :id");
    $st->execute([':id' => $id]);
    $editModel = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }
}


$editGroupCols = [];
$editValueCol = null;

if ($editGrafico) {
  if (!empty($editGrafico['coluna_grupo'])) {
    $editGroupCols = array_map('trim', explode(',', $editGrafico['coluna_grupo']));
  }
  $editValueCol = $editGrafico['coluna_valor'] ?? null;
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <title><?= $lang->get('configurar_metricas_graficos'); ?> - Smart Office 4.0</title>
  <?php include 'includes/header.php'; ?>
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
  <div class="app-wrapper">

    <?php include 'includes/menu.php'; ?>

    <main class="app-main">
      <div class="app-content">
        <div class="container-fluid">

          <!-- Título / breadcrumb -->
          <div class="row mb-3">
            <div class="col-sm-6">
              <h3 class="mb-0"><?= $lang->get('configuracoes_metricase_graficos'); ?></h3>
            </div>
          </div>

          <div class="row">
            <!-- FORM MÉTRICA -->
            <div class="col-lg-7">
              <div class="card card-primary card-outline mb-4">
                <div class="card-header">
                  <h3 class="card-title mb-0"><?= $lang->get('definir_metrica'); ?></h3>
                </div>


                <form method="post" autocomplete="off">
                  <input type="hidden" name="form" value="grafico">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfGrafico) ?>">
                  <!-- se estiver editando, manda o id -->
                  <input type="hidden" name="id"
                    value="<?= isset($editGrafico['id']) ? (int) $editGrafico['id'] : '' ?>">

                  <div class="card-body">
                    <div class="mb-3">
                      <label class="form-label"><?= $lang->get('nome_da_metrica'); ?></label>
                      <input type="text" name="nome" class="form-control"
                        placeholder="Ex.: Clientes por status, Faturamento mensal" required
                        value="<?= htmlspecialchars($editGrafico['nome'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                      <label class="form-label"><?= $lang->get('Descrição'); ?></label>
                      <textarea name="descricao" class="form-control" rows="2"
                        placeholder="Descrição opcional desta métrica."><?= htmlspecialchars($editGrafico['descricao'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                      <label class="form-label"><?= $lang->get('tabela_principal'); ?></label>
                      <select name="tabela" id="tabela" class="form-select" required>
                        <option value=""><?= $lang->get('selecione'); ?></option>
                        <?php foreach ($tables as $t): ?>
                          <option value="<?= htmlspecialchars($t) ?>" <?= (!empty($editGrafico['tabela']) && $editGrafico['tabela'] === $t) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <small class="form-text text-muted">
                        <?= $lang->get('tabela_schema'); ?> <strong><?= htmlspecialchars($dbName ?: 'N/A') ?></strong>.
                      </small>
                    </div>

                    <div class="mb-3">
                      <label class="form-label"><?= $lang->get('colunas_grupo'); ?></label>
                      <div id="cols_group" class="row g-1 small"></div>
                      <small class="form-text text-muted">
                        <?= $lang->get('colunas_grupo_help'); ?>
                      </small>
                    </div>

                    <div class="mb-3">
                      <label class="form-label"><?= $lang->get('colunas_valor'); ?></label>
                      <div id="cols_value" class="row g-1 small"></div>
                      <small class="form-text text-muted">
                        <?= $lang->get('colunas_valor_help'); ?>
                      </small>
                    </div>

                    <?php
                    $aggSelected = $editGrafico['agregacao'] ?? 'COUNT';
                    $filtroWhere = $editGrafico['filtro_where'] ?? '';
                    ?>

                    <div class="row">
                      <div class="col-md-4 mb-3">
                        <label class="form-label"><?= $lang->get('agregacao'); ?></label>
                        <select name="agregacao" class="form-select">
                          <option value="COUNT" <?= $aggSelected === 'COUNT' ? 'selected' : '' ?>>COUNT
                            (<?= $lang->get('count'); ?>)</option>
                          <option value="SUM" <?= $aggSelected === 'SUM' ? 'selected' : '' ?>>SUM
                            (<?= $lang->get('sum'); ?>)</option>
                          <option value="AVG" <?= $aggSelected === 'AVG' ? 'selected' : '' ?>>AVG
                            (<?= $lang->get('avg'); ?>)</option>
                          <option value="MAX" <?= $aggSelected === 'MAX' ? 'selected' : '' ?>>MAX
                            (<?= $lang->get('max'); ?>)</option>
                          <option value="MIN" <?= $aggSelected === 'MIN' ? 'selected' : '' ?>>MIN
                            (<?= $lang->get('min'); ?>)</option>
                        </select>
                      </div>
                      <div class="col-md-8 mb-3">
                        <label class="form-label"><?= $lang->get('filtro_where'); ?></label>
                        <input type="text" name="filtro_where" class="form-control"
                          placeholder="Ex.: status = 'ativo' AND data_inicio >= '2025-01-01'"
                          value="<?= htmlspecialchars($filtroWhere) ?>">
                      </div>
                    </div>

                  </div>

                  <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                      <i class="bi bi-save"></i>
                      <?= $editGrafico ? 'Atualizar métrica' : 'Salvar métrica' ?>
                    </button>
                    <?php if ($editGrafico): ?>
                      <a href="novagrafico.php" class="btn btn-secondary ms-2">
                        <?= $lang->get('cancelar_edicao'); ?>
                      </a>
                    <?php endif; ?>
                  </div>
                </form>
              </div>
            </div>

            <!-- LISTA DE MÉTRICAS -->
            <div class="col-lg-5">
              <div class="card card-secondary mb-4">
                <div class="card-header">
                  <h3 class="card-title mb-0"><?= $lang->get('metricas_cadastradas'); ?></h3>
                </div>
                <div class="card-body p-0">
                  <table class="table table-sm mb-0">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th><?= $lang->get('nome'); ?></th>
                        <th><?= $lang->get('agregacao'); ?></th>
                        <th class="text-end"><?= $lang->get('acoes'); ?></th>
                      </tr>
                    </thead>

                    <tbody>
                      <?php if (!$graficos): ?>
                        <tr>
                          <td colspan="3" class="text-center text-muted py-3">
                            <?= $lang->get('nenhuma_metrica_cadastrada'); ?>
                          </td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($graficos as $g): ?>
                          <tr>
                            <td><?= (int) $g['id'] ?></td>
                            <td>
                              <?= htmlspecialchars($g['nome']) ?><br>
                              <small class="text-muted">
                                <?= htmlspecialchars($g['tabela']) ?>
                                <?php if (!empty($g['coluna_grupo'])): ?>
                                  · grupo: <?= htmlspecialchars($g['coluna_grupo']) ?>
                                <?php endif; ?>
                              </small>
                            </td>
                            <td><?= htmlspecialchars($g['agregacao']) ?></td>
                            <td class="text-end">
                              <a href="novagrafico.php?edit_grafico=<?= (int) $g['id'] ?>"
                                class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                              </a>
                              <form method="post" class="d-inline"
                                onsubmit="return confirm('Deseja realmente excluir esta métrica? Modelos e joins associados poderão ser afetados.');">
                                <input type="hidden" name="form" value="delete_grafico">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfGrafico) ?>">
                                <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                  <i class="bi bi-trash"></i>
                                </button>
                              </form>
                            </td>
                          </tr>

                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div><!-- /row 1 -->

          <!-- ROW 2: JOINS + MODELOS -->
          <div class="row">
            <!-- FORM JOIN -->
            <div class="col-lg-6">
              <div class="card card-info card-outline mb-4">
                <div class="card-header">
                  <h3 class="card-title mb-0">
                    <?= $lang->get('definir_join'); ?>
                    <?php if ($editJoin): ?>
                      <span
                        class="badge bg-warning ms-2"><?= $lang->get('editando_id'); ?><?= (int) $editJoin['id'] ?></span>
                    <?php endif; ?>
                  </h3>
                </div>

                <form method="post" autocomplete="off">
                  <input type="hidden" name="form" value="join">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfJoin) ?>">
                  <!-- se estiver editando, manda o id do JOIN -->
                  <input type="hidden" name="id" value="<?= isset($editJoin['id']) ? (int) $editJoin['id'] : '' ?>">

                  <div class="card-body">
                    <div class="mb-3">
                      <label class="form-label"><?= $lang->get('modelo_metrica'); ?></label>
                      <select name="grafico_id" class="form-select" required>
                        <option value=""><?= $lang->get('selecione'); ?></option>
                        <?php foreach ($graficosOpts as $g): ?>
                          <option value="<?= (int) $g['id'] ?>" <?= (!empty($editJoin['grafico_id']) && $editJoin['grafico_id'] == $g['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['nome']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="mb-3">
                      <label class="form-label"><?= $lang->get('tabela_juntar'); ?></label>
                      <select name="tabela_join" class="form-select" required>
                        <option value=""><?= $lang->get('selecione'); ?></option>
                        <?php foreach ($tables as $t): ?>
                          <option value="<?= htmlspecialchars($t) ?>" <?= (!empty($editJoin['tabela']) && $editJoin['tabela'] === $t) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <small class="form-text text-muted">
                        <?= $lang->get('tabela_juntar_help'); ?>
                      </small>
                    </div>

                    <div class="row">
                      <div class="col-md-4 mb-3">
                        <label class="form-label"><?= $lang->get('alias'); ?></label>
                        <input type="text" name="alias" class="form-control" placeholder="Ex.: c" required
                          value="<?= htmlspecialchars($editJoin['alias'] ?? '') ?>">
                      </div>
                      <div class="col-md-4 mb-3">
                        <label class="form-label"><?= $lang->get('tipo_join'); ?></label>
                        <?php $tj = $editJoin['tipo_join'] ?? 'INNER'; ?>
                        <select name="tipo_join" class="form-select">
                          <option value="INNER" <?= $tj === 'INNER' ? 'selected' : '' ?>>INNER</option>
                          <option value="LEFT" <?= $tj === 'LEFT' ? 'selected' : '' ?>>LEFT</option>
                          <option value="RIGHT" <?= $tj === 'RIGHT' ? 'selected' : '' ?>>RIGHT</option>
                        </select>
                      </div>
                    </div>

                    <div class="mb-3">
                      <label class="form-label"><?= $lang->get('condicao_on'); ?></label>
                      <input type="text" name="on_expr" class="form-control" placeholder="Ex.: ct.cliente_id = c.id"
                        required value="<?= htmlspecialchars($editJoin['on_expr'] ?? '') ?>">
                      <small class="form-text text-muted">
                        <?= $lang->get('condicao_on_help'); ?>
                      </small>
                    </div>

                  </div>
                  <div class="card-footer">
                    <button type="submit" class="btn btn-info">
                      <i class="bi bi-link-45deg"></i>
                      <?= $editJoin ? 'Atualizar JOIN' : 'Adicionar JOIN' ?>
                    </button>
                    <?php if ($editJoin): ?>
                      <a href="novagrafico.php" class="btn btn-secondary ms-2">
                        <?= $lang->get('cancelar_edicao'); ?>
                      </a>
                    <?php endif; ?>
                  </div>
                </form>

                <!-- Lista de joins (mantém exatamente como você já tem) -->
                <div class="card-body p-0">
                  <table class="table table-sm mb-0">
                    <thead>
                      <tr>
                        <th><?= $lang->get('modelo_metrica'); ?></th>
                        <th><?= $lang->get('tabela_alias'); ?></th>
                        <th><?= $lang->get('condicao_onn'); ?></th>
                        <th class="text-end"><?= $lang->get('acoes'); ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!$joins): ?>
                        <tr>
                          <td colspan="3" class="text-center text-muted py-2">
                            <?= $lang->get('nenhum_join_cadastrado'); ?>
                          </td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($joins as $j): ?>
                          <tr>
                            <td><?= htmlspecialchars($j['grafico_nome']) ?></td>
                            <td><?= htmlspecialchars($j['tabela']) ?> (<?= htmlspecialchars($j['alias']) ?>)</td>
                            <td><code><?= htmlspecialchars($j['on_expr']) ?></code></td>
                            <td class="text-end">
                              <a href="novagrafico.php?edit_join=<?= (int) $j['id'] ?>"
                                class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                              </a>
                              <form method="post" class="d-inline"
                                onsubmit="return confirm('Deseja realmente excluir este JOIN?');">
                                <input type="hidden" name="form" value="delete_join">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfJoin) ?>">
                                <input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                  <i class="bi bi-trash"></i>
                                </button>
                              </form>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>


            <!-- FORM MODEL -->

            <!-- FORM MODEL -->
            <div class="col-lg-6">
              <div class="card card-warning card-outline mb-4">
                <div class="card-header">
                  <h3 class="card-title mb-0">
                    <?= $lang->get('modelo_de_exibicao'); ?>
                    <?php if ($editModel): ?>
                      <span class="badge bg-warning ms-2"><?= $lang->get('editando_id'); ?><?= (int) $editModel['id'] ?></span>
                    <?php endif; ?>
                  </h3>
                </div>

                <form method="post" autocomplete="off">
                  <input type="hidden" name="form" value="model">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfModel) ?>">
                  <input type="hidden" name="id" value="<?= isset($editModel['id']) ? (int) $editModel['id'] : '' ?>">

                  <div class="card-body">

                    <!-- MÉTRICA -->
                    <div class="mb-3">
                      <label class="form-label"><?= $lang->get('modelo_metrica'); ?></label>
                      <select name="grafico_id" class="form-select" required>
                        <option value=""><?= $lang->get('selecione'); ?></option>
                        <?php foreach ($graficosOpts as $g): ?>
                          <option value="<?= (int) $g['id'] ?>" <?= (!empty($editModel['grafico_id']) && $editModel['grafico_id'] == $g['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['nome']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <!-- NOME -->
                    <div class="mb-3">
                      <label class="form-label"><?= $lang->get('modelo_nome'); ?> *</label>
                      <input type="text" name="nome_model" class="form-control"
                        placeholder="<?= $lang->get('modelo_nome_placeholder'); ?>" required
                        value="<?= htmlspecialchars($editModel['nome'] ?? '') ?>">
                    </div>

                    <!-- DESCRIÇÃO -->
                    <div class="mb-3">
                      <label class="form-label"><?= $lang->get('descricao'); ?></label>
                      <textarea name="descricao_model" class="form-control" rows="2"
                        placeholder="<?= $lang->get('modelo_descricao_placeholder'); ?>"><?= htmlspecialchars($editModel['descricao'] ?? '') ?></textarea>
                    </div>

                    <!-- ÍCONE + INFO -->
                    <div class="row">
                      <div class="col-md-6 mb-3">
                        <label class="form-label"><?= $lang->get('modelo_icone'); ?></label>
                        <input type="text" name="icone" class="form-control" placeholder="bi bi-graph-up"
                          value="<?= htmlspecialchars($editModel['icone'] ?? '') ?>">
                        <small class="form-text text-muted">
                          <?= $lang->get('modelo_icone_hint'); ?>
                        </small>
                      </div>

                      <div class="col-md-6 mb-3">
                        <label class="form-label"><?= $lang->get('modelo_info'); ?></label>
                        <?php $infoSel = $editModel['info'] ?? 'INFO'; ?>
                        <select name="info" class="form-select">
                          <option value="INFO" <?= $infoSel === 'INFO' ? 'selected' : '' ?>>INFO</option>
                          <option value="ALERT" <?= $infoSel === 'ALERT' ? 'selected' : '' ?>>ALERT</option>
                          <option value="SUCCESS" <?= $infoSel === 'SUCCESS' ? 'selected' : '' ?>>SUCCESS</option>
                          <option value="DANGER" <?= $infoSel === 'DANGER' ? 'selected' : '' ?>>DANGER</option>
                        </select>
                      </div>
                    </div>

                    <!-- TIPO + EXIBIÇÃO + ORDEM -->
                    <div class="row">
                      <div class="col-md-4 mb-3">
                        <label class="form-label"><?= $lang->get('modelo_tipo'); ?></label>
                        <?php $tipoSel = $editModel['tipo'] ?? 'KPI-1'; ?>
                        <select name="tipo" class="form-select">
                          <option value="KPI-1" <?= $tipoSel === 'KPI-1' ? 'selected' : '' ?>>KPI-1</option>
                          <option value="KPI-2" <?= $tipoSel === 'KPI-2' ? 'selected' : '' ?>>KPI-2</option>
                          <option value="BARRA" <?= $tipoSel === 'BARRA' ? 'selected' : '' ?>>Barras</option>
                          <option value="LINHA" <?= $tipoSel === 'LINHA' ? 'selected' : '' ?>>Line</option>
                          <option value="DONUT" <?= $tipoSel === 'DONUT' ? 'selected' : '' ?>>Donut</option>
                          <option value="AREA" <?= $tipoSel === 'AREA' ? 'selected' : '' ?>>Área</option>
                        </select>
                      </div>

                      <div class="col-md-4 mb-3">
                        <label class="form-label"><?= $lang->get('modelo_exibicao'); ?></label>
                        <?php $exbSel = $editModel['exibicao'] ?? 'CARD'; ?>
                        <select name="exibicao" class="form-select">
                          <option value="CARD" <?= $exbSel === 'CARD' ? 'selected' : '' ?>><?= $lang->get('modelo_card'); ?></option>
                          <option value="FULL" <?= $exbSel === 'FULL' ? 'selected' : '' ?>><?= $lang->get('modelo_full'); ?></option>
                          <option value="GRANDE" <?= $exbSel === 'GRANDE' ? 'selected' : '' ?>><?= $lang->get('modelo_grande'); ?></option>
                          <option value="MEDIO" <?= $exbSel === 'MEDIO' ? 'selected' : '' ?>><?= $lang->get('modelo_medio'); ?></option>
                          <option value="PEQUENO" <?= $exbSel === 'PEQUENO' ? 'selected' : '' ?>><?= $lang->get('modelo_pequeno'); ?></option>
                          <option value="MINI" <?= $exbSel === 'MINI' ? 'selected' : '' ?>><?= $lang->get('modelo_mini'); ?></option>
                          <option value="MICRO" <?= $exbSel === 'MICRO' ? 'selected' : '' ?>><?= $lang->get('modelo_micro'); ?></option>
                        </select>
                      </div>

                      <div class="col-md-4 mb-3">
                        <label class="form-label"><?= $lang->get('modelo_ordem'); ?></label>
                        <input type="number" name="ordem" class="form-control" min="1"
                          value="<?= htmlspecialchars($editModel['ordem'] ?? 1) ?>">
                      </div>
                    </div>

                    <!-- CORES -->
                    <div class="row">
                      <div class="col-md-6 mb-3">
                        <label class="form-label"><?= $lang->get('modelo_cor_principal'); ?></label>
                        <input type="text" name="cor_principal" class="form-control" placeholder="#0d6efd"
                          value="<?= htmlspecialchars($editModel['cor_principal'] ?? '') ?>">
                      </div>

                      <div class="col-md-6 mb-3">
                        <label class="form-label"><?= $lang->get('modelo_cor_secundaria'); ?></label>
                        <input type="text" name="cor_secundaria" class="form-control" placeholder="#dc3545"
                          value="<?= htmlspecialchars($editModel['cor_secundaria'] ?? '') ?>">
                      </div>
                    </div>

                  </div>

                  <div class="card-footer">
                    <button type="submit" class="btn btn-warning">
                      <i class="bi bi-kanban"></i>
                      <?= $editModel ? $lang->get('modelo_atualizar') : $lang->get('modelo_salvar'); ?>
                    </button>

                    <?php if ($editModel): ?>
                      <a href="novagrafico.php" class="btn btn-secondary ms-2">
                        <?= $lang->get('cancelar_edicao'); ?>
                      </a>
                    <?php endif; ?>
                  </div>
                </form>

                <!-- LISTA DE MODELOS -->
                <div class="card-body p-0">
                  <table class="table table-sm mb-0">
                    <thead>
                      <tr>
                        <th><?= $lang->get('modelo_metrica'); ?></th>
                        <th><?= $lang->get('modelo_nome'); ?></th>
                        <th><?= $lang->get('modelo_tipo'); ?></th>
                        <th class="text-end"><?= $lang->get('acoes'); ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!$models): ?>
                        <tr>
                          <td colspan="4" class="text-center text-muted py-2">
                            <?= $lang->get('modelo_nenhum'); ?>
                          </td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($models as $m): ?>
                          <tr>
                            <td><?= htmlspecialchars($m['grafico_nome']) ?></td>
                            <td>
                              <?= htmlspecialchars($m['nome']) ?><br>
                              <small class="text-muted">
                                <?= $lang->get('ordem'); ?> <?= (int) $m['ordem'] ?> · <?= $lang->get('exibicao'); ?> <?= htmlspecialchars($m['exibicao']) ?>
                              </small>
                            </td>
                            <td><?= htmlspecialchars($m['tipo']) ?></td>
                            <td class="text-end">

                              <a href="novagrafico.php?edit_model=<?= (int) $m['id'] ?>"
                                class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                              </a>

                              <form method="post" class="d-inline"
                                onsubmit="return confirm('Deseja excluir este modelo?');">
                                <input type="hidden" name="form" value="delete_model">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfModel) ?>">
                                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">
                                  <i class="bi bi-trash"></i>
                                </button>
                              </form>

                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

              </div>
            </div>


          </div><!-- /row 2 -->

        </div><!-- /.container-fluid -->
      </div><!-- /.app-content -->
    </main>

    <?php include 'includes/footer.php'; ?>
  </div><!-- /.app-wrapper -->

  <!-- JS para carregar colunas da tabela (métrica) -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const tabelaSelect = document.getElementById('tabela');
      const groupContainer = document.getElementById('cols_group');
      const valueContainer = document.getElementById('cols_value');

      if (!tabelaSelect || !groupContainer || !valueContainer) return;

      const selectedGroups = <?= json_encode($editGroupCols, JSON_UNESCAPED_UNICODE); ?>;
      const selectedValue = <?= json_encode($editValueCol, JSON_UNESCAPED_UNICODE); ?>;
      const tabelaEdit = <?= json_encode($editGrafico['tabela'] ?? null, JSON_UNESCAPED_UNICODE); ?>;

      function resetColumns() {
        groupContainer.innerHTML = '<div class="col-12 text-muted">Selecione uma tabela acima.</div>';
        valueContainer.innerHTML = '<div class="col-12 text-muted">Selecione uma tabela acima.</div>';
      }

      resetColumns();

      tabelaSelect.addEventListener('change', function () {
        const tbl = this.value;

        if (!tbl) {
          resetColumns();
          return;
        }

        groupContainer.innerHTML = '<div class="col-12 text-muted">Carregando colunas...</div>';
        valueContainer.innerHTML = '<div class="col-12 text-muted">Carregando colunas...</div>';

        fetch('../api/list_columns.php?table=' + encodeURIComponent(tbl))
          .then(function (r) { return r.json(); })
          .then(function (data) {
            groupContainer.innerHTML = '';
            valueContainer.innerHTML = '';

            if (!data.ok) {
              groupContainer.innerHTML = '<div class="col-12 text-danger">Erro: ' + (data.error || 'desconhecido') + '</div>';
              valueContainer.innerHTML = '<div class="col-12 text-danger">Erro ao carregar colunas.</div>';
              return;
            }

            if (!data.columns || data.columns.length === 0) {
              groupContainer.innerHTML = '<div class="col-12 text-muted">Nenhuma coluna encontrada.</div>';
              valueContainer.innerHTML = '<div class="col-12 text-muted">Nenhuma coluna encontrada.</div>';
              return;
            }

            function isNumericType(type) {
              const t = (type || '').toLowerCase();
              return ['int', 'decimal', 'float', 'double', 'bigint', 'smallint', 'tinyint'].includes(t);
            }

            data.columns.forEach(function (col) {
              const colName = col.name;
              const colType = col.type;

              // checkbox de grupo
              const gDiv = document.createElement('div');
              gDiv.className = 'col-md-6';
              const checkedGrp = selectedGroups && selectedGroups.includes(colName) ? 'checked' : '';
              gDiv.innerHTML =
                '<div class="form-check">' +
                '<input class="form-check-input" type="checkbox" name="colunas_grupo[]" ' +
                'value="' + colName + '" id="grp_' + colName + '" ' + checkedGrp + '>' +
                '<label class="form-check-label" for="grp_' + colName + '">' +
                colName + ' <small class="text-muted">(' + colType + ')</small>' +
                '</label>' +
                '</div>';
              groupContainer.appendChild(gDiv);

              // radio de valor
              const vDiv = document.createElement('div');
              vDiv.className = 'col-md-6';
              const badge = isNumericType(colType)
                ? '<span class="badge bg-success ms-1">numérico</span>'
                : '<span class="badge bg-secondary ms-1">texto</span>';
              const checkedVal = (selectedValue && selectedValue === colName) ? 'checked' : '';

              vDiv.innerHTML =
                '<div class="form-check">' +
                '<input class="form-check-input" type="radio" name="coluna_valor" ' +
                'value="' + colName + '" id="val_' + colName + '" ' + checkedVal + '>' +
                '<label class="form-check-label" for="val_' + colName + '">' +
                colName + ' <small class="text-muted">(' + colType + ')</small> ' + badge +
                '</label>' +
                '</div>';
              valueContainer.appendChild(vDiv);
            });
          })
          .catch(function (err) {
            console.error(err);
            groupContainer.innerHTML = '<div class="col-12 text-danger">Erro ao carregar colunas.</div>';
            valueContainer.innerHTML = '<div class="col-12 text-danger">Erro ao carregar colunas.</div>';
          });
      });

      // Se estiver em modo edição, dispara o change automaticamente
      if (tabelaEdit) {
        tabelaSelect.value = tabelaEdit;
        tabelaSelect.dispatchEvent(new Event('change'));
      }
    });
  </script>

</body>

</html>