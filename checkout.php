<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (empty($_SESSION['utilizador'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['carrinho']) || !is_array($_SESSION['carrinho'])) {
    header('Location: carrinho.php');
    exit;
}

$utilizador = $_SESSION['utilizador'];
$erro = '';
$pedidoCriado = null;

$ids = array_map('intval', array_keys($_SESSION['carrinho']));
$ids = array_filter($ids, function ($id) { return $id > 0; });

$itensCheckout = [];
$total = 0.0;

if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, nome, preco, estoque FROM produtos WHERE id IN ($placeholders) AND ativo = 1");
    $stmt->execute(array_values($ids));
    $produtosDb = $stmt->fetchAll();

    foreach ($produtosDb as $prod) {
        $pid = (int) $prod['id'];
        $qtd = (int) ($_SESSION['carrinho'][$pid] ?? 0);
        if ($qtd > 0) {
            $subtotal = $qtd * (float) $prod['preco'];
            $total += $subtotal;
            $itensCheckout[] = [
                'id'      => $pid,
                'nome'    => $prod['nome'],
                'preco'   => (float) $prod['preco'],
                'estoque' => (int) $prod['estoque'],
                'qtd'     => $qtd,
            ];
        }
    }
}

if (empty($itensCheckout)) {
    header('Location: carrinho.php');
    exit;
}

$labelsPagamento = [
    'PIX'    => 'PIX',
    'Boleto' => 'Boleto',
    'Cartao' => 'Cartão',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pagamento = $_POST['pagamento'] ?? '';

    if (!array_key_exists($pagamento, $labelsPagamento)) {
        $erro = 'Seleciona uma forma de pagamento válida (PIX, Boleto ou Cartão).';
    } else {
        try {
            $pdo->beginTransaction();

            $stmtCheck = $pdo->prepare("SELECT id, nome, preco, estoque FROM produtos WHERE id = :id AND ativo = 1 FOR UPDATE");
            $itensConfirmados = [];
            $totalConfirmado = 0.0;

            foreach ($itensCheckout as $item) {
                $stmtCheck->execute(['id' => $item['id']]);
                $prodAtual = $stmtCheck->fetch();

                if (!$prodAtual) {
                    throw new Exception("O produto {$item['nome']} já não está disponível.");
                }
                if ((int) $prodAtual['estoque'] < $item['qtd']) {
                    throw new Exception("Estoque insuficiente para {$prodAtual['nome']} (disponível: {$prodAtual['estoque']}).");
                }

                $precoUnit = (float) $prodAtual['preco'];
                $totalConfirmado += $precoUnit * $item['qtd'];
                $itensConfirmados[] = [
                    'produto_id'     => (int) $prodAtual['id'],
                    'quantidade'     => (int) $item['qtd'],
                    'preco_unitario' => $precoUnit,
                ];
            }

            $stmtPedido = $pdo->prepare("
                INSERT INTO pedidos (utilizador_id, metodo_pagamento, status_pagamento, total)
                VALUES (:utilizador_id, :metodo_pagamento, 'Confirmado', :total)
            ");
            $stmtPedido->execute([
                'utilizador_id'    => (int) $utilizador['id'],
                'metodo_pagamento' => $pagamento,
                'total'            => $totalConfirmado,
            ]);

            $pedidoId = (int) $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("
                INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario)
                VALUES (:pedido_id, :produto_id, :quantidade, :preco_unitario)
            ");
            $stmtEstoque = $pdo->prepare("
                UPDATE produtos SET estoque = estoque - :qtd WHERE id = :id
            ");

            foreach ($itensConfirmados as $ic) {
                $stmtItem->execute([
                    'pedido_id'      => $pedidoId,
                    'produto_id'     => $ic['produto_id'],
                    'quantidade'     => $ic['quantidade'],
                    'preco_unitario' => $ic['preco_unitario'],
                ]);

                $stmtEstoque->execute([
                    'qtd' => $ic['quantidade'],
                    'id'  => $ic['produto_id'],
                ]);
            }

            $pdo->commit();
            $_SESSION['carrinho'] = [];

            $pedidoCriado = [
                'id'        => $pedidoId,
                'pagamento' => $labelsPagamento[$pagamento],
                'total'     => $totalConfirmado,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loja SENAC — Finalizar Compra</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f6fa; color: #222; }
        header { background: #1e272e; color: #fff; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; margin-left: 16px; font-size: 14px; }
        .container { max-width: 760px; margin: 28px auto; padding: 0 20px; }
        .card { background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .erro { background: #ffeaa7; color: #2d3436; padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        .sucesso { background: #dff9fb; border-left: 4px solid #00b894; padding: 18px; border-radius: 6px; }
        .radio-group label { display: block; margin: 10px 0; font-size: 15px; cursor: pointer; }
        .btn { display: inline-block; background: #00b894; color: #fff; border: none; padding: 12px 20px; border-radius: 6px; font-weight: bold; text-decoration: none; cursor: pointer; font-size: 15px; }
        .btn-cinza { background: #636e72; margin-left: 10px; }
    </style>
</head>
<body>
    <header>
        <strong>Loja SENAC</strong>
        <nav>
            <a href="index.php">Catálogo</a>
            <a href="carrinho.php">Carrinho</a>
            <span>Olá, <?= htmlspecialchars($utilizador['nome']) ?></span>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <main class="container">
        <h1>Finalizar Compra</h1>

        <?php if ($pedidoCriado): ?>
            <div class="card sucesso">
                <h2>Pedido #<?= (int) $pedidoCriado['id'] ?> confirmado!</h2>
                <p><strong>Forma de pagamento:</strong> <?= htmlspecialchars($pedidoCriado['pagamento']) ?></p>
                <p><strong>Total pago:</strong> R$ <?= number_format($pedidoCriado['total'], 2, ',', '.') ?></p>
                <p>O estoque foi atualizado e o carrinho foi limpo.</p>
                <a class="btn" href="index.php">Voltar ao catálogo</a>
            </div>
        <?php else: ?>
            <?php if ($erro !== ''): ?>
                <div class="erro"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>

            <div class="card">
                <h2>Resumo do pedido</h2>
                <ul>
                    <?php foreach ($itensCheckout as $item): ?>
                        <li>
                            <?= htmlspecialchars($item['nome']) ?> — <?= (int) $item['qtd'] ?>x
                            (R$ <?= number_format($item['preco'] * $item['qtd'], 2, ',', '.') ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p><strong>Total: R$ <?= number_format($total, 2, ',', '.') ?></strong></p>
            </div>

            <form class="card" method="POST" action="checkout.php">
                <h2>Forma de pagamento</h2>
                <div class="radio-group">
                    <label><input type="radio" name="pagamento" value="PIX" required> PIX</label>
                    <label><input type="radio" name="pagamento" value="Boleto" required> Boleto</label>
                    <label><input type="radio" name="pagamento" value="Cartao" required> Cartão</label>
                </div>
                <button class="btn" type="submit">Confirmar pedido</button>
                <a class="btn btn-cinza" href="carrinho.php">Voltar ao carrinho</a>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>