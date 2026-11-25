<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$servidor = "localhost";
$porta = 3307   ;
$banco = "controle_financeiro";
$usuario = "root";
$senha = "";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conexao = new mysqli(
        $servidor,
        $usuario,
        $senha,
        $banco,
        $porta
    );

    $conexao->set_charset("utf8mb4");
} catch (mysqli_sql_exception $erro) {
    die("Erro ao conectar ao banco de dados: " . $erro->getMessage());
}