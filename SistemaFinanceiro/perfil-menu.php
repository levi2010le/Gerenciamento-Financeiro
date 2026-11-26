<?php
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
?>

<style>
  .profile-menu {
    position: relative;
    z-index: 100;
  }

  .profile-menu summary {
    list-style: none;
    cursor: pointer;
  }

  .profile-menu summary::-webkit-details-marker {
    display: none;
  }

  .profile-menu .avatar {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border: 1px solid #e3e3e3;
    border-radius: 50%;
    background: #f7f7f7;
    cursor: pointer;
  }

  .profile-dropdown {
    position: absolute;
    top: 48px;
    right: 0;
    width: 320px;
    max-width: 88vw;
    max-height: 80vh;
    overflow-y: auto;
    box-sizing: border-box;
    display: grid;
    gap: 16px;
    padding: 18px;
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 12px;
    box-shadow: 0 10px 28px rgba(0, 0, 0, .15);
    color: #333;
  }

  .profile-dropdown strong,
  .profile-dropdown label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 700;
  }

  .profile-email {
    font-size: 13px;
    overflow-wrap: anywhere;
  }

  .profile-dropdown form {
    margin: 0;
  }

  .profile-dropdown input {
    width: 100%;
    height: 38px;
    box-sizing: border-box;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 0 10px;
    background: #fff;
    font: inherit;
    font-size: 13px;
  }

  .profile-dropdown button[type="submit"] {
    width: 100%;
    min-height: 38px;
    margin-top: 9px;
    border: 0;
    border-radius: 8px;
    background: #b900d8;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
  }

  .profile-dropdown a {
    color: #a000bd;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
  }

  .profile-dropdown a:hover {
    text-decoration: underline;
  }

  .profile-password-field {
    position: relative;
  }

  .profile-password-field input {
    padding-right: 44px;
  }

  .profile-eye-button {
    position: absolute;
    top: 50%;
    right: 6px;
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

  .profile-eye-button:hover,
  .profile-eye-button:focus-visible {
    background: #f3e4f8;
    color: #a000bd;
    outline: none;
  }

  .profile-eye-button svg {
    width: 19px;
    height: 19px;
  }

  .profile-eye-button .eye-off {
    display: none;
  }

  .profile-eye-button[data-visible="true"] .eye-open {
    display: none;
  }

  .profile-eye-button[data-visible="true"] .eye-off {
    display: block;
  }

  .profile-password-note {
    margin: 7px 0 0;
    color: #777;
    font-size: 11px;
    line-height: 1.4;
  }

  @media (max-width: 600px) {
    .profile-dropdown {
      position: fixed;
      top: 62px;
      right: 12px;
    }
  }
</style>

<details class="profile-menu">
  <summary class="avatar" aria-label="Abrir menu do usuário">
    👤
  </summary>

  <div class="profile-dropdown">
    <div>
      <strong>E-mail</strong>
      <div class="profile-email">
        <?= htmlspecialchars(
            $_SESSION["usuario_email"] ?? "",
            ENT_QUOTES,
            "UTF-8"
        ) ?>
      </div>
    </div>

    <form method="POST" action="perfil.php">
      <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(
            $_SESSION["csrf_token"],
            ENT_QUOTES,
            "UTF-8"
        ) ?>"
      >
      <input type="hidden" name="acao" value="salvar_nome">

      <label for="nome-perfil-menu">Nome de usuário</label>
      <input
        id="nome-perfil-menu"
        type="text"
        name="nome"
        maxlength="100"
        value="<?= htmlspecialchars(
            $_SESSION["usuario_nome"] ?? "",
            ENT_QUOTES,
            "UTF-8"
        ) ?>"
        required
      >

      <button type="submit">Salvar nome</button>
    </form>

    <form method="POST" action="perfil.php">
      <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(
            $_SESSION["csrf_token"],
            ENT_QUOTES,
            "UTF-8"
        ) ?>"
      >
      <input type="hidden" name="acao" value="alterar_senha">

      <strong>Alterar senha</strong>

      <p class="profile-password-note">
        Digite sua senha atual para confirmá-la. A senha cadastrada
        não pode ser exibida pelo sistema.
      </p>

      <label for="senha-atual-menu">Senha atual</label>
      <div class="profile-password-field">
        <input
          id="senha-atual-menu"
          type="password"
          name="senha_atual"
          autocomplete="current-password"
          required
        >

        <button
          class="profile-eye-button"
          type="button"
          aria-label="Mostrar senha atual"
          data-visible="false"
          onclick="alternarSenhaPerfilMenu('senha-atual-menu', this)"
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

      <label for="nova-senha-menu">Nova senha</label>
      <div class="profile-password-field">
        <input
          id="nova-senha-menu"
          type="password"
          name="nova_senha"
          minlength="6"
          autocomplete="new-password"
          required
        >

        <button
          class="profile-eye-button"
          type="button"
          aria-label="Mostrar nova senha"
          data-visible="false"
          onclick="alternarSenhaPerfilMenu('nova-senha-menu', this)"
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

      <label for="confirmar-senha-menu">Confirmar nova senha</label>
      <div class="profile-password-field">
        <input
          id="confirmar-senha-menu"
          type="password"
          name="confirmar_nova_senha"
          minlength="6"
          autocomplete="new-password"
          required
        >

        <button
          class="profile-eye-button"
          type="button"
          aria-label="Mostrar confirmação da senha"
          data-visible="false"
          onclick="alternarSenhaPerfilMenu('confirmar-senha-menu', this)"
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

      <button type="submit">Alterar senha</button>
    </form>
  </div>
</details>

<script>
  function alternarSenhaPerfilMenu(idCampo, botao) {
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