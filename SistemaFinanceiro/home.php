<?php
require_once __DIR__ . "/auth.php";

$usuarioId = (int) $_SESSION["usuario_id"];

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function moedaBR($valor)
{
    return "R$ " . number_format((float) $valor, 2, ",", ".");
}

function voltarParaHome()
{
    header("Location: home.php");
    exit;
}

$mensagem = $_SESSION["home_mensagem"] ?? "";
$tipoMensagem = $_SESSION["home_tipo_mensagem"] ?? "";

unset(
    $_SESSION["home_mensagem"],
    $_SESSION["home_tipo_mensagem"]
);

$pagamentosPermitidos = [
    "PIX",
    "CARTAO_DEBITO",
    "CARTAO_CREDITO",
    "BOLETO",
    "OUTROS"
];

// Conta principal do usuário.
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

if (!$conta) {
    $stmt = $conexao->prepare(
        "INSERT INTO tb_banco
            (nm_banco, ds_saldo, ds_moeda, id_usuario)
         VALUES ('Conta principal', 0.00, 'BRL', ?)"
    );
    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();
    $bancoId = (int) $conexao->insert_id;
    $stmt->close();
} else {
    $bancoId = (int) $conta["cd_banco"];
}

// Processamento dos formulários.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $transacaoIniciada = false;

    try {
        $tokenRecebido = $_POST["csrf_token"] ?? "";

        if (
            !is_string($tokenRecebido)
            || !hash_equals($_SESSION["csrf_token"], $tokenRecebido)
        ) {
            throw new DomainException(
                "A página expirou. Atualize e tente novamente."
            );
        }

        $acao = $_POST["acao"] ?? "";

        /*
         * Editar ou excluir uma movimentação.
         */
        if (in_array($acao, ["editar", "excluir"], true)) {
            $tipo = $_POST["tipo"] ?? "";
            $movimentacaoId = filter_var(
                $_POST["movimentacao_id"] ?? null,
                FILTER_VALIDATE_INT
            );

            if (
                !in_array($tipo, ["ENTRADA", "SAIDA"], true)
                || !$movimentacaoId
            ) {
                throw new DomainException(
                    "Movimentação inválida."
                );
            }

            $tabela =
                $tipo === "ENTRADA"
                    ? "tb_entrada"
                    : "tb_saida";
            $campoId =
                $tipo === "ENTRADA"
                    ? "cd_entrada"
                    : "cd_saida";
            $campoValor =
                $tipo === "ENTRADA"
                    ? "vl_entrada"
                    : "vl_saida";
            $campoDescricao =
                $tipo === "ENTRADA"
                    ? "ds_entrada"
                    : "ds_saida";

            $conexao->begin_transaction();
            $transacaoIniciada = true;

            $stmt = $conexao->prepare(
                "SELECT
                    m.{$campoValor} AS valor,
                    m.{$campoDescricao} AS descricao,
                    m.tp_pagamento,
                    m.id_banco
                 FROM {$tabela} m
                 INNER JOIN tb_banco b
                     ON b.cd_banco = m.id_banco
                 WHERE m.{$campoId} = ?
                   AND b.id_usuario = ?
                 FOR UPDATE"
            );
            $stmt->bind_param("ii", $movimentacaoId, $usuarioId);
            $stmt->execute();
            $movimentacaoAtual =
                $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$movimentacaoAtual) {
                throw new DomainException(
                    "Movimentação não encontrada."
                );
            }

            $bancoMovimento =
                (int) $movimentacaoAtual["id_banco"];
            $valorAntigo =
                (float) $movimentacaoAtual["valor"];

            if ($acao === "excluir") {
                $stmt = $conexao->prepare(
                    "DELETE FROM {$tabela}
                     WHERE {$campoId} = ?"
                );
                $stmt->bind_param("i", $movimentacaoId);
                $stmt->execute();
                $stmt->close();

                if ($tipo === "ENTRADA") {
                    $stmt = $conexao->prepare(
                        "UPDATE tb_banco
                         SET ds_saldo = ds_saldo - ?
                         WHERE cd_banco = ?
                           AND id_usuario = ?"
                    );
                } else {
                    $stmt = $conexao->prepare(
                        "UPDATE tb_banco
                         SET ds_saldo = ds_saldo + ?
                         WHERE cd_banco = ?
                           AND id_usuario = ?"
                    );
                }

                $stmt->bind_param(
                    "dii",
                    $valorAntigo,
                    $bancoMovimento,
                    $usuarioId
                );
                $stmt->execute();
                $stmt->close();

                $conexao->commit();
                $transacaoIniciada = false;

                $_SESSION["home_mensagem"] =
                    "Movimentação excluída.";
                $_SESSION["home_tipo_mensagem"] =
                    "sucesso";

                voltarParaHome();
            }

            $descricaoNova =
                trim($_POST["descricao"] ?? "");
            $valorNovoTexto =
                $_POST["valor"] ?? "";
            $tipoPagamentoNovo =
                $_POST["tipo_pagamento"] ?? "";

            if (
                $descricaoNova === ""
                || strlen($descricaoNova) > 250
            ) {
                throw new DomainException(
                    "Informe uma descrição válida."
                );
            }

            if (
                !is_numeric($valorNovoTexto)
                || (float) $valorNovoTexto <= 0
            ) {
                throw new DomainException(
                    "Informe um valor maior que zero."
                );
            }

            if (
                !in_array(
                    $tipoPagamentoNovo,
                    $pagamentosPermitidos,
                    true
                )
            ) {
                throw new DomainException(
                    "Selecione um tipo de pagamento válido."
                );
            }

            $valorNovo =
                round((float) $valorNovoTexto, 2);

            // Estorna o efeito da movimentação antiga.
            if ($tipo === "ENTRADA") {
                $stmt = $conexao->prepare(
                    "UPDATE tb_banco
                     SET ds_saldo = ds_saldo - ?
                     WHERE cd_banco = ?
                       AND id_usuario = ?"
                );
            } else {
                $stmt = $conexao->prepare(
                    "UPDATE tb_banco
                     SET ds_saldo = ds_saldo + ?
                     WHERE cd_banco = ?
                       AND id_usuario = ?"
                );
            }

            $stmt->bind_param(
                "dii",
                $valorAntigo,
                $bancoMovimento,
                $usuarioId
            );
            $stmt->execute();
            $stmt->close();

            // Impede saída maior que o saldo atual.
            if ($tipo === "SAIDA") {
                $stmt = $conexao->prepare(
                    "SELECT ds_saldo
                     FROM tb_banco
                     WHERE cd_banco = ?
                       AND id_usuario = ?
                     FOR UPDATE"
                );
                $stmt->bind_param(
                    "ii",
                    $bancoMovimento,
                    $usuarioId
                );
                $stmt->execute();
                $bancoAtual =
                    $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (
                    !$bancoAtual
                    || (float) $bancoAtual["ds_saldo"] < $valorNovo
                ) {
                    throw new DomainException(
                        "Saldo insuficiente para esse novo valor."
                    );
                }
            }

            $stmt = $conexao->prepare(
                "UPDATE {$tabela}
                 SET {$campoValor} = ?,
                     {$campoDescricao} = ?,
                     tp_pagamento = ?
                 WHERE {$campoId} = ?"
            );
            $stmt->bind_param(
                "dssi",
                $valorNovo,
                $descricaoNova,
                $tipoPagamentoNovo,
                $movimentacaoId
            );
            $stmt->execute();
            $stmt->close();

            // Aplica o novo efeito no saldo.
            if ($tipo === "ENTRADA") {
                $stmt = $conexao->prepare(
                    "UPDATE tb_banco
                     SET ds_saldo = ds_saldo + ?
                     WHERE cd_banco = ?
                       AND id_usuario = ?"
                );
            } else {
                $stmt = $conexao->prepare(
                    "UPDATE tb_banco
                     SET ds_saldo = ds_saldo - ?
                     WHERE cd_banco = ?
                       AND id_usuario = ?"
                );
            }

            $stmt->bind_param(
                "dii",
                $valorNovo,
                $bancoMovimento,
                $usuarioId
            );
            $stmt->execute();
            $stmt->close();

            $conexao->commit();
            $transacaoIniciada = false;

            $_SESSION["home_mensagem"] =
                "Movimentação atualizada.";
            $_SESSION["home_tipo_mensagem"] =
                "sucesso";

            voltarParaHome();
        }

        /*
         * Criar nova entrada ou saída.
         */
        $tipo = $_POST["tipo"] ?? "";
        $descricao = trim($_POST["descricao"] ?? "");
        $valorTexto = $_POST["valor"] ?? "";
        $tipoPagamento = $_POST["tipo_pagamento"] ?? "";

        if (!in_array($tipo, ["ENTRADA", "SAIDA"], true)) {
            throw new DomainException(
                "Selecione um tipo de movimentação válido."
            );
        }

        if (
            $descricao === ""
            || strlen($descricao) > 250
        ) {
            throw new DomainException(
                "Informe uma descrição com até 250 caracteres."
            );
        }

        if (
            !is_numeric($valorTexto)
            || (float) $valorTexto <= 0
        ) {
            throw new DomainException(
                "Informe um valor maior que zero."
            );
        }

        if (
            !in_array(
                $tipoPagamento,
                $pagamentosPermitidos,
                true
            )
        ) {
            throw new DomainException(
                "Selecione um tipo de pagamento válido."
            );
        }

        $valor = round((float) $valorTexto, 2);

        $conexao->begin_transaction();
        $transacaoIniciada = true;

        $stmt = $conexao->prepare(
            "SELECT ds_saldo
             FROM tb_banco
             WHERE cd_banco = ?
               AND id_usuario = ?
             FOR UPDATE"
        );
        $stmt->bind_param("ii", $bancoId, $usuarioId);
        $stmt->execute();
        $banco = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$banco) {
            throw new DomainException(
                "Conta principal não encontrada."
            );
        }

        if ($tipo === "ENTRADA") {
            $stmt = $conexao->prepare(
                "INSERT INTO tb_entrada
                    (
                        vl_entrada,
                        dt_entrada,
                        ds_entrada,
                        id_banco,
                        tp_pagamento
                    )
                 VALUES (?, NOW(), ?, ?, ?)"
            );
            $stmt->bind_param(
                "dsis",
                $valor,
                $descricao,
                $bancoId,
                $tipoPagamento
            );
            $stmt->execute();
            $stmt->close();

            $stmt = $conexao->prepare(
                "UPDATE tb_banco
                 SET ds_saldo = ds_saldo + ?
                 WHERE cd_banco = ?
                   AND id_usuario = ?"
            );
            $stmt->bind_param(
                "dii",
                $valor,
                $bancoId,
                $usuarioId
            );
            $stmt->execute();
            $stmt->close();

            $mensagemSucesso =
                "Entrada registrada.";
        } else {
            if ((float) $banco["ds_saldo"] < $valor) {
                throw new DomainException(
                    "Saldo insuficiente para registrar essa saída."
                );
            }

            $stmt = $conexao->prepare(
                "INSERT INTO tb_saida
                    (
                        vl_saida,
                        dt_saida,
                        ds_saida,
                        id_banco,
                        tp_pagamento
                    )
                 VALUES (?, NOW(), ?, ?, ?)"
            );
            $stmt->bind_param(
                "dsis",
                $valor,
                $descricao,
                $bancoId,
                $tipoPagamento
            );
            $stmt->execute();
            $stmt->close();

            $stmt = $conexao->prepare(
                "UPDATE tb_banco
                 SET ds_saldo = ds_saldo - ?
                 WHERE cd_banco = ?
                   AND id_usuario = ?"
            );
            $stmt->bind_param(
                "dii",
                $valor,
                $bancoId,
                $usuarioId
            );
            $stmt->execute();
            $stmt->close();

            $mensagemSucesso =
                "Saída registrada.";
        }

        $conexao->commit();
        $transacaoIniciada = false;

        $_SESSION["home_mensagem"] =
            $mensagemSucesso;
        $_SESSION["home_tipo_mensagem"] =
            "sucesso";

        voltarParaHome();
    } catch (DomainException $erro) {
        if ($transacaoIniciada) {
            $conexao->rollback();
        }

        $mensagem = $erro->getMessage();
        $tipoMensagem = "erro";
    } catch (Throwable $erro) {
        if ($transacaoIniciada) {
            $conexao->rollback();
        }

        $mensagem =
            "Não foi possível processar a movimentação.";
        $tipoMensagem =
            "erro";
    }
}

