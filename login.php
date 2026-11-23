<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['utilizador'])) {
    header('Location: index.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $erro = 'Preenche o e-mail e a palavra-passe.';
    } else {
        $stmt = $pdo->prepare("SELECT id, nome, email, senha_hash, perfil FROM utilizadores WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $utilizador = $stmt->fetch();

        // Se for uma das contas de teste e a senha digitada for 123456, garante um hash válido gerado pelo teu próprio PHP
        if ($utilizador && in_array($utilizador['email'], ['cliente@loja.com', 'admin@loja.com'], true) && $senha === '123456' && !password_verify('123456', $utilizador['senha_hash'])) {
            $novoHash = password_hash('123456', PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE utilizadores SET senha_hash = :hash WHERE id = :id");
            $upd->execute(['hash' => $novoHash, 'id' => $utilizador['id']]);
            $utilizador['senha_hash'] = $novoHash;
        }

        if ($utilizador && password_verify($senha, $utilizador['senha_hash'])) {
            $_SESSION['utilizador'] = [
                'id'     => (int) $utilizador['id'],
                'nome'   => $utilizador['nome'],
                'email'  => $utilizador['email'],
                'perfil' => $utilizador['perfil'],
            ];
            header('Location: index.php');
            exit;
        } else {
            $erro = 'E-mail ou palavra-passe incorretos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loja SENAC — Entrar</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f6fa; color: #222; }
        header { background: #1e272e; color: #fff; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; margin-left: 16px; font-size: 14px; }
        .box { max-width: 400px; margin: 48px auto; background: #fff; padding: 28px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        label { display: block; margin-top: 14px; font-weight: bold; font-size: 14px; }
        input { width: 100%; padding: 10px; margin-top: 6px; border: 1px solid #dcdde1; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; margin-top: 20px; background: #0984e3; color: #fff; border: none; padding: 12px; border-radius: 6px; font-size: 15px; font-weight: bold; cursor: pointer; }
        .erro { background: #ffeaa7; color: #2d3436; padding: 10px; border-radius: 6px; margin-bottom: 14px; font-size: 14px; }
        .dica { margin-top: 18px; font-size: 12px; color: #636e72; background: #f1f2f6; padding: 10px; border-radius: 6px; }
    </style>
</head>
<body>
    <header>
        <strong>Loja SENAC</strong>
        <nav>
            <a href="index.php">Catálogo</a>
            <a href="carrinho.php">Carrinho</a>
            <a href="login.php">Entrar</a>
        </nav>
    </header>

    <main class="box">
        <h1>Entrar</h1>

        <?php if ($erro !== ''): ?>
            <div class="erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" required placeholder="cliente@loja.com">

            <label for="senha">Palavra-passe</label>
            <input type="password" id="senha" name="senha" required placeholder="123456">

            <button type="submit">Entrar</button>
        </form>

        <div class="dica">
            <strong>Contas de teste:</strong><br>
            Cliente: <code>cliente@loja.com</code> / Senha: <code>123456</code><br>
            Admin: <code>admin@loja.com</code> / Senha: <code>123456</code>
        </div>
    </main>
</body>
</html>