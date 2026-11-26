<?php
require_once __DIR__ . "/auth.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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

function redirecionarMetas()
{
    header("Location: metas.php");
    exit;
}

$mensagem = $_SESSION["metas_mensagem"] ?? "";
$tipoMensagem = $_SESSION["metas_tipo_mensagem"] ?? "";

unset(
    $_SESSION["metas_mensagem"],
    $_SESSION["metas_tipo_mensagem"]
);

$stmt = $conexao->prepare(
    "SELECT cd_banco
     FROM tb_banco
     WHERE id_usuario = ?
     ORDER BY cd_banco
     LIMIT 1"
);
$stmt->bind_param("i", $usuarioId);
$stmt->execute();
$contaBanco = $stmt->get_result()->fetch_assoc();
$stmt->close();

$bancoPrincipalId = $contaBanco
    ? (int) $contaBanco["cd_banco"]
    : null;

$inicioMes = date("Y-m-01 00:00:00");
$inicioProximoMes = date(
    "Y-m-d 00:00:00",
    strtotime("first day of next month")
);

$gastosMes = [];

$stmt = $conexao->prepare(
    "SELECT
        s.ds_saida AS descricao,
        SUM(s.vl_saida) AS total
     FROM tb_saida s
     INNER JOIN tb_banco b
         ON b.cd_banco = s.id_banco
     WHERE b.id_usuario = ?
       AND s.dt_saida >= ?
       AND s.dt_saida < ?
     GROUP BY s.ds_saida
     ORDER BY total DESC"
);
$stmt->bind_param(
    "iss",
    $usuarioId,
    $inicioMes,
    $inicioProximoMes
);
$stmt->execute();
$resultadoGastos = $stmt->get_result();

while ($linha = $resultadoGastos->fetch_assoc()) {
    $descricaoGasto = (string) $linha["descricao"];

    $gastosMes[] = [
        "descricao" => $descricaoGasto,
        "total" => (float) $linha["total"],
        "hash" => hash("sha256", $descricaoGasto)
    ];
}