/*
 * Resumo da conta.
 */
$inicioMes =
    date("Y-m-01 00:00:00");

$stmt = $conexao->prepare(
    "SELECT
        (
            SELECT COALESCE(ds_saldo, 0)
            FROM tb_banco
            WHERE cd_banco = ? AND id_usuario = ?
        ) AS saldo_total,
        (
            SELECT COALESCE(SUM(vl_entrada), 0)
            FROM tb_entrada
            WHERE id_banco = ? AND dt_entrada >= ?
        ) AS entradas_mes,
        (
            SELECT COALESCE(SUM(vl_saida), 0)
            FROM tb_saida
            WHERE id_banco = ? AND dt_saida >= ?
        ) AS saidas_mes"
);
$stmt->bind_param(
    "iiisis",
    $bancoId,
    $usuarioId,
    $bancoId,
    $inicioMes,
    $bancoId,
    $inicioMes
);
$stmt->execute();
$resumo =
    $stmt->get_result()->fetch_assoc();
$stmt->close();

/*
 * Dados dos últimos seis meses.
 */
$inicioGrafico =
    new DateTime("first day of this month");
$inicioGrafico->modify("-5 months");
$dataInicioGrafico =
    $inicioGrafico->format("Y-m-01 00:00:00");

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
     GROUP BY movimentos.mes"
);
$stmt->bind_param(
    "isis",
    $bancoId,
    $dataInicioGrafico,
    $bancoId,
    $dataInicioGrafico
);
$stmt->execute();
$resultado =
    $stmt->get_result();

