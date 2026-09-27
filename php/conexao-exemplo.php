<?php
// Mostra erros de conexao sem gerar uma tela fatal.
mysqli_report(MYSQLI_REPORT_OFF);

// Informa onde esta o MySQL.
$host = '127.0.0.1';
// Informa o usuario do banco.
$usuario = 'root';
// Informa a senha do banco.
$senha = '';
// Informa o nome do banco.
$banco = 'techflow';

// Abre a conexao usando host, usuario, senha, banco e porta.
$conexao = @new mysqli($host, $usuario, $senha, $banco, 3307);
// Interrompe o exemplo se o MySQL estiver desligado.
if ($conexao->connect_errno) {
    exit('Inicie o MySQL no XAMPP.');
}

// Define UTF-8 para aceitar acentos corretamente.
$conexao->set_charset('utf8mb4');
// Fecha a conexao depois do uso.
$conexao->close();
