<?php
require_once __DIR__ . "/auth.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$usuarioId = (int) $_SESSION["usuario_id"];

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function moedaBR($valor)
{
    return "R$ " . number_format((float) $valor, 2, ",", ".");
}

function nomePagamento($tipo)
{
    $pagamentos = [
        "PIX" => "Pix",
        "CARTAO_DEBITO" => "Cartão de débito",
        "CARTAO_CREDITO" => "Cartão de crédito",
        "BOLETO" => "Boleto",
        "OUTROS" => "Outros"
    ];

    return $pagamentos[$tipo] ?? ($tipo ?: "Não informado");
}

function dataValida($data)
{
    if (!is_string($data)) {
        return false;
    }

    $objetoData = DateTime::createFromFormat("!Y-m-d", $data);

    return $objetoData
        && $objetoData->format("Y-m-d") === $data;
}

/*
 * Filtros do relatório.
 */
$dataInicio = $_GET["inicio"] ?? date("Y-m-01");
$dataFim = $_GET["fim"] ?? date("Y-m-d");

if (!dataValida($dataInicio)) {
    $dataInicio = date("Y-m-01");
}

if (!dataValida($dataFim)) {
    $dataFim = date("Y-m-d");
}

if ($dataInicio > $dataFim) {
    $temporaria = $dataInicio;
    $dataInicio = $dataFim;
    $dataFim = $temporaria;
}

$horaInicio = $dataInicio . " 00:00:00";
$horaFimExclusiva = date(
    "Y-m-d 00:00:00",
    strtotime($dataFim . " +1 day")
);

/*
 * Busca todas as entradas do usuário no período,
 * incluindo as que vieram de depósitos/resgates de metas.
 */
$entradas = [];

$stmt = $conexao->prepare(
    "SELECT
        e.cd_entrada,
        e.vl_entrada,
        e.dt_entrada,
        e.ds_entrada,
        e.tp_pagamento,
        b.nm_banco
     FROM tb_entrada e
     INNER JOIN tb_banco b
         ON b.cd_banco = e.id_banco
     WHERE b.id_usuario = ?
       AND e.dt_entrada >= ?
       AND e.dt_entrada < ?
     ORDER BY e.dt_entrada DESC, e.cd_entrada DESC"
);
$stmt->bind_param(
    "iss",
    $usuarioId,
    $horaInicio,
    $horaFimExclusiva
);
$stmt->execute();
$resultadoEntradas = $stmt->get_result();

while ($linha = $resultadoEntradas->fetch_assoc()) {
    $entradas[] = $linha;
}

$stmt->close();

/*
 * Busca todas as saídas do usuário no período,
 * incluindo depósitos nas metas.
 */
$saidas = [];

$stmt = $conexao->prepare(
    "SELECT
        s.cd_saida,
        s.vl_saida,
        s.dt_saida,
        s.ds_saida,
        s.tp_pagamento,
        b.nm_banco
     FROM tb_saida s
     INNER JOIN tb_banco b
         ON b.cd_banco = s.id_banco
     WHERE b.id_usuario = ?
       AND s.dt_saida >= ?
       AND s.dt_saida < ?
     ORDER BY s.dt_saida DESC, s.cd_saida DESC"
);
$stmt->bind_param(
    "iss",
    $usuarioId,
    $horaInicio,
    $horaFimExclusiva
);
$stmt->execute();
$resultadoSaidas = $stmt->get_result();

while ($linha = $resultadoSaidas->fetch_assoc()) {
    $saidas[] = $linha;
}

$stmt->close();

/*
 * Totais do período.
 */
$totalEntradas = 0.0;

foreach ($entradas as $entrada) {
    $totalEntradas += (float) $entrada["vl_entrada"];
}

$totalSaidas = 0.0;

foreach ($saidas as $saida) {
    $totalSaidas += (float) $saida["vl_saida"];
}