$dadosPorMes = [];

while ($linha = $resultado->fetch_assoc()) {
    $dadosPorMes[$linha["mes"]] = [
        "entradas" => (float) $linha["entradas"],
        "saidas" => (float) $linha["saidas"]
    ];
}

$stmt->close();

$nomesMeses = [
    1 => "Jan",
    2 => "Fev",
    3 => "Mar",
    4 => "Abr",
    5 => "Mai",
    6 => "Jun",
    7 => "Jul",
    8 => "Ago",
    9 => "Set",
    10 => "Out",
    11 => "Nov",
    12 => "Dez"
];

$mesesGrafico = [];
$maiorValorGrafico = 0;

for ($i = 0; $i < 6; $i++) {
    $dataMes = clone $inicioGrafico;
    $dataMes->modify("+{$i} months");

    $chaveMes =
        $dataMes->format("Y-m");

    $entradas =
        $dadosPorMes[$chaveMes]["entradas"] ?? 0;
    $saidas =
        $dadosPorMes[$chaveMes]["saidas"] ?? 0;

    $mesesGrafico[] = [
        "nome" =>
            $nomesMeses[(int) $dataMes->format("n")],
        "entradas" => $entradas,
        "saidas" => $saidas
    ];

    $maiorValorGrafico = max(
        $maiorValorGrafico,
        $entradas,
        $saidas
    );
}

