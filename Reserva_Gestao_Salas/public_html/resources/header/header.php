<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isUser($role) {
    return isset($_SESSION['cargo']) && $_SESSION['cargo'] == $role;
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de salas - ESTG</title>
    <link rel="stylesheet" href="/header/header.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo">
                <img class="PPorto" src="/media/logoPPorto.png">
                <a href="/index.php">
                    <?php echo isUser('Administrador') ? 'Gestão de salas ESTG' : 'Salas ESTG'; ?>
                </a>
            </div>

            <div class="nav-links">
                <?php if (isUser('Administrador')): ?>
                    <div class="dropdown">
                        <button class="dropdown-btn">Área de administração</button>
                        <div class="dropdown-content">
                            <a href="/areaAdmin/areaAdmin.php">Utilizadores</a>
                            <a href="/areaAdmin/reserva.php">Reservas</a>
                        </div>
                    </div>
                    <a href="/perfil/perfil.php">Perfil</a>
                    <a href="/logout.php">Logout</a>
                <?php elseif (isUser('Professor')): ?>
                    <a href="/reservarSala/minhaReserva.php">Minhas Reservas</a>
                    <a href="/perfil/perfil.php">Perfil</a>
                    <a href="/logout.php">Logout</a>
                <?php else: ?>
                    <a href="/login/login.php">Login</a>
                    <a href="/registar/registar.php">Registar</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</body>
</html>
