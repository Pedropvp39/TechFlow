# TechFlow

Loja demonstrativa de pecas de PC feita em PHP, MySQL, HTML, CSS e JavaScript. O projeto inclui catalogo, busca, filtros, carrinho persistente, checkout, contas, enderecos, pedidos, avaliacoes, curtidas, denuncias e paineis por funcao e varias outras coisas.

## 1. Requisitos

- Windows com XAMPP.
- Apache ativo.
- MySQL ativo.
- PHP 8.0 ou superior.
- Deixar o site na pasta `Projeto-Integrador`.
- Extensoes PHP `mysqli`, `fileinfo` e `mbstring` habilitadas.

## 2. Instalacao no XAMPP
> **ATENCAO — muito importante:** abra o site SEMPRE pelo endereco
> `http://localhost/...` (ou pelo `ABRIR-SITE.bat`). **Nunca** clique duas
> vezes no `index.html`. Se abrir o arquivo direto do disco, os botoes e
> links vao BAIXAR os arquivos `.php` em vez de abrir as paginas, porque o
> PHP precisa do Apache para ser executado.

1. Copie a pasta para `C:\xampp\htdocs\`.
2. Abra o painel do XAMPP.
3. Inicie Apache e MySQL.
4. Abra no navegador:

   `http://localhost/Projeto-Integrador/
   index.html`

   (ou execute o `ABRIR-SITE.bat`, que liga o Apache e abre o site sozinho)

O sistema cria o banco e as tabelas automaticamente conforme as credenciais configuradas em `php/conexao.php`.

## 3. Configuracao do banco

A configuracao padrao usa:

- Host: `127.0.0.1`
- Usuario: `root`
- Senha: Geralmente vem vazia
- Banco: `techflow`
- Porta: `3307`

Para outro ambiente, ajuste as constantes no inicio de `php/conexao.php` ou use variaveis de ambiente quando essa versao estiver configurada.

O schema cria e atualiza tabelas de usuarios, produtos, categorias, pedidos, carrinhos, enderecos, cupons, chamados, avaliacoes, interacoes e logistica.

## 4. Contas de demonstracao

A senha de acesso inicial das contas criadas automaticamente e `Teste123`.

| Cargos | E-mails |
| --- | --- |
| Administrador | `admin@techflow.com` |
| Desenvolvedor | `dev@techflow.com` |
| Suporte | `suporte@techflow.com` |
| Moderador | `mod@techflow.com` |
| Gerente | `gerente@techflow.com` |
| Financeiro | `financeiro@techflow.com` |
| Logistica | `logistica@techflow.com` |
| Cliente demo | `demo@techflow.com` |

As chaves mestre dos cargos sao usadas para autorizar operacoes sensiveis. Em ambiente real, troque todas elas e configure `ADMIN_MASTER_PIN` fora do codigo.

## 5. Paginas principais

- `index.html`: home ou pagina inicial, categorias, destaques e estatisticas atuais.
- `pages/produtos.php`: catalogo, busca, categorias, filtros e ordenacao.
- `pages/produto.php?id=1`: detalhe, compra, avaliacao, curtida e denuncia.
- `pages/carrinho.php`: carrinho e checkout em etapas.
- `pages/login.php`: entrada na conta.
- `pages/cadastro.php`: criacao de conta.
- `pages/dashboard.php`: perfil, avatar, pedidos e dados pessoais.
- `pages/enderecos.php`: cadastro e remocao de enderecos.
- `pages/admin-produtos.php`: administracao geral.
- `pages/painel.php?area=...`: modulos de desenvolvimento, suporte, moderacao, loja, financeiro e logistica.
- `pages/ajuda.php`: FAQ, termos, trocas e privacidade.

## 6. Fluxo de compra

1. Abra o catalogo ou um produto.
2. Clique em `Comprar` ou `Adicionar ao carrinho`.
3. No carrinho, altere quantidades ou remova produtos.
4. Escolha frete e pagamento.
5. Avance para o endereco e selecione um endereco salvo.
6. Confira a revisao.
7. Finalize o pedido.
8. Consulte o pedido em `Meu perfil`.

O carrinho usa `localStorage` para a experiencia imediata e sincroniza com a sessao ao seu  MySQL. O servidor valida produtos existentes e limita quantidades.

## 7. Avaliacoes e denuncias

Clientes autenticados podem publicar avaliacao e interagir com comentarios aprovados.

Ao denunciar, o cliente escolhe um motivo:

- Ofensa ou assedio.
- Spam ou propaganda.
- Conteudo improprio.
- Informacao falsa.
- Outro.

Tambem pode informar detalhes. O sistema registra a avaliacao, o motivo, os detalhes e o nome/e-mail do denunciante. Moderadores e administradores visualizam esses dados junto com curtidas e total de denuncias.

A moderacao pode:

- Aprovar ou rejeitar avaliacao.
- Excluir comentario e interacoes.
- Bloquear o autor da avaliacao.
- Aprovar conta recriada por usuario bloqueado.

## 8. Contas bloqueadas

Uma conta pode ter estes estados:

- `ativo`: pode entrar normalmente.
- `bloqueado`: nao pode fazer login.
- `pendente`: aguarda aprovacao da moderacao.

Quando uma conta bloqueada tenta se recriar usando o mesmo e-mail, os dados sao atualizados e o estado vira `pendente`. O login continua bloqueado ate um administrador ou moderador aprovar a conta.

Nao existe login em nome de outro cliente ou mecanismo de impersonacao.

## 9. Permissoes

- Administrador: acesso total a produtos, usuarios, pedidos, categorias, denuncias, moderacao, suporte, cupons, financeiro, logistica e desenvolvimento.
- Desenvolvedor: diagnostico tecnico e cache.
- Suporte: chamados e consulta de pedidos.
- Moderador: avaliacoes, denuncias, bloqueios e aprovacao de contas.
- Gerente: produtos, precos, destaques e cupons.
- Financeiro: pedidos, valores e reembolsos.
- Logistica: expedicao, status e rastreio.
- Cliente: catalogo, carrinho, compras, perfil, enderecos e avaliacoes.

Operacoes administrativas exigem CSRF e chave mestre quando aplicavel.

## 10. Seguranca

- Formularios POST usam token CSRF.
- Endpoints AJAX do carrinho e checkout aceitam apenas POST com CSRF.
- Consultas de banco usam `mysqli` e consultas preparadas nas entradas dinamicas.
- Saidas HTML passam por escape com `htmlspecialchars`.
- Uploads validam MIME e tamanho.
- Produtos e quantidades sao conferidos no servidor.
- Nunca use senhas e chaves de demonstracao em producao.

## 11. Arquivo de exemplo de conexao

O arquivo `php/conexao-exemplo.php` demonstra, de forma isolada e comentada, como:

1. Definir credenciais.
2. Abrir conexao com `mysqli`.
3. Definir `utf8mb4`.
4. Preparar uma consulta.
5. Vincular parametros.
6. Executar e ler o resultado.
7. Fechar recursos.

Ele e didatico e nao substitui `php/conexao.php`.

## 12. Testes manuais

### Publico

- Abrir home.
- Avancar e voltar slides.
- Usar busca no topo.
- Abrir cada categoria.
- Filtrar e ordenar catalogo.
- Abrir detalhe de produto.
- Adicionar produto ao carrinho.
- Remover produto e alterar quantidade.

### Conta e compra

- Cadastrar conta valida.
- Recusar cadastro com dados invalidos.
- Entrar e sair.
- Atualizar perfil.
- Adicionar e excluir endereco.
- Avancar checkout ate revisao.
- Finalizar pedido e conferir dashboard.

### Administracao

- Entrar com cada conta de cargo.
- Abrir todos os paineis.
- Cadastrar e editar produto.
- Criar cupom.
- Atualizar pedido e rastreio.
- Processar reembolso.
- Publicar avaliacao.
- Curtir e denunciar comentario.
- Ver motivo e denunciante no moderador.
- Excluir comentario.
- Bloquear usuario.
- Aprovar conta pendente.

### Validacao tecnica

No PowerShell, na pasta do projeto:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Todos os arquivos devem retornar `No syntax errors detected`.

## 13. Problemas comuns

### Ao clicar em um link o arquivo e BAIXADO em vez de abrir a pagina
Isso acontece quando o `index.html` e aberto direto pelo disco (endereco
`file:///C:/...`), clicando duas vezes no arquivo. Os links apontam para
arquivos `.php`, e o PHP so funciona quando o site e aberto pelo Apache.