$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $transacaoIniciada = false;

    try {
        $tokenRecebido = $_POST["csrf_token"] ?? "";

        if (
            !is_string($tokenRecebido)
            || !hash_equals(
                $_SESSION["csrf_token"],
                $tokenRecebido
            )
        ) {
            throw new DomainException(
                "A página expirou. Atualize e tente novamente."
            );
        }

        $acao = $_POST["acao"] ?? "";

        if ($acao === "criar") {
            $nome = trim($_POST["nm_meta"] ?? "");
            $descricao = trim($_POST["ds_meta"] ?? "");
            $valorObjetivoTexto = $_POST["vl_objetivo"] ?? "";
            $prazo = $_POST["dt_prazo"] ?? "";

            if ($nome === "" || strlen($nome) > 100) {
                throw new DomainException(
                    "Informe o nome da meta."
                );
            }

            if (strlen($descricao) > 250) {
                throw new DomainException(
                    "A descrição pode ter no máximo 250 caracteres."
                );
            }

            if (
                !is_numeric($valorObjetivoTexto)
                || (float) $valorObjetivoTexto <= 0
            ) {
                throw new DomainException(
                    "Informe um valor objetivo maior que zero."
                );
            }

            $dataPrazo = DateTime::createFromFormat(
                "!Y-m-d",
                $prazo
            );
            $hoje = new DateTime("today");

            if (
                !$dataPrazo
                || $dataPrazo->format("Y-m-d") !== $prazo
            ) {
                throw new DomainException(
                    "Informe um prazo válido."
                );
            }

            if ($dataPrazo < $hoje) {
                throw new DomainException(
                    "O prazo não pode estar no passado."
                );
            }

            $valorObjetivo = round(
                (float) $valorObjetivoTexto,
                2
            );
            $dataInicio = date("Y-m-d");
            $statusMeta = "EM_ANDAMENTO";

            $stmt = $conexao->prepare(
                "INSERT INTO tb_meta
                    (
                        nm_meta,
                        ds_meta,
                        vl_objetivo,
                        vl_atual,
                        dt_inicio,
                        dt_prazo,
                        st_meta,
                        id_usuario,
                        id_banco,
                        ds_revisao_gastos
                    )
                 VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, NULL)"
            );

            $stmt->bind_param(
                "ssdsssii",
                $nome,
                $descricao,
                $valorObjetivo,
                $dataInicio,
                $prazo,
                $statusMeta,
                $usuarioId,
                $bancoPrincipalId
            );
            $stmt->execute();
            $stmt->close();

            $_SESSION["metas_mensagem"] =
                "Meta cadastrada.";
            $_SESSION["metas_tipo_mensagem"] =
                "sucesso";

            redirecionarMetas();
        }

        if (in_array($acao, ["depositar", "resgatar"], true)) {
            $metaId = filter_var(
                $_POST["cd_meta"] ?? null,
                FILTER_VALIDATE_INT
            );

            $valorTexto = $_POST["valor"] ?? "";

            if (!$metaId) {
                throw new DomainException(
                    "A meta selecionada não é válida."
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

            $valor = round((float) $valorTexto, 2);

            $conexao->begin_transaction();
            $transacaoIniciada = true;

            $stmt = $conexao->prepare(
                "SELECT
                    nm_meta,
                    vl_objetivo,
                    vl_atual,
                    st_meta,
                    id_banco
                 FROM tb_meta
                 WHERE cd_meta = ?
                   AND id_usuario = ?
                 FOR UPDATE"
            );
            $stmt->bind_param("ii", $metaId, $usuarioId);
            $stmt->execute();
            $meta = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$meta) {
                throw new DomainException(
                    "A meta não foi encontrada."
                );
            }

            if ($meta["st_meta"] === "CANCELADA") {
                throw new DomainException(
                    "Não é possível movimentar uma meta cancelada."
                );
            }

            $valorAtual =
                (float) $meta["vl_atual"];
            $valorObjetivo =
                (float) $meta["vl_objetivo"];
            $nomeMeta =
                (string) $meta["nm_meta"];

            $bancoMetaId =
                $meta["id_banco"] !== null
                    ? (int) $meta["id_banco"]
                    : $bancoPrincipalId;

            if ($bancoMetaId === null) {
                throw new DomainException(
                    "Não existe uma conta bancária para esta operação."
                );
            }

            $stmt = $conexao->prepare(
                "SELECT ds_saldo
                 FROM tb_banco
                 WHERE cd_banco = ?
                   AND id_usuario = ?
                 FOR UPDATE"
            );
            $stmt->bind_param(
                "ii",
                $bancoMetaId,
                $usuarioId
            );
            $stmt->execute();
            $banco = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$banco) {
                throw new DomainException(
                    "A conta bancária não foi encontrada."
                );
            }

            $tipoPagamento = "OUTROS";

            if ($acao === "depositar") {
                $valorRestante = max(
                    0,
                    $valorObjetivo - $valorAtual
                );

                if ($valor > $valorRestante) {
                    throw new DomainException(
                        "O depósito não pode exceder o valor que falta."
                    );
                }

                if ((float) $banco["ds_saldo"] < $valor) {
                    throw new DomainException(
                        "Saldo insuficiente para realizar o depósito."
                    );
                }

                $descricaoMovimento =
                    "Meta - Depósito: " . $nomeMeta;

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
                    $descricaoMovimento,
                    $bancoMetaId,
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
                    $bancoMetaId,
                    $usuarioId
                );
                $stmt->execute();
                $stmt->close();

                $novoValorMeta =
                    round($valorAtual + $valor, 2);

                $novoStatusMeta =
                    $novoValorMeta >= $valorObjetivo
                        ? "CONCLUIDA"
                        : "EM_ANDAMENTO";

                $mensagemSucesso =
                    "Depósito registrado na meta.";
            } else {
                if ($valor > $valorAtual) {
                    throw new DomainException(
                        "O resgate não pode ser maior que o valor guardado."
                    );
                }

                $descricaoMovimento =
                    "Meta - Resgate: " . $nomeMeta;

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
                    $descricaoMovimento,
                    $bancoMetaId,
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
                    $bancoMetaId,
                    $usuarioId
                );
                $stmt->execute();
                $stmt->close();

                $novoValorMeta =
                    round($valorAtual - $valor, 2);

                $novoStatusMeta =
                    "EM_ANDAMENTO";

                $mensagemSucesso =
                    "Resgate registrado e valor devolvido ao saldo.";
            }

            $stmt = $conexao->prepare(
                "UPDATE tb_meta
                 SET vl_atual = ?,
                     st_meta = ?
                 WHERE cd_meta = ?
                   AND id_usuario = ?"
            );
            $stmt->bind_param(
                "dsii",
                $novoValorMeta,
                $novoStatusMeta,
                $metaId,
                $usuarioId
            );
            $stmt->execute();
            $stmt->close();

            $conexao->commit();
            $transacaoIniciada = false;

            $_SESSION["metas_mensagem"] =
                $mensagemSucesso;
            $_SESSION["metas_tipo_mensagem"] =
                "sucesso";

            redirecionarMetas();
        }

        if ($acao === "deletar") {
            $metaId = filter_var(
                $_POST["cd_meta"] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$metaId) {
                throw new DomainException(
                    "A meta selecionada não é válida."
                );
            }

            $conexao->begin_transaction();
            $transacaoIniciada = true;

            $stmt = $conexao->prepare(
                "SELECT
                    nm_meta,
                    vl_atual,
                    id_banco
                 FROM tb_meta
                 WHERE cd_meta = ?
                   AND id_usuario = ?
                 FOR UPDATE"
            );
            $stmt->bind_param("ii", $metaId, $usuarioId);
            $stmt->execute();
            $meta = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$meta) {
                throw new DomainException(
                    "Meta não encontrada."
                );
            }

            $valorGuardado =
                (float) $meta["vl_atual"];

            $bancoMetaId =
                $meta["id_banco"] !== null
                    ? (int) $meta["id_banco"]
                    : $bancoPrincipalId;

            if ($valorGuardado > 0) {
                if ($bancoMetaId === null) {
                    throw new DomainException(
                        "Não foi encontrada uma conta para devolver o valor."
                    );
                }

                $descricao =
                    "Desistência da meta: "
                    . $meta["nm_meta"];

                $tipoPagamento = "OUTROS";

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
                    $valorGuardado,
                    $descricao,
                    $bancoMetaId,
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
                    $valorGuardado,
                    $bancoMetaId,
                    $usuarioId
                );
                $stmt->execute();
                $stmt->close();
            }

            $stmt = $conexao->prepare(
                "DELETE FROM tb_meta
                 WHERE cd_meta = ?
                   AND id_usuario = ?"
            );
            $stmt->bind_param("ii", $metaId, $usuarioId);
            $stmt->execute();
            $stmt->close();

            $conexao->commit();
            $transacaoIniciada = false;

            $_SESSION["metas_mensagem"] =
                $valorGuardado > 0
                    ? "Meta excluída e valor devolvido ao saldo."
                    : "Meta excluída.";
            $_SESSION["metas_tipo_mensagem"] =
                "sucesso";

            redirecionarMetas();
        }

        if ($acao === "salvar_revisao") {
            $selecionados =
                $_POST["gastos_selecionados"] ?? [];

            if (!is_array($selecionados)) {
                $selecionados = [];
            }

            $estadoGastos = [];

            foreach ($gastosMes as $gasto) {
                $estadoGastos[$gasto["hash"]] =
                    in_array(
                        $gasto["hash"],
                        $selecionados,
                        true
                    );
            }

            $jsonGastos = json_encode(
                $estadoGastos,
                JSON_UNESCAPED_UNICODE
            );

            if ($jsonGastos === false) {
                throw new DomainException(
                    "Não foi possível preparar a revisão."
                );
            }

            $stmt = $conexao->prepare(
                "UPDATE tb_meta
                 SET ds_revisao_gastos = ?
                 WHERE id_usuario = ?
                   AND st_meta = 'EM_ANDAMENTO'"
            );
            $stmt->bind_param(
                "si",
                $jsonGastos,
                $usuarioId
            );
            $stmt->execute();
            $stmt->close();

            $_SESSION["metas_mensagem"] =
                "Revisão dos gastos salva nas metas.";
            $_SESSION["metas_tipo_mensagem"] =
                "sucesso";

            redirecionarMetas();
        }

        throw new DomainException("Ação inválida.");
    } catch (DomainException $erro) {
        if ($transacaoIniciada) {
            $conexao->rollback();
        }

        $_SESSION["metas_mensagem"] =
            $erro->getMessage();
        $_SESSION["metas_tipo_mensagem"] =
            "erro";

        redirecionarMetas();
    } catch (Throwable $erro) {
        if ($transacaoIniciada) {
            $conexao->rollback();
        }

        $_SESSION["metas_mensagem"] =
            "Não foi possível processar a solicitação.";
        $_SESSION["metas_tipo_mensagem"] =
            "erro";

        redirecionarMetas();
    }
}