/*
 * Quatro últimas movimentações.
 */
$stmt = $conexao->prepare(
    "SELECT *
     FROM (
        SELECT
            cd_entrada AS movimentacao_id,
            dt_entrada AS data_movimento,
            ds_entrada AS descricao,
            vl_entrada AS valor,
            tp_pagamento,
            'ENTRADA' AS tipo
        FROM tb_entrada
        WHERE id_banco = ?

        UNION ALL

        SELECT
            cd_saida AS movimentacao_id,
            dt_saida AS data_movimento,
            ds_saida AS descricao,
            vl_saida AS valor,
            tp_pagamento,
            'SAIDA' AS tipo
        FROM tb_saida
        WHERE id_banco = ?
     ) AS movimentacoes
     ORDER BY data_movimento DESC
     LIMIT 4"
);
$stmt->bind_param("ii", $bancoId, $bancoId);
$stmt->execute();
$resultado =
    $stmt->get_result();

$movimentacoes = [];

while ($linha = $resultado->fetch_assoc()) {
    $movimentacoes[] = $linha;
}

$stmt->close();

/*
 * Movimentações restantes para “Ver mais”.
 */
$stmt = $conexao->prepare(
    "SELECT *
     FROM (
        SELECT
            cd_entrada AS movimentacao_id,
            dt_entrada AS data_movimento,
            ds_entrada AS descricao,
            vl_entrada AS valor,
            tp_pagamento,
            'ENTRADA' AS tipo
        FROM tb_entrada
        WHERE id_banco = ?

        UNION ALL

        SELECT
            cd_saida AS movimentacao_id,
            dt_saida AS data_movimento,
            ds_saida AS descricao,
            vl_saida AS valor,
            tp_pagamento,
            'SAIDA' AS tipo
        FROM tb_saida
        WHERE id_banco = ?
     ) AS movimentacoes
     ORDER BY data_movimento DESC
     LIMIT 4, 18446744073709551615"
);
$stmt->bind_param("ii", $bancoId, $bancoId);
$stmt->execute();
$resultado =
    $stmt->get_result();