$resultadoPeriodo = $totalEntradas - $totalSaidas;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

  <title>LEKAN - Relatórios</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
  <link rel="stylesheet" href="assets/css/sidebar.css">

  <style>
    .content {
      padding-top: 4px;
    }

    .page-title {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 6px;
    }

    .subtitle {
      font-size: 14px;
      color: #666;
      margin-bottom: 18px;
    }

    .panel {
      background: #f7f7f7;
      border: 1px solid #e7e7e7;
      border-radius: 14px;
      padding: 18px;
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.04);
    }

    .report-box {
      display: grid;
      grid-template-columns: repeat(3, minmax(180px, 1fr));
      gap: 18px;
    }

    .report-card {
      background: linear-gradient(180deg, #ffffff 0%, #f4f4f4 100%);
      border: 1px solid #e2e2e2;
      border-radius: 12px;
      padding: 18px;
    }

    .report-card .label {
      font-size: 12px;
      color: #666;
      margin-bottom: 10px;
    }

    .report-card .value {
      font-size: 24px;
      font-weight: 700;
      color: #1d1d1d;
    }

    .report-card .value.green {
      color: #2e8b57;
    }

    .report-card .value.red {
      color: #d04f4f;
    }

    .report-card .value.blue {
      color: #3d74d8;
    }

    .page-header {
      margin-bottom: 18px;
    }

    .filters {
      display: flex;
      align-items: end;
      gap: 12px;
      margin-bottom: 18px;
      background: #f7f7f7;
      border: 1px solid #e7e7e7;
      border-radius: 14px;
      padding: 14px 18px;
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.04);
    }

    .filter-field {
      min-width: 150px;
    }

    .filter-field label {
      display: block;
      margin-bottom: 6px;
      color: #555;
      font-size: 12px;
      font-weight: 700;
    }

    .filter-field input {
      width: 100%;
      height: 38px;
      padding: 0 10px;
      border: 1px solid #ddd;
      border-radius: 8px;
      background: #fff;
      font: inherit;
      font-size: 13px;
    }

    .filter-button,
    .pdf-button {
      min-height: 38px;
      border: 0;
      border-radius: 8px;
      padding: 0 15px;
      background: linear-gradient(180deg, #d84ef6 0%, #c740eb 100%);
      color: #fff;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 5px 12px rgba(178, 0, 217, 0.14);
    }

    .tables-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px;
      margin-top: 18px;
    }

    .table-panel {
      min-width: 0;
    }

    .table-panel h2 {
      margin: 0 0 14px;
      color: #292929;
      font-size: 16px;
      font-weight: 700;
    }

    .table-wrap {
      overflow-x: auto;
      background: #fff;
      border: 1px solid #e5e5e5;
      border-radius: 10px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 580px;
    }

    th {
      padding: 11px 10px;
      text-align: left;
      color: #555;
      font-size: 11px;
      font-weight: 700;
      background: #f3f3f3;
      border-bottom: 1px solid #e5e5e5;
    }

    td {
      padding: 11px 10px;
      color: #333;
      font-size: 12px;
      background: #fff;
      border-bottom: 1px solid #ededed;
      vertical-align: top;
    }

    tbody tr:last-child td {
      border-bottom: none;
    }

    tbody tr:hover td {
      background: #fcfcfc;
    }

    .value-entry,
    .value-output {
      font-weight: 700;
      white-space: nowrap;
    }

    .value-entry {
      color: #2e8b57;
    }

    .value-output {
      color: #d04f4f;
    }

    .empty {
      padding: 18px;
      text-align: center;
      color: #777;
      font-size: 13px;
    }

    .pdf-area {
      display: flex;
      justify-content: flex-end;
      margin-top: 18px;
    }

    @media print {
      .sidebar,
      .topbar,
      .filters,
      .pdf-area,
      .subtitle {
        display: none !important;
      }

      .main,
      .content,
      .dashboard,
      .app-shell {
        width: 100%;
        max-width: none;
        margin: 0;
        padding: 0;
      }

      .tables-grid {
        grid-template-columns: 1fr 1fr;
      }

      .panel {
        box-shadow: none;
      }

      .table-panel {
        break-inside: avoid;
      }
    }

    @media (max-width: 900px) {
      .report-box {
        grid-template-columns: 1fr;
      }

      .tables-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 700px) {
      .filters {
        align-items: stretch;
        flex-direction: column;
      }

      .filter-field,
      .filter-button,
      .pdf-area,
      .pdf-button {
        width: 100%;
      }

      .report-card .value {
        font-size: 22px;
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

          <a class="nav-item" href="grafico.php">
            <span class="nav-icon">▣</span>
            <span>Gráfico</span>
          </a>

          <a class="nav-item" href="metas.php">
            <span class="nav-icon">◔</span>
            <span>Metas</span>
          </a>

          <a class="nav-item active" href="relatorios.php">
            <span class="nav-icon">▤</span>
            <span>Relatório</span>
          </a>
        </nav>

        <div class="sidebar-footer">
          <div class="user-box">
            <div class="user-avatar">L+K</div>
            <div class="user-data">
               <strong>
              <?= htmlspecialchars(
                  $_SESSION["usuario_nome"] ?? "Usuário",
                  ENT_QUOTES,
                  "UTF-8"
              ) ?>
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
              <?php require __DIR__ . "/perfil-menu.php"; ?>
          </div>
        </div>

        <div class="content">
          <div class="page-header">
            <h1 class="page-title">Relatórios</h1>
            <div class="subtitle">
              Entradas e saídas completas por período
            </div>
          </div>

          <form class="filters"
                method="GET"
                action="relatorios.php">
            <div class="filter-field">
              <label for="inicio">Data inicial</label>
              <input type="date"
                     id="inicio"
                     name="inicio"
                     value="<?= escapar($dataInicio) ?>"
                     required>
            </div>

            <div class="filter-field">
              <label for="fim">Data final</label>
              <input type="date"
                     id="fim"
                     name="fim"
                     value="<?= escapar($dataFim) ?>"
                     required>
            </div>

            <button class="filter-button" type="submit">
              Aplicar filtro
            </button>
          </form>

          <section class="panel">
            <div class="report-box">
              <article class="report-card">
                <div class="label">Total de entradas</div>
                <div class="value green">
                  <?= moedaBR($totalEntradas) ?>
                </div>
              </article>

              <article class="report-card">
                <div class="label">Total de saídas</div>
                <div class="value red">
                  <?= moedaBR($totalSaidas) ?>
                </div>
              </article>

              <article class="report-card">
                <div class="label">Resultado no período</div>
                <div class="value blue">
                  <?= moedaBR($resultadoPeriodo) ?>
                </div>
              </article>
            </div>
          </section>

          <div class="tables-grid">
            <section class="panel table-panel">
              <h2>Entradas</h2>

              <div class="table-wrap">
                <?php if (count($entradas) === 0): ?>
                  <div class="empty">
                    Nenhuma entrada encontrada no período.
                  </div>
                <?php else: ?>
                  <table>
                    <thead>
                      <tr>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Conta</th>
                        <th>Pagamento</th>
                        <th>Valor</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($entradas as $entrada): ?>
                        <tr>
                          <td>
                            <?= date(
                                "d/m/Y H:i",
                                strtotime($entrada["dt_entrada"])
                            ) ?>
                          </td>
                          <td><?= escapar($entrada["ds_entrada"]) ?></td>
                          <td><?= escapar($entrada["nm_banco"]) ?></td>
                          <td>
                            <?= escapar(
                                nomePagamento(
                                    $entrada["tp_pagamento"] ?? ""
                                )
                            ) ?>
                          </td>
                          <td class="value-entry">
                            + <?= moedaBR($entrada["vl_entrada"]) ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                <?php endif; ?>
              </div>
            </section>

            <section class="panel table-panel">
              <h2>Saídas</h2>

              <div class="table-wrap">
                <?php if (count($saidas) === 0): ?>
                  <div class="empty">
                    Nenhuma saída encontrada no período.
                  </div>
                <?php else: ?>
                  <table>
                    <thead>
                      <tr>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Conta</th>
                        <th>Pagamento</th>
                        <th>Valor</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($saidas as $saida): ?>
                        <tr>
                          <td>
                            <?= date(
                                "d/m/Y H:i",
                                strtotime($saida["dt_saida"])
                            ) ?>
                          </td>
                          <td><?= escapar($saida["ds_saida"]) ?></td>
                          <td><?= escapar($saida["nm_banco"]) ?></td>
                          <td>
                            <?= escapar(
                                nomePagamento(
                                    $saida["tp_pagamento"] ?? ""
                                )
                            ) ?>
                          </td>
                          <td class="value-output">
                            − <?= moedaBR($saida["vl_saida"]) ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                <?php endif; ?>
              </div>
            </section>
          </div>

          <div class="pdf-area">
            <button
              class="pdf-button"
              type="button"
              onclick="window.print()"
            >
              Gerar PDF
            </button>
          </div>
        </div>
      </main>
    </div>
  </section>
</body>
</html>
