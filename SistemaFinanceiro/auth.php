<?php
    require_once __DIR__ . "/inc/conexao.php";

    if (!isset($_SESSION["usuario_id"])) {
        header("Location: login.php");
        exit;
    }
?>