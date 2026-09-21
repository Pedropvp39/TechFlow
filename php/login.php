<?php
require_once __DIR__ . '/../includes/config.php';
//pega as configurações gerais do sistema, incluindo sessão, segurança e URL base
//_dir_ e um metodo para pegar o caminho absoluto do arquivo atual, garantindo que os arquivos de configuração sejam incluídos corretamente independentemente do diretório de execução
header('Location: ' . base_url() . '/pages/login.php');
exit();

