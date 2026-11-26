<?php
require_once __DIR__ . "/auth.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$usuarioId = (int) $_SESSION["usuario_id"];

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function escaparPerfil($valor)
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

$mensagem = "";
$tipoMensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
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

        if ($acao === "salvar_nome") {
            $nome = trim($_POST["nome"] ?? "");

            if ($nome === "") {
                throw new DomainException(
                    "Informe um nome de usuário."
                );
            }

            if (strlen($nome) > 100) {
                throw new DomainException(
                    "O nome pode ter no máximo 100 caracteres."
                );
            }

            $stmt = $conexao->prepare(
                "UPDATE tb_usuario
                 SET nm_usuario = ?
                 WHERE cd_usuario = ?"
            );
            $stmt->bind_param("si", $nome, $usuarioId);
            $stmt->execute();
            $stmt->close();

            $_SESSION["usuario_nome"] = $nome;

            $mensagem = "Nome atualizado com sucesso.";
            $tipoMensagem = "sucesso";
        } elseif ($acao === "alterar_senha") {
            $senhaAtual = $_POST["senha_atual"] ?? "";
            $novaSenha = $_POST["nova_senha"] ?? "";
            $confirmarNovaSenha =
                $_POST["confirmar_nova_senha"] ?? "";

            if (
                !is_string($senhaAtual)
                || !is_string($novaSenha)
                || !is_string($confirmarNovaSenha)
            ) {
                throw new DomainException(
                    "Os dados de senha enviados são inválidos."
                );
            }

            $stmt = $conexao->prepare(
                "SELECT ds_senha
                 FROM tb_usuario
                 WHERE cd_usuario = ?
                 LIMIT 1"
            );
            $stmt->bind_param("i", $usuarioId);
            $stmt->execute();
            $usuarioSenha = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (
                !$usuarioSenha
                || !password_verify(
                    $senhaAtual,
                    $usuarioSenha["ds_senha"]
                )
            ) {
                throw new DomainException(
                    "A senha atual está incorreta."
                );
            }

            $tamanhoSenha = function_exists("mb_strlen")
                ? mb_strlen($novaSenha, "UTF-8")
                : strlen($novaSenha);

            if ($tamanhoSenha < 6) {
                throw new DomainException(
                    "A nova senha deve ter pelo menos 6 caracteres."
                );
            }

            if ($novaSenha !== $confirmarNovaSenha) {
                throw new DomainException(
                    "A nova senha e a confirmação não são iguais."
                );
            }

            $novoHash = password_hash(
                $novaSenha,
                PASSWORD_DEFAULT
            );

            $stmt = $conexao->prepare(
                "UPDATE tb_usuario
                 SET ds_senha = ?
                 WHERE cd_usuario = ?"
            );
            $stmt->bind_param("si", $novoHash, $usuarioId);
            $stmt->execute();
            $stmt->close();

            $mensagem = "Senha alterada com sucesso.";
            $tipoMensagem = "sucesso";
        } else {
            throw new DomainException("Ação inválida.");
        }
    } catch (DomainException $erro) {
        $mensagem = $erro->getMessage();
        $tipoMensagem = "erro";
    } catch (Throwable $erro) {
        $mensagem = "Não foi possível atualizar o perfil.";
        $tipoMensagem = "erro";
    }
}

$stmt = $conexao->prepare(
    "SELECT nm_usuario, ds_email
     FROM tb_usuario
     WHERE cd_usuario = ?
     LIMIT 1"
);
$stmt->bind_param("i", $usuarioId);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

$_SESSION["usuario_email"] = $usuario["ds_email"];

