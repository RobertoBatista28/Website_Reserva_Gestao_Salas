<?php
session_start();
require_once '../conexao.php';
require_once '../logs.php';


$conn = getDatabaseConnection();

$id = $_GET['id'];

$sql = "SELECT * FROM utilizador WHERE idUtilizador = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$utilizador = $result->fetch_assoc();

$sqlAdmin = "SELECT username FROM utilizador WHERE idUtilizador = ?";
$stmtAdmin = $conn->prepare($sqlAdmin);
$stmtAdmin->bind_param("i", $_SESSION['idUtilizador']);
$stmtAdmin->execute();
$usernameAdmin = $stmtAdmin->get_result()->fetch_assoc()['username'];

if (!isset($_SESSION['idUtilizador'])) {
    header("Location: ../login/login.php");
    exit();
} else if ($_SESSION['cargo'] !== "Administrador") {
    writeAdminLog("Utilizador '$usernameAdmin' tentou aceder à página editarUtilizador.php para o utilizador com o ID '$id'.");
    header ("Location: ../error.php?code=403&message=Você não tem permissão para acessar esta área.");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = ($_POST['nome']);
    $username = trim(filter_var(strtolower($_POST['username']), FILTER_SANITIZE_STRING));
    $email = trim(filter_var(strtolower($_POST['email']), FILTER_SANITIZE_EMAIL));
    $contacto = trim(filter_var($_POST['contacto'], FILTER_SANITIZE_STRING));
    $cargo = $_POST['cargo'];

    // Verificar se o email já existe para outro utilizador
    $checkSql = "SELECT idUtilizador FROM utilizador WHERE email = ? AND idUtilizador != ?";
    $stmtCheck = $conn->prepare($checkSql);
    $stmtCheck->bind_param("si", $email, $id);
    $stmtCheck->execute();
    $checkResult = $stmtCheck->get_result();

    // Verificação de username
    $checkUsernameSql = "SELECT idUtilizador FROM utilizador WHERE username = ? AND idUtilizador != ?";
    $stmtCheckUsername = $conn->prepare($checkUsernameSql);
    $stmtCheckUsername->bind_param("si", $username, $id);
    $stmtCheckUsername->execute();
    $checkUsernameResult = $stmtCheckUsername->get_result();
    $imagemPerfil = $utilizador['imagemPerfil'];

    if ($checkResult->num_rows > 0) {
        $erro = "Email já está a ser usado por outra conta!";
    } elseif ($checkUsernameResult->num_rows > 0) {
        $erro = "Username já está a ser usado!";
    } elseif (!preg_match('/^[a-zA-Z0-9._-]{4,}$/', $username)) {
        $erro = "Username deve conter pelo menos 4 caracteres e pode incluir letras, números, pontos, underscores e hífens.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Formato de email inválido!";
    } elseif (!preg_match('/^(255|91|92|93|96)[0-9]{7}$/', $contacto)) {
        $erro = "O número deve começar por 255, 91, 92, 93 ou 96 e ter 9 dígitos.";
    } elseif (($_FILES['imagemPerfil']['size'] > 5000000)) {
        $erro = "O tamanho da imagem não pode ser maior que 5MB!";
    } else {
        if (isset($_POST['removerImagem']) && $_POST['removerImagem'] == 1) {
            $imagemPerfil = null;
        } elseif (isset($_FILES['imagemPerfil']) && $_FILES['imagemPerfil']['error'] == 0) {
            $imagemTipo = $_FILES['imagemPerfil']['type'];
            if ($imagemTipo == 'image/png' || $imagemTipo == 'image/jpeg') {
                $imagemPerfil = file_get_contents($_FILES['imagemPerfil']['tmp_name']);
            } else {
                $erro = "Por favor, carregue uma imagem PNG ou JPEG.";
            }
        }

        $sql = "UPDATE utilizador SET nome = ?, username = ?, email = ?, contacto = ?, imagemPerfil = ?, cargo = ? WHERE idUtilizador = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $nome, $username, $email, $contacto, $imagemPerfil, $cargo, $id);

        if ($stmt->execute()) {
            writeAdminLog("O administrador '$usernameAdmin' editou o utilizador '$username'.");
            $_SESSION['mensagem_sucesso'] = "Informações atualizadas com sucesso!";
            header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $id);
            exit();
        } else {
            writeAdminLog("O administrador '$usernameAdmin' ocorreu um erro ao editar o utilizador '$username'. Erro: " . $stmt->error);
            $erro = "Erro ao editar utilizador.";
        }
    }
}

if (isset($_SESSION['mensagem_sucesso'])) {
    $mensagem = $_SESSION['mensagem_sucesso'];
    unset($_SESSION['mensagem_sucesso']);
}
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Utilizador</title>
    <link rel="stylesheet" href="editarUtilizador.css">
</head>

<body>
    <div class="container">
        <div class="register-box">
            <h1>Editar Utilizador</h1>

            
            <form method="POST" enctype="multipart/form-data">
                <div class="imagemPerfil">
                    <?php
                    if ($utilizador['imagemPerfil']) {
                        echo '<img id="fotoPerfil" src="data:image/jpeg;base64,' . base64_encode($utilizador['imagemPerfil']) . '" style="width: 180px; height: 180px; border-radius: 50%; object-fit: cover;">';
                    } else {
                        echo '<img id="fotoPerfil" src="../media/semFotoPerfil.png" alt="Foto de Perfil Padrão" style="width: 180px; height: 180px; border-radius: 50%; object-fit: cover;">';
                    }
                    ?>
                </div>
                <?php if ($utilizador['imagemPerfil']): ?>
                    <button type="button" id="eliminarFoto" style="background-color: #e74c3c; color: white; margin-bottom: 15px;">Eliminar Foto</button>
                <?php endif; ?>
                <input type="file" id="imagemPerfil" name="imagemPerfil" accept="image/png, image/jpeg">

                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required
                    value="<?php echo $utilizador['username']; ?>">

                <label for="nome">Nome completo:</label>
                <input type="text" id="nome" name="nome" required value="<?php echo $utilizador['nome']; ?>">

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required value="<?php echo $utilizador['email']; ?>">

                <label for="contacto">Contacto:</label>
                <input type="text" id="contacto" name="contacto" required
                    value="<?php echo $utilizador['contacto']; ?>">

                <label for="cargo">Cargo:</label>
                <select id="cargo" name="cargo">
                    <option value="Professor" <?php echo $utilizador['cargo'] === 'Professor' ? 'selected' : ''; ?>>
                        Professor</option>
                    <option value="Administrador" <?php echo $utilizador['cargo'] === 'Administrador' ? 'selected' : ''; ?>>Administrador</option>
                </select>

                <button type="submit" name="submit">Editar</button>

                <a class="voltar" href="areaAdmin.php">◄ Voltar</a>
            </form>
            <?php if (isset($mensagem)): ?>
                <p style="color:lightgreen; font-weight:bold; text-align:center; margin-top: 15px;"><?php echo $mensagem; ?></p>
            <?php endif; ?>
            <?php if (isset($erro)): ?>
                <p style="color:red; font-weight:bold; text-align:center; margin-top: 15px;"><?php echo $erro; ?></p>
            <?php endif; ?>
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