**Solucao:** abra o site pelo endereco (ou use o `ABRIR-SITE.bat`):

`http://localhost/Projeto-Integrador/Projeto-Integrador/index.html`

Nunca clique duas vezes no `index.html` para abrir o site.

### Apache nao abre o projeto
Confirme que Apache esta ativo e que a pasta esta dentro de `htdocs`. Verifique se a URL possui `/Projeto-Integrador/`.

### Erro de conexao MySQL

Confirme que MySQL esta ativo, revise host, usuario, senha e porta em `php/conexao.php` e confira se a extensao `mysqli` esta habilitada.

### Carrinho parece restaurar item removido

Recarregue a pagina depois da atualizacao dos arquivos JavaScript. O carrinho atual usa o estado salvo no navegador como fonte principal e sincroniza com o servidor.

### Conta nao consegue entrar

Confira se ela esta `ativo`. Contas `bloqueado` ou `pendente` precisam de decisao da moderacao.

### Estilos antigos aparecem

O CSS recebe uma versao baseada no horario de modificacao. Force uma atualizacao do navegador com `Ctrl+F5` se houver cache antigo.

## 14. Estrutura resumida

```text
assets/
  css/style.css
  img/
  js/
includes/
  auth.php
  cart.php
  config.php
  data.php
  header.php
  pedidos.php
pages/
  index publico e telas da aplicacao
php/
  endpoints POST/AJAX e conexao MySQL
```

## 15. Observacao para producao

Este projeto e uma base didatica. Antes de publicar, troque credenciais, remova dados de demonstracao, configure HTTPS, desative exibicao de erros, use variaveis de ambiente e revise regras de estoque, pagamento, auditoria e privacidade.
 ## 16. Acesso Online
 https://pedropvp39.github.io/Vendas_Online/
