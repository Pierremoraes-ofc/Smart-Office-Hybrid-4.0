<?php

require_once __DIR__ . '/../api/functions.php';            //  Funções obrigatorias

$pdo = db();                                               //  Conexões ao banco de dados
require_login();                                           //  Verifica se usuário ja esta logado
$settings = get_dso_settings($pdo);                   //  Configurações globais do Smart Office 4.0 registrado na tabela do DB dso_settings
$lang = get_current_language($pdo);                   //  Idioma atual
$csrfIa = dso_csrf_ensure('csrf_ia');

// Configura timezone
date_default_timezone_set('America/Sao_Paulo');

// Tenta conectar com o banco e preencher as variáveis que o HTML usa
try {
  // Abre conexão via sua classe
  $pdo = (new Database())->getConnection();

  // Ping simples
  $pdo->query('SELECT 1');

  // Nome do banco selecionado
  $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
  $dbExists = !empty($dbName);

  // Data/hora do servidor SQL
  $serverNow = $pdo->query('SELECT NOW()')->fetchColumn();

  // Lista de tabelas do schema atual
  $tables = [];
  if ($dbExists) {
    $stmt = $pdo->prepare("
            SELECT TABLE_NAME 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = :db
            ORDER BY TABLE_NAME
        ");
    $stmt->execute([':db' => $dbName]);
    $tables = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'TABLE_NAME');
  }

  // Monta os objetos exatamente como seu HTML espera
  $connectionTest = [
    'success' => true,
    'message' => $lang->get('connection_success'),
    'data' => ['data_dia' => date('d/m/Y H:i:s', strtotime($serverNow ?: 'now'))]
  ];

  $databaseInfo = [
    'exists' => (bool) $dbExists,
    'name' => $dbName ?: $lang->get('db_exist')
  ];

} catch (Throwable $e) {
  // Em qualquer falha, mantém as mesmas chaves que seu HTML já usa
  $connectionTest = ['success' => false, 'message' => $lang->get('connection_fail') . $e->getMessage()];
  $databaseInfo = ['exists' => false, 'name' => null];
  $tables = [];
}

// ======================================================================
// Carrega modelos de gráfico (dso_model_grafico) + dados agregados
// ======================================================================
$chartModels = dso_get_dashboard_models($pdo);
$chartConfigs = [];

foreach ($chartModels as $m) {
  try {
    $data = dso_fetch_grafico_data($pdo, $m);

    $chartConfigs[] = [
      'id' => 'chart_' . (int) $m['id'],
      'titulo' => $m['nome'],
      'descricao' => $m['descricao'] ?? '',
      'icone' => $m['icone'],
      'info' => $m['info'],
      'tipo' => $m['tipo'],        // KPI, BARRA, LINHA, PIZZA, DONUT, AREA, RADAR
      'exibicao' => $m['exibicao'],    // CARD, FULL, MINI
      'labels' => $data['labels'],
      'series' => $data['series'],
      'cor_principal' => $m['cor_principal'] ?: null,
      'cor_secundaria' => $m['cor_secundaria'] ?: null,
    ];
  } catch (Throwable $e) {
    // Se der problema em algum modelo, simplesmente pula e segue
    // (pode logar se quiser)
    error_log("Erro modelo {$m['id']} ({$m['nome']}): " . $e->getMessage());
    continue;
  }
}

?>

<!doctype html>
<html lang="en">
<!--begin::Head-->

