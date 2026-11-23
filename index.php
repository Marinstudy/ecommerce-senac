<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$busca = trim($_GET['busca'] ?? '');
$categoriaId = isset($_GET['categoria']) ? (int) $_GET['categoria'] : 0;
$ordem = $_GET['ordem'] ?? 'recentes';

$categorias = $pdo->query("SELECT id, nome FROM categorias ORDER BY nome ASC")->fetchAll();

$sql = "
    SELECT p.id, p.nome, p.descricao, p.preco, p.estoque, p.imagem, c.nome AS categoria_nome
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    WHERE p.ativo = 1
";
$params = [];

if ($busca !== '') {
    $sql .= " AND (p.nome LIKE :busca OR p.descricao LIKE :busca)";
    $params['busca'] = '%' . $busca . '%';
}

if ($categoriaId > 0) {
    $sql .= " AND p.categoria_id = :categoria_id";
    $params['categoria_id'] = $categoriaId;
}

switch ($ordem) {
    case 'menor_preco':
        $sql .= " ORDER BY p.preco ASC";
        break;
    case 'maior_preco':
        $sql .= " ORDER BY p.preco DESC";
        break;
    case 'nome_asc':
        $sql .= " ORDER BY p.nome ASC";
        break;
    default:
        $sql .= " ORDER BY p.id DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll();

$utilizador = $_SESSION['utilizador'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loja SENAC — Catálogo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f6fa; color: #222; }
        header { background: #1e272e; color: #fff; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; margin-left: 16px; font-size: 14px; }
        .container { max-width: 1100px; margin: 28px auto; padding: 0 20px; }
        .filtros { background: #fff; padding: 16px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
        .filtros input, .filtros select, .filtros button { padding: 10px 12px; border: 1px solid #dcdde1; border-radius: 6px; font-size: 14px; }
        .filtros input { flex: 1; min-width: 200px; }
        .filtros button { background: #0984e3; color: #fff; border: none; cursor: pointer; font-weight: bold; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px; }
        .card { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; flex-direction: column; }
        .card-img-link { display: block; text-decoration: none; }
        .card-img { height: 170px; background: #ecf0f1; display: flex; align-items: center; justify-content: center; color: #636e72; font-weight: bold; overflow: hidden; }
        .card-img img { width: 100%; height: 100%; object-fit: cover; }
        .card-body { padding: 16px; display: flex; flex-direction: column; flex: 1; }
        .categoria { font-size: 12px; color: #0984e3; font-weight: bold; text-transform: uppercase; }
        .card h3 { margin: 6px 0 8px 0; font-size: 17px; }
        .card h3 a { color: #1e272e; text-decoration: none; }
        .card h3 a:hover { color: #0984e3; text-decoration: underline; }
        .desc { font-size: 13px; color: #555; line-height: 1.4; flex: 1; margin-bottom: 12px; }
        .preco { font-size: 19px; font-weight: bold; color: #1e272e; margin-bottom: 4px; }
        .estoque { font-size: 12px; color: #636e72; margin-bottom: 14px; }
        .acoes { display: flex; gap: 8px; }
        .btn { flex: 1; text-align: center; padding: 9px 10px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: bold; color: #fff; }
        .btn-detalhes { background: #636e72; }
        .btn-carrinho { background: #00b894; }
        .btn-esgotado { background: #b2bec3; cursor: not-allowed; }
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
        <h1>Catálogo de Produtos</h1>

        <form class="filtros" method="GET" action="index.php">
            <input type="text" name="busca" placeholder="Pesquisar produto..." value="<?= htmlspecialchars($busca) ?>">

            <select name="categoria">
                <option value="0">Todas as categorias</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>" <?= $categoriaId === (int) $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="ordem">
                <option value="recentes" <?= $ordem === 'recentes' ? 'selected' : '' ?>>Mais recentes</option>
                <option value="menor_preco" <?= $ordem === 'menor_preco' ? 'selected' : '' ?>>Menor preço</option>
                <option value="maior_preco" <?= $ordem === 'maior_preco' ? 'selected' : '' ?>>Maior preço</option>
                <option value="nome_asc" <?= $ordem === 'nome_asc' ? 'selected' : '' ?>>Nome (A-Z)</option>
            </select>

            <button type="submit">Filtrar</button>
        </form>

        <?php if (empty($produtos)): ?>
            <p>Nenhum produto encontrado com os filtros selecionados.</p>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($produtos as $produto): ?>
                    <?php
                        $idProd = (int) $produto['id'];
                        $estoque = (int) $produto['estoque'];
                    ?>
                    <div class="card">
                        <a class="card-img-link" href="produto.php?id=<?= $idProd ?>">
                            <div class="card-img">
                                <?php if (!empty($produto['imagem'])): ?>
                                    <img src="<?= htmlspecialchars($produto['imagem']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>">
                                <?php else: ?>
                                    <span><?= htmlspecialchars($produto['nome']) ?></span>
                                <?php endif; ?>
                            </div>
                        </a>

                        <div class="card-body">
                            <div class="categoria"><?= htmlspecialchars($produto['categoria_nome'] ?? 'Geral') ?></div>
                            <h3>
                                <a href="produto.php?id=<?= $idProd ?>">
                                    <?= htmlspecialchars($produto['nome']) ?>
                                </a>
                            </h3>
                            <p class="desc"><?= htmlspecialchars($produto['descricao'] ?: 'Sem descrição.') ?></p>
                            <div class="preco">R$ <?= number_format((float) $produto['preco'], 2, ',', '.') ?></div>
                            <div class="estoque">
                                <?= $estoque > 0 ? "Estoque: {$estoque} un." : "Esgotado" ?>
                            </div>

                            <div class="acoes">
                                <a class="btn btn-detalhes" href="produto.php?id=<?= $idProd ?>">Ver detalhes</a>
                                <?php if ($estoque > 0): ?>
                                    <a class="btn btn-carrinho" href="carrinho.php?acao=adicionar&id=<?= $idProd ?>">+ Carrinho</a>
                                <?php else: ?>
                                    <span class="btn btn-esgotado">Esgotado</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>