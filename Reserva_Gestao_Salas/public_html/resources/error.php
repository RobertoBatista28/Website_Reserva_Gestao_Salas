<?php
$error_code = $_GET['code'] ?? 500;
$error_message = $_GET['message'] ?? 'Algo deu errado. Por favor, tente novamente mais tarde.';
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro <?php echo htmlspecialchars($error_code); ?></title>
    <link rel="stylesheet" href="./error.css">
</head>

<body>
    <?php include './header/header.php'; ?> <!-- Incluindo o header aqui -->
    <div class="error-container">
        <div class="error-code">Erro <?php echo htmlspecialchars($error_code); ?></div>
        <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <div class="error-actions">
            <a href="/">Voltar ao Início</a>
        </div>
    </div>
</body>

</html>