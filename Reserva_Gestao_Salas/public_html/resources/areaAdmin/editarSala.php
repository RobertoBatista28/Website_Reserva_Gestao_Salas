<?php
session_start();
require_once '../conexao.php';
require_once '../logs.php';

$conn = getDatabaseConnection();
$idSala = intval($_GET['idSala']);

$sqlAdmin = "SELECT username FROM utilizador WHERE idUtilizador = ?";
$stmtAdmin = $conn->prepare($sqlAdmin);
$stmtAdmin->bind_param("i", $_SESSION['idUtilizador']);
$stmtAdmin->execute();
$username = $stmtAdmin->get_result()->fetch_assoc()['username'];


if (!isset($_SESSION['idUtilizador'])) {
    header("Location: ../login/login.php");
    exit();
} else if ($_SESSION['cargo'] !== "Administrador") {
    writeAdminLog("Utilizador '$username' tentou aceder à edição de sala (editarSala.php) com o ID '$idSala'.");
    header ("Location: ../error.php?code=403&message=Você não tem permissão para acessar esta área.");
    exit();
}



// Recuperação dos dados da sala
$sql = "SELECT * FROM sala WHERE idSala = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idSala);
$stmt->execute();
$result = $stmt->get_result();

if (!isset($_GET['idSala']) || empty($_GET['idSala']) || ($_GET['idSala'] === "")) {
    writeAdminLog("Administrador '$username' tentou aceder à página de editar sala sem especificar a sala (ID).");
    header("Location: ../error.php?code=404&message=ID da sala não especificado.");
    exit();
} else if ($result->num_rows === 0) {
    writeAdminLog("Administrador '$username' tentou aceder à página de editar sala, onde sala com ID '$idSala' não foi encontrada.");
    header("Location: ../error.php?code=404&message=Sala não encontrada.");
    exit();
}

$sala = $result->fetch_assoc();



// Atualização dos dados da sala
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $tipo = $_POST['tipo'];
    $descricao = $_POST['descricao'];
    $capacidade = isset($_POST['capacidade']) ? intval($_POST['capacidade']) : null;
    $estado = $_POST['estado'];
    
    $checkSql = "SELECT idSala FROM sala WHERE nome = ? AND idSala != ?";
    $stmtCheck = $conn->prepare($checkSql);
    $stmtCheck->bind_param("si", $nome, $idSala);
    $stmtCheck->execute();
    $checkResult = $stmtCheck->get_result();


    // Validação básica dos campos
    if (empty($nome) || empty($tipo) || empty($estado)) {
        $erro = "Preencha todos os campos obrigatórios.";
    } else if ($checkResult->num_rows > 0) {
        $erro = "Já existe uma sala com esse nome.";
    } else if ($capacidade !== null && $capacidade <= 10) {
        $erro = "A Sala tem que ter pelo menos 10 lugares de capacidade.";
    } else {
        $sqlUpdate = "UPDATE sala SET nome = ?, tipo = ?, descricao = ?, capacidade = ?, estado = ? WHERE idSala = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bind_param("sssisi", $nome, $tipo, $descricao, $capacidade, $estado, $idSala);

        if ($stmtUpdate->execute()) {
            writeAdminLog("Administrador '$username' atualizou a sala com o ID '$idSala'.");
            $sucesso = "Sala atualizada com sucesso!";
            // Recarregar os dados atualizados

            $sala['nome'] = $nome;
            $sala['tipo'] = $tipo;
            $sala['descricao'] = $descricao;
            $sala['capacidade'] = $capacidade;
            $sala['estado'] = $estado;
        } else {
            writeAdminLog("Administrador '$username' tentou atualizar a sala com o ID '$idSala', mas ocorreu um erro: " . $stmtUpdate->error);
            $erro = "Erro ao atualizar a sala. Por favor, tente novamente.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Sala</title>
    <link rel="stylesheet" href="editarSala.css">
</head>

<body>
    <h1>Editar Sala</h1>

    <?php if (isset($erro)): ?>
        <p style="color: red;"><?php echo $erro; ?></p>
    <?php endif; ?>

    <?php if (isset($sucesso)): ?>
        <p style="color: green;"><?php echo $sucesso; ?></p>
    <?php endif; ?>

    <form method="post">
        <label for="nome">Nome:</label>
        <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($sala['nome']); ?>" required><br>

        <label for="tipo">Tipo:</label>
        <select id="tipo" name="tipo" required>
            <?php
            $tipos = ['Arte', 'Auditório', 'Biblioteca', 'Informática', 'Laboratório', 'Mecânica', 'Multimédia', 'Música', 'Outros', 'Pavilhão', 'Reunião', 'Teórica'];
            foreach ($tipos as $tipo) {
                $selected = $tipo === $sala['tipo'] ? 'selected' : '';
                echo "<option value='$tipo' $selected>$tipo</option>";
            }
            ?>
        </select><br>

        <label for="descricao">Descrição:</label><br>
        <textarea id="descricao" name="descricao"><?php echo htmlspecialchars($sala['descricao']); ?></textarea><br>

        <label for="capacidade">Capacidade:</label>
        <input type="number" id="capacidade" name="capacidade" min="10" required
            value="<?php echo htmlspecialchars($sala['capacidade']); ?>"><br>

        <label for="estado">Estado:</label>
        <select id="estado" name="estado" required>
            <?php
            $estados = ['Disponível', 'Brevemente'];
            foreach ($estados as $estado) {
                $selected = $estado === $sala['estado'] ? 'selected' : '';
                echo "<option value='$estado' $selected>$estado</option>";
            }
            ?>
        </select><br>

        <button type="submit">Atualizar</button>
        <a class="voltar" href="../index.php ">◄ Voltar</a>
    </form>
</body>

</html>