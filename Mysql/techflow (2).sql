-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3307
-- Tempo de geração: 04/09/2026 às 14:32
-- Versão do servidor: 8.0.44
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `techflow`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacoes_interacoes`
--

CREATE TABLE `avaliacoes_interacoes` (
  `id` int NOT NULL,
  `avaliacao_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `tipo` enum('like','denuncia') NOT NULL,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `motivo_denuncia` varchar(80) DEFAULT NULL,
  `detalhes_denuncia` text,
  `denunciante_nome` varchar(255) DEFAULT NULL,
  `denunciante_email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `avaliacoes_interacoes`
--

INSERT INTO `avaliacoes_interacoes` (`id`, `avaliacao_id`, `usuario_id`, `tipo`, `criado_em`, `motivo_denuncia`, `detalhes_denuncia`, `denunciante_nome`, `denunciante_email`) VALUES
(5, 6, 4, 'denuncia', '2026-09-04 12:28:10', 'Outro', 'ele chama o produto de ruim sendo que o produto ta claramente bom', 'Administrador', 'admin@techflow.com');

-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacoes_produtos`
--

CREATE TABLE `avaliacoes_produtos` (
  `id` int NOT NULL,
  `produto_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `usuario_nome` varchar(255) NOT NULL,
  `nota` int NOT NULL DEFAULT '5',
  `comentario` text NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Aprovado',
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `avaliacoes_produtos`
--

INSERT INTO `avaliacoes_produtos` (`id`, `produto_id`, `usuario_id`, `usuario_nome`, `nota`, `comentario`, `status`, `criado_em`) VALUES
(1, 9, 1, 'Cliente Demo', 5, 'PC Gamer excelente! Chegou muito rápido e muito bem embalado.', 'Aprovado', '2026-08-21 14:48:08'),
(2, 1, 9, 'pedro henrique lopes', 1, 'o escapamento nao veio', 'Rejeitado', '2026-08-26 14:04:29'),
(6, 22, 4, 'Administrador', 1, 'RUIM!!!', 'Aprovado', '2026-09-04 12:27:27');

-- --------------------------------------------------------

--
-- Estrutura para tabela `carts`
--

CREATE TABLE `carts` (
  `id` int NOT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` varchar(20) NOT NULL DEFAULT 'ativo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `carts`
--

INSERT INTO `carts` (`id`, `session_id`, `user_id`, `updated_at`, `status`) VALUES
(1, NULL, 1, '2026-08-21 14:47:45', 'ativo'),
(2, NULL, 4, '2026-08-21 14:49:38', 'ativo'),
(3, NULL, 9, '2026-08-31 11:49:46', 'finalizado'),
(4, NULL, 9, '2026-09-02 11:36:29', 'finalizado'),
(5, NULL, 9, '2026-09-02 11:39:23', 'ativo');

-- --------------------------------------------------------

--
-- Estrutura para tabela `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int NOT NULL,
  `cart_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `produto_id` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `cart_items`
--

INSERT INTO `cart_items` (`id`, `cart_id`, `product_id`, `quantity`, `price`, `created_at`, `produto_id`) VALUES
(115, 3, 1, 20, 949.90, '2026-08-31 11:49:26', 0),
(116, 3, 2, 2, 2199.00, '2026-08-31 11:49:26', 0),
(117, 3, 10, 1, 50000.00, '2026-08-31 11:49:26', 0),
(131, 4, 10, 1, 50000.00, '2026-09-02 11:36:21', 0),
(216, 2, 22, 2, 229.00, '2026-09-04 11:25:55', 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias`
--

CREATE TABLE `categorias` (
  `id` int NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text,
  `icone` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `categorias`
--

INSERT INTO `categorias` (`id`, `nome`, `descricao`, `icone`) VALUES
(1, 'Processadores', 'Desempenho para games, edição e multitarefas.', 'cpu-ryzen.png'),
(2, 'Placas de vídeo', 'Potência para jogos em alta qualidade.', 'gpu-rtx.png'),
(3, 'Memória RAM', 'Mais velocidade e estabilidade para o seu setup.', 'ram.png'),
(4, 'Armazenamento', 'SSD e HD com performance superior.', 'ssd.png'),
(5, 'Placas-mãe', 'Suporte a componentes modernos e expansível.', 'motherboard.png'),
(6, 'Gabinetes', 'Espaço e circulação de ar para builds profissionais.', 'gabinete.png'),
(7, 'Fontes', 'Alta eficiência e proteção para seus componentes.', 'fonte.png'),
(8, 'Refrigeração', 'Mantém sua CPU fria em qualquer situação.', 'cooler.png'),
(11, 'PC', 'Produtos da categoria PC', '');

-- --------------------------------------------------------

--
-- Estrutura para tabela `chamados_suporte`
--

CREATE TABLE `chamados_suporte` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `pedido_id` int DEFAULT NULL,
  `assunto` varchar(255) NOT NULL,
  `mensagem` text NOT NULL,
  `resposta` text,
  `status` varchar(30) NOT NULL DEFAULT 'Aberto',
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `chamados_suporte`
--

INSERT INTO `chamados_suporte` (`id`, `usuario_id`, `pedido_id`, `assunto`, `mensagem`, `resposta`, `status`, `criado_em`) VALUES
(1, 1, 1, 'Dúvida sobre a entrega do meu PC Gamer', 'Gostaria de confirmar a previsão de entrega do pedido #1.', 'chegara amanha', 'Respondido', '2026-08-21 14:48:08');

-- --------------------------------------------------------

--
-- Estrutura para tabela `cupons`
--

CREATE TABLE `cupons` (
  `id` int NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `desconto_percentual` decimal(5,2) NOT NULL DEFAULT '10.00',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `cupons`
--

INSERT INTO `cupons` (`id`, `codigo`, `desconto_percentual`, `ativo`, `criado_em`) VALUES
(1, 'TECH10', 10.00, 1, '2026-08-21 14:48:08'),
(2, 'GAMER15', 15.00, 1, '2026-08-21 14:48:08'),
(3, 'PROMO3005', 20.00, 1, '2026-09-04 12:09:06');

-- --------------------------------------------------------

--
-- Estrutura para tabela `enderecos`
--

CREATE TABLE `enderecos` (
  `id_endereco` int NOT NULL,
  `cep` varchar(10) NOT NULL,
  `rua` varchar(100) NOT NULL,
  `numero` varchar(5) NOT NULL,
  `cidade` varchar(100) NOT NULL,
  `estado` enum('AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO') NOT NULL,
  `id_usuario` int DEFAULT NULL,
  `usuario_id` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `enderecos`
--

INSERT INTO `enderecos` (`id_endereco`, `cep`, `rua`, `numero`, `cidade`, `estado`, `id_usuario`, `usuario_id`) VALUES
(1, '01003-000', 'Avenida Paulista', '1200', 'São Paulo', 'SP', NULL, 1),
(2, '01003-000', 'Avenida Paulista', '1200', 'São Paulo', 'SP', NULL, 16),
(3, '01003-000', 'Avenida Paulista', '1200', 'São Paulo', 'SP', NULL, 16),
(4, '01004-000', 'Avenida Brigadeiro Faria Lima', '2000', 'São Paulo', 'SP', 16, 16),
(5, '01004-000', 'Avenida Brigadeiro Faria Lima', '2000', 'São Paulo', 'SP', 16, 16),
(6, '01004-000', 'Avenida Brigadeiro Faria Lima', '2000', 'São Paulo', 'SP', 16, 16),
(7, '01004-000', 'Avenida Brigadeiro Faria Lima', '2000', 'São Paulo', 'SP', 16, 16),
(8, '01004-000', 'Avenida Brigadeiro Faria Lima', '2000', 'São Paulo', 'SP', 16, 16),
(9, '01004-000', 'Avenida Brigadeiro Faria Lima', '2000', 'São Paulo', 'SP', 16, 16),
(10, '01004-000', 'Avenida Brigadeiro Faria Lima', '2000', 'São Paulo', 'SP', 16, 16),
(11, '01004-000', 'Avenida Brigadeiro Faria Lima', '2000', 'São Paulo', 'SP', 16, 16),
(12, '01004-000', 'Avenida Brigadeiro Faria Lima', '2000', 'São Paulo', 'SP', 16, 16),
(13, '01004-000', 'Avenida Brigadeiro Faria Lima', '2000', 'São Paulo', 'SP', 16, 16),
(14, '72725331', 'Veredas', '1', 'Brasilia', 'DF', 9, 9);

-- --------------------------------------------------------

--
-- Estrutura para tabela `logistica_pedidos`
--

CREATE TABLE `logistica_pedidos` (
  `id` int NOT NULL,
  `pedido_id` int NOT NULL,
  `codigo_rastreio` varchar(100) DEFAULT NULL,
  `status_expedicao` varchar(50) NOT NULL DEFAULT 'Aguardando Separação',
  `atualizado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `logistica_pedidos`
--

INSERT INTO `logistica_pedidos` (`id`, `pedido_id`, `codigo_rastreio`, `status_expedicao`, `atualizado_em`) VALUES
(1, 1, 'TF123456789BR', 'Em Separação no Estoque', '2026-08-21 14:48:08'),
(2, 21, '72725301', 'Enviado', '2026-09-04 12:11:46'),
(3, 19, '72725301', 'Entregue', '2026-09-04 12:26:28');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `produto_id` int NOT NULL,
  `produto_nome` varchar(150) NOT NULL,
  `categoria` varchar(80) NOT NULL,
  `preco` decimal(10,2) NOT NULL,
  `quantidade` int NOT NULL DEFAULT '1',
  `status` varchar(30) NOT NULL DEFAULT 'Pago',
  `removido` tinyint(1) NOT NULL DEFAULT '0',
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `nome_cliente` varchar(255) DEFAULT NULL,
  `email_cliente` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `cep` varchar(20) DEFAULT NULL,
  `rua` varchar(255) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `pedidos`
--

INSERT INTO `pedidos` (`id`, `usuario_id`, `produto_id`, `produto_nome`, `categoria`, `preco`, `quantidade`, `status`, `removido`, `criado_em`, `nome_cliente`, `email_cliente`, `telefone`, `cep`, `rua`, `numero`, `cidade`, `estado`) VALUES
(4, 9, 2, 'GeForce RTX 4060', 'Placas de vídeo', 2199.00, 1, 'Reembolsado', 0, '2026-08-19 13:12:19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 9, 1, 'AMD Ryzen 5 5600 (Edicao Especial)', 'Processadores', 949.90, 1, 'Reembolsado', 1, '2026-08-21 12:01:12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 9, 1, 'AMD Ryzen 5 5600 (Edicao Especial)', 'Processadores', 949.90, 1, 'Reembolsado', 1, '2026-08-21 12:17:12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 3, 1, 'AMD Ryzen', 'CPUs', 899.00, 1, 'Pago', 0, '2026-08-21 13:09:47', 'João', 'demo@techflow.com', '11999998888', '01001000', 'Av Paulista', '1000', 'SP', 'SP'),
(10, 3, 1, 'AMD Ryzen 5 5600 (Edicao Especial)', 'Processadores', 949.90, 1, 'Pago', 0, '2026-08-21 13:09:59', 'João da Silva', 'demo@techflow.com', '(11) 98888-7777', '01001-000', 'Avenida Paulista', '1000', 'São Paulo', 'SP'),
(11, 9, 1, 'AMD Ryzen 5 5600 (Edicao Especial)', 'Processadores', 949.90, 1, 'Entregue', 1, '2026-08-21 13:12:43', '', '', '', '', '', '', '', ''),
(12, 9, 2, 'GeForce RTX 4060', 'Placas de vídeo', 2199.00, 1, 'Reembolsado', 0, '2026-08-21 13:27:33', 'pedro henrique lopes', 'ph305099@gmail.com', '(99) 98452-5003', '72725-301', 'Quadra', '3', 'Brasília', 'DF'),
(13, 9, 2, 'GeForce RTX 4060', 'Placas de vídeo', 2199.00, 1, 'Reembolsado', 0, '2026-08-21 13:27:41', 'pedro henrique lopes', 'ph305099@gmail.com', '(99) 98452-5003', '72725-301', 'Quadra', '3', 'Brasília', 'DF'),
(14, 9, 2, 'GeForce RTX 4060', 'Placas de vídeo', 2199.00, 1, 'Reembolsado', 0, '2026-08-21 13:29:26', 'pedro henrique lopes', 'ph305099@gmail.com', '(99) 98452-5003', '72725-301', 'Quadra', '3', 'Brasília', 'DF'),
(15, 9, 10, 'PC Gamer', 'PC', 50000.00, 1, 'Entregue', 0, '2026-08-21 13:34:22', 'pedro henrique lopes', 'ph305099@gmail.com', '(99) 98452-5003', '72725-301', 'Quadra', '3', 'Brasília', 'DF'),
(16, 1, 1, 'AMD Ryzen', 'CPUs', 899.00, 1, 'Pago', 0, '2026-08-21 13:34:54', 'João da Silva', 'demo@techflow.com', '11999998888', '01001000', 'Av Paulista', '1000', 'SP', 'SP'),
(17, 16, 1, 'AMD Ryzen 5 5600 (Edicao Especial)', 'Processadores', 949.90, 1, 'Pago', 0, '2026-08-21 13:36:51', 'João da Silva', 'demo@techflow.com', '(11) 98888-7777', '01001-000', 'Avenida Paulista', '1000', 'São Paulo', 'SP'),
(18, 1, 1, 'AMD Ryzen 5 5600 (Edicao Especial)', 'Processadores', 949.90, 1, 'Pago', 0, '2026-08-21 14:00:16', 'João da Silva', 'demo@techflow.com', '11999998888', '01001000', 'Av Paulista', '1000', 'São Paulo', 'SP'),
(19, 1, 1, 'AMD Ryzen 5 5600 (Edicao Especial)', 'Processadores', 949.90, 1, 'Entregue', 0, '2026-08-21 14:00:33', 'João da Silva', 'demo@techflow.com', '11999998888', '01001000', 'Av Paulista', '1000', 'São Paulo', 'SP'),
(20, 1, 1, 'AMD Ryzen 5 5600 (Edicao Especial)', 'Processadores', 949.90, 1, 'Pago', 0, '2026-08-21 14:01:00', 'João da Silva', 'demo@techflow.com', '11999998888', '01001000', 'Av Paulista', '1000', 'São Paulo', 'SP'),
(21, 16, 1, 'AMD Ryzen 5 5600 (Edicao Especial)', 'Processadores', 949.90, 1, 'Reembolsado', 0, '2026-08-21 14:03:45', 'João da Silva', 'demo@techflow.com', '(11) 98888-7777', '01001-000', 'Avenida Paulista', '1000', 'São Paulo', 'SP'),
(22, 9, 1, 'AMD Ryzen 5 5600 (Edicao Especial)', 'Processadores', 949.90, 20, 'Reembolsado', 0, '2026-08-31 11:49:46', 'pedro henrique lopes', 'ph305099@gmail.com', '(99) 98452-5003', '72725331', 'Veredas', '1', 'Brasilia', 'DF'),
(23, 9, 2, 'GeForce RTX 4060', 'Placas de vídeo', 2199.00, 2, 'Reembolsado', 1, '2026-08-31 11:49:46', 'pedro henrique lopes', 'ph305099@gmail.com', '(99) 98452-5003', '72725331', 'Veredas', '1', 'Brasilia', 'DF'),
(24, 9, 10, 'PC Gamer', 'PC', 50000.00, 1, 'Reembolsado', 0, '2026-08-31 11:49:46', 'pedro henrique lopes', 'ph305099@gmail.com', '(99) 98452-5003', '72725331', 'Veredas', '1', 'Brasilia', 'DF'),
(25, 9, 10, 'PC Gamer', 'PC', 50000.00, 1, 'Reembolsado', 0, '2026-09-02 11:36:29', 'pedro henrique lopes', 'ph305099@gmail.com', '(99) 98452-5003', '72725331', 'Veredas', '1', 'Brasilia', 'DF');

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int NOT NULL,
  `nome` varchar(150) NOT NULL,
  `categoria` varchar(80) NOT NULL,
  `preco` decimal(10,2) NOT NULL,
  `descricao` text NOT NULL,
  `imagem` varchar(255) NOT NULL,
  `destaque` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `nome`, `categoria`, `preco`, `descricao`, `imagem`, `destaque`, `created_at`) VALUES
(1, 'AMD Ryzen 5 5600', 'Processadores', 899.00, 'Versão com clock aprimorado e cooler box incluso.', 'cpu-ryzen.png', 1, '2026-08-19 12:35:04'),
(2, 'GeForce RTX 4060', 'Placas de vídeo', 2199.00, 'Excelente desempenho para jogos em Full HD e 2K com ray tracing.', 'gpu-rtx.png', 1, '2026-08-19 12:35:04'),
(3, 'SSD NVMe 1TB', 'Armazenamento', 349.00, 'Mais velocidade de boot e carregamento de jogos e programas.', 'ssd.png', 1, '2026-08-19 12:35:04'),
(4, 'Memória RAM DDR5 32GB', 'Memória RAM', 749.00, 'Kit 2x16GB com mais velocidade e estabilidade para multitarefas.', 'ram.png', 0, '2026-08-19 12:35:04'),
(5, 'Placa-mãe B650 Gaming', 'Placas-mãe', 1099.00, 'Suporte a DDR5 e PCIe 4.0 para builds modernas e expansíveis.', 'motherboard.png', 0, '2026-08-19 12:35:04'),
(6, 'Gabinete Gamer Mid-Tower', 'Gabinetes', 459.00, 'Lateral em vidro temperado e fans RGB para exibir seu setup com estilo.', 'gabinete.png', 0, '2026-08-19 12:35:04'),
(7, 'Fonte 750W 80 Plus Gold', 'Fontes', 629.00, 'Fonte modular com alta eficiência e proteção para seus componentes.', 'fonte.png', 0, '2026-08-19 12:35:04'),
(8, 'Water Cooler 240mm', 'Refrigeração', 539.00, 'Refrigeração líquida com iluminação RGB para manter a CPU fria.', 'cooler.png', 0, '2026-08-19 12:35:04'),
(9, 'PC Gamer TechFlow RGB RTX 4060', 'PCs Gamer', 4599.00, 'PC Gamer completo montado e testado com Ryzen 5, RTX 4060, 16GB RAM DDR5 e SSD 1TB.', 'gabinete.png', 1, '2026-09-02 12:14:21'),
(10, 'Ryzen 7 5700X', 'PC', 1299.00, 'PC Gamer Completinho E Farmador De Aura', 'prod_1787150294_4245.jpg', 1, '2026-08-19 14:38:14'),
(11, 'Ryzen 7 7800X3D', 'Processadores', 2399.00, 'Componente selecionado para montar um computador rápido e confiável.', 'cpu-ryzen.png', 0, '2026-09-02 12:18:47'),
(12, 'Core i5 14400F', 'Processadores', 1199.00, 'Componente selecionado para montar um computador rápido e confiável.', 'cpu-ryzen.png', 0, '2026-09-02 12:18:47'),
(13, 'Core i7 14700K', 'Processadores', 2499.00, 'Componente selecionado para montar um computador rápido e confiável.', 'cpu-ryzen.png', 0, '2026-09-02 12:18:47'),
(14, 'GeForce RTX 4060 Ti', 'Placas de vídeo', 2699.00, 'Componente selecionado para montar um computador rápido e confiável.', 'gpu-rtx.png', 0, '2026-09-02 12:18:47'),
(15, 'GeForce RTX 4070', 'Placas de vídeo', 3999.00, 'Componente selecionado para montar um computador rápido e confiável.', 'gpu-rtx.png', 0, '2026-09-02 12:18:47'),
(16, 'Radeon RX 7600', 'Placas de vídeo', 2299.00, 'Componente selecionado para montar um computador rápido e confiável.', 'gpu-rtx.png', 0, '2026-09-02 12:18:47'),
(17, 'GeForce RTX 4080 Super', 'Placas de vídeo', 6999.00, 'Componente selecionado para montar um computador rápido e confiável.', 'gpu-rtx.png', 0, '2026-09-02 12:18:47'),
(18, 'Memória DDR4 16GB', 'Memória RAM', 299.00, 'Componente selecionado para montar um computador rápido e confiável.', 'ram.png', 0, '2026-09-02 12:18:47'),
(19, 'Memória DDR5 16GB', 'Memória RAM', 399.00, 'Componente selecionado para montar um computador rápido e confiável.', 'ram.png', 0, '2026-09-02 12:18:47'),
(20, 'Kit RAM DDR5 64GB', 'Memória RAM', 1299.00, 'Componente selecionado para montar um computador rápido e confiável.', 'ram.png', 0, '2026-09-02 12:18:47'),
(21, 'Memória RGB 32GB', 'Memória RAM', 849.00, 'Componente selecionado para montar um computador rápido e confiável.', 'ram.png', 0, '2026-09-02 12:18:47'),
(22, 'SSD NVMe 500GB', 'Armazenamento', 229.00, 'Componente selecionado para montar um computador rápido e confiável.', 'ssd.png', 0, '2026-09-02 12:18:47'),
(23, 'SSD NVMe 2TB', 'Armazenamento', 699.00, 'Componente selecionado para montar um computador rápido e confiável.', 'ssd.png', 0, '2026-09-02 12:18:47'),
(24, 'HD 2TB Sata', 'Armazenamento', 429.00, 'Componente selecionado para montar um computador rápido e confiável.', 'ssd.png', 0, '2026-09-02 12:18:47'),
(25, 'SSD Sata 1TB', 'Armazenamento', 389.00, 'Componente selecionado para montar um computador rápido e confiável.', 'ssd.png', 0, '2026-09-02 12:18:47'),
(26, 'Placa-mãe B550M', 'Placas-mãe', 699.00, 'Componente selecionado para montar um computador rápido e confiável.', 'motherboard.png', 0, '2026-09-02 12:18:47'),
(27, 'Placa-mãe X670 Gaming', 'Placas-mãe', 1899.00, 'Componente selecionado para montar um computador rápido e confiável.', 'motherboard.png', 0, '2026-09-02 12:18:47'),
(28, 'Placa-mãe H610M', 'Placas-mãe', 499.00, 'Componente selecionado para montar um computador rápido e confiável.', 'motherboard.png', 0, '2026-09-02 12:18:47'),
(29, 'Placa-mãe Z790 Wi-Fi', 'Placas-mãe', 2199.00, 'Componente selecionado para montar um computador rápido e confiável.', 'motherboard.png', 0, '2026-09-02 12:18:47'),
(30, 'Gabinete Compacto Airflow', 'Gabinetes', 329.00, 'Componente selecionado para montar um computador rápido e confiável.', 'gabinete.png', 0, '2026-09-02 12:18:47'),
(31, 'Gabinete RGB Glass', 'Gabinetes', 579.00, 'Componente selecionado para montar um computador rápido e confiável.', 'gabinete.png', 0, '2026-09-02 12:18:47'),
(32, 'Gabinete Full Tower', 'Gabinetes', 899.00, 'Componente selecionado para montar um computador rápido e confiável.', 'gabinete.png', 0, '2026-09-02 12:18:47'),
(33, 'Gabinete Mesh Branco', 'Gabinetes', 649.00, 'Componente selecionado para montar um computador rápido e confiável.', 'gabinete.png', 0, '2026-09-02 12:18:47'),
(34, 'Fonte 550W 80 Plus', 'Fontes', 399.00, 'Componente selecionado para montar um computador rápido e confiável.', 'fonte.png', 0, '2026-09-02 12:18:47'),
(35, 'Fonte 850W Modular', 'Fontes', 899.00, 'Componente selecionado para montar um computador rápido e confiável.', 'fonte.png', 0, '2026-09-02 12:18:47'),
(36, 'Fonte 1000W Gold', 'Fontes', 1199.00, 'Componente selecionado para montar um computador rápido e confiável.', 'fonte.png', 0, '2026-09-02 12:18:47'),
(37, 'Fonte 650W Bronze', 'Fontes', 479.00, 'Componente selecionado para montar um computador rápido e confiável.', 'fonte.png', 0, '2026-09-02 12:18:47'),
(38, 'Air Cooler 120mm', 'Refrigeração', 159.00, 'Componente selecionado para montar um computador rápido e confiável.', 'cooler.png', 0, '2026-09-02 12:18:47'),
(39, 'Water Cooler 120mm', 'Refrigeração', 299.00, 'Componente selecionado para montar um computador rápido e confiável.', 'cooler.png', 0, '2026-09-02 12:18:47'),
(40, 'Water Cooler 360mm', 'Refrigeração', 799.00, 'Componente selecionado para montar um computador rápido e confiável.', 'cooler.png', 0, '2026-09-02 12:18:47'),
(41, 'Kit 3 Fans RGB', 'Refrigeração', 219.00, 'Componente selecionado para montar um computador rápido e confiável.', 'cooler.png', 0, '2026-09-02 12:18:47'),
(42, 'PC Gamer Ryzen 5', 'PCs Gamer', 3299.00, 'Componente selecionado para montar um computador rápido e confiável.', 'gabinete.png', 0, '2026-09-02 12:18:47'),
(43, 'PC Gamer RTX 4060 Ti', 'PCs Gamer', 5499.00, 'Componente selecionado para montar um computador rápido e confiável.', 'gabinete.png', 0, '2026-09-02 12:18:47'),
(44, 'PC Gamer Ryzen 7', 'PCs Gamer', 6499.00, 'Componente selecionado para montar um computador rápido e confiável.', 'gabinete.png', 0, '2026-09-02 12:18:47'),
(45, 'PC Gamer Black Edition', 'PCs Gamer', 7999.00, 'Componente selecionado para montar um computador rápido e confiável.', 'gabinete.png', 0, '2026-09-02 12:18:47');

-- --------------------------------------------------------

--
-- Estrutura para tabela `recuperacao_senhas`
--

CREATE TABLE `recuperacao_senhas` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expira_em` datetime NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT '0',
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `recuperacao_senhas`
--

INSERT INTO `recuperacao_senhas` (`id`, `usuario_id`, `token_hash`, `expira_em`, `usado`, `criado_em`) VALUES
(4, 9, 'f5eaa21afdb77c7f2d75be1fd843af45c46774c19a16d0b6c4a77b653f6a2766', '2026-09-04 15:07:22', 1, '2026-09-04 12:07:22');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `email` varchar(255) NOT NULL,
  `nascimento` varchar(50) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` varchar(50) NOT NULL DEFAULT 'cliente',
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `avatar` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `cep` varchar(20) DEFAULT NULL,
  `rua` varchar(255) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` varchar(10) DEFAULT NULL,
  `chave_mestre` varchar(255) DEFAULT NULL,
  `status_conta` varchar(20) NOT NULL DEFAULT 'ativo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `email`, `nascimento`, `nome`, `senha`, `tipo`, `is_admin`, `avatar`, `telefone`, `created_at`, `cep`, `rua`, `numero`, `cidade`, `estado`, `chave_mestre`, `status_conta`) VALUES
(4, 'admin@techflow.com', '1990-01-15', 'Administrador', '$2y$10$YJwGr8oh1nPud/edW2RMuullyQLVHWBl/MgjWBG34/hSBDb8EdcDS', 'admin', 1, 'assets/img/avatars/avatar_4_1787147198.jpg', NULL, '2026-08-21 13:08:21', NULL, NULL, NULL, NULL, NULL, NULL, 'ativo'),
(9, 'ph305099@gmail.com', '2008-05-30', 'pedro henrique lopes', '$2y$10$eW3DKCmDKW.An77C7IQ4GuzKbe.GDc.LzWV9t6qR2Nhv.tYpZ98fS', 'customer', 0, NULL, '(99) 98452-5003', '2026-08-21 13:08:21', NULL, NULL, NULL, NULL, NULL, NULL, 'ativo'),
(15, 'henrique@gmail.com', '2009-12-17', 'Henrique Bernades', '$2y$10$YJwGr8oh1nPud/edW2RMuullyQLVHWBl/MgjWBG34/hSBDb8EdcDS', 'admin', 1, NULL, NULL, '2026-08-21 13:08:21', NULL, NULL, NULL, NULL, NULL, NULL, 'ativo'),
(16, 'demo@techflow.com', '1998-05-20', 'João da Silva', '$2y$10$YJwGr8oh1nPud/edW2RMuullyQLVHWBl/MgjWBG34/hSBDb8EdcDS', 'cliente', 0, NULL, '(11) 98888-7777', '2026-08-21 13:36:47', NULL, NULL, NULL, NULL, NULL, NULL, 'ativo'),
(17, 'dev@techflow.com', '1994-03-10', 'Desenvolvedor Lead', '$2y$10$YJwGr8oh1nPud/edW2RMuullyQLVHWBl/MgjWBG34/hSBDb8EdcDS', 'developer', 0, NULL, NULL, '2026-08-21 14:40:29', NULL, NULL, NULL, NULL, NULL, 'dev12345', 'ativo'),
(18, 'suporte@techflow.com', '1996-07-22', 'Atendente Suporte', '$2y$10$YJwGr8oh1nPud/edW2RMuullyQLVHWBl/MgjWBG34/hSBDb8EdcDS', 'support', 0, NULL, NULL, '2026-08-21 14:40:29', NULL, NULL, NULL, NULL, NULL, 'supp1234', 'ativo'),
(19, 'mod@techflow.com', '1995-11-05', 'Moderador de Conteúdo', '$2y$10$YJwGr8oh1nPud/edW2RMuullyQLVHWBl/MgjWBG34/hSBDb8EdcDS', 'moderator', 0, NULL, NULL, '2026-08-21 14:40:29', NULL, NULL, NULL, NULL, NULL, 'mod12345', 'ativo'),
(20, 'gerente@techflow.com', '1992-09-18', 'Gerente da Loja', '$2y$10$YJwGr8oh1nPud/edW2RMuullyQLVHWBl/MgjWBG34/hSBDb8EdcDS', 'manager', 0, NULL, NULL, '2026-08-21 14:40:29', NULL, NULL, NULL, NULL, NULL, 'man12345', 'ativo'),
(21, 'financeiro@techflow.com', '1991-04-30', 'Analista Financeiro', '$2y$10$YJwGr8oh1nPud/edW2RMuullyQLVHWBl/MgjWBG34/hSBDb8EdcDS', 'financial', 0, NULL, NULL, '2026-08-21 14:40:29', NULL, NULL, NULL, NULL, NULL, 'fin12345', 'ativo'),
(22, 'logistica@techflow.com', '1993-12-12', 'Operador Logístico', '$2y$10$YJwGr8oh1nPud/edW2RMuullyQLVHWBl/MgjWBG34/hSBDb8EdcDS', 'customer', 0, NULL, NULL, '2026-08-21 14:40:29', NULL, NULL, NULL, NULL, NULL, 'log12345', 'ativo');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `avaliacoes_interacoes`
--
ALTER TABLE `avaliacoes_interacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `avaliacao_usuario_tipo` (`avaliacao_id`,`usuario_id`,`tipo`),
  ADD KEY `avaliacao_id_idx` (`avaliacao_id`),
  ADD KEY `usuario_id_idx` (`usuario_id`);

--
-- Índices de tabela `avaliacoes_produtos`
--
ALTER TABLE `avaliacoes_produtos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cart_id_idx` (`cart_id`),
  ADD KEY `product_id_idx` (`product_id`);

--
-- Índices de tabela `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `chamados_suporte`
--
ALTER TABLE `chamados_suporte`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `cupons`
--
ALTER TABLE `cupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Índices de tabela `enderecos`
--
ALTER TABLE `enderecos`
  ADD PRIMARY KEY (`id_endereco`),
  ADD KEY `fk_enderecos_usuarios` (`id_usuario`);

--
-- Índices de tabela `logistica_pedidos`
--
ALTER TABLE `logistica_pedidos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `recuperacao_senhas`
--
ALTER TABLE `recuperacao_senhas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `usuario_id_idx` (`usuario_id`),
  ADD KEY `expira_em_idx` (`expira_em`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `avaliacoes_interacoes`
--
ALTER TABLE `avaliacoes_interacoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `avaliacoes_produtos`
--
ALTER TABLE `avaliacoes_produtos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `carts`
--
ALTER TABLE `carts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=220;

--
-- AUTO_INCREMENT de tabela `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `chamados_suporte`
--
ALTER TABLE `chamados_suporte`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `cupons`
--
ALTER TABLE `cupons`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `enderecos`
--
ALTER TABLE `enderecos`
  MODIFY `id_endereco` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `logistica_pedidos`
--
ALTER TABLE `logistica_pedidos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT de tabela `recuperacao_senhas`
--
ALTER TABLE `recuperacao_senhas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `enderecos`
--
ALTER TABLE `enderecos`
  ADD CONSTRAINT `fk_enderecos_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
