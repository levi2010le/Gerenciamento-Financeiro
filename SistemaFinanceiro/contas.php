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

function redirecionarContas()
{
    header("Location: contas.php");
    exit;
}

$mensagem = $_SESSION["contas_mensagem"] ?? "";
$tipoMensagem = $_SESSION["contas_tipo_mensagem"] ?? "";

unset(
    $_SESSION["contas_mensagem"],
    $_SESSION["contas_tipo_mensagem"]
);

$pagamentos = [
    "PIX" => "Pix",
    "CARTAO_DEBITO" => "Cartão de débito",
    "CARTAO_CREDITO" => "Cartão de crédito",
    "BOLETO" => "Boleto",
    "OUTROS" => "Outros"
];

$stmt = $conexao->prepare(
    "SELECT cd_banco FROM tb_banco
     WHERE id_usuario = ?
     ORDER BY cd_banco
     LIMIT 1"
);
$stmt->bind_param("i", $usuarioId);
$stmt->execute();
$contaBanco = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$contaBanco) {
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
    $bancoId = (int) $contaBanco["cd_banco"];
}

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

        if ($acao === "criar") {
            $descricao = trim($_POST["descricao"] ?? "");
            $valorTexto = $_POST["valor"] ?? "";
            $tipoPagamento = $_POST["tipo_pagamento"] ?? "";
            $vencimento = $_POST["dt_vencimento"] ?? "";

            if ($descricao === "" || strlen($descricao) > 250) {
                throw new DomainException(
                    "Informe uma descrição com até 250 caracteres."
                );
            }

            if (!is_numeric($valorTexto) || (float) $valorTexto <= 0) {
                throw new DomainException(
                    "Informe um valor maior que zero."
                );
            }

            if (!isset($pagamentos[$tipoPagamento])) {
                throw new DomainException(
                    "Selecione um tipo de pagamento válido."
                );
            }

            $dataValida = DateTime::createFromFormat(
                "!Y-m-d",
                $vencimento
            );

            if (
                !$dataValida
                || $dataValida->format("Y-m-d") !== $vencimento
            ) {
                throw new DomainException(
                    "Informe uma data de vencimento válida."
                );
            }

            $valor = round((float) $valorTexto, 2);

            $stmt = $conexao->prepare(
                "INSERT INTO tb_conta_pagar
                    (
                        id_usuario,
                        id_banco,
                        ds_descricao,
                        vl_conta,
                        tp_pagamento,
                        dt_vencimento
                    )
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "iisdss",
                $usuarioId,
                $bancoId,
                $descricao,
                $valor,
                $tipoPagamento,
                $vencimento
            );
            $stmt->execute();
            $stmt->close();

            $_SESSION["contas_mensagem"] = "Conta cadastrada.";
            $_SESSION["contas_tipo_mensagem"] = "sucesso";

            redirecionarContas();
        }

        if ($acao === "pagar") {
            $contaId = filter_var(
                $_POST["conta_id"] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$contaId) {
                throw new DomainException(
                    "A conta selecionada não é válida."
                );
            }

            $conexao->begin_transaction();
            $transacaoIniciada = true;

            $stmt = $conexao->prepare(
                "SELECT
                    cd_conta,
                    id_banco,
                    ds_descricao,
                    vl_conta,
                    tp_pagamento,
                    st_conta
                 FROM tb_conta_pagar
                 WHERE cd_conta = ?
                   AND id_usuario = ?
                 FOR UPDATE"
            );
            $stmt->bind_param("ii", $contaId, $usuarioId);
            $stmt->execute();
            $contaPagar = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$contaPagar) {
                throw new DomainException(
                    "A conta a pagar não foi encontrada."
                );
            }

            if ($contaPagar["st_conta"] !== "PENDENTE") {
                throw new DomainException(
                    "Esta conta já foi paga."
                );
            }

            if (!isset($pagamentos[$contaPagar["tp_pagamento"]])) {
                throw new DomainException(
                    "O tipo de pagamento desta conta é inválido."
                );
            }

            $bancoDaConta = (int) $contaPagar["id_banco"];
            $valorConta = (float) $contaPagar["vl_conta"];

            $stmt = $conexao->prepare(
                "SELECT ds_saldo
                 FROM tb_banco
                 WHERE cd_banco = ?
                   AND id_usuario = ?
                 FOR UPDATE"
            );
            $stmt->bind_param("ii", $bancoDaConta, $usuarioId);
            $stmt->execute();
            $banco = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$banco) {
                throw new DomainException(
                    "A conta bancária não foi encontrada."
                );
            }

            if ((float) $banco["ds_saldo"] < $valorConta) {
                throw new DomainException(
                    "Saldo insuficiente para pagar esta conta."
                );
            }

            $descricaoSaida = $contaPagar["ds_descricao"];
            $tipoPagamento = $contaPagar["tp_pagamento"];

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
                $valorConta,
                $descricaoSaida,
                $bancoDaConta,
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
                $valorConta,
                $bancoDaConta,
                $usuarioId
            );
            $stmt->execute();
            $stmt->close();

            $stmt = $conexao->prepare(
                "UPDATE tb_conta_pagar
                 SET st_conta = 'PAGA',
                     dt_pagamento = NOW()
                 WHERE cd_conta = ?
                   AND id_usuario = ?
                   AND st_conta = 'PENDENTE'"
            );
            $stmt->bind_param("ii", $contaId, $usuarioId);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                $stmt->close();

                throw new DomainException(
                    "Não foi possível atualizar o estado da conta."
                );
            }

            $stmt->close();

            $conexao->commit();
            $transacaoIniciada = false;

            $_SESSION["contas_mensagem"] =
                "Conta paga. A saída foi registrada e o saldo atualizado.";
            $_SESSION["contas_tipo_mensagem"] = "sucesso";

            redirecionarContas();
        }

        throw new DomainException("Ação inválida.");
    } catch (DomainException $erro) {
        if ($transacaoIniciada) {
            $conexao->rollback();
        }

        $_SESSION["contas_mensagem"] = $erro->getMessage();
        $_SESSION["contas_tipo_mensagem"] = "erro";

        redirecionarContas();
    } catch (Throwable $erro) {
        if ($transacaoIniciada) {
            $conexao->rollback();
        }

        $_SESSION["contas_mensagem"] =
            "Não foi possível processar a operação.";
        $_SESSION["contas_tipo_mensagem"] = "erro";

        redirecionarContas();
    }
}

