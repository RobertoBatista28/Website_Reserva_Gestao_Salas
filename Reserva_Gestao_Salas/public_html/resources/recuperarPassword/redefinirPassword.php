<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Password</title>
    <link rel="stylesheet" href="redefinirPassword.css">
</head>

<body>
    <div class="container">
        <div class="login-box">
            <h1>Redefinir Password</h1>
            <form method="POST" action="">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                <label for="new_password">Nova Password:</label>
                <input type="password" id="new_password" name="new_password" required>
                <label for="confirm_new_password">Confirmar Nova Password:</label>
                <input type="password" id="confirm_new_password" name="confirm_new_password" required>
                <button type="submit">Redefinir</button>
            </form>
        </div>
    </div>

    <?php
    require_once '../conexao.php';
    require_once '../logs.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['token'];
        $new_password = $_POST['new_password'];

        $conn = getDatabaseConnection();
        $sql = "SELECT * FROM utilizador WHERE expirePasswordToken > NOW() AND resetPasswordToken IS NOT NULL";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Verificar o token
            if (password_verify($token, $user['resetPasswordToken'])) {
                $username = $user['username'];

                $verificaPassword = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/";
                if (!preg_match($verificaPassword, $new_password)) {
                    echo "<p style='color:red;text-align:center;'>Palavra-passe deve ter 8 caracteres, maiúsculas, minúsculas, números e caracteres especiais.</p>";
                } elseif ($new_password !== $_POST['confirm_new_password']) {
                    echo "<p style='color:red;text-align:center;'>As Palavra-passes não coincidem.</p>";
                } else {
                    $salt = bin2hex(random_bytes(10));

                    $hashedPassword = password_hash($salt . $new_password, PASSWORD_BCRYPT);
                    $update_sql = "UPDATE utilizador SET password = ?, salt = ?, resetPasswordToken = NULL, expirePasswordToken = NULL WHERE email = ?";
                    $stmt_update = $conn->prepare($update_sql);
                    $stmt_update->bind_param("sss", $hashedPassword, $salt, $user['email']);
                    $stmt_update->execute();

                    writeLoginLog("Utilizador '$username' redefiniu a palavra-passe.");
                    echo "<p style='color:green;text-align:center;'>Palavra-passe redefinida com sucesso! Será redirecionado para a página de login.</p>";
                    echo "<script>setTimeout(function(){window.location.href = '../Login/login.php';}, 3000);</script>";
                }
            } else {
                // Nenhum token válido encontrado, utilizador não identificado
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                writeLoginLog("Token inválido com a base de dados. IP do utilizador: $ipAddress. Token utilizado: $token.");
                echo "<p style='color:red;text-align:center;'>Token inválido ou expirado.</p>";
            }
        } else {
            // Nenhum token válido encontrado, utilizador não identificado
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            writeLoginLog("Tentativa de redefinição de palavra-passe falhou devido a Token inválido ou expirado. IP do utilizador: $ipAddress. Token utilizado: $token.");
            echo "<p style='color:red;text-align:center;'>Token inválido ou expirado.</p>";
        }
    }
    ?>
</body>

</html>