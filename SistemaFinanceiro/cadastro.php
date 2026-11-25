<?php
require_once __DIR__ . "/inc/conexao.php";

if (isset($_SESSION["usuario_id"])) {
    header("Location: home.php");
    exit;
}

$mensagem = "";
$tipoMensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = isset($_POST["email"]) ? trim($_POST["email"]) : "";
    $senha = isset($_POST["senha"]) ? $_POST["senha"] : "";
    $confirmarSenha = isset($_POST["confirmarSenha"]) ? $_POST["confirmarSenha"] : "";

    if ($email === "" || $senha === "" || $confirmarSenha === "") {
        $mensagem = "Preencha todos os campos.";
        $tipoMensagem = "erro";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "Informe um e-mail válido.";
        $tipoMensagem = "erro";
    } elseif ($senha !== $confirmarSenha) {
        $mensagem = "As senhas não coincidem.";
        $tipoMensagem = "erro";
    } elseif (!function_exists("password_hash")) {
        $mensagem = "Atualize o PHP para uma versão igual ou superior a 5.5.";
        $tipoMensagem = "erro";
    } else {
        $sqlVerifica = "SELECT cd_usuario
                        FROM tb_usuario
                        WHERE ds_email = ?
                        LIMIT 1";

        $stmtVerifica = $conexao->prepare($sqlVerifica);

        if (!$stmtVerifica) {
            $mensagem = "Erro ao preparar a consulta.";
            $tipoMensagem = "erro";
        } else {
            $stmtVerifica->bind_param("s", $email);
            $stmtVerifica->execute();
            $stmtVerifica->store_result();

            if ($stmtVerifica->num_rows > 0) {
                $mensagem = "Este e-mail já está cadastrado.";
                $tipoMensagem = "erro";
            } else {
                $nome = "Usuário";
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

                $sqlInsert = "INSERT INTO tb_usuario
                              (nm_usuario, ds_senha, ds_email)
                              VALUES (?, ?, ?)";

                $stmtInsert = $conexao->prepare($sqlInsert);

                if (!$stmtInsert) {
                    $mensagem = "Erro ao preparar o cadastro.";
                    $tipoMensagem = "erro";
                } else {
                    $stmtInsert->bind_param("sss", $nome, $senhaHash, $email);

                    if ($stmtInsert->execute()) {
                        $stmtInsert->close();
                        $stmtVerifica->close();

                        header("Location: login.php?cadastro=ok");
                        exit;
                    } else {
                        $mensagem = "Não foi possível cadastrar.";
                        $tipoMensagem = "erro";
                    }

                    $stmtInsert->close();
                }
            }

            $stmtVerifica->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LEKAN - Cadastrar</title>

  <style>
    * {
      box-sizing: border-box;
    }

    html,
    body {
      margin: 0;
      padding: 0;
      width: 100%;
      min-height: 100%;
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
      background: linear-gradient(180deg, #b900d8 0%, #a800cf 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px;
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
      border-radius: 50%;
      background: #ffffff;
      flex-shrink: 0;
    }

    .logo-descricao {
      margin: 0;
      font-size: 14px;
    }

    .painel-cadastro {
      width: 50%;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 30px;
      background: #ffffff;
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

      .painel-cadastro {
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
        <p class="logo-descricao">Gestão financeira para a sua empresa</p>
      </div>
    </section>

    <section class="painel-cadastro">
      <form class="formulario" method="POST" action="cadastro.php">
        <h1>Cadastrar</h1>

        <?php if ($mensagem !== "") { ?>
          <p class="mensagem <?php echo htmlspecialchars($tipoMensagem, ENT_QUOTES, "UTF-8"); ?>">
            <?php echo htmlspecialchars($mensagem, ENT_QUOTES, "UTF-8"); ?>
          </p>
        <?php } ?>

        <div class="mb-campo">
          <label for="email" class="form-label">E-mail *</label>
          <input
            type="email"
            class="form-control"
            id="email"
            name="email"
            maxlength="100"
            value="<?php echo isset($_POST["email"]) ? htmlspecialchars(trim($_POST["email"]), ENT_QUOTES, "UTF-8") : ""; ?>"
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

        <div class="mb-campo">
          <label for="confirmarSenha" class="form-label">Confirmar senha *</label>
          <input
            type="password"
            class="form-control"
            id="confirmarSenha"
            name="confirmarSenha"
            required
          >
        </div>

        <button type="submit" class="btn-lekan">Cadastrar</button>

        <div class="link-box">
          <span>Já tem conta?</span>
          <a href="login.php">Entrar</a>
        </div>
      </form>
    </section>
  </main>
</body>
</html>   