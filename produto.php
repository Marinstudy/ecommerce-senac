<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$produto = null;

if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT p.id, p.nome, p.descricao, p.preco, p.estoque, p.imagem, p.ativo, c.nome AS categoria_nome
        FROM produtos p
        LEFT JOIN categorias c ON c.id = p.categoria_id
        WHERE p.id = :id AND p.ativo = 1
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    $produto = $stmt->fetch();
}

$utilizador = $_SESSION['utilizador'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loja SENAC — <?= $produto ? htmlspecialchars($produto['nome']) : 'Produto não encontrado' ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f6fa; color: #222; }
        header { background: #1e272e; color: #fff; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; margin-left: 16px; font-size: 14px; }
        .container { max-width: 900px; margin: 28px auto; padding: 0 20px; }
        .card { background: #fff; padding: 26px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; gap: 28px; flex-wrap: wrap; align-items: flex-start; }
        .img-box { flex: 1 1 280px; background: #ecf0f1; border-radius: 8px; min-height: 240px; display: flex; align-items: center; justify-content: center; overflow: hidden; color: #636e72; font-weight: bold; }
        .img-box img { max-width: 100%; max-height: 320px; object-fit: cover; display: block; }
        .info-box { flex: 1.4 1 320px; }
        .categoria { font-size: 13px; color: #0984e3; font-weight: bold; text-transform: uppercase; }
        .preco { font-size: 24px; font-weight: bold; margin: 14px 0; color: #1e272e; }
        .estoque-ok { color: #00b894; font-weight: bold; margin-bottom: 18px; }
        .estoque-out { color: #d63031; font-weight: bold; margin-bottom: 18px; }
        .btn { display: inline-block; background: #00b894; color: #fff; padding: 11px 18px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        .btn-cinza { background: #636e72; margin-left: 10px; }
        .btn-desativado { background: #b2bec3; cursor: not-allowed; }
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
        <?php if (!$produto): ?>
            <div class="card" style="display: block;">
                <h1>Produto não encontrado</h1>
                <p>O produto solicitado não existe ou está indisponível no momento.</p>
                <a class="btn" href="index.php">Voltar ao catálogo</a>
            </div>
        <?php else: ?>
            <?php $estoque = (int) $produto['estoque']; ?>
            <div class="card">
                <div class="img-box">
                    <?php if (!empty($produto['imagem'])): ?>
                        <img src="<?= htmlspecialchars($produto['imagem']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>">
                    <?php else: ?>
                        <span><?= htmlspecialchars($produto['nome']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="info-box">
                    <div class="categoria"><?= htmlspecialchars($produto['categoria_nome'] ?? 'Geral') ?></div>
                    <h1 style="margin: 6px 0 10px 0;">#<?= (int) $produto['id'] ?> — <?= htmlspecialchars($produto['nome']) ?></h1>
                    <p style="line-height: 1.5; color: #444;">
                        <?= nl2br(htmlspecialchars($produto['descricao'] ?: 'Sem descrição detalhada disponível.')) ?>
                    </p>
                    <div class="preco">R$ <?= number_format((float) $produto['preco'], 2, ',', '.') ?></div>

                    <?php if ($estoque > 0): ?>
                        <div class="estoque-ok">Disponível em estoque (<?= $estoque ?> unidade<?= $estoque > 1 ? 's' : '' ?>)</div>
                        <a class="btn" href="carrinho.php?acao=adicionar&id=<?= (int) $produto['id'] ?>">Adicionar ao carrinho</a>
                    <?php else: ?>
                        <div class="estoque-out">Esgotado no momento</div>
                        <span class="btn btn-desativado">Indisponível</span>
                    <?php endif; ?>

                    <a class="btn btn-cinza" href="index.php">Voltar ao catálogo</a>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>