$movimentacoesExtras = [];

while ($linha = $resultado->fetch_assoc()) {
    $movimentacoesExtras[] = $linha;
}

$stmt->close();

$saldoTotal =
    (float) ($resumo["saldo_total"] ?? 0);
$entradasMes =
    (float) ($resumo["entradas_mes"] ?? 0);
$saidasMes =
    (float) ($resumo["saidas_mes"] ?? 0);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

  <title>LEKAN - Home</title>

  <link rel="preconnect"
        href="https://fonts.googleapis.com">
  <link rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
  <link rel="stylesheet"
        href="assets/css/sidebar.css">

  <style>
    .content {
      padding-top: 4px;
    }

    .page-title {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 4px;
    }

    .subtitle {
      font-size: 14px;
      color: #666;
      margin: 0 0 18px;
    }

    .alerta {
      padding: 11px 14px;
      border-radius: 8px;
      margin-bottom: 16px;
      font-size: 14px;
    }

    .alerta.sucesso {
      background: #effaf2;
      color: #16803c;
    }

    .alerta.erro {
      background: #fff0f0;
      color: #c00000;
    }

    .stats {
      display: grid;
      grid-template-columns: repeat(3, minmax(160px, 1fr));
      gap: 16px;
      margin-bottom: 18px;
    }

    .stat-card,
    .panel,
    .quick-panel {
      background: #f8f8f8;
      border: 1px solid #e5e5e5;
      border-radius: 14px;
      padding: 16px;
    }

    .stat-label {
      color: #555;
      font-size: 13px;
      font-weight: 600;
    }

    .stat-value {
      color: #1a1a1a;
      font-size: 20px;
      font-weight: 700;
      margin-top: 8px;
    }

    .stat-note {
      color: #666;
      font-size: 12px;
      margin-top: 6px;
    }

    .bottom-row {
      display: grid;
      grid-template-columns: 1.5fr 1fr;
      gap: 18px;
      align-items: start;
    }

    .panel-title {
      font-size: 15px;
      font-weight: 700;
      margin-bottom: 12px;
    }

    .chart-legend {
      display: flex;
      gap: 16px;
      font-size: 12px;
      color: #666;
      margin-bottom: 12px;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 7px;
    }

    .legend-dot {
      width: 10px;
      height: 10px;
      border-radius: 3px;
    }

    .chart {
      min-height: 210px;
      display: grid;
      grid-template-columns: repeat(6, minmax(0, 1fr));
      gap: 12px;
      align-items: end;
      border-bottom: 1px solid #ddd;
      padding-top: 10px;
    }

    .month-column {
      height: 190px;
      display: flex;
      flex-direction: column;
      justify-content: end;
      align-items: center;
      gap: 6px;
      min-width: 0;
    }

    .bars {
      height: 160px;
      display: flex;
      align-items: end;
      justify-content: center;
      gap: 5px;
      width: 100%;
    }

    .bar {
      width: min(28px, 38%);
      border-radius: 5px 5px 0 0;
    }

    .bar.entrada {
      background: #8bcf78;
    }

    .bar.saida {
      background: #ef8585;
    }

    .month-name {
      color: #666;
      font-size: 11px;
    }

    .movement-list {
      display: flex;
      flex-direction: column;
      gap: 9px;
    }

    .movement-item {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 10px;
      background: #fff;
      border: 1px solid #e6e6e6;
      border-radius: 9px;
      padding: 10px;
    }

    .movement-description {
      font-size: 13px;
      font-weight: 700;
      color: #333;
    }

    .movement-meta {
      margin-top: 3px;
      color: #777;
      font-size: 11px;
    }

    .movement-value {
      font-size: 13px;
      font-weight: 700;
      white-space: nowrap;
    }

    .movement-value.entrada {
      color: #268043;
    }

    .movement-value.saida {
      color: #c44040;
    }

    .movement-actions,
    .more-movements {
      margin-top: 8px;
      font-size: 11px;
    }

    .movement-actions summary,
    .more-movements summary {
      cursor: pointer;
      color: #a000bd;
      font-weight: 700;
    }

    .movement-actions form {
      margin-top: 8px;
    }

    .movement-actions .field {
      margin-bottom: 8px;
    }

    .movement-actions .field input,
    .movement-actions .field select {
      height: 34px;
      font-size: 12px;
    }

    .delete-btn {
      width: 100%;
      min-height: 34px;
      border: 0;
      border-radius: 8px;
      background: #ef8585;
      color: #541d1d;
      font-weight: 700;
      cursor: pointer;
    }

    .quick-entry {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px;
      margin-top: 18px;
    }

    .quick-panel h2 {
      font-size: 16px;
      margin: 0 0 14px;
    }

    .quick-panel.entrada {
      border-top: 4px solid #7bc06c;
    }

    .quick-panel.saida {
      border-top: 4px solid #ef7a7a;
    }

    .field {
      margin-bottom: 12px;
    }

    .field label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 5px;
    }

    .field input,
    .field select {
      width: 100%;
      height: 40px;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 0 10px;
      background: #fff;
      font: inherit;
      font-size: 13px;
    }

    .save-btn {
      width: 100%;
      min-height: 40px;
      border: 0;
      border-radius: 8px;
      background: #8bcf78;
      color: #183c1d;
      font-weight: 700;
      cursor: pointer;
    }

    .saida .save-btn {
      background: #ef8585;
      color: #541d1d;
    }

    .empty {
      color: #777;
      font-size: 13px;
      padding: 8px 0;
    }

    @media (max-width: 900px) {
      .stats,
      .bottom-row,
      .quick-entry {
        grid-template-columns: 1fr;
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
          <a class="nav-item active" href="home.php">
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
              <?= htmlspecialchars(
                  $_SESSION["usuario_nome"] ?? "Usuário",
                  ENT_QUOTES,
                  "UTF-8"
              ) ?>
            </strong>
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
          <div class="page-title">Visão Geral</div>

          <div class="subtitle">
            Acompanhe suas contas e movimentações
          </div>

          <?php if ($mensagem !== ""): ?>
            <div class="alerta <?= escapar($tipoMensagem) ?>">
              <?= escapar($mensagem) ?>
            </div>
          <?php endif; ?>

          <div class="stats">
            <section class="stat-card">
              <div class="stat-label">Saldo total</div>
              <div class="stat-value">
                <?= moedaBR($saldoTotal) ?>
              </div>
              <div class="stat-note">
                Saldo da conta principal
              </div>
            </section>

            <section class="stat-card">
              <div class="stat-label">
                Entradas neste mês
              </div>
              <div class="stat-value">
                <?= moedaBR($entradasMes) ?>
              </div>
            </section>

            <section class="stat-card">
              <div class="stat-label">
                Saídas neste mês
              </div>
              <div class="stat-value">
                <?= moedaBR($saidasMes) ?>
              </div>
            </section>
          </div>

          <div class="bottom-row">
            <section class="panel">
              <div class="panel-title">
                Entradas e saídas — últimos 6 meses
              </div>

              <div class="chart-legend">
                <div class="legend-item">
                  <span class="legend-dot"
                        style="background:#8bcf78"></span>
                  Entradas
                </div>

                <div class="legend-item">
                  <span class="legend-dot"
                        style="background:#ef8585"></span>
                  Saídas
                </div>
              </div>

              <div class="chart">
                <?php foreach ($mesesGrafico as $mes): ?>
                  <?php
                    $alturaEntrada =
                        $maiorValorGrafico > 0
                            ? (int) round(
                                (
                                    $mes["entradas"]
                                    / $maiorValorGrafico
                                ) * 150
                              )
                            : 0;

                    $alturaSaida =
                        $maiorValorGrafico > 0
                            ? (int) round(
                                (
                                    $mes["saidas"]
                                    / $maiorValorGrafico
                                ) * 150
                              )
                            : 0;
                  ?>

                  <div class="month-column">
                    <div class="bars">
                      <div class="bar entrada"
                           style="height: <?= $alturaEntrada ?>px"
                           title="Entradas:
                           <?= escapar(
                               moedaBR($mes["entradas"])
                           ) ?>">
                      </div>

                      <div class="bar saida"
                           style="height: <?= $alturaSaida ?>px"
                           title="Saídas:
                           <?= escapar(
                               moedaBR($mes["saidas"])
                           ) ?>">
                      </div>
                    </div>

                    <div class="month-name">
                      <?= escapar($mes["nome"]) ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </section>

            <section class="panel">
              <div class="panel-title">
                Últimas movimentações
              </div>

              <?php
                function renderizarMovimentacao(
                    array $movimento,
                    array $pagamentosPermitidos
                ): void {
                    global $conexao;

                    $ehEntrada =
                        $movimento["tipo"] === "ENTRADA";
                    ?>

                    <div class="movement-item">
                      <div>
                        <div class="movement-description">
                          <?= escapar(
                              $movimento["descricao"]
                          ) ?>
                        </div>

                        <div class="movement-meta">
                          <?= $ehEntrada
                              ? "Entrada"
                              : "Saída" ?>

                          ·

                          <?= escapar(
                              $movimento["tp_pagamento"]
                          ) ?>

                          ·

                          <?= date(
                              "d/m/Y H:i",
                              strtotime(
                                  $movimento["data_movimento"]
                              )
                          ) ?>
                        </div>

                        <details class="movement-actions">
                          <summary>Editar / excluir</summary>

                          <form method="POST"
                                action="home.php">
                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?= escapar(
                                       $_SESSION["csrf_token"]
                                   ) ?>">
                            <input type="hidden"
                                   name="acao"
                                   value="editar">
                            <input type="hidden"
                                   name="tipo"
                                   value="<?= escapar(
                                       $movimento["tipo"]
                                   ) ?>">
                            <input type="hidden"
                                   name="movimentacao_id"
                                   value="<?= (int)
                                       $movimento["movimentacao_id"] ?>">

                            <div class="field">
                              <label>Descrição</label>
                              <input type="text"
                                     name="descricao"
                                     maxlength="250"
                                     value="<?= escapar(
                                         $movimento["descricao"]
                                     ) ?>"
                                     required>
                            </div>

                            <div class="field">
                              <label>Valor</label>
                              <input type="number"
                                     name="valor"
                                     min="0.01"
                                     step="0.01"
                                     value="<?= number_format(
                                         (float) $movimento["valor"],
                                         2,
                                         ".",
                                         ""
                                     ) ?>"
                                     required>
                            </div>

                            <div class="field">
                              <label>Tipo de pagamento</label>
                              <select name="tipo_pagamento"
                                      required>
                                <option value="PIX"
                                  <?= $movimento["tp_pagamento"] === "PIX"
                                      ? "selected"
                                      : "" ?>>
                                  Pix
                                </option>

                                <option value="CARTAO_DEBITO"
                                  <?= $movimento["tp_pagamento"] === "CARTAO_DEBITO"
                                      ? "selected"
                                      : "" ?>>
                                  Cartão de débito
                                </option>

                                <option value="CARTAO_CREDITO"
                                  <?= $movimento["tp_pagamento"] === "CARTAO_CREDITO"
                                      ? "selected"
                                      : "" ?>>
                                  Cartão de crédito
                                </option>

                                <option value="BOLETO"
                                  <?= $movimento["tp_pagamento"] === "BOLETO"
                                      ? "selected"
                                      : "" ?>>
                                  Boleto
                                </option>

                                <option value="OUTROS"
                                  <?= $movimento["tp_pagamento"] === "OUTROS"
                                      ? "selected"
                                      : "" ?>>
                                  Outros
                                </option>
                              </select>
                            </div>

                            <button class="save-btn"
                                    type="submit">
                              Salvar alterações
                            </button>
                          </form>

                          <form method="POST"
                                action="home.php"
                                onsubmit="return confirm('Deseja excluir esta movimentação?');">
                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?= escapar(
                                       $_SESSION["csrf_token"]
                                   ) ?>">
                            <input type="hidden"
                                   name="acao"
                                   value="excluir">
                            <input type="hidden"
                                   name="tipo"
                                   value="<?= escapar(
                                       $movimento["tipo"]
                                   ) ?>">
                            <input type="hidden"
                                   name="movimentacao_id"
                                   value="<?= (int)
                                       $movimento["movimentacao_id"] ?>">

                            <button class="delete-btn"
                                    type="submit">
                              Excluir movimentação
                            </button>
                          </form>
                        </details>
                      </div>

                      <div class="movement-value
                          <?= $ehEntrada
                              ? "entrada"
                              : "saida" ?>">
                        <?= $ehEntrada ? "+" : "−" ?>
                        <?= moedaBR(
                            $movimento["valor"]
                        ) ?>
                      </div>
                    </div>
                    <?php
                }
              ?>

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
                      renderizarMovimentacao(
                          $movimento,
                          $pagamentosPermitidos
                      );
                    ?>
                  <?php endforeach; ?>
                </div>

                <?php if (
                    count($movimentacoesExtras) > 0
                ): ?>
                  <details class="more-movements">
                    <summary>
                      Ver mais movimentações
                    </summary>

                    <div class="movement-list"
                         style="margin-top:10px">
                      <?php foreach (
                          $movimentacoesExtras
                          as $movimento
                      ): ?>
                        <?php
                          renderizarMovimentacao(
                              $movimento,
                              $pagamentosPermitidos
                          );
                        ?>
                      <?php endforeach; ?>
                    </div>
                  </details>
                <?php endif; ?>
              <?php endif; ?>
            </section>
          </div>

          <div class="quick-entry">
            <?php foreach (
                [
                    "ENTRADA" => "Entrada",
                    "SAIDA" => "Saída"
                ]
                as $tipo => $rotulo
            ): ?>
              <?php
                $classe =
                    $tipo === "ENTRADA"
                        ? "entrada"
                        : "saida";
              ?>

              <form class="quick-panel <?= $classe ?>"
                    method="POST"
                    action="home.php">
                <h2>
                  Adicionar <?= strtolower($rotulo) ?>
                </h2>

                <input type="hidden"
                       name="csrf_token"
                       value="<?= escapar(
                           $_SESSION["csrf_token"]
                       ) ?>">
                <input type="hidden"
                       name="tipo"
                       value="<?= escapar($tipo) ?>">

                <div class="field">
                  <label>
                    Descrição
                  </label>

                  <input type="text"
                         name="descricao"
                         maxlength="250"
                         placeholder="<?= $tipo === "ENTRADA"
                             ? "Ex.: Pagamento de cliente"
                             : "Ex.: Conta de luz" ?>"
                         required>
                </div>

                <div class="field">
                  <label>
                    Valor (R$)
                  </label>

                  <input type="number"
                         name="valor"
                         min="0.01"
                         step="0.01"
                         placeholder="0.00"
                         required>
                </div>

                <div class="field">
                  <label>
                    Tipo de pagamento
                  </label>

                  <select name="tipo_pagamento"
                          required>
                    <option value="">
                      Selecione o pagamento
                    </option>
                    <option value="PIX">Pix</option>
                    <option value="CARTAO_DEBITO">
                      Cartão de débito
                    </option>
                    <option value="CARTAO_CREDITO">
                      Cartão de crédito
                    </option>
                    <option value="BOLETO">
                      Boleto
                    </option>
                    <option value="OUTROS">
                      Outros
                    </option>
                  </select>
                </div>

                <button class="save-btn"
                        type="submit">
                  Salvar <?= strtolower($rotulo) ?>
                </button>
              </form>
            <?php endforeach; ?>
          </div>
        </div>
      </main>
    </div>
  </section>
</body>
</html>
