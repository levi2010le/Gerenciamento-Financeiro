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
    } else {
        try {
            $sql =
                "SELECT cd_usuario, ds_email, ds_senha
                 FROM tb_usuario
                 WHERE ds_email = ?
                 LIMIT 1";

            $stmt = $conexao->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $resultado = $stmt->get_result();
            $usuario = $resultado->fetch_assoc();

            if (
                $usuario &&
                password_verify(
                    $senha,
                    $usuario["ds_senha"]
                )
            ) {
                session_regenerate_id(true);

                $_SESSION["usuario_id"] = $usuario["cd_usuario"];
                $_SESSION["usuario_email"] = $usuario["ds_email"];

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

    html,
    body {
      width: 100%;
      min-height: 100%;
      margin: 0;
      padding: 0;
      font-family: Arial, sans-serif;
      background: #ffffff;
    }

    .tela {
      width: 100%;
      min-height: 100vh;
      display: flex;
    }

    .painel-logo {
      width: 50%;
      min-height: 100vh;
      padding: 40px;
      background: linear-gradient(180deg, #b900d8 0%, #a800cf 100%);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .logo-bloco {
      text-align: center;
      color: #ffffff;
    }

    .logo-nome {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-bottom: 10px;
      font-size: 48px;
      font-weight: bold;
      letter-spacing: -1px;
    }

    .logo-circulo {
      width: 28px;
      height: 28px;
      flex-shrink: 0;
      border-radius: 50%;
      background: #ffffff;
    }

    .logo-descricao {
      margin: 0;
      font-size: 14px;
    }

    .painel-login {
      width: 50%;
      min-height: 100vh;
      padding: 40px 30px;
      background: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .formulario {
      width: 100%;
      max-width: 380px;
    }

    .formulario h1 {
      margin: 0 0 28px;
      color: #111111;
      font-size: 22px;
      text-align: center;
    }

    .mb-campo {
      margin-bottom: 16px;
    }

    .form-label {
      display: block;
      margin-bottom: 6px;
      color: #222222;
      font-size: 13px;
      font-weight: bold;
    }

    .form-control {
      width: 100%;
      height: 42px;
      border: 1px solid #dc55f2;
      border-radius: 8px;
      padding: 9px 12px;
      color: #222222;
      font-size: 14px;
      outline: none;
    }

    .form-control:focus {
      border-color: #b900d8;
      box-shadow: 0 0 0 3px rgba(185, 0, 216, 0.12);
    }

    .btn-lekan {
      width: 100%;
      height: 44px;
      margin-top: 6px;
      border: none;
      border-radius: 8px;
      background: #b900d8;
      color: #ffffff;
      font-size: 14px;
      font-weight: bold;
      cursor: pointer;
    }

    .btn-lekan:hover,
    .btn-lekan:focus {
      background: #a000bd;
    }

    .mensagem {
      min-height: 20px;
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
      color: #333333;
      text-align: center;
      font-size: 13px;
    }

    .link-box a {
      color: #b900d8;
      text-decoration: none;
      font-weight: bold;
    }

    .link-box a:hover {
      text-decoration: underline;
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
          <p class="mensagem <?= htmlspecialchars($tipoMensagem, ENT_QUOTES, "UTF-8") ?>">
            <?= htmlspecialchars($mensagem, ENT_QUOTES, "UTF-8") ?>
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
            value="<?= htmlspecialchars($email ?? "", ENT_QUOTES, "UTF-8") ?>"
            required
          >
        </div>

        <div class="mb-campo">
          <label for="senha" class="form-label">Senha *</label>
          <input
            type="password"
            class="form-control"
            id="senha"
            name="senha"
            required
          >
        </div>

        <button type="submit" class="btn-lekan">Entrar</button>

        <div class="link-box">
          <span>Não tem conta?</span>
          <a href="cadastro.php">Cadastre-se</a>
        </div>
      </form>
    </section>
  </main>
</body>
</html>