<head>

  <title><?= $lang->get('dashboard'); ?> | Smart Office 4.0</title>
  <?php include 'includes/header.php'; ?>
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
  <!--begin::App Wrapper-->
  <div class="app-wrapper">

    <?php include 'includes/menu.php'; ?>


    <!--begin::App Main-->
    <main class="app-main">
      <div class="app-content">
        <div class="container-fluid">
          <!-- CARDS -->
          <div class="row">
            <!-- Pagina atual -->
            <div class="col-sm-6">
              <h3 class="mb-0"><?= $lang->get('list_database'); ?></h3>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-end">
                <li class="breadcrumb-item active" aria-current="page"><?= $lang->get('list_database'); ?></li>
              </ol>
            </div>


            <?php include 'includes/cards.php'; ?>
            <!-- Pagina atual -->
          </div>
          <!-- CARDS -->

          <!-- ===================== KPIs ===================== -->
          <div class="row">
            <?php foreach ($chartConfigs as $cfg): ?>

              <?php
              // Só entra aqui se for KPI
              if ($cfg['tipo'] !== 'KPI-1' && $cfg['tipo'] !== 'KPI-2') {
                continue;
              }

              // largura do card pelo campo exibicao
              $colClass = 'col-lg-6';
              switch ($cfg['exibicao']) {
                case 'FULL':
                  $colClass = 'col-12';
                  break;
                case 'GRANDE':
                  $colClass = 'col-lg-8';
                  break;
                case 'MEDIO':
                  $colClass = 'col-md-6';
                  break;
                case 'PEQUENO':
                  $colClass = 'col-md-4';
                  break;
                case 'MINI':
                  $colClass = 'col-md-3';
                  break;
                case 'MICRO':
                  $colClass = 'col-md-2';
                  break;
              }

              // cor do info-box por "info"
              $infoClass = 'info-box mb-3 text-bg-info';
              if ($cfg['info'] === 'ALERT')
                $infoClass = 'info-box mb-3 text-bg-warning';
              elseif ($cfg['info'] === 'SUCCESS')
                $infoClass = 'info-box mb-3 text-bg-success';
              elseif ($cfg['info'] === 'DANGER')
                $infoClass = 'info-box mb-3 text-bg-danger';
              ?>

              <?php if ($cfg['tipo'] === 'KPI-1'): ?>
                <!-- KPI-1 = info-box -->
                <div class="<?= $colClass ?>">
                  <div class="card-body">
                    <div class="<?= $infoClass ?>">
                      <span class="info-box-icon">
                        <?php if (!empty($cfg['icone'])): ?>
                          <i class="<?= htmlspecialchars($cfg['icone']); ?>"></i>
                        <?php else: ?>
                          <i class="bi bi-info-circle"></i>
                        <?php endif; ?>
                      </span>
                      <div class="info-box-content">
                        <span class="info-box-text"><?= htmlspecialchars($cfg['titulo']); ?></span>
                        <span class="info-box-number" id="<?= $cfg['id']; ?>_value">0</span>
                        <span class="info-box-text small">
                          <?= htmlspecialchars($cfg['descricao']); ?>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

              <?php elseif ($cfg['tipo'] === 'KPI-2'): ?>
                <!-- KPI-2 = card “clean” com número grande -->
                <div class="<?= $colClass ?>">
                  <div class="card mb-4">
                    <div class="card-header border-0">
                      <div class="d-flex justify-content-between">
                        <h3 class="card-title"><?= htmlspecialchars($cfg['titulo']); ?></h3>
                      </div>
                    </div>
                    <div class="card-body">
                      <div class="d-flex">
                        <p class="d-flex flex-column mb-0">
                          <span class="fw-bold fs-3" id="<?= $cfg['id']; ?>_value">0</span>
                          <?php if (!empty($cfg['descricao'])): ?>
                            <span><?= htmlspecialchars($cfg['descricao']); ?></span>
                          <?php endif; ?>
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endif; ?>

            <?php endforeach; ?>
          </div>
          <!-- ================== Fim KPIs =================== -->

          <!-- ===================== GRÁFICOS ===================== -->
          <div class="row">
            <?php foreach ($chartConfigs as $cfg): ?>

              <?php if (in_array($cfg['tipo'], ['KPI-1', 'KPI-2'], true))
                continue; ?>

              <?php
              $colClass = 'col-lg-6';
              switch ($cfg['exibicao']) {
                case 'FULL':
                  $colClass = 'col-12';
                  break;
                case 'GRANDE':
                  $colClass = 'col-lg-8';
                  break;
                case 'MEDIO':
                  $colClass = 'col-md-6';
                  break;
                case 'PEQUENO':
                  $colClass = 'col-md-4';
                  break;
                case 'MINI':
                  $colClass = 'col-md-3';
                  break;
                case 'MICRO':
                  $colClass = 'col-md-2';
                  break;
              }
              ?>

              <div class="<?= $colClass ?>">
                <div class="card mb-4">
                  <div class="card-header border-0">
                    <div class="d-flex justify-content-between">
                      <h3 class="card-title"><?= htmlspecialchars($cfg['titulo']); ?></h3>
                      <?php if (!empty($cfg['descricao'])): ?>
                        <span class="text-secondary small"><?= htmlspecialchars($cfg['descricao']); ?></span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="card-body">
                    <div class="position-relative mb-4">
                      <div id="<?= $cfg['id']; ?>"></div>
                    </div>
                  </div>
                </div>
              </div>

            <?php endforeach; ?>
          </div>
          <!-- ================== Fim GRÁFICOS =================== -->

          <!-- ================== CHAT IA =================== -->
          <?php
          $dashboardDataForIA = $chartConfigs; // já montado no dashboard
          $response = null;

          if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_msg'])) {
            $msg = trim($_POST['chat_msg']);
            $response = dso_process_chat_message($pdo, $msg, $dashboardDataForIA);
          }
          ?>
          <div class="row mt-4">
            <div class="col-md-6">
              <div class="card direct-chat direct-chat-primary">
                <div class="card-header">
                  <h3 class="card-title"><?= $lang->get('ia_chat_title'); ?></h3>
                </div>

                <div class="card-body">
                  <div class="direct-chat-messages" style="height:300px; overflow-y:auto;">

                    <!-- Mensagem inicial -->
                    <div class="direct-chat-msg">
                      <div class="direct-chat-infos clearfix">
                        <span class="direct-chat-name float-start">Smart IA</span>
                      </div>
                      <img class="direct-chat-img" src="../images/chatgpt.png">
                      <div class="direct-chat-text">
                        <?= nl2br(dso_chat_menu()); ?>
                      </div>
                    </div>

                    <?php if (!empty($_POST['chat_msg'])): ?>
                      <div class="direct-chat-msg end">
                        <div class="direct-chat-infos clearfix">
                          <span class="direct-chat-name float-end"><?= $lang->get('ia_chat_user'); ?></span>
                        </div>
                        <img class="direct-chat-img" src="../images/user.png">
                        <div class="direct-chat-text"><?= htmlspecialchars($_POST['chat_msg']); ?></div>
                      </div>

                      <div class="direct-chat-msg">
                        <div class="direct-chat-infos clearfix">
                          <span class="direct-chat-name float-start">Smart IA</span>
                        </div>
                        <img class="direct-chat-img" src="../images/chatgpt.png">
                        <div class="direct-chat-text">
                          <?= nl2br($response); ?>
                        </div>
                      </div>
                    <?php endif; ?>

                  </div>
                </div>

                <div class="card-footer">
                  <form method="post">
                    <div class="input-group">
                      <input type="text" name="chat_msg" class="form-control" placeholder="<?= $lang->get('ia_chat_placeholder'); ?>">
                      <button class="btn btn-primary"><?= $lang->get('ia_chat_send'); ?></button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- ================== FIM CHAT IA =================== -->
        </div>
      </div>
    </main>

    <!-- Div de fechamento do app-wrapper fica no footer.php -->
    <!--</div>-->
    <?php include 'includes/footer.php'; ?>
    <!-- Div de fechamento fica no footer.php -->

    <script>
      // Configurações vindas do PHP
      const dsoCharts = <?= json_encode($chartConfigs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

      console.log('DSO CHART CONFIGS:', dsoCharts);

      function mapTipoApex(tipo) {
        switch (tipo) {
          case 'LINHA': return 'line';
          case 'BARRA': return 'bar';
          case 'AREA': return 'area';
          // DONUT é tratado separadamente
          default: return 'line';
        }
      }

      dsoCharts.forEach(cfg => {
        console.log('PROCESSANDO CFG:', cfg.id, 'tipo:', cfg.tipo);

        // =============== KPIs (KPI-1 e KPI-2) ===============
        if (cfg.tipo === 'KPI-1' || cfg.tipo === 'KPI-2') {
          const el = document.getElementById(cfg.id + '_value');
          if (!el) {
            console.warn('Elemento KPI não encontrado:', cfg.id + '_value');
            return;
          }

          let value = 0;

          if (
            cfg.series &&
            cfg.series[0] &&
            Array.isArray(cfg.series[0].data) &&
            cfg.series[0].data.length > 0
          ) {
            value = cfg.series[0].data.reduce((acc, v) => acc + Number(v || 0), 0);
          }

          el.textContent = value.toLocaleString('pt-BR');
          return; // NÃO cria gráfico para KPI
        }

        // =============== GRÁFICOS (BARRA / LINHA / AREA / DONUT) ===============
        const dom = document.getElementById(cfg.id);
        if (!dom) {
          console.warn('Container de gráfico não encontrado:', cfg.id);
          return;
        }

        // =============== DONUT (tratamento especial, estilo AdminLTE) -------===============---
        if (cfg.tipo === 'DONUT') {

          const rawSeries = cfg.series || [];
          let donutValues = [];
          let donutLabels = [];

          // Caso 1: series = [ { name:'Clientes', data:[125,30,5] } ]
          if (rawSeries.length === 1 && Array.isArray(rawSeries[0].data)) {
            donutValues = rawSeries[0].data.map(v => Number(v || 0));
            donutLabels = Array.isArray(cfg.labels) ? cfg.labels : [];
          }
          // Caso 2: series = [ { name:'Ativo', data:[125] }, { name:'Inativo', data:[30] } ... ]
          else if (
            rawSeries.length > 1 &&
            Array.isArray(rawSeries[0].data) &&
            rawSeries[0].data.length === 1
          ) {
            donutValues = rawSeries.map(s =>
              Number((s.data && s.data[0]) || 0)
            );
            donutLabels = rawSeries.map(s => s.name || '');
          }
          // Fallback: achata tudo
          else {
            rawSeries.forEach(s => {
              if (Array.isArray(s.data)) {
                s.data.forEach((v, idx) => {
                  donutValues.push(Number(v || 0));
                  donutLabels.push(s.name ? `${s.name} ${idx + 1}` : `Item ${idx + 1}`);
                });
              }
            });
          }

          console.log('DONUT DEBUG', {
            id: cfg.id,
            values: donutValues,
            labels: donutLabels,
            rawSeries: rawSeries
          });

          // Se não tiver dados, usa valor fake só para testar visual
          if (!donutValues.length) {
            donutValues = [1];
            donutLabels = ['Sem dados'];
          }

          const donutOptions = {
            series: donutValues,
            chart: {
              type: 'donut',
              height: 260,
              toolbar: { show: false }
            },
            labels: donutLabels,
            dataLabels: {
              enabled: false
            },
            legend: {
              show: true,
              position: 'right'
            }
          };

          // cores (se definidas no banco)
          const donutColors = [];
          if (cfg.cor_principal) donutColors.push(cfg.cor_principal);
          if (cfg.cor_secundaria) donutColors.push(cfg.cor_secundaria);
          if (donutColors.length) {
            donutOptions.colors = donutColors;
          }

          const donutChart = new ApexCharts(dom, donutOptions);
          donutChart.render();
          return; // IMPORTANTE: não deixar cair no bloco de linha/barra/área
        }

        // =============== Linha, Barra, Área ===============
        const baseOptions = {
          chart: {
            type: mapTipoApex(cfg.tipo),
            height: 260,
            toolbar: { show: false }
          },
          series: cfg.series || [],
          dataLabels: { enabled: false },
          legend: { show: true },
          stroke: { curve: 'smooth' },
          xaxis: {
            categories: cfg.labels || []
          }
        };

        const colors = [];
        if (cfg.cor_principal) colors.push(cfg.cor_principal);
        if (cfg.cor_secundaria) colors.push(cfg.cor_secundaria);
        if (colors.length) baseOptions.colors = colors;

        const chart = new ApexCharts(dom, baseOptions);
        chart.render();
      });
    </script>
</body>

</html>