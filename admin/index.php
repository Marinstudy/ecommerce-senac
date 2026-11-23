<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$utilizador = $_SESSION['utilizador'] ?? null;

if (!$utilizador || ($utilizador['perfil'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$mensagem = '';
$erro = '';

$categorias = $pdo->query("SELECT id, nome FROM categorias ORDER BY nome ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acaoPost = $_POST['acao_post'] ?? '';

    if ($acaoPost === 'criar_produto') {
        $nome        = trim($_POST['nome'] ?? '');
        $descricao   = trim($_POST['descricao'] ?? '');
        $categoriaId = (int) ($_POST['categoria_id'] ?? 0);
        $preco       = (float) str_replace(',', '.', $_POST['preco'] ?? '0');
        $estoque     = (int) ($_POST['estoque'] ?? 0);
        $imagem      = trim($_POST['imagem'] ?? '');

        if ($nome === '' || $categoriaId <= 0 || $preco <= 0 || $estoque < 0) {
            $erro = 'Preenche nome, categoria, preço maior que zero e estoque válido.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO produtos (categoria_id, nome, descricao, preco, estoque, imagem, ativo)
                VALUES (:categoria_id, :nome, :descricao, :preco, :estoque, :imagem, 1)
            ");
            $stmt->execute([
                'categoria_id' => $categoriaId,
                'nome'         => $nome,
                'descricao'    => $descricao,
                'preco'        => $preco,
                'estoque'      => $estoque,
                'imagem'       => $imagem,
            ]);
            $mensagem = 'Produto cadastrado com sucesso!';
        }
    }

    if ($acaoPost === 'atualizar_produto') {
        $prodId  = (int) ($_POST['produto_id'] ?? 0);
        $preco   = (float) str_replace(',', '.', $_POST['preco'] ?? '0');
        $estoque = (int) ($_POST['estoque'] ?? 0);
        $ativo   = isset($_POST['ativo']) ? 1 : 0;

        if ($prodId <= 0 || $preco <= 0 || $estoque < 0) {
            $erro = 'Dados inválidos para atualizar o produto.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE produtos
                SET preco = :preco, estoque = :estoque, ativo = :ativo
                WHERE id = :id
            ");
            $stmt->execute([
                'preco'   => $preco,
                'estoque' => $estoque,
                'ativo'   => $ativo,
                'id'      => $prodId,
            ]);
            $mensagem = 'Produto atualizado com sucesso!';
        }
    }
}

if (isset($_GET['excluir'])) {
    $excluirId = (int) $_GET['excluir'];
    if ($excluirId > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = :id");
            $stmt->execute(['id' => $excluirId]);
            $mensagem = 'Produto excluído com sucesso!';
        } catch (Throwable $e) {
            $stmtDesativar = $pdo->prepare("UPDATE produtos SET ativo = 0 WHERE id = :id");
            $stmtDesativar->execute(['id' => $excluirId]);
            $mensagem = 'Produto já possui pedidos associados, por isso foi desativado em vez de excluído.';
        }
    }
}

$produtos = $pdo->query("
    SELECT p.id, p.nome, p.descricao, p.preco, p.estoque, p.imagem, p.ativo, c.nome AS categoria_nome
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    ORDER BY p.id DESC
")->fetchAll();

$pedidos = $pdo->query("
    SELECT p.id, p.metodo_pagamento, p.status_pagamento, p.total, p.criado_em, u.nome AS cliente_nome, u.email AS cliente_email
    FROM pedidos p
    INNER JOIN utilizadores u ON u.id = p.utilizador_id
    ORDER BY p.id DESC
")->fetchAll();

$itensPorPedido = [];
if (!empty($pedidos)) {
    $stmtItens = $pdo->query("
        SELECT pi.pedido_id, pi.quantidade, pi.preco_unitario, pr.nome AS produto_nome
        FROM pedido_itens pi
        INNER JOIN produtos pr ON pr.id = pi.produto_id
        ORDER BY pi.id ASC
    ");
    foreach ($stmtItens->fetchAll() as $rowItem) {
        $itensPorPedido[(int) $rowItem['pedido_id']][] = $rowItem;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loja SENAC — Painel Administrativo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f6fa; color: #222; }
        header { background: #1e272e; color: #fff; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; margin-left: 16px; font-size: 14px; }
        .container { max-width: 1120px; margin: 28px auto; padding: 0 20px; }
        .card { background: #fff; padding: 22px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 24px; }
        .msg { background: #dff9fb; border-left: 4px solid #00b894; padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        .erro { background: #ffeaa7; color: #2d3436; padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        .grid-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; }
        label { display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; }
        input[type="text"], input[type="number"], select { width: 100%; padding: 8px; border: 1px solid #dcdde1; border-radius: 6px; box-sizing: border-box; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eceff1; font-size: 14px; }
        th { background: #f1f2f6; }
        .btn { display: inline-block; background: #00b894; color: #fff; border: none; padding: 8px 14px; border-radius: 6px; font-weight: bold; text-decoration: none; cursor: pointer; font-size: 13px; }
        .btn-azul { background: #0984e3; }
        .btn-perigo { background: #d63031; }
        .pedido-box { border: 1px solid #eceff1; border-radius: 6px; padding: 14px; margin-bottom: 12px; background: #fafafa; }
    </style>
</head>
<body>
    <header>
        <strong>Loja SENAC — Admin</strong>
        <nav>
            <a href="../index.php">Ver Catálogo</a>
            <a href="../carrinho.php">Carrinho</a>
            <span>Admin: <?= htmlspecialchars($utilizador['nome']) ?></span>
            <a href="../logout.php">Sair</a>
        </nav>
    </header>

    <main class="container">
        <h1>Painel Administrativo</h1>

        <?php if ($mensagem !== ''): ?>
            <div class="msg"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>
        <?php if ($erro !== ''): ?>
            <div class="erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <section class="card">
            <h2>Cadastrar novo produto</h2>
            <form method="POST" action="index.php">
                <input type="hidden" name="acao_post" value="criar_produto">
                <div class="grid-form">
                    <div>
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" required>
                    </div>
                    <div>
                        <label for="categoria_id">Categoria</label>
                        <select id="categoria_id" name="categoria_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="preco">Preço (R$)</label>
                        <input type="text" id="preco" name="preco" placeholder="99.90" required>
                    </div>
                    <div>
                        <label for="estoque">Estoque</label>
                        <input type="number" id="estoque" name="estoque" min="0" value="10" required>
                    </div>
                    <div>
                        <label for="imagem">Imagem (URL ou caminho)</label>
                        <input type="text" id="imagem" name="imagem" placeholder="https://...">
                    </div>
                    <div>
                        <label for="descricao">Descrição</label>
                        <input type="text" id="descricao" name="descricao" placeholder="Descrição breve">
                    </div>
                </div>
                <div style="margin-top: 14px;">
                    <button class="btn" type="submit">Cadastrar produto</button>
                </div>
            </form>
        </section>

        <section class="card">
            <h2>Gerir produtos</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Preço (R$)</th>
                        <th>Estoque</th>
                        <th>Ativo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $prod): ?>
                        <tr>
                            <td><?= (int) $prod['id'] ?></td>
                            <td><?= htmlspecialchars($prod['nome']) ?></td>
                            <td><?= htmlspecialchars($prod['categoria_nome'] ?? 'Sem categoria') ?></td>
                            <td colspan="4">
                                <form method="POST" action="index.php" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                    <input type="hidden" name="acao_post" value="atualizar_produto">
                                    <input type="hidden" name="produto_id" value="<?= (int) $prod['id'] ?>">
                                    <input type="text" name="preco" value="<?= number_format((float) $prod['preco'], 2, '.', '') ?>" style="width: 90px;" required>
                                    <input type="number" name="estoque" min="0" value="<?= (int) $prod['estoque'] ?>" style="width: 80px;" required>
                                    <label style="margin: 0; font-weight: normal;">
                                        <input type="checkbox" name="ativo" value="1" <?= (int) $prod['ativo'] === 1 ? 'checked' : '' ?>> Ativo
                                    </label>
                                    <button class="btn btn-azul" type="submit">Salvar</button>
                                    <a class="btn btn-perigo" href="index.php?excluir=<?= (int) $prod['id'] ?>" onclick="return confirm('Excluir este produto?');">Excluir</a>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="card">
            <h2>Pedidos realizados</h2>
            <?php if (empty($pedidos)): ?>
                <p>Ainda não existem pedidos registados.</p>
            <?php else: ?>
                <?php foreach ($pedidos as $ped): ?>
                    <div class="pedido-box">
                        <strong>Pedido #<?= (int) $ped['id'] ?></strong> —
                        Cliente: <?= htmlspecialchars($ped['cliente_nome']) ?> (<?= htmlspecialchars($ped['cliente_email']) ?>) —
                        Pagamento: <?= htmlspecialchars($ped['metodo_pagamento']) ?> (<?= htmlspecialchars($ped['status_pagamento']) ?>) —
                        <strong>Total: R$ <?= number_format((float) $ped['total'], 2, ',', '.') ?></strong>
                        <ul style="margin: 8px 0 0 0;">
                            <?php foreach ($itensPorPedido[(int) $ped['id']] ?? [] as $it): ?>
                                <li>
                                    <?= htmlspecialchars($it['produto_nome']) ?> — <?= (int) $it['quantidade'] ?>x
                                    (R$ <?= number_format((float) $it['preco_unitario'], 2, ',', '.') ?> cada)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>