<?php
error_reporting(0);
session_start();
require_once '../conexao.php';
require_once '../logs.php';

$conn = getDatabaseConnection();

$sqlAdmin = "SELECT username FROM utilizador WHERE idUtilizador = ?";
$stmtAdmin = $conn->prepare($sqlAdmin);
$stmtAdmin->bind_param("i", $_SESSION['idUtilizador']);
$stmtAdmin->execute();
$username = $stmtAdmin->get_result()->fetch_assoc()['username'];

if (!isset($_SESSION['idUtilizador'])) {
    header("Location: ../login/login.php");
    exit();
} else if ($_SESSION['cargo'] !== "Administrador") {
    writeAdminLog("Utilizador '$username' tentou aceder à página de adicionar sala (adicionarSala.php).");
    header ("Location: ../error.php?code=403&message=Você não tem permissão para acessar esta área.");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $tipo = $_POST['tipo'];
    $descricao = $_POST['descricao'];
    $capacidade = isset($_POST['capacidade']) ? intval($_POST['capacidade']) : 10;
    $estado = $_POST['estado'];

    $sql = "SELECT * FROM sala WHERE nome = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nome);
    $stmt->execute();
    $result = $stmt->get_result();

    if (empty($nome) || empty($tipo) || empty($estado)) {
        $erro = "Preencha todos os campos obrigatórios.";
    } else if ($result->num_rows > 0) {
        $erro = "Já existe uma sala com esse nome.";
    } else if ($capacidade !== null && $capacidade <= 10) {
        $erro = "A Sala tem que ter pelo menos 10 lugares de capacidade.";
    } else {
        $sql = "INSERT INTO sala (nome, tipo, descricao, capacidade, estado) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssis", $nome, $tipo, $descricao, $capacidade, $estado);

        if ($stmt->execute()) {
            writeAdminLog("Administrador '$username' adicionou a sala '$nome' com sucesso!");
            $sucesso = "Sala adicionada com sucesso!";
        } else {
            writeAdminLog("Administrador '$username' tentou adicionar a sala '$nome', mas ocorreu um erro: " . $stmt->error);
            $erro = "Erro ao adicionar a sala. Por favor, tente novamente.";
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Sala</title>
    <link rel="stylesheet" href="adicionarSala.css">
</head>

<body>
    <div class="container">
        <div class="sala-box">
            <h1>Adicionar Sala</h1>
            <form method="POST" action="">
                <?php if (isset($sucesso)): ?>
                    <p style="color:lightgreen; font-weight:bold;"><?php echo $sucesso; ?></p>
                <?php endif; ?>
                <?php if (isset($erro)): ?>
                    <p style="color:red; font-weight:bold;"><?php echo $erro; ?></p>
                <?php endif; ?>
                <label for="nome">Nome:</label>
                <input type="text" id="nome" name="nome" required>
                <label for="tipo">Tipo:</label>
                <select id="tipo" name="tipo" required>
                    <?php
                    $tipos = ['Arte', 'Auditório', 'Biblioteca', 'Informática', 'Laboratório', 'Mecânica', 'Multimédia', 'Música', 'Outros', 'Pavilhão', 'Reunião', 'Teórica'];
                    foreach ($tipos as $tipo) {
                        echo "<option value='$tipo'>$tipo</option>";
                    }
                    ?>
                </select>
                <label for="descricao">Descrição:</label>
                <textarea id="descricao" name="descricao" rows="4"></textarea>
                <label for="capacidade">Capacidade:</label>
                <input type="number" id="capacidade" name="capacidade" min="10" required>
                <label for="estado">Estado:</label>
                <select id="estado" name="estado" required>
                    <option value="Disponível">Disponível</option>
                    <option value="Brevemente">Brevemente</option>
                </select>
                <button type="submit" name="submit">Adicionar Sala</button>
                <a class="voltar" href="../index.php ">◄ Voltar</a>
            </form>
        </div>
    </div>
</body>
</html>