$nomeUsuario = trim((string) ($usuario["nm_usuario"] ?? ""));
$_SESSION["usuario_nome"] =
    $nomeUsuario !== "" ? $nomeUsuario : "Usuário";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

  <title>LEKAN - Meu perfil</title>

  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      padding: 32px 16px;
      background: #f5f5f7;
      color: #222;
      font-family: Arial, sans-serif;
    }

    .perfil-layout {
      width: 100%;
      max-width: 760px;
      margin: 0 auto;
    }

    .perfil-header {
      margin-bottom: 20px;
    }

    .perfil-header h1 {
      margin: 0;
      font-size: 26px;
    }

    .perfil-header p {
      margin: 7px 0 0;
      color: #6b6b6b;
      font-size: 14px;
    }

    .perfil-card {
      margin-bottom: 18px;
      padding: 22px;
      background: #fff;
      border: 1px solid #e7e7e7;
      border-radius: 14px;
      box-shadow: 0 8px 22px rgba(0, 0, 0, .04);
    }

    .perfil-card h2 {
      margin: 0 0 16px;
      font-size: 17px;
    }

    .perfil-info {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    .info-item {
      min-width: 0;
      padding: 14px;
      border: 1px solid #ececec;
      border-radius: 9px;
      background: #fafafa;
    }

    .info-item span {
      display: block;
      margin-bottom: 6px;
      color: #777;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
    }

    .info-item strong {
      display: block;
      font-size: 14px;
      overflow-wrap: anywhere;
    }

    .info-note {
      margin: 14px 0 0;
      color: #777;
      font-size: 12px;
      line-height: 1.5;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 14px;
    }

    .campo label {
      display: block;
      margin-bottom: 6px;
      color: #333;
      font-size: 12px;
      font-weight: 700;
    }

    .campo input {
      width: 100%;
      height: 42px;
      border: 1px solid #dcdcdc;
      border-radius: 8px;
      padding: 0 12px;
      font: inherit;
      font-size: 14px;
    }

    .campo input:focus {
      border-color: #b900d8;
      outline: none;
      box-shadow: 0 0 0 3px rgba(185, 0, 216, .12);
    }

    .senha-container {
      position: relative;
    }

    .senha-container input {
      padding-right: 46px;
    }

    .botao-olho {
      position: absolute;
      top: 50%;
      right: 7px;
      width: 30px;
      height: 30px;
      transform: translateY(-50%);
      display: grid;
      place-items: center;
      border: 0;
      border-radius: 50%;
      padding: 0;
      background: transparent;
      color: #777;
      cursor: pointer;
    }

    .botao-olho:hover,
    .botao-olho:focus-visible {
      background: #f3e4f8;
      color: #a000bd;
      outline: none;
    }

    .botao-olho svg {
      width: 19px;
      height: 19px;
    }

    .botao-olho .eye-off {
      display: none;
    }

    .botao-olho[data-visible="true"] .eye-open {
      display: none;
    }

    .botao-olho[data-visible="true"] .eye-off {
      display: block;
    }

    .form-hint {
      margin: 6px 0 0;
      color: #777;
      font-size: 11px;
    }

    .botao-principal,
    .botao-voltar {
      min-height: 42px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 0;
      border-radius: 8px;
      padding: 0 18px;
      background: #b900d8;
      color: #fff;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      cursor: pointer;
    }

    .botao-voltar {
      background: #777;
    }

    .acoes {
      display: flex;
      gap: 10px;
      margin-top: 16px;
    }

    .alerta {
      margin-bottom: 18px;
      padding: 12px 14px;
      border-radius: 8px;
      font-size: 13px;
    }

    .alerta.sucesso {
      background: #effaf2;
      color: #16803c;
    }

    .alerta.erro {
      background: #fff0f0;
      color: #c00000;
    }

    @media (max-width: 600px) {
      body {
        padding: 20px 12px;
      }

      .perfil-card {
        padding: 18px;
      }

      .perfil-info {
        grid-template-columns: 1fr;
      }

      .acoes {
        flex-direction: column;
      }

      .botao-principal,
      .botao-voltar {
        width: 100%;
      }
    }
  </style>
</head>

<body>
  <main class="perfil-layout">
    <header class="perfil-header">
      <h1>Meu perfil</h1>
      <p>Gerencie seu nome e sua senha.</p>
    </header>

    <?php if ($mensagem !== ""): ?>
      <div class="alerta <?= escaparPerfil($tipoMensagem) ?>">
        <?= escaparPerfil($mensagem) ?>
      </div>
    <?php endif; ?>

    <section class="perfil-card">
      <h2>Dados da conta</h2>

      <div class="perfil-info">
        <div class="info-item">
          <span>Nome de usuário</span>
          <strong>
            <?= escaparPerfil($_SESSION["usuario_nome"]) ?>
          </strong>
        </div>

        <div class="info-item">
          <span>E-mail</span>
          <strong>
            <?= escaparPerfil($usuario["ds_email"]) ?>
          </strong>
        </div>
      </div>

      <p class="info-note">
        A senha cadastrada é armazenada de forma protegida e não pode
        ser exibida. Para alterá-la, confirme sua senha atual abaixo.
      </p>
    </section>

    <section class="perfil-card">
      <h2>Alterar nome de usuário</h2>

      <form method="POST" action="perfil.php">
        <input
          type="hidden"
          name="csrf_token"
          value="<?= escaparPerfil($_SESSION["csrf_token"]) ?>"
        >
        <input type="hidden" name="acao" value="salvar_nome">

        <div class="campo">
          <label for="nome">Nome de usuário</label>
          <input
            id="nome"
            type="text"
            name="nome"
            maxlength="100"
            value="<?= escaparPerfil($_SESSION["usuario_nome"]) ?>"
            required
          >
        </div>

        <div class="acoes">
          <button class="botao-principal" type="submit">
            Salvar nome
          </button>

          <a class="botao-voltar" href="home.php">
            Voltar para a página inicial
          </a>
        </div>
      </form>
    </section>

    <section class="perfil-card">
      <h2>Alterar senha</h2>

      <form method="POST" action="perfil.php">
        <input
          type="hidden"
          name="csrf_token"
          value="<?= escaparPerfil($_SESSION["csrf_token"]) ?>"
        >
        <input type="hidden" name="acao" value="alterar_senha">

        <div class="form-grid">
          <div class="campo">
            <label for="senha-atual">Senha atual</label>

            <div class="senha-container">
              <input
                id="senha-atual"
                type="password"
                name="senha_atual"
                autocomplete="current-password"
                required
              >

              <button
                class="botao-olho"
                type="button"
                aria-label="Mostrar senha atual"
                data-visible="false"
                onclick="alternarSenhaPerfil('senha-atual', this)"
              >
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                  <g class="eye-open">
                    <path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z"/>
                    <circle cx="12" cy="12" r="2.5"/>
                  </g>
                  <g class="eye-off">
                    <path d="m3 3 18 18"/>
                    <path d="M10.6 6.2A10.7 10.7 0 0 1 12 6c6 0 9.5 6 9.5 6a16 16 0 0 1-3 3.5"/>
                    <path d="M6.2 6.3C3.8 7.7 2.5 12 2.5 12s3.5 6 9.5 6c1.1 0 2.1-.2 3-.5"/>
                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
                  </g>
                </svg>
              </button>
            </div>
          </div>

          <div class="campo">
            <label for="nova-senha">Nova senha</label>

            <div class="senha-container">
              <input
                id="nova-senha"
                type="password"
                name="nova_senha"
                minlength="6"
                autocomplete="new-password"
                required
              >

              <button
                class="botao-olho"
                type="button"
                aria-label="Mostrar nova senha"
                data-visible="false"
                onclick="alternarSenhaPerfil('nova-senha', this)"
              >
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                  <g class="eye-open">
                    <path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z"/>
                    <circle cx="12" cy="12" r="2.5"/>
                  </g>
                  <g class="eye-off">
                    <path d="m3 3 18 18"/>
                    <path d="M10.6 6.2A10.7 10.7 0 0 1 12 6c6 0 9.5 6 9.5 6a16 16 0 0 1-3 3.5"/>
                    <path d="M6.2 6.3C3.8 7.7 2.5 12 2.5 12s3.5 6 9.5 6c1.1 0 2.1-.2 3-.5"/>
                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
                  </g>
                </svg>
              </button>
            </div>

            <p class="form-hint">Use pelo menos 6 caracteres.</p>
          </div>

          <div class="campo">
            <label for="confirmar-nova-senha">
              Confirmar nova senha
            </label>

            <div class="senha-container">
              <input
                id="confirmar-nova-senha"
                type="password"
                name="confirmar_nova_senha"
                minlength="6"
                autocomplete="new-password"
                required
              >

              <button
                class="botao-olho"
                type="button"
                aria-label="Mostrar confirmação da senha"
                data-visible="false"
                onclick="alternarSenhaPerfil('confirmar-nova-senha', this)"
              >
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                  <g class="eye-open">
                    <path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z"/>
                    <circle cx="12" cy="12" r="2.5"/>
                  </g>
                  <g class="eye-off">
                    <path d="m3 3 18 18"/>
                    <path d="M10.6 6.2A10.7 10.7 0 0 1 12 6c6 0 9.5 6 9.5 6a16 16 0 0 1-3 3.5"/>
                    <path d="M6.2 6.3C3.8 7.7 2.5 12 2.5 12s3.5 6 9.5 6c1.1 0 2.1-.2 3-.5"/>
                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
                  </g>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <div class="acoes">
          <button class="botao-principal" type="submit">
            Alterar senha
          </button>
        </div>
      </form>
    </section>
  </main>

  <script>
    function alternarSenhaPerfil(idCampo, botao) {
      const campo = document.getElementById(idCampo);
      const mostrar = campo.type === "password";

      campo.type = mostrar ? "text" : "password";
      botao.dataset.visible = mostrar ? "true" : "false";

      const descricao = botao.getAttribute("aria-label") || "";
      botao.setAttribute(
        "aria-label",
        mostrar
          ? descricao.replace("Mostrar", "Ocultar")
          : descricao.replace("Ocultar", "Mostrar")
      );
    }
  </script>
</body>
</html>