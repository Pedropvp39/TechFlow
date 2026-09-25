<?php
/**
 * ============================================================================
 * CONEXÃO DE PREFERÊNCIA (HOSPEDAGEM / InfinityFree)
 * ============================================================================
 *
 * Este arquivo é SEPARADO da conexão local (php/conexao.php).
 * Ele guarda os dados de acesso do servidor de hospedagem gratuita, para que
 * o site funcione tanto na máquina local quanto no servidor online.
 *
 * Dados da conta InfinityFree:
 *   Domínio principal....: kv85ftlf.infinityfree.com
 *   Nome do host FTP.....: ftpupload.net
 *   Usuário FTP..........: if0_42990430
 *   Host MySQL...........: sql309.infinityfree.com
 *   Usuário MySQL........: if0_42990430
 *   Volume de hospedagem.: vol8_1
 *
 * IMPORTANTE — A SENHA DO MYSQL:
 * A senha do MySQL da InfinityFree (a "vPanel Password") NÃO foi informada,
 * por isso o campo abaixo está vazio. Enquanto estiver vazio, a hospedagem
 * NÃO é usada e o site continua trabalhando com o banco LOCAL automaticamente.
 *
 * Para ativar a hospedagem, preencha 'pass' abaixo (ou defina a variável de
 * ambiente DB_PASS antes de rodar o PHP). O nome do banco na InfinityFree
 * costuma ser exatamente o usuário: if0_42990430.
 *
 * Este arquivo NÃO conecta nada sozinho — quem decide usar estes dados é a
 * função db_preference_is_configured() / db_connect_preference() em conexao.php.
 */

if (!defined('PREF_HOST')) {
    // Host MySQL da hospedagem (NÃO é o host FTP).
    define('PREF_HOST', getenv('DB_HOST') ?: 'sql309.infinityfree.com');
}

if (!defined('PREF_USER')) {
    // Usuário do MySQL = usuário da conta if0_.
    define('PREF_USER', getenv('DB_USER') ?: 'if0_42990430');
}

if (!defined('PREF_PASS')) {
    // Senha do MySQL da InfinityFree — preencha aqui para ativar a hospedagem.
    // Fica vazia de propósito: sem senha, a hospedagem é ignorada e o site usa
    // o banco local automaticamente (nenhum erro é exibido ao usuário).
    define('PREF_PASS', getenv('DB_PASS') ?: '');
}

if (!defined('PREF_NAME')) {
    // Na InfinityFree o banco tem o mesmo nome do usuário da conta.
    define('PREF_NAME', getenv('DB_NAME') ?: 'if0_42990430');
}

if (!defined('PREF_PORT')) {
    // A InfinityFree usa a porta padrão do MySQL.
    define('PREF_PORT', (int) (getenv('DB_PORT') ?: 3306));
}

if (!defined('PREF_DOMAIN')) {
    // Domínio principal do site na hospedagem (usado apenas para referência).
    define('PREF_DOMAIN', 'kv85ftlf.infinityfree.com');
}

if (!defined('PREF_FTP_HOST')) {
    // Host de upload de arquivos (FTP), guardado para referência de deploy.
    define('PREF_FTP_HOST', 'ftpupload.net');
}

if (!defined('PREF_FTP_USER')) {
    // Usuário de upload de arquivos (FTP).
    define('PREF_FTP_USER', 'if0_42990430');
}

if (!defined('PREF_VOLUME')) {
    // Volume de hospedagem contratado (Referência: vol8_1).
    define('PREF_VOLUME', 'vol8_1');
}

/**
 * Informa se a conexão de preferência (hospedagem) pode ser usada.
 * Só é considerada pronta quando existe host, usuário e senha preenchidos.
 */
function db_preference_is_configured(): bool
{
    return PREF_HOST !== '' && PREF_USER !== '' && PREF_PASS !== '';
}

/**
 * Tenta abrir a conexão com o banco da hospedagem (InfinityFree).
 * Devolve o mysqli conectado, ou null quando os dados estão incompletos
 * ou o servidor remoto está fora do ar/sem permissão — sem lançar erro fatal,
 * para que o site simplesmente continue usando o banco local.
 */
function db_connect_preference(?string $database = null): ?mysqli
{
    if (!db_preference_is_configured()) {
        return null;
    }

    $database = $database ?? PREF_NAME;

    try {
        $test = @new mysqli(PREF_HOST, PREF_USER, PREF_PASS, $database, PREF_PORT);
        if ($test->connect_errno) {
            return null;
        }
        $test->set_charset('utf8mb4');
        return $test;
    } catch (Throwable $e) {
        error_log('db_connect_preference: ' . $e->getMessage());
        return null;
    }
}