$stmt = $conexao->prepare(
    "SELECT ds_saldo
     FROM tb_banco
     WHERE cd_banco = ?
       AND id_usuario = ?"
);
$stmt->bind_param("ii", $bancoId, $usuarioId);
$stmt->execute();
$saldoBanco = $stmt->get_result()->fetch_assoc();
$stmt->close();

$saldoDisponivel = (float) ($saldoBanco["ds_saldo"] ?? 0);

$stmt = $conexao->prepare(
    "SELECT
        COUNT(*) AS quantidade_pendentes,
        COALESCE(SUM(vl_conta), 0) AS total_pendente,
        COALESCE(
            SUM(
                CASE
                    WHEN dt_vencimento < CURDATE()
                    THEN vl_conta
                    ELSE 0
                END
            ),
            0
        ) AS total_atrasado
     FROM tb_conta_pagar
     WHERE id_usuario = ?
       AND st_conta = 'PENDENTE'"
);
$stmt->bind_param("i", $usuarioId);
$stmt->execute();
$resumo = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conexao->prepare(
    "SELECT
        cd_conta,
        ds_descricao,
        vl_conta,
        tp_pagamento,
        dt_vencimento,
        st_conta,
        dt_pagamento
     FROM tb_conta_pagar
     WHERE id_usuario = ?
     ORDER BY
        CASE WHEN st_conta = 'PENDENTE' THEN 0 ELSE 1 END,
        dt_vencimento ASC,
        cd_conta DESC"
);
$stmt->bind_param("i", $usuarioId);
$stmt->execute();
$resultadoContas = $stmt->get_result();

$contas = [];

while ($linha = $resultadoContas->fetch_assoc()) {
    $contas[] = $linha;
}

$stmt->close();

$quantidadePendentes =
    (int) ($resumo["quantidade_pendentes"] ?? 0);
$totalPendente =
    (float) ($resumo["total_pendente"] ?? 0);
