<?php
require_once '../conexao.php';
include('../header/header.php');
include_once '../logs.php';

$conn = getDatabaseConnection();

$sqlAdmin = "SELECT username FROM utilizador WHERE idUtilizador = ?";
$stmtAdmin = $conn->prepare($sqlAdmin);
$stmtAdmin->bind_param("i", $_SESSION['idUtilizador']);
$stmtAdmin->execute();
$username = $stmtAdmin->get_result()->fetch_assoc()['username'];


// Eliminar utilizador
if (isset($_GET['eliminar'])) {
    $idUtilizador = (int) $_GET['eliminar'];
    $sql = "SELECT username FROM utilizador WHERE idUtilizador = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idUtilizador);
    $stmt->execute();
    $usernameUtilizador = $stmt->get_result()->fetch_assoc()['username'];

    $stmt = $conn->prepare("DELETE FROM utilizador WHERE idUtilizador = ?");
    $stmt->bind_param("i", $idUtilizador);
    if ($stmt->execute()) {
        writeAdminLog("Administrador '$username' eliminou o utilizador '$usernameUtilizador'.");
        $_SESSION['mensagem_sucesso'] = "Utilizador eliminado com sucesso!";
    } else {
        writeAdminLog("Administrador '$username' tentou eliminar o utilizador '$usernameUtilizador', mas ocorreu um erro: " . $stmt->error);
        echo "<script>alert('Erro ao eliminar o utilizador.');</script>";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (!isset($_SESSION['idUtilizador'])) {
    header("Location: ../login/login.php");
    exit();
} else if ($_SESSION['cargo'] !== "Administrador") {
    writeAdminLog("Utilizador '$username' tentou aceder à gestão de utilizadores (areaAdmin.php).");
    header ("Location: ../error.php?code=403&message=Você não tem permissão para acessar esta área.");
    exit();
}

// Filtros
$whereClauses = [];
$params = [];
$types = "";

if (!empty($_GET['id'])) {
    $whereClauses[] = "idUtilizador = ?";
    $params[] = (int) $_GET['id'];
    $types .= "i";
}
if (!empty($_GET['username'])) {
    $whereClauses[] = "username LIKE ?";
    $params[] = "%" . $_GET['username'] . "%";
    $types .= "s";
}
if (!empty($_GET['nome'])) {
    $whereClauses[] = "nome LIKE ?";
    $params[] = "%" . $_GET['nome'] . "%";
    $types .= "s";
}
if (!empty($_GET['email'])) {
    $whereClauses[] = "email LIKE ?";
    $params[] = "%" . $_GET['email'] . "%";
    $types .= "s";
}
if (!empty($_GET['contacto'])) {
    $whereClauses[] = "contacto LIKE ?";
    $params[] = "%" . $_GET['contacto'] . "%";
    $types .= "s";
}
if (!empty($_GET['cargo'])) {
    $whereClauses[] = "cargo LIKE ?";
    $params[] = "%" . $_GET['cargo'] . "%";
    $types .= "s";
}

$whereSQL = "";
if ($whereClauses) {
    $whereSQL = "WHERE " . implode(" AND ", $whereClauses);
}

$sql = "SELECT idUtilizador, username, nome, email, contacto, cargo FROM utilizador $whereSQL";
$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-PT">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área de Administração</title>
    <link rel="stylesheet" href="areaAdmin.css">
</head>

<body>
    <div class="container">
        <h1>Gestão de Utilizadores</h1>

        <div class="filters-container">
            <form method="GET" id="filtersForm">
                <div class="filters">
                    <p>Filtros de pesquisa</p>
                    <div class="filter-row">
                        <div class="filter-item">
                            <label for="id">ID</label>
                            <input type="text" name="id" value="<?php echo htmlspecialchars($_GET['id'] ?? ''); ?>">
                        </div>

                        <div class="filter-item">
                            <label for="username">Utilizador</label>
                            <input type="text" name="username"
                                value="<?php echo htmlspecialchars($_GET['username'] ?? ''); ?>">
                        </div>

                        <div class="filter-item">
                            <label for="nome">Nome</label>
                            <input type="text" name="nome" value="<?php echo htmlspecialchars($_GET['nome'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="filter-row">
                        <div class="filter-item">
                            <label for="email">Email</label>
                            <input type="text" name="email"
                                value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
                        </div>

                        <div class="filter-item">
                            <label for="contacto">Contacto</label>
                            <input type="text" name="contacto"
                                value="<?php echo htmlspecialchars($_GET['contacto'] ?? ''); ?>" maxlength="9">
                        </div>

                        <div class="filter-item">
                            <label for="cargo">Cargo</label>
                            <select name="cargo" id="cargo" onchange="this.form.submit()">
                                <option value="">Selecione um cargo</option>
                                <option value="Administrador" <?php echo isset($_GET['cargo']) && $_GET['cargo'] === 'Administrador' ? 'selected' : ''; ?>>Administrador</option>
                                <option value="Professor" <?php echo isset($_GET['cargo']) && $_GET['cargo'] === 'Professor' ? 'selected' : ''; ?>>Professor</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
            <div class="button-aplicar">
                            <button type="submit">Aplicar</button>
                        </div>
        </div>

        <table class="user-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Contacto</th>
                    <th>Cargo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['idUtilizador']; ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['nome']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['contacto']); ?></td>
                        <td><?php echo htmlspecialchars($row['cargo']); ?></td>
                        <td>
                            <a class="btn btn-secondary"
                                href="editarUtilizador.php?id=<?php echo $row['idUtilizador']; ?>">Editar</a>
                            <a class="btn" href="?eliminar=<?php echo $row['idUtilizador']; ?>"
                                onclick="return confirm('Tem a certeza que deseja eliminar?')">Eliminar</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
        // Função para aplicar os filtros quando a tecla 'Enter' for pressionada
        document.getElementById('filtersForm').addEventListener('keypress', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                applyFilters();
            }
        });

        function applyFilters() {
            const form = document.getElementById('filtersForm');
            const formData = new FormData(form);
            let url = window.location.pathname + "?";

            formData.forEach((value, key) => {
                if (value) {
                    url += `${key}=${value}&`;
                }
            });

            url = url.endsWith('&') ? url.slice(0, -1) : url;
            window.location.href = url;
        }
    </script>
</body>

</html>