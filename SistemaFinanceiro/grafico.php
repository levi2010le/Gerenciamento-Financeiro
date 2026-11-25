<?php
require_once __DIR__ . "/auth.php";

$usuarioId = (int) $_SESSION["usuario_id"];

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function moedaBR($valor)
{
    return "R$ " . number_format((float) $valor, 2, ",", ".");
}

$stmt = $conexao->prepare(
    "SELECT cd_banco
     FROM tb_banco
     WHERE id_usuario = ?
     ORDER BY cd_banco
     LIMIT 1"
);
$stmt->bind_param("i", $usuarioId);
$stmt->execute();
$conta = $stmt->get_result()->fetch_assoc();
$stmt->close();

$bancoId = $conta ? (int) $conta["cd_banco"] : 0;

$filtroMes = $_GET["mes"] ?? date("Y-m");

if (!preg_match('/^\d{4}-\d{2}$/', $filtroMes)) {
    $filtroMes = date("Y-m");
}

$inicioMes = $filtroMes . "-01";
$dataMes = DateTime::createFromFormat("!Y-m-d", $inicioMes);

if (!$dataMes) {
    $filtroMes = date("Y-m");
    $inicioMes = date("Y-m-01");
}

$fimMes = (new DateTime($inicioMes))
    ->modify("+1 month")
    ->format("Y-m-d");

$gastosPorDescricao = [];

if ($bancoId > 0) {
    $stmt = $conexao->prepare(
        "SELECT
            ds_saida AS descricao,
            SUM(vl_saida) AS total
         FROM tb_saida
         WHERE id_banco = ?
           AND dt_saida >= ?
           AND dt_saida < ?
         GROUP BY ds_saida
         ORDER BY total DESC"
    );
    $stmt->bind_param("iss", $bancoId, $inicioMes, $fimMes);
    $stmt->execute();
    $resultado = $stmt->get_result();

    while ($linha = $resultado->fetch_assoc()) {
        $gastosPorDescricao[] = [
            "descricao" => $linha["descricao"],
            "total" => (float) $linha["total"]
        ];
    }

    $stmt->close();
}

$totalGastosMes = 0;
$maiorGasto = null;

foreach ($gastosPorDescricao as $item) {
    $totalGastosMes += $item["total"];

    if (
        !$maiorGasto
        || $item["total"] > $maiorGasto["total"]
    ) {
        $maiorGasto = $item;
    }
}

$dadosMensais = [];
$maiorValorMensal = 0;

if ($bancoId > 0) {
    $inicioSeisMeses = (new DateTime("first day of this month"))
        ->modify("-5 months")
        ->format("Y-m-01");

    $stmt = $conexao->prepare(
        "SELECT
            movimentos.mes,
            SUM(movimentos.entradas) AS entradas,
            SUM(movimentos.saidas) AS saidas
         FROM (
            SELECT
                DATE_FORMAT(dt_entrada, '%Y-%m') AS mes,
                SUM(vl_entrada) AS entradas,
                0 AS saidas
            FROM tb_entrada
            WHERE id_banco = ?
              AND dt_entrada >= ?
            GROUP BY DATE_FORMAT(dt_entrada, '%Y-%m')

            UNION ALL

            SELECT
                DATE_FORMAT(dt_saida, '%Y-%m') AS mes,
                0 AS entradas,
                SUM(vl_saida) AS saidas
            FROM tb_saida
            WHERE id_banco = ?
              AND dt_saida >= ?
            GROUP BY DATE_FORMAT(dt_saida, '%Y-%m')
         ) AS movimentos
         GROUP BY movimentos.mes
         ORDER BY movimentos.mes"
    );

    $stmt->bind_param(
        "isis",
        $bancoId,
        $inicioSeisMeses,
        $bancoId,
        $inicioSeisMeses
    );
    $stmt->execute();
    $resultado = $stmt->get_result();

    while ($linha = $resultado->fetch_assoc()) {
        $dadosMensais[$linha["mes"]] = [
            "entradas" => (float) $linha["entradas"],
            "saidas" => (float) $linha["saidas"]
        ];
    }

    $stmt->close();
}

$nomesMeses = [
    1 => "Jan", 2 => "Fev", 3 => "Mar",
    4 => "Abr", 5 => "Mai", 6 => "Jun",
    7 => "Jul", 8 => "Ago", 9 => "Set",
    10 => "Out", 11 => "Nov", 12 => "Dez"
];

$inicioSeis = new DateTime("first day of this month");
$inicioSeis->modify("-5 months");

$mesesGrafico = [];

