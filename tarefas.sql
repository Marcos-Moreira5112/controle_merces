-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 05/03/2026 às 15:10
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `otica_db`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `tarefas`
--

CREATE TABLE `tarefas` (
  `id` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `prazo` date NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pendente',
  `observacoes` text DEFAULT NULL,
  `arquivada` tinyint(1) DEFAULT 0,
  `data_arquivamento` datetime DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `atribuida_para` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tipo` enum('normal','fixa') NOT NULL DEFAULT 'normal',
  `mes_referencia` int(11) DEFAULT NULL,
  `ano_referencia` int(11) DEFAULT NULL,
  `tarefa_origem_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tarefas`
--

INSERT INTO `tarefas` (`id`, `titulo`, `prazo`, `status`, `observacoes`, `arquivada`, `data_arquivamento`, `usuario_id`, `atribuida_para`, `created_at`, `tipo`, `mes_referencia`, `ano_referencia`, `tarefa_origem_id`) VALUES
(38, 'Adicionar configuração de não deixar adicionar tarefa com data anterior', '2026-01-20', 'concluida', 'oi, testejhuvvvv', 1, NULL, 1, NULL, '2026-01-20 18:05:51', 'normal', NULL, NULL, NULL),
(40, 'adicionar configuração de ajeitar essas datas aí tudo errada', '2026-01-20', 'concluida', 'oi, teste 2', 1, NULL, 1, NULL, '2026-01-20 19:19:28', 'normal', NULL, NULL, NULL),
(47, 'tarefa fixa 2', '2026-01-22', 'concluida', NULL, 0, NULL, 1, NULL, '2026-01-22 21:10:38', 'fixa', NULL, NULL, NULL),
(50, 'tarefa fixa 2', '2026-02-22', 'pendente', NULL, 0, NULL, 1, NULL, '2026-01-23 14:32:45', 'fixa', 2, 2026, 47),
(55, 'tarefa teste', '2026-01-29', 'pendente', NULL, 0, NULL, 1, 2, '2026-01-29 15:15:03', 'fixa', NULL, NULL, NULL),
(56, 'tarefa teste', '2026-03-01', 'pendente', NULL, 0, NULL, 1, NULL, '2026-01-30 16:04:11', 'fixa', 3, 2026, 55),
(57, 'preciso adicionar menu hamburguer', '2026-02-03', 'pendente', 'teste de observção', 0, NULL, 1, NULL, '2026-01-31 13:19:48', 'normal', NULL, NULL, NULL),
(58, 'Adicionar bgl de colocar a senha manualmente', '2026-02-03', 'concluida', NULL, 0, NULL, 1, NULL, '2026-01-31 13:23:22', 'normal', NULL, NULL, NULL),
(59, 'adicionar filtro de pessoas pra supervisores e chefes', '2026-03-03', 'pendente', NULL, 0, NULL, 1, NULL, '2026-01-31 13:23:53', 'normal', NULL, NULL, NULL),
(60, 'adicionar dica que fica mudando toda vez que atualiza ou algo do tipo', '2026-02-03', 'concluida', NULL, 0, NULL, 1, NULL, '2026-01-31 13:27:12', 'normal', NULL, NULL, NULL),
(62, 'Adicionar tela de cadastro da empresa', '2026-02-03', 'pendente', NULL, 0, NULL, 1, NULL, '2026-01-31 13:34:14', 'normal', NULL, NULL, NULL),
(63, 'Ver essa questão do total de tarefas que aparece que está meio errado', '2026-02-03', 'pendente', NULL, 0, NULL, 1, NULL, '2026-01-31 13:38:07', 'normal', NULL, NULL, NULL),
(68, 'teste para o usuário teste', '2026-02-03', 'pendente', NULL, 0, NULL, 1, 4, '2026-01-31 16:43:15', 'normal', NULL, NULL, NULL),
(69, 'Adicionar filtro por usuários', '2026-02-03', 'pendente', NULL, 0, NULL, 1, NULL, '2026-01-31 16:43:45', 'normal', NULL, NULL, NULL),
(70, 'ajeitar os cabeçalhos', '2026-02-03', 'pendente', NULL, 0, NULL, 1, NULL, '2026-01-31 16:44:10', 'normal', NULL, NULL, NULL),
(71, 'adicionar \"classificar\" além do  filtro', '2026-02-03', 'pendente', NULL, 0, NULL, 1, NULL, '2026-01-31 16:46:37', 'normal', NULL, NULL, NULL),
(74, 'tarefa fixa 2', '2026-03-22', 'pendente', NULL, 0, NULL, 1, NULL, '2026-03-03 13:38:56', 'fixa', 3, 2026, 47),
(75, 'tarefa teste', '2026-04-01', 'pendente', NULL, 0, NULL, 1, NULL, '2026-03-03 13:38:56', 'fixa', 4, 2026, 55),
(76, 'jvhvgv', '2026-03-03', 'pendente', NULL, 0, NULL, 1, NULL, '2026-03-03 13:39:10', 'normal', NULL, NULL, NULL);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `tarefas`
--
ALTER TABLE `tarefas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_usuario` (`usuario_id`),
  ADD KEY `atribuida_para` (`atribuida_para`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `tarefas`
--
ALTER TABLE `tarefas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `tarefas`
--
ALTER TABLE `tarefas`
  ADD CONSTRAINT `fk_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tarefas_ibfk_1` FOREIGN KEY (`atribuida_para`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
