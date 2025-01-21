<?php
session_start();
require_once '../conexao.php';
require_once '../logs.php';
$conn = getDatabaseConnection();

if (!isset($_SESSION['idUtilizador'])) {
    header('Location: ../login/login.php');
    exit();
}

$idUtilizador = $_SESSION['idUtilizador'];

// Obter os dados do utilizador
$sql = "SELECT * FROM utilizador WHERE idUtilizador = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idUtilizador);
$stmt->execute();
$result = $stmt->get_result();
$utilizador = $result->fetch_assoc();

// Processar submissão do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = ($_POST['nome']);
    $username = trim(filter_var(strtolower($_POST['username']), FILTER_SANITIZE_STRING));
    $email = trim(filter_var(strtolower($_POST['email']), FILTER_SANITIZE_EMAIL));
    $contacto = trim(filter_var($_POST['contacto'], FILTER_SANITIZE_STRING));
    $confirmarPassword = $_POST['confirmarPassword'];

    // Verificar se o email já existe para outro utilizador
    $checkSql = "SELECT idUtilizador FROM utilizador WHERE email = ? AND idUtilizador != ?";
    $stmtCheck = $conn->prepare($checkSql);
    $stmtCheck->bind_param("si", $email, $idUtilizador);
    $stmtCheck->execute();
    $checkResult = $stmtCheck->get_result();

    // Verificação de username
    $checkUsernameSql = "SELECT idUtilizador FROM utilizador WHERE username = ? AND idUtilizador != ?";
    $stmtCheckUsername = $conn->prepare($checkUsernameSql);
    $stmtCheckUsername->bind_param("si", $username, $idUtilizador);
    $stmtCheckUsername->execute();
    $checkUsernameResult = $stmtCheckUsername->get_result();

    // Verificar a palavra-passe
    $salt = $utilizador['salt'];
    $hashedPassword = $utilizador['password'];
    $imagemPerfil = $utilizador['imagemPerfil'];

    if (empty($confirmarPassword)) {
        $erro = "Por favor, insira a sua palavra-passe!";
    } elseif (!password_verify($salt . $confirmarPassword, $hashedPassword)) {
        $erro = "Palavra-passe incorreta!";
    } elseif ($checkResult->num_rows > 0) {
        $erro = "Email já está a ser usado por outra conta!";
    } elseif ($checkUsernameResult->num_rows > 0) {
        $erro = "Username já está a ser usado!";
    } elseif (!preg_match('/^[a-zA-Z0-9._-]{4,}$/', $username)) {
        $erro = "Username deve conter pelo menos 4 caracteres e pode incluir letras, números, pontos, underscores e hífens.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Formato de email inválido!";
    } elseif (!preg_match('/^(255|91|92|93|96)[0-9]{7}$/', $contacto)) {
        $erro = "O número deve começar por 255, 91, 92, 93 ou 96 e ter 9 dígitos.";
    } else {
        if (isset($_POST['removerImagem']) && $_POST['removerImagem'] == 1) {
            // Se a remoção foi solicitada, defina a imagem como NULL
            $imagemPerfil = null;
        } elseif (isset($_FILES['imagemPerfil']) && $_FILES['imagemPerfil']['error'] == 0) {
            $imagemTipo = $_FILES['imagemPerfil']['type'];
            if ($imagemTipo == 'image/png' || $imagemTipo == 'image/jpeg') {
                $imagemPerfil = file_get_contents($_FILES['imagemPerfil']['tmp_name']);
            } else {
                $erro = "Por favor, carregue uma imagem PNG ou JPEG.";
            }
        }

        // Atualizar os dados do utilizador
        $updateSql = "UPDATE utilizador SET username = ?, nome = ?, email = ?, contacto = ?, imagemPerfil = ? WHERE idUtilizador = ?";
        $stmtUpdate = $conn->prepare($updateSql);
        $stmtUpdate->bind_param("sssssi", $username, $nome, $email, $contacto, $imagemPerfil, $idUtilizador);

        if ($stmtUpdate->execute()) {
            writeLoginLog("Utilizador '$username' atualizou o seu perfil.");
            $_SESSION['mensagem_sucesso'] = "Informações atualizadas com sucesso!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            writeLoginLog("O utilizador '$username' tentou atualizar o seu perfil, mas ocorreu um erro." . $stmtUpdate->error);
            $erro = "Erro ao atualizar informações!";
        }
    }
}

if (isset($_SESSION['mensagem_sucesso'])) {
    $mensagem = $_SESSION['mensagem_sucesso'];
    unset($_SESSION['mensagem_sucesso']);
}
?>


<!DOCTYPE html>
<html lang="pt-PT">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil</title>
    <link rel="stylesheet" href="perfil.css">
</head>

<body>
    <div class="container">
        <div class="perfil-box">
            <?php if (isset($mensagem)): ?>
                <p style="color:lightgreen; font-weight:bold;"><?php echo $mensagem; ?></p>
            <?php endif; ?>
            <?php if (isset($erro)): ?>
                <p style="color:red; font-weight:bold;"><?php echo $erro; ?></p>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <h1><?php echo htmlspecialchars($utilizador['nome']); ?></h1>
                <div class="imagemPerfil">
                    <img id="fotoPerfil"
                        src="<?php echo $utilizador['imagemPerfil']
                                    ? 'data:image/jpeg;base64,' . base64_encode($utilizador['imagemPerfil'])
                                    : '../media/semFotoPerfil.png'; ?>"
                        alt="Foto de Perfil"
                        style="width: 180px; height: 180px; border-radius: 50%; object-fit: cover;">
                </div>
                <?php if ($utilizador['imagemPerfil']): ?>
                    <button type="button" id="eliminarFoto" style="background-color: #e74c3c; color: white; margin-bottom: 15px;">Eliminar Foto</button>
                <?php endif; ?>
                <input type="file" id="imagemPerfil" name="imagemPerfil" accept="image/png, image/jpeg">

                <label for="nome">Nome:</label>
                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($utilizador['nome']); ?>" required>

                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($utilizador['username']); ?>" required>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($utilizador['email']); ?>" required>

                <label for="contacto">Contacto:</label>
                <input type="text" id="contacto" name="contacto" value="<?php echo htmlspecialchars($utilizador['contacto']); ?>" required>

                <label for="confirmarPassword">Validar palavra-passe:</label>
                <input type="password" id="confirmarPassword" name="confirmarPassword">

                <button type="submit">Guardar alterações</button>

                <button type="button" class="buttonSec" onclick="window.location.href='alterarPassword.php'">Alterar palavra-passe</button>

                <a class="voltar" href="../index.php">◄ Voltar</a>
            </form>
        </div>
    </div>

    <script>
        //eliminar foto do lado do cliente
        document.getElementById('eliminarFoto').addEventListener('click', function() {
            // Substituir a imagem atual pela padrão
            const fotoPerfil = document.getElementById('fotoPerfil');
            fotoPerfil.src = "../media/semFotoPerfil.png";

            // Limpar o campo de upload
            const uploadInput = document.getElementById('imagemPerfil');
            uploadInput.value = "";

            // Criar um campo oculto para informar ao servidor sobre a remoção
            let hiddenInput = document.getElementById('removerImagem');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = "hidden";
                hiddenInput.name = "removerImagem";
                hiddenInput.id = "removerImagem";
                hiddenInput.value = "1";
                document.querySelector('form').appendChild(hiddenInput);
            }
        });
    </script>

</body>

</html>