$totalAtrasado =
    (float) ($resumo["total_atrasado"] ?? 0);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

  <title>LEKAN - Contas</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
  <link rel="stylesheet" href="assets/css/sidebar.css">

  <style>
    .content {
      padding-top: 4px;
    }

    .page-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 18px;
      gap: 16px;
    }

    .page-title {
      font-size: 20px;
      font-weight: 700;
      margin: 0;
    }

    .subtitle {
      font-size: 14px;
      color: #666;
      margin-top: 2px;
    }

    .btn-new {
      background: linear-gradient(180deg, #d84ef6 0%, #c340e9 100%);
      border: none;
      color: white;
      border-radius: 10px;
      padding: 12px 18px;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 6px 12px rgba(178, 0, 217, 0.18);
    }

    .balance-small {
      color: #666;
      font-size: 12px;
      margin-top: 5px;
    }

    .balance-small strong {
      color: #333;
      font-size: 13px;
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

    .cards {
      display: grid;
      grid-template-columns: repeat(2, minmax(250px, 1fr));
      gap: 18px;
      margin-bottom: 20px;
    }

    .card {
      border-radius: 12px;
      min-height: 90px;
      padding: 16px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border: 1px solid transparent;
    }

    .card.red {
      background: #fde9e9;
      border-color: #f3c0c0;
    }

    .card.blue {
      background: #eaf3ff;
      border-color: #bfd4ff;
    }

    .card-text {
      font-size: 13px;
      color: #333;
      font-weight: 600;
    }

    .card-value {
      font-size: 18px;
      font-weight: 700;
      margin-top: 4px;
    }

    .card.red .card-text,
    .card.red .card-value {
      color: #3f2a2a;
    }

    .card.blue .card-text,
    .card.blue .card-value {
      color: #24446e;
    }

    .add-account {
      margin-bottom: 18px;
      background: #f8f8f8;
      border: 1px solid #e5e5e5;
      border-radius: 12px;
      overflow: hidden;
    }

    .add-account summary {
      list-style: none;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 14px 16px;
      font-size: 14px;
      font-weight: 700;
      color: #a000bd;
    }

    .add-account summary::-webkit-details-marker {
      display: none;
    }

    .add-account summary::after {
      content: "+";
      font-size: 20px;
      line-height: 1;
    }

    .add-account[open] summary::after {
      content: "−";
    }

    .form-content {
      padding: 0 16px 16px;
      border-top: 1px solid #e8e8e8;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr auto;
      gap: 12px;
      align-items: end;
      padding-top: 16px;
    }

    .field label {
      display: block;
      margin-bottom: 6px;
      color: #333;
      font-size: 12px;
      font-weight: 700;
    }

    .field input,
    .field select {
      width: 100%;
      height: 40px;
      padding: 0 10px;
      border: 1px solid #ddd;
      border-radius: 8px;
      background: #fff;
      font: inherit;
      font-size: 13px;
    }

    .btn-submit {
      min-height: 40px;
      border: none;
      border-radius: 8px;
      padding: 0 16px;
      background: #b900d8;
      color: #fff;
      font-weight: 700;
      cursor: pointer;
    }

    .btn-mostrar-todas {
      border: 1px solid #ccc;
      border-radius: 8px;
      padding: 7px 10px;
      margin: 0 0 10px;
      background: #fff;
      color: #555;
      font: inherit;
      font-size: 12px;
      cursor: pointer;
    }

    .table-panel-wrapper {
      background: #f3f3f3;
      border-radius: 12px;
      overflow: hidden;
      border: 1px solid #e1e1e1;
    }

    .table-scroll {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 760px;
    }

    thead th {
      background: #f0f0f0;
      text-align: left;
      font-size: 12px;
      font-weight: 700;
      color: #444;
      padding: 14px 16px;
      border-bottom: 1px solid #ddd;
    }

    tbody td {
      padding: 13px 16px;
      font-size: 12px;
      color: #333;
      border-bottom: 1px solid #e1e1e1;
      background: #f7f7f7;
    }

    tbody tr:last-child td {
      border-bottom: none;
    }

    .valor {
      font-weight: 700;
      white-space: nowrap;
    }

    .status {
      display: inline-block;
      padding: 5px 9px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
      white-space: nowrap;
    }

    .status.pendente {
      background: #fff1d6;
      color: #8a5a00;
    }

    .status.atrasada {
      background: #ffe4e4;
      color: #b42323;
    }

    .status.paga {
      background: #e5f7e9;
      color: #21743a;
    }

    .vencimento-atrasado {
      color: #b42323;
      font-weight: 700;
    }

    .btn-pagar {
      background: linear-gradient(180deg, #d84ef6 0%, #c340e9 100%);
      border: none;
      color: white;
      border-radius: 8px;
      padding: 8px 13px;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
    }

    .btn-pagar:disabled {
      background: #bbb;
      cursor: not-allowed;
    }

    .acoes-conta {
      display: flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap;
    }

    .btn-ocultar {
      border: 1px solid #ccc;
      border-radius: 6px;
      padding: 5px 8px;
      background: #fff;
      color: #555;
      font: inherit;
      font-size: 11px;
      cursor: pointer;
    }

    .form-pagar {
      margin: 0;
    }

    .vazio {
      padding: 18px;
      color: #666;
      text-align: center;
      font-size: 13px;
    }

    @media (max-width: 1050px) {
      .form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .btn-submit {
        width: 100%;
      }
    }

    @media (max-width: 900px) {
      .cards {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 600px) {
      .page-header {
        align-items: flex-start;
        flex-direction: column;
      }

      .btn-new {
        width: 100%;
      }

      .form-grid {
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
          <a class="nav-item" href="home.php">
            <span class="nav-icon">◉</span>
            <span>Geral</span>
          </a>

          <a class="nav-item active" href="contas.php">
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
            <div>
              <h1 class="page-title">Contas a Pagar</h1>
              <div class="subtitle">Gerencie seus pagamentos</div>
              <div class="balance-small">
                Saldo disponível:
                <strong><?= moedaBR($saldoDisponivel) ?></strong>
              </div>
            </div>

            <button
              class="btn-new"
              type="button"
              onclick="document.getElementById('form-nova-conta').open = true;
                       document.getElementById('form-nova-conta').scrollIntoView({behavior: 'smooth'});"
            >
              + Nova Conta
            </button>
          </div>

          <?php if ($mensagem !== ""): ?>
            <div class="alerta <?= escapar($tipoMensagem) ?>">
              <?= escapar($mensagem) ?>
            </div>
          <?php endif; ?>

          <div class="cards">
            <div class="card red">
              <div>
                <div class="card-text">
                  Total em aberto (<?= $quantidadePendentes ?>)
                </div>
                <div class="card-value">
                  <?= moedaBR($totalPendente) ?>
                </div>
              </div>
            </div>

            <div class="card blue">
              <div>
                <div class="card-text">Total vencido</div>
                <div class="card-value">
                  <?= moedaBR($totalAtrasado) ?>
                </div>
              </div>
            </div>
          </div>

          <details class="add-account" id="form-nova-conta">
            <summary>Adicionar uma conta a pagar</summary>

            <div class="form-content">
              <form method="POST" action="contas.php">
                <input type="hidden"
                       name="csrf_token"
                       value="<?= escapar($_SESSION["csrf_token"]) ?>">
                <input type="hidden" name="acao" value="criar">

                <div class="form-grid">
                  <div class="field">
                    <label for="descricao">Descrição</label>
                    <input type="text"
                           id="descricao"
                           name="descricao"
                           maxlength="250"
                           placeholder="Ex.: Conta de internet"
                           required>
                  </div>

                  <div class="field">
                    <label for="valor">Valor</label>
                    <input type="number"
                           id="valor"
                           name="valor"
                           min="0.01"
                           step="0.01"
                           placeholder="0.00"
                           required>
                  </div>

                  <div class="field">
                    <label for="tipo_pagamento">Tipo</label>
                    <select id="tipo_pagamento"
                            name="tipo_pagamento"
                            required>
                      <option value="">Selecione</option>
                      <?php foreach ($pagamentos as $codigo => $nome): ?>
                        <option value="<?= escapar($codigo) ?>">
                          <?= escapar($nome) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="field">
                    <label for="dt_vencimento">Prazo / vencimento</label>
                    <input type="date"
                           id="dt_vencimento"
                           name="dt_vencimento"
                           required>
                  </div>

                  <button class="btn-submit" type="submit">
                    Adicionar
                  </button>
                </div>
              </form>
            </div>
          </details>

          <button
            class="btn-mostrar-todas"
            id="btn-mostrar-todas"
            type="button"
            hidden
          >
            Mostrar todas as contas
          </button>

          <div class="table-panel-wrapper">
            <div class="table-scroll">
              <?php if (count($contas) === 0): ?>
                <div class="vazio">
                  Nenhuma conta cadastrada.
                </div>
              <?php else: ?>
                <table>
                  <thead>
                    <tr>
                      <th>Vencimento</th>
                      <th>Descrição</th>
                      <th>Tipo</th>
                      <th>Valor</th>
                      <th>Status</th>
                      <th>Ação</th>
                    </tr>
                  </thead>

                  <tbody>
                    <?php foreach ($contas as $item): ?>
                      <?php
                        $estaPaga = $item["st_conta"] === "PAGA";
                        $estaAtrasada =
                            !$estaPaga
                            && $item["dt_vencimento"] < date("Y-m-d");

                        if ($estaPaga) {
                            $textoStatus = "Paga";
                            $classeStatus = "paga";
                        } elseif ($estaAtrasada) {
                            $textoStatus = "Atrasada";
                            $classeStatus = "atrasada";
                        } else {
                            $textoStatus = "Pendente";
                            $classeStatus = "pendente";
                        }

                        $codigoPagamento = $item["tp_pagamento"];
                        $nomePagamento =
                            $pagamentos[$codigoPagamento]
                            ?? $codigoPagamento;
                      ?>

                      <tr data-conta-id="<?= (int) $item["cd_conta"] ?>">
                        <td class="<?= $estaAtrasada
                            ? "vencimento-atrasado"
                            : "" ?>">
                          <?= date(
                              "d/m/Y",
                              strtotime($item["dt_vencimento"])
                          ) ?>
                        </td>

                        <td><?= escapar($item["ds_descricao"]) ?></td>
                        <td><?= escapar($nomePagamento) ?></td>

                        <td class="valor">
                          <?= moedaBR($item["vl_conta"]) ?>
                        </td>

                        <td>
                          <span class="status <?= escapar($classeStatus) ?>">
                            <?= escapar($textoStatus) ?>
                          </span>
                        </td>

                        <td>
                          <div class="acoes-conta">
                            <?php if (!$estaPaga): ?>
                              <form class="form-pagar"
                                    method="POST"
                                    action="contas.php"
                                    onsubmit="return confirm('Deseja pagar esta conta? O valor será descontado do saldo e registrado na Home.');">
                                <input type="hidden"
                                       name="csrf_token"
                                       value="<?= escapar($_SESSION["csrf_token"]) ?>">
                                <input type="hidden"
                                       name="acao"
                                       value="pagar">
                                <input type="hidden"
                                       name="conta_id"
                                       value="<?= (int) $item["cd_conta"] ?>">

                                <button class="btn-pagar"
                                        type="submit"
                                        <?= $saldoDisponivel < (float) $item["vl_conta"]
                                            ? "disabled"
                                            : "" ?>>
                                  Pagar
                                </button>
                              </form>
                            <?php elseif (!empty($item["dt_pagamento"])): ?>
                              <span>
                                Pago em <?= date(
                                    "d/m/Y",
                                    strtotime($item["dt_pagamento"])
                                ) ?>
                              </span>

                              <button
                                class="btn-ocultar"
                                type="button"
                                onclick="ocultarConta(this)"
                              >
                                Ocultar
                              </button>
                            <?php else: ?>
                              <span>—</span>

                              <button
                                class="btn-ocultar"
                                type="button"
                                onclick="ocultarConta(this)"
                              >
                                Ocultar
                              </button>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </main>
    </div>
  </section>

  <script>
    const chaveContasOcultas =
      "lekan-contas-ocultas-<?= $usuarioId ?>";

    function obterContasOcultas() {
      try {
        return JSON.parse(
          localStorage.getItem(chaveContasOcultas) || "[]"
        );
      } catch (erro) {
        return [];
      }
    }

    function salvarContasOcultas(ids) {
      localStorage.setItem(
        chaveContasOcultas,
        JSON.stringify(ids)
      );
    }

    function atualizarTabela() {
      const idsOcultos = obterContasOcultas();
      let quantidadeOculta = 0;

      document
        .querySelectorAll("tr[data-conta-id]")
        .forEach((linha) => {
          const id = linha.dataset.contaId;
          const estaOculta = idsOcultos.includes(id);

          linha.hidden = estaOculta;

          if (estaOculta) {
            quantidadeOculta++;
          }
        });

      const botaoMostrar =
        document.getElementById("btn-mostrar-todas");

      botaoMostrar.hidden = quantidadeOculta === 0;
    }

    function ocultarConta(botao) {
      const linha = botao.closest("tr");
      const id = linha.dataset.contaId;
      const idsOcultos = obterContasOcultas();

      if (!idsOcultos.includes(id)) {
        idsOcultos.push(id);
        salvarContasOcultas(idsOcultos);
      }

      atualizarTabela();
    }

    document
      .getElementById("btn-mostrar-todas")
      .addEventListener("click", () => {
        salvarContasOcultas([]);
        atualizarTabela();
      });

    atualizarTabela();
  </script>
</body>
</html>
