<?php
require_once __DIR__ . "/inc/conexao.php";

if (isset($_SESSION["usuario_id"])) {
    header("Location: home.php");
    exit;
}

$mensagem = "";
$tipoMensagem = "";

if (isset($_SESSION["cadastro_ok"])) {
    $mensagem = $_SESSION["cadastro_ok"];
    $tipoMensagem = "sucesso";
    unset($_SESSION["cadastro_ok"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";

    if ($email === "" || $senha === "") {
        $mensagem = "Preencha e-mail e senha.";
        $tipoMensagem = "erro";
    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !preg_match('/\.com(?:\.br)?$/i', $email)
    ) {
        $mensagem = "Informe um e-mail válido terminado em .com.";
        $tipoMensagem = "erro";
    } elseif (strlen($senha) < 6) {
        $mensagem = "A senha deve ter pelo menos 6 caracteres.";
        $tipoMensagem = "erro";
    } else {
        try {
            $sql =
                "SELECT cd_usuario, nm_usuario, ds_email, ds_senha
                 FROM tb_usuario
                 WHERE ds_email = ?
                 LIMIT 1";

            $stmt = $conexao->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $usuario = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($usuario && password_verify($senha, $usuario["ds_senha"])) {
                session_regenerate_id(true);

                $_SESSION["usuario_id"] = (int) $usuario["cd_usuario"];
                $_SESSION["usuario_email"] = $usuario["ds_email"];
                $_SESSION["usuario_nome"] =
                    trim($usuario["nm_usuario"] ?? "") ?: "Usuário";

                if (empty($_SESSION["csrf_token"])) {
                    $_SESSION["csrf_token"] =
                        bin2hex(random_bytes(32));
                }

                header("Location: home.php");
                exit;
            }

            $mensagem = "E-mail ou senha inválidos.";
            $tipoMensagem = "erro";
        } catch (mysqli_sql_exception $erro) {
            $mensagem = "Erro ao processar o login.";
            $tipoMensagem = "erro";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LEKAN - Entrar</title>

  <style>
    * {
      box-sizing: border-box;
    }

    html, body {
      width: 100%;
      min-height: 100%;
      margin: 0;
      font-family: Arial, sans-serif;
      background: #fff;
    }

    .tela {
      width: 100%;
      min-height: 100vh;
      display: flex;
    }

    .painel-logo {
      width: 50%;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px;
      background: linear-gradient(180deg, #b900d8, #a800cf);
    }

    .logo-bloco {
      text-align: center;
      color: #fff;
    }

    .logo-nome {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-bottom: 10px;
      font-size: 48px;
      font-weight: bold;
    }

    .logo-circulo {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: #fff;
    }

    .logo-descricao {
      margin: 0;
      font-size: 14px;
    }

    .painel-login {
      width: 50%;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 30px;
    }

    .formulario {
      width: 100%;
      max-width: 380px;
    }

    .formulario h1 {
      margin: 0 0 28px;
      font-size: 22px;
      text-align: center;
    }

    .mb-campo {
      margin-bottom: 16px;
    }

    .form-label {
      display: block;
      margin-bottom: 6px;
      font-size: 13px;
      font-weight: bold;
    }

    .senha-container {
      position: relative;
    }

    .form-control {
      width: 100%;
      height: 42px;
      border: 1px solid #dc55f2;
      border-radius: 8px;
      padding: 9px 44px 9px 12px;
      font-size: 14px;
      outline: none;
    }

    .form-control:focus {
      border-color: #b900d8;
      box-shadow: 0 0 0 3px rgba(185, 0, 216, 0.12);
    }

    .olho-senha {
      position: absolute;
      top: 50%;
      right: 10px;
      width: 28px;
      height: 28px;
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

    .olho-senha:hover,
    .olho-senha:focus-visible {
      background: #f3e4f8;
      color: #a000bd;
      outline: none;
    }

    .olho-senha svg {
      width: 19px;
      height: 19px;
    }

    .btn-lekan {
      width: 100%;
      height: 44px;
      margin-top: 6px;
      border: none;
      border-radius: 8px;
      background: #b900d8;
      color: #fff;
      font-size: 14px;
      font-weight: bold;
      cursor: pointer;
    }

    .btn-lekan:hover {
      background: #a000bd;
    }

    .mensagem {
      margin: 0 0 16px;
      padding: 10px;
      border-radius: 6px;
      text-align: center;
      font-size: 13px;
    }

    .mensagem.erro {
      background: #fff0f0;
      color: #c00000;
    }

    .mensagem.sucesso {
      background: #effaf2;
      color: #16803c;
    }

    .link-box {
      margin-top: 18px;
      text-align: center;
      font-size: 13px;
    }

    .link-box a {
      color: #b900d8;
      font-weight: bold;
      text-decoration: none;
    }

    @media (max-width: 768px) {
      .tela {
        flex-direction: column;
      }

      .painel-logo {
        width: 100%;
        min-height: 220px;
        height: 35vh;
        padding: 25px;
      }

      .logo-nome {
        font-size: 38px;
      }

      .painel-login {
        width: 100%;
        min-height: 65vh;
        padding: 30px 22px;
      }
    }
  </style>
</head>

<body>
  <main class="tela">
    <section class="painel-logo">
      <div class="logo-bloco">
        <div class="logo-nome">
          <span class="logo-circulo"></span>
          <span>LEKAN</span>
        </div>

        <p class="logo-descricao">
          Gestão financeira para a sua empresa
        </p>
      </div>
    </section>

    <section class="painel-login">
      <form class="formulario" method="POST" action="login.php">
        <h1>Entrar</h1>

        <?php if ($mensagem !== ""): ?>
          <p class="mensagem <?= htmlspecialchars($tipoMensagem) ?>">
            <?= htmlspecialchars($mensagem) ?>
          </p>
        <?php endif; ?>

        <div class="mb-campo">
          <label for="email" class="form-label">E-mail *</label>

          <input
            type="email"
            class="form-control"
            id="email"
            name="email"
            maxlength="100"
            value="<?= htmlspecialchars($email ?? "") ?>"
            required
          >
        </div>

        <div class="mb-campo">
          <label for="senha" class="form-label">Senha *</label>

          <div class="senha-container">
            <input
              type="password"
              class="form-control"
              id="senha"
              name="senha"
              minlength="6"
              required
            >

            <button
              type="button"
              class="olho-senha"
              aria-label="Mostrar senha"
              onclick="alternarSenha('senha', this)"
            >
              <svg class="icone-olho" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="1.8"
                   stroke-linecap="round" stroke-linejoin="round"
                   aria-hidden="true">
                <path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z"/>
                <circle cx="12" cy="12" r="2.5"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-lekan">Entrar</button>

        <div class="link-box">
          <span>Não tem conta?</span>
          <a href="cadastro.php">Cadastre-se</a>
        </div>
      </form>
    </section>
  </main>

  <script>
    function alternarSenha(idCampo, botao) {
      const campo = document.getElementById(idCampo);

      if (campo.type === "password") {
        campo.type = "text";
        botao.setAttribute("aria-label", "Ocultar senha");
      } else {
        campo.type = "password";
        botao.setAttribute("aria-label", "Mostrar senha");
      }
    }
  </script>
</body>
</html>
