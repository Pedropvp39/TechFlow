<?php
require_once __DIR__ . '/../includes/config.php';
//pega as configurações gerais do sistema, incluindo sessão, segurança e URL base
header('Location: ' . base_url() . '/pages/cadastro.php');
// Redireciona o usuário para a página de cadastro de conta
exit();