$stmt = $conexao->prepare(
    "SELECT
        cd_meta,
        nm_meta,
        ds_meta,
        vl_objetivo,
        vl_atual,
        dt_inicio,
        dt_prazo,
        st_meta,
        ds_revisao_gastos,
        id_banco
     FROM tb_meta
     WHERE id_usuario = ?
     ORDER BY
        CASE st_meta
            WHEN 'EM_ANDAMENTO' THEN 0
            WHEN 'CONCLUIDA' THEN 1
            ELSE 2
        END,
        dt_prazo ASC,
        cd_meta DESC"
);
$stmt->bind_param("i", $usuarioId);
$stmt->execute();
$resultadoMetas = $stmt->get_result();

$metas = [];
$revisaoGastos = [];

while ($linha = $resultadoMetas->fetch_assoc()) {
    $revisao = json_decode(
        $linha["ds_revisao_gastos"] ?? "",
        true
    );

    $linha["revisao_gastos"] =
        is_array($revisao)
            ? $revisao
            : [];

    if (
        $linha["st_meta"] === "EM_ANDAMENTO"
        && empty($revisaoGastos)
        && count($linha["revisao_gastos"]) > 0
    ) {
        $revisaoGastos =
            $linha["revisao_gastos"];
    }

    $metas[] = $linha;
}