for ($i = 0; $i < 6; $i++) {
    $dataMesAtual = clone $inicioSeis;
    $dataMesAtual->modify("+{$i} months");

    $chaveMes = $dataMesAtual->format("Y-m");

    $entradas =
        $dadosMensais[$chaveMes]["entradas"] ?? 0;
    $saidas =
        $dadosMensais[$chaveMes]["saidas"] ?? 0;

    $mesesGrafico[] = [
        "nome" =>
            $nomesMeses[(int) $dataMesAtual->format("n")],
        "entradas" => $entradas,
        "saidas" => $saidas
    ];

    $maiorValorMensal = max(
        $maiorValorMensal,
        $entradas,
        $saidas
    );
}

$movimentacoes = [];

if ($bancoId > 0) {
    $stmt = $conexao->prepare(
        "SELECT *
         FROM (
            SELECT
                dt_entrada AS data_movimento,
                ds_entrada AS descricao,
                vl_entrada AS valor,
                'ENTRADA' AS tipo,
                tp_pagamento
            FROM tb_entrada
            WHERE id_banco = ?

            UNION ALL

            SELECT
                dt_saida AS data_movimento,
                ds_saida AS descricao,
                vl_saida AS valor,
                'SAIDA' AS tipo,
                tp_pagamento
            FROM tb_saida
            WHERE id_banco = ?
         ) AS movimentacoes
         ORDER BY data_movimento DESC
         LIMIT 5"
    );

    $stmt->bind_param("ii", $bancoId, $bancoId);
    $stmt->execute();
    $resultado = $stmt->get_result();

    while ($linha = $resultado->fetch_assoc()) {
        $movimentacoes[] = $linha;
    }

    $stmt->close();
}

$cores = [
    "#8ecbf2",
    "#f8d875",
    "#f4a39a",
    "#9be0bf",
    "#b39ddb",
    "#ffb74d",
    "#81c784",
    "#e57373"
];

$partes = [];
$somaAcumulada = 0;

if ($totalGastosMes > 0) {
    foreach ($gastosPorDescricao as $indice => $item) {
        $inicio = $somaAcumulada;
        $fim =
            $somaAcumulada
            + (($item["total"] / $totalGastosMes) * 100);

        $cor = $cores[$indice % count($cores)];

        $partes[] = "{$cor} {$inicio}% {$fim}%";
        $somaAcumulada = $fim;
    }

    $gradienteDespesas =
        "conic-gradient(" . implode(", ", $partes) . ")";
} else {
    $gradienteDespesas =
        "conic-gradient(#e5e5e5 0 100%)";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

  <title>LEKAN - Gráficos</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
  <link rel="stylesheet" href="assets/css/sidebar.css">

  <style>
    .content {
      display: flex;
      flex-direction: column;
      gap: 18px;
      padding-top: 4px;
    }

    .page-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }

    .page-title {
      font-size: 20px;
      font-weight: 700;
    }

    .subtitle {
      font-size: 14px;
      color: #666;
      margin-top: 2px;
    }

    .month-filter {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .month-filter label {
      font-size: 12px;
      font-weight: 700;
      color: #555;
    }

    .month-filter input {
      height: 38px;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 0 10px;
      background: #fff;
      font: inherit;
      font-size: 13px;
    }

    .main-grid {
      display: grid;
      grid-template-columns: 1.7fr 0.9fr;
      gap: 18px;
      align-items: start;
    }

    .panel,
    .movements-panel {
      background: #f7f7f7;
      border: 1px solid #e7e7e7;
      border-radius: 14px;
      box-shadow: 0 10px 24px rgba(0,0,0,0.06);
      padding: 16px 18px;
    }

    .panel-title {
      font-size: 15px;
      font-weight: 700;
      margin-bottom: 8px;
    }

    .summary-box {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
    }

    .summary-card {
      background: #fff;
      border: 1px solid #e5e5e5;
      border-radius: 10px;
      padding: 12px;
    }

    .summary-card span {
      display: block;
      font-size: 11px;
      color: #777;
      margin-bottom: 5px;
    }

    .summary-card strong {
      font-size: 15px;
      color: #333;
    }

    .summary-card.alert strong {
      color: #c44040;
    }

    .donut-wrap {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 12px 0;
    }

    .donut-chart {
      position: relative;
      width: 230px;
      height: 230px;
      border-radius: 50%;
      background: <?= $gradienteDespesas ?>;
    }

    .donut-chart::before {
      content: "";
      position: absolute;
      inset: 38px;
      background: #fafafa;
      border-radius: 50%;
    }

    .donut-center {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      z-index: 2;
      color: #3a3a3a;
    }

    .donut-center span {
      font-size: 11px;
      color: #777;
    }

    .donut-center strong {
      margin-top: 3px;
      font-size: 14px;
    }

    .legend {
      display: flex;
      flex-wrap: wrap;
      gap: 10px 16px;
      justify-content: center;
      margin-top: 12px;
      font-size: 12px;
      color: #666;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 7px;
    }

    .legend-dot {
      width: 11px;
      height: 11px;
      border-radius: 3px;
    }

    .category-ranking {
      margin-top: 16px;
      display: flex;
      flex-direction: column;
      gap: 9px;
    }

    .category-row {
      display: grid;
      grid-template-columns: 140px 1fr 100px;
      align-items: center;
      gap: 10px;
      font-size: 12px;
      color: #555;
    }

    .progress {
      height: 8px;
      border-radius: 20px;
      background: #e8e8e8;
      overflow: hidden;
    }

    .progress-bar {
      height: 100%;
      border-radius: 20px;
    }

    .category-value {
      text-align: right;
      font-weight: 700;
      color: #333;
      white-space: nowrap;
    }

    .movement-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-top: 12px;
    }

    .movement-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px;
      background: #f1f1f1;
      border: 1px solid #e2e2e2;
      border-radius: 10px;
      gap: 10px;
    }

    .movement-description {
      font-size: 12px;
      line-height: 1.3;
      color: #333;
    }

    .movement-description strong {
      display: block;
      font-size: 13px;
    }

    .movement-value {
      font-size: 12px;
      font-weight: 700;
      white-space: nowrap;
    }

    .movement-value.entrada {
      color: #268043;
    }

    .movement-value.saida {
      color: #c44040;
    }

    .bar-panel {
      min-height: 260px;
    }

    .bar-chart {
      height: 170px;
      display: flex;
      align-items: end;
      justify-content: space-between;
      gap: 12px;
      padding-top: 10px;
      border-bottom: 1px solid #d7d7d7;
      margin-top: 12px;
    }

    .bar-group {
      flex: 1;
      height: 100%;
      display: flex;
      align-items: end;
      justify-content: center;
      gap: 6px;
    }

    .bar {
      width: 18px;
      border-radius: 8px 8px 0 0;
      min-height: 4px;
    }

    .bar.entrada {
      background: #a7d87d;
    }

    .bar.saida {
      background: #ec7d7d;
    }

    .month-labels {
      display: grid;
      grid-template-columns: repeat(6, minmax(0, 1fr));
      gap: 10px;
      margin-top: 12px;
      font-size: 11px;
      color: #666;
      text-align: center;
    }

    .empty {
      color: #777;
      font-size: 13px;
      padding: 10px 0;
    }

    @media (max-width: 950px) {
      .main-grid {
        grid-template-columns: 1fr;
      }

      .summary-box {
        grid-template-columns: 1fr;
      }

      .page-header {
        align-items: flex-start;
        flex-direction: column;
      }
    }
  </style>
