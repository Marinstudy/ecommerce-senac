<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (!isset($_SESSION['carrinho']) || !is_array($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

$acao = $_GET['acao'] ?? '';
$id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($acao === 'adicionar' && $id > 0) {
    $stmt = $pdo->prepare("SELECT id, nome, preco, estoque, imagem FROM produtos WHERE id = :id AND ativo = 1");
    $stmt->execute(['id' => $id]);
    $produto = $stmt->fetch();

    if ($produto && (int) $produto['estoque'] > 0) {
        $atual = $_SESSION['carrinho'][$id] ?? 0;
        if ($atual < (int) $produto['estoque']) {
            $_SESSION['carrinho'][$id] = $atual + 1;
        }
    }
    header('Location: carrinho.php');
    exit;
}

if ($acao === 'diminuir' && $id > 0) {
    if (isset($_SESSION['carrinho'][$id])) {
        if ($_SESSION['carrinho'][$id] > 1) {
            $_SESSION['carrinho'][$id]--;
        } else {
            unset($_SESSION['carrinho'][$id]);
        }
    }
    header('Location: carrinho.php');
    exit;
}

if ($acao === 'remover' && $id > 0) {
    unset($_SESSION['carrinho'][$id]);
    header('Location: carrinho.php');
    exit;
}

if ($acao === 'limpar') {
    $_SESSION['carrinho'] = [];
    header('Location: carrinho.php');
    exit;
}

$itensCarrinho = [];
$total = 0.0;

if (!empty($_SESSION['carrinho'])) {
    $ids = array_map('intval', array_keys($_SESSION['carrinho']));
    $ids = array_filter($ids, function ($v) { return $v > 0; });

    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id, nome, preco, estoque, imagem FROM produtos WHERE id IN ($placeholders) AND ativo = 1");
        $stmt->execute(array_values($ids));
        $produtosDb = $stmt->fetchAll();

        foreach ($produtosDb as $prod) {
            $pid = (int) $prod['id'];
            $qtd = (int) ($_SESSION['carrinho'][$pid] ?? 0);
            if ($qtd > 0) {
                $subtotal = $qtd * (float) $prod['preco'];
                $total += $subtotal;
                $itensCarrinho[] = [
                    'id'       => $pid,
                    'nome'     => $prod['nome'],
                    'preco'    => (float) $prod['preco'],
                    'estoque'  => (int) $prod['estoque'],
                    'imagem'   => $prod['imagem'],
                    'qtd'      => $qtd,
                    'subtotal' => $subtotal,
                ];
            }
        }
    }
}

$utilizador = $_SESSION['utilizador'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loja SENAC — Carrinho</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f6fa; color: #222; }
        header { background: #1e272e; color: #fff; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; margin-left: 16px; font-size: 14px; }
        .container { max-width: 960px; margin: 28px auto; padding: 0 20px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        th, td { padding: 14px; text-align: left; border-bottom: 1px solid #eceff1; }
        th { background: #f1f2f6; }
        .total-box { margin-top: 20px; background: #fff; padding: 20px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .btn { display: inline-block; background: #00b894; color: #fff; padding: 10px 16px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        .btn-cinza { background: #636e72; }
        .btn-perigo { background: #d63031; padding: 6px 10px; font-size: 13px; }
        .btn-qtd { display: inline-block; background: #dfe6e9; color: #2d3436; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
    <header>
        <strong>Loja SENAC</strong>
        <nav>
            <a href="index.php">Catálogo</a>
            <a href="carrinho.php">Carrinho</a>
            <?php if ($utilizador): ?>
                <span>Olá, <?= htmlspecialchars($utilizador['nome']) ?></span>
                <?php if (($utilizador['perfil'] ?? '') === 'admin'): ?>
                    <a href="admin/index.php">Painel Admin</a>
                <?php endif; ?>
                <a href="logout.php">Sair</a>
            <?php else: ?>
                <a href="login.php">Entrar</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="container">
        <h1>O teu carrinho</h1>

        <?php if (empty($itensCarrinho)): ?>
            <p>O carrinho está vazio.</p>
            <a class="btn" href="index.php">Voltar ao catálogo</a>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Preço unitário</th>
                        <th>Quantidade</th>
                        <th>Subtotal</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itensCarrinho as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['nome']) ?></td>
                            <td>R$ <?= number_format($item['preco'], 2, ',', '.') ?></td>
                            <td>
                                <a class="btn-qtd" href="carrinho.php?acao=diminuir&id=<?= (int) $item['id'] ?>">-</a>
                                <strong style="margin: 0 8px;"><?= (int) $item['qtd'] ?></strong>
                                <?php if ($item['qtd'] < $item['estoque']): ?>
                                    <a class="btn-qtd" href="carrinho.php?acao=adicionar&id=<?= (int) $item['id'] ?>">+</a>
                                <?php endif; ?>
                            </td>
                            <td>R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></td>
                            <td><a class="btn btn-perigo" href="carrinho.php?acao=remover&id=<?= (int) $item['id'] ?>">Remover</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="total-box">
                <div>
                    <a class="btn btn-cinza" href="index.php">Continuar a comprar</a>
                    <a class="btn btn-perigo" href="carrinho.php?acao=limpar">Limpar carrinho</a>
                </div>
                <div>
                    <strong>Total: R$ <?= number_format($total, 2, ',', '.') ?></strong>
                    <a class="btn" style="margin-left: 14px;" href="checkout.php">Finalizar compra</a>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>