$stmt->close();

$nomesStatus = [
    "EM_ANDAMENTO" => "Em andamento",
    "CONCLUIDA" => "Concluída",
    "CANCELADA" => "Cancelada"
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

  <title>LEKAN - Metas</title>

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
      gap: 16px;
      margin-bottom: 18px;
    }

    .page-title {
      margin: 0;
      font-size: 20px;
      font-weight: 700;
    }

    .subtitle {
      margin-top: 3px;
      color: #666;
      font-size: 14px;
    }

    .new-btn {
      background: linear-gradient(180deg, #d84ef6 0%, #c740eb 100%);
      border: none;
      color: #fff;
      border-radius: 10px;
      padding: 11px 16px;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 6px 12px rgba(178, 0, 217, 0.15);
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

    .new-goal-panel {
      display: none;
      background: #f8f8f8;
      border: 1px solid #e5e5e5;
      border-radius: 14px;
      padding: 18px;
      margin-bottom: 18px;
    }

    .new-goal-panel.open {
      display: block;
    }

    .new-goal-panel h2 {
      margin: 0 0 14px;
      font-size: 16px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px 16px;
    }

    .field.full {
      grid-column: 1 / -1;
    }

    .field label {
      display: block;
      margin-bottom: 6px;
      color: #333;
      font-size: 12px;
      font-weight: 700;
    }

    .field input,
    .field textarea {
      width: 100%;
      min-height: 40px;
      padding: 9px 11px;
      border: 1px solid #ddd;
      border-radius: 8px;
      background: #fff;
      font: inherit;
      font-size: 13px;
    }

    .field textarea {
      min-height: 72px;
      resize: vertical;
    }

    .submit-btn {
      min-height: 40px;
      border: 0;
      border-radius: 8px;
      padding: 0 16px;
      background: #b900d8;
      color: #fff;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
    }

    .goals-layout {
      display: grid;
      grid-template-columns: 1.35fr 1fr;
      gap: 18px;
      align-items: start;
    }

    .goals-panel {
      background: linear-gradient(180deg, #b300d7 0%, #a700cf 100%);
      border-radius: 18px;
      padding: 20px 18px;
      box-shadow: 0 12px 22px rgba(0, 0, 0, 0.05);
      color: #fff;
    }

    .goals-panel h2 {
      margin: 0;
      font-size: 24px;
      font-weight: 700;
    }

    .goals-lead {
      margin: 5px 0 16px;
      color: rgba(255,255,255,.9);
      font-size: 13px;
    }

    .goal-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .goal-card {
      background: #f8ebff;
      border: 1px solid #e6c6ef;
      border-radius: 13px;
      padding: 14px;
      color: #333;
    }

    .goal-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 10px;
    }

    .goal-name {
      margin: 0;
      color: #3d2443;
      font-size: 15px;
      font-weight: 700;
      overflow-wrap: anywhere;
    }

    .goal-description {
      margin: 5px 0 0;
      color: #66576a;
      font-size: 12px;
      line-height: 1.45;
      overflow-wrap: anywhere;
    }

    .status {
      flex-shrink: 0;
      padding: 5px 8px;
      border-radius: 20px;
      background: #fff1d6;
      color: #8a5a00;
      font-size: 10px;
      font-weight: 700;
    }

    .status.concluida {
      background: #e5f7e9;
      color: #21743a;
    }

    .status.cancelada {
      background: #eee;
      color: #666;
    }

    .goal-values {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 8px;
      margin-top: 14px;
    }

    .goal-values span {
      display: block;
      color: #756979;
      font-size: 10px;
    }

    .goal-values strong {
      display: block;
      margin-top: 4px;
      color: #382b3b;
      font-size: 13px;
    }

    .progress-track {
      height: 9px;
      margin-top: 12px;
      overflow: hidden;
      border-radius: 10px;
      background: #e8dce9;
    }

    .progress-bar {
      height: 100%;
      border-radius: inherit;
      background: linear-gradient(90deg, #d84ef6, #a700cf);
    }

    .goal-meta {
      display: flex;
      justify-content: space-between;
      gap: 8px;
      margin-top: 7px;
      color: #6a5a6e;
      font-size: 11px;
    }

    .goal-actions {
      display: grid;
      gap: 10px;
      margin-top: 14px;
    }

    .goal-money-form label {
      display: block;
      margin-bottom: 5px;
      color: #66576a;
      font-size: 11px;
      font-weight: 700;
    }

    .goal-money-controls {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 110px;
      gap: 8px;
    }

    .goal-money-controls input {
      width: 100%;
      min-width: 0;
      height: 36px;
      padding: 0 9px;
      border: 1px solid #dfcde4;
      border-radius: 8px;
      font: inherit;
      font-size: 12px;
    }

    .goal-money-controls button {
      width: 110px;
      height: 36px;
      border: 0;
      border-radius: 8px;
      background: #b900d8;
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      cursor: pointer;
    }

    .goal-money-controls .resgatar-btn {
      background: #777;
    }

    .delete-meta-form {
      margin-top: 10px;
    }

    .delete-meta-btn {
      width: 100%;
      height: 36px;
      border: 0;
      border-radius: 8px;
      background: #777;
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      cursor: pointer;
    }

    .expenses-panel {
      height: fit-content;
      background: #f8f8f8;
      border: 1px solid #e5e5e5;
      border-radius: 14px;
      padding: 16px;
    }

    .expenses-title {
      margin: 0;
      color: #292929;
      font-size: 16px;
      font-weight: 700;
    }

    .expenses-subtitle {
      margin: 4px 0 12px;
      color: #777;
      font-size: 12px;
    }

    .expense-list {
      display: flex;
      flex-direction: column;
    }

    .expense-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding: 11px 2px;
      border-top: 1px solid #e7e7e7;
      cursor: pointer;
    }

    .expense-name {
      display: block;
      min-width: 0;
      color: #333;
      font-size: 13px;
      font-weight: 600;
      overflow-wrap: anywhere;
    }

    .expense-amount {
      display: block;
      margin-top: 3px;
      color: #777;
      font-size: 12px;
    }

    .expense-check {
      width: 20px;
      height: 20px;
      flex: 0 0 20px;
      accent-color: #b300d7;
      cursor: pointer;
    }

    .empty {
      color: #777;
      font-size: 13px;
      padding: 10px 0;
    }

    .save-review-btn {
      width: 100%;
      min-height: 40px;
      border: 0;
      border-radius: 9px;
      margin-top: 14px;
      background: #b900d8;
      color: #fff;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 5px 12px rgba(178, 0, 217, 0.14);
    }

    @media (max-width: 950px) {
      .goals-layout {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 600px) {
      .page-header {
        align-items: flex-start;
        flex-direction: column;
      }

      .new-btn,
      .submit-btn,
      .save-review-btn {
        width: 100%;
      }

      .form-grid {
        grid-template-columns: 1fr;
      }

      .field.full {
        grid-column: auto;
      }

      .goal-values {
        grid-template-columns: 1fr;
      }

      .goal-meta {
        flex-direction: column;
      }

      .goal-money-controls {
        grid-template-columns: minmax(0, 1fr) 110px;
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

          <a class="nav-item active" href="metas.php">
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
              <h1 class="page-title">Metas</h1>
              <div class="subtitle">
                Planeje o futuro com foco e disciplina
              </div>
            </div>

            <button
              class="new-btn"
              type="button"
              onclick="const form = document.getElementById('form-nova-meta'); form.classList.toggle('open'); if (form.classList.contains('open')) form.scrollIntoView({behavior: 'smooth'});"
            >
              + Nova meta
            </button>
          </div>

          <?php if ($mensagem !== ""): ?>
            <div class="alerta <?= escapar($tipoMensagem) ?>">
              <?= escapar($mensagem) ?>
            </div>
          <?php endif; ?>

          <section class="new-goal-panel" id="form-nova-meta">
            <h2>Criar uma meta</h2>

            <form method="POST" action="metas.php">
              <input type="hidden"
                     name="csrf_token"
                     value="<?= escapar($_SESSION["csrf_token"]) ?>">
              <input type="hidden"
                     name="acao"
                     value="criar">

              <div class="form-grid">
                <div class="field">
                  <label for="nm_meta">Nome da meta</label>
                  <input type="text"
                         id="nm_meta"
                         name="nm_meta"
                         maxlength="100"
                         placeholder="Ex.: Reserva de emergência"
                         required>
                </div>

                <div class="field">
                  <label for="vl_objetivo">Valor necessário (R$)</label>
                  <input type="number"
                         id="vl_objetivo"
                         name="vl_objetivo"
                         min="0.01"
                         step="0.01"
                         placeholder="0.00"
                         required>
                </div>

                <div class="field">
                  <label for="dt_prazo">Prazo para atingir</label>
                  <input type="date"
                         id="dt_prazo"
                         name="dt_prazo"
                         min="<?= date("Y-m-d") ?>"
                         required>
                </div>

                <div class="field full">
                  <label for="ds_meta">Descrição</label>
                  <textarea id="ds_meta"
                            name="ds_meta"
                            maxlength="250"
                            placeholder="Descreva sua meta"></textarea>
                </div>

                <div class="field full">
                  <button class="submit-btn" type="submit">
                    Salvar meta
                  </button>
                </div>
              </div>
            </form>
          </section>

          <div class="goals-layout">
            <section class="goals-panel">
              <h2>Suas metas</h2>

              <p class="goals-lead">
                Acompanhe o progresso e o prazo de cada objetivo.
              </p>

              <?php if (count($metas) === 0): ?>
                <div class="goal-card">
                  <div class="goal-description">
                    Use “+ Nova meta” para adicionar seu primeiro objetivo.
                  </div>
                </div>
              <?php else: ?>
                <div class="goal-list">
                  <?php foreach ($metas as $meta): ?>
                    <?php
                      $objetivo =
                          (float) $meta["vl_objetivo"];
                      $atual =
                          (float) $meta["vl_atual"];
                      $restante =
                          max(0, $objetivo - $atual);

                      $progresso =
                          $objetivo > 0
                              ? min(100, ($atual / $objetivo) * 100)
                              : 0;

                      $status =
                          $meta["st_meta"];

                      $classeStatus =
                          $status === "CONCLUIDA"
                              ? "concluida"
                              : (
                                  $status === "CANCELADA"
                                      ? "cancelada"
                                      : ""
                              );
                    ?>

                    <article class="goal-card">
                      <div class="goal-top">
                        <div>
                          <h3 class="goal-name">
                            <?= escapar($meta["nm_meta"]) ?>
                          </h3>

                          <?php if (!empty($meta["ds_meta"])): ?>
                            <p class="goal-description">
                              <?= nl2br(
                                  escapar($meta["ds_meta"])
                              ) ?>
                            </p>
                          <?php endif; ?>
                        </div>

                        <span class="status <?= escapar($classeStatus) ?>">
                          <?= escapar(
                              $nomesStatus[$status] ?? $status
                          ) ?>
                        </span>
                      </div>

                      <div class="goal-values">
                        <div>
                          <span>Guardado</span>
                          <strong>
                            <?= moedaBR($atual) ?>
                          </strong>
                        </div>

                        <div>
                          <span>Objetivo</span>
                          <strong>
                            <?= moedaBR($objetivo) ?>
                          </strong>
                        </div>

                        <div>
                          <span>Falta</span>
                          <strong>
                            <?= moedaBR($restante) ?>
                          </strong>
                        </div>
                      </div>

                      <div class="progress-track">
                        <div
                          class="progress-bar"
                          style="width: <?= $progresso ?>%"
                        ></div>
                      </div>

                      <div class="goal-meta">
                        <span>
                          Progresso:
                          <?= number_format(
                              $progresso,
                              1,
                              ",",
                              "."
                          ) ?>%
                        </span>

                        <span>
                          Prazo:
                          <?= date(
                              "d/m/Y",
                              strtotime($meta["dt_prazo"])
                          ) ?>
                        </span>
                      </div>

                      <?php if ($status !== "CANCELADA"): ?>
                        <div class="goal-actions">
                          <?php if ($restante > 0): ?>
                            <form class="goal-money-form"
                                  method="POST"
                                  action="metas.php">
                              <input type="hidden"
                                     name="csrf_token"
                                     value="<?= escapar($_SESSION["csrf_token"]) ?>">
                              <input type="hidden"
                                     name="acao"
                                     value="depositar">
                              <input type="hidden"
                                     name="cd_meta"
                                     value="<?= (int) $meta["cd_meta"] ?>">

                              <label for="deposito-<?= (int) $meta["cd_meta"] ?>">
                                Depositar na meta
                              </label>

                              <div class="goal-money-controls">
                                <input
                                  id="deposito-<?= (int) $meta["cd_meta"] ?>"
                                  type="number"
                                  name="valor"
                                  min="0.01"
                                  max="<?= number_format(
                                      $restante,
                                      2,
                                      ".",
                                      ""
                                  ) ?>"
                                  step="0.01"
                                  placeholder="Valor do depósito"
                                  required
                                >

                                <button type="submit">
                                  Depositar
                                </button>
                              </div>
                            </form>
                          <?php endif; ?>

                          <?php if ($atual > 0): ?>
                            <form class="goal-money-form"
                                  method="POST"
                                  action="metas.php"
                                  onsubmit="return confirm('Deseja resgatar esse valor da meta? Ele será devolvido ao saldo da conta.');">
                              <input type="hidden"
                                     name="csrf_token"
                                     value="<?= escapar($_SESSION["csrf_token"]) ?>">
                              <input type="hidden"
                                     name="acao"
                                     value="resgatar">
                              <input type="hidden"
                                     name="cd_meta"
                                     value="<?= (int) $meta["cd_meta"] ?>">

                              <label for="resgate-<?= (int) $meta["cd_meta"] ?>">
                                Resgatar da meta
                              </label>

                              <div class="goal-money-controls">
                                <input
                                  id="resgate-<?= (int) $meta["cd_meta"] ?>"
                                  type="number"
                                  name="valor"
                                  min="0.01"
                                  max="<?= number_format(
                                      $atual,
                                      2,
                                      ".",
                                      ""
                                  ) ?>"
                                  step="0.01"
                                  placeholder="Valor do resgate"
                                  required
                                >

                                <button
                                  class="resgatar-btn"
                                  type="submit"
                                >
                                  Resgatar
                                </button>
                              </div>
                            </form>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>

                      <form class="delete-meta-form"
                            method="POST"
                            action="metas.php"
                            onsubmit="return confirm('Excluir esta meta e devolver o valor guardado ao saldo?');">
                        <input type="hidden"
                               name="csrf_token"
                               value="<?= escapar($_SESSION["csrf_token"]) ?>">
                        <input type="hidden"
                               name="acao"
                               value="deletar">
                        <input type="hidden"
                               name="cd_meta"
                               value="<?= (int) $meta["cd_meta"] ?>">

                        <button
                          class="delete-meta-btn"
                          type="submit"
                        >
                          Excluir meta
                        </button>
                      </form>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>

            <section class="expenses-panel">
              <h2 class="expenses-title">Gastos do mês</h2>

              <p class="expenses-subtitle">
                Marque os gastos que deseja revisar.
              </p>

              <form
                id="form-revisao"
                method="POST"
                action="metas.php"
              >
                <input type="hidden"
                       name="csrf_token"
                       value="<?= escapar($_SESSION["csrf_token"]) ?>">

                <input type="hidden"
                       name="acao"
                       value="salvar_revisao">

                <?php if (count($gastosMes) === 0): ?>
                  <div class="empty">
                    Nenhuma saída registrada neste mês.
                  </div>
                <?php else: ?>
                  <div class="expense-list">
                    <?php foreach ($gastosMes as $gasto): ?>
                      <?php
                        $marcado =
                            !empty(
                                $revisaoGastos[$gasto["hash"]]
                            );
                      ?>

                      <label class="expense-row">
                        <span>
                          <span class="expense-name">
                            <?= escapar($gasto["descricao"]) ?>
                          </span>

                          <span class="expense-amount">
                            <?= moedaBR($gasto["total"]) ?>
                          </span>
                        </span>

                        <input
                          class="expense-check"
                          type="checkbox"
                          name="gastos_selecionados[]"
                          value="<?= escapar($gasto["hash"]) ?>"
                          <?= $marcado ? "checked" : "" ?>
                          aria-label="Marcar gasto para revisão"
                        >
                      </label>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <button
                  class="save-review-btn"
                  type="submit"
                >
                  Salvar revisão do mês
                </button>
              </form>
            </section>
          </div>
        </div>
      </main>
    </div>
  </section>
</body>
</html>