</head>

<body>
  <section class="dashboard">
    <div class="app-shell">
      <aside class="sidebar">
        <div class="brand-mini">
          <span class="dot"></span>
          <span>LEKAN</span>
        </div>

        <nav class="nav">
          <a class="nav-item" href="home.php">
            <span class="nav-icon">◉</span>
            <span>Geral</span>
          </a>

          <a class="nav-item" href="contas.php">
            <span class="nav-icon">≡</span>
            <span>Contas</span>
          </a>

          <a class="nav-item active" href="grafico.php">
            <span class="nav-icon">▣</span>
            <span>Gráfico</span>
          </a>

          <a class="nav-item" href="metas.php">
            <span class="nav-icon">◔</span>
            <span>Metas</span>
          </a>

          <a class="nav-item" href="relatorios.php">
            <span class="nav-icon">▤</span>
            <span>Relatório</span>
          </a>
        </nav>

        <div class="sidebar-footer">
          <div class="user-box">
            <div class="user-avatar">L+K</div>
            <div class="user-data">
              <strong>
              Usuario
              </strong>
            </div>
          </div>

          <a class="logout" href="logout.php">Sair</a>
        </div>
      </aside>

      <main class="main">
        <div class="topbar">
          <div class="title">Sistema Financeiro</div>
          <div class="topbar-right">
            <div class="avatar">👤</div>
          </div>
        </div>

        <div class="content">
          <div class="page-header">
            <div>
              <div class="page-title">Gráficos</div>
              <div class="subtitle">
                Compare gastos e movimentações
              </div>
            </div>

            <form class="month-filter"
                  method="GET"
                  action="grafico.php">
              <label for="mes">Mês</label>
              <input type="month"
                     id="mes"
                     name="mes"
                     value="<?= escapar($filtroMes) ?>"
                     onchange="this.form.submit()">
            </form>
          </div>

          <div class="summary-box">
            <div class="summary-card">
              <span>Total gasto no mês</span>
              <strong><?= moedaBR($totalGastosMes) ?></strong>
            </div>

            <div class="summary-card alert">
              <span>Descrição com maior gasto</span>
              <strong>
                <?= escapar(
                    $maiorGasto["descricao"] ?? "Nenhuma"
                ) ?>
              </strong>
            </div>

            <div class="summary-card">
              <span>Maior gasto individual agrupado</span>
              <strong>
                <?= moedaBR($maiorGasto["total"] ?? 0) ?>
              </strong>
            </div>
          </div>

          <div class="main-grid">
            <div class="panel">
              <div class="panel-title">
                Gastos por descrição
              </div>

              <?php if (count($gastosPorDescricao) === 0): ?>
                <div class="empty">
                  Não há gastos registrados neste mês.
                </div>
              <?php else: ?>
                <div class="donut-wrap">
                  <div class="donut-chart">
                    <div class="donut-center">
                      <span>Total</span>
                      <strong><?= moedaBR($totalGastosMes) ?></strong>
                    </div>
                  </div>
                </div>

                <div class="legend">
                  <?php foreach (
                      $gastosPorDescricao
                      as $indice => $item
                  ): ?>
                    <?php
                      $cor =
                          $cores[$indice % count($cores)];
                    ?>
                    <div class="legend-item">
                      <span class="legend-dot"
                            style="background:<?= $cor ?>"></span>
                      <?= escapar($item["descricao"]) ?>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div class="category-ranking">
                  <?php foreach (
                      $gastosPorDescricao
                      as $indice => $item
                  ): ?>
                    <?php
                      $porcentagem =
                          $totalGastosMes > 0
                              ? (
                                  $item["total"]
                                  / $totalGastosMes
                                ) * 100
                              : 0;

                      $cor =
                          $cores[$indice % count($cores)];
                    ?>

                    <div class="category-row">
                      <div>
                        <?= escapar($item["descricao"]) ?>
                      </div>

                      <div class="progress">
                        <div class="progress-bar"
                             style="width: <?= $porcentagem ?>%;
                                    background: <?= $cor ?>"></div>
                      </div>

                      <div class="category-value">
                        <?= moedaBR($item["total"]) ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <aside class="movements-panel">
              <div class="panel-title">
                Últimas movimentações
              </div>

              <?php if (count($movimentacoes) === 0): ?>
                <div class="empty">
                  Nenhuma movimentação registrada.
                </div>
              <?php else: ?>
                <div class="movement-list">
                  <?php foreach (
                      $movimentacoes
                      as $movimento
                  ): ?>
                    <?php
                      $ehEntrada =
                          $movimento["tipo"] === "ENTRADA";
                    ?>

                    <div class="movement-item">
                      <div class="movement-description">
                        <strong>
                          <?= escapar(
                              $movimento["descricao"]
                          ) ?>
                        </strong>

                        <?= escapar(
                            $movimento["tp_pagamento"] ?? ""
                        ) ?>
                        ·
                        <?= date(
                            "d/m/Y",
                            strtotime(
                                $movimento["data_movimento"]
                            )
                        ) ?>
                      </div>

                      <div class="movement-value
                          <?= $ehEntrada
                              ? "entrada"
                              : "saida" ?>">
                        <?= $ehEntrada ? "+" : "−" ?>
                        <?= moedaBR($movimento["valor"]) ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </aside>
          </div>

          <div class="panel bar-panel">
            <div class="panel-title">
              Fluxo de Caixa Mensal
            </div>

            <div class="bar-chart">
              <?php foreach ($mesesGrafico as $mes): ?>
                <?php
                  $alturaEntrada =
                      $maiorValorMensal > 0
                          ? (int) round(
                              (
                                  $mes["entradas"]
                                  / $maiorValorMensal
                              ) * 150
                            )
                          : 0;

                  $alturaSaida =
                      $maiorValorMensal > 0
                          ? (int) round(
                              (
                                  $mes["saidas"]
                                  / $maiorValorMensal
                              ) * 150
                            )
                          : 0;
                ?>

                <div class="bar-group">
                  <div class="bar saida"
                       style="height: <?= $alturaSaida ?>px"
                       title="Saída:
                       <?= escapar(
                           moedaBR($mes["saidas"])
                       ) ?>">
                  </div>

                  <div class="bar entrada"
                       style="height: <?= $alturaEntrada ?>px"
                       title="Entrada:
                       <?= escapar(
                           moedaBR($mes["entradas"])
                       ) ?>">
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="month-labels">
              <?php foreach ($mesesGrafico as $mes): ?>
                <div><?= escapar($mes["nome"]) ?></div>
              <?php endforeach; ?>
            </div>

            <div class="legend" style="margin-top:14px">
              <div class="legend-item">
                <span class="legend-dot"
                      style="background:#ec7d7d"></span>
                Saída
              </div>

              <div class="legend-item">
                <span class="legend-dot"
                      style="background:#a7d87d"></span>
                Entrada
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </section>
</body>
</html>