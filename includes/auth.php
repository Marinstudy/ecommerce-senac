<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function exigirLogin(): void {
    if (empty($_SESSION['utilizador'])) {
        header('Location: login.php');
        exit;
    }
}

function exigirAdmin(): void {
    exigirLogin();
    if (($_SESSION['utilizador']['perfil'] ?? '') !== 'admin') {
        http_response_code(403);
        die("Acesso restrito a administradores.");
    }
}