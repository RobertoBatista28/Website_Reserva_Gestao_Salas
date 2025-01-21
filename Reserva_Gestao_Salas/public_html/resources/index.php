<?php
error_reporting(0);
include('./header/header.php');
include('conexao.php');
include('logs.php');
$conn = getDatabaseConnection();

$logFile = __DIR__ . '../../../logs/loginLogs.log';
$utilizadorFile = __DIR__ . '../../../logs/utilizadorLogs.log';
$adminFile = __DIR__ . '../../../logs/administradorLogs.log';
$markerFile = __DIR__ . '../../../logs/verificarLogs.txt';

$autenticado = false;
if (isset($_SESSION['cargo'])) {
    $autenticado = true;
} elseif (isset($_COOKIE['remember_me'])) {
    $rememberMeToken = $_COOKIE['remember_me'];

    $sql = "SELECT idUtilizador, cargo, nome, username, rememberToken FROM utilizador WHERE rememberToken IS NOT NULL";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($user = $result->fetch_assoc()) {
        if (password_verify($rememberMeToken, $user['rememberToken'])) {

            session_start();
            $_SESSION['idUtilizador'] = $user['idUtilizador'];
            $_SESSION['cargo'] = $user['cargo'];
            $_SESSION['nome'] = $user['nome'];
            $autenticado = true;

            writeLoginLog("Utilizador '" . $user['username'] . "' iniciou sessão com cookie 'remember_me'.");
            break;
        }
    }
}

if ($_SESSION['cargo'] == 'Administrador') {
    if (shouldCleanLogs($markerFile)) {
        cleanOldLogs($logFile, $adminFile, $utilizadorFile, 30);
    }
}

// Eliminar sala
if (isset($_GET['eliminarSala'])) {
    $idSala = (int) $_GET['eliminarSala'];
    $idUtilizador = $_SESSION['idUtilizador'];
    $stmt = $conn->prepare("DELETE FROM sala WHERE idSala = ?");
    $stmt->bind_param("i", $idSala);
    $stmt->execute();
    $stmt->close();

    $sqlUtilizador = "SELECT username FROM utilizador WHERE idUtilizador = ?";
    $stmtUtilizador = $conn->prepare($sqlUtilizador);
    $stmtUtilizador->bind_param("i", $idUtilizador);
    $stmtUtilizador->execute();
    $resultUtilizador = $stmtUtilizador->get_result();
    $username = $resultUtilizador->fetch_assoc()['username'];

    writeAdminLog("Sala com ID '$idSala' foi eliminada pelo administrador '" . $username. "'.");
    $_SESSION['mensagem_sucesso'] = "Sala eliminada com sucesso!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_SESSION['mensagem_sucesso'])) {
    echo "<script>alert('" . $_SESSION['mensagem_sucesso'] . "');</script>";
    unset($_SESSION['mensagem_sucesso']);
}

// Receber os parâmetros dos filtros
$tipoFiltro = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$capacidadeFiltro = isset($_GET['capacidade']) ? $_GET['capacidade'] : '';

// Preparar a query com placeholders
$sql = "SELECT idSala, nome, tipo, descricao, capacidade, estado FROM sala WHERE 1=1";

// Adicionar filtro de tipo, se presente
if ($tipoFiltro && $tipoFiltro !== '') {
    $sql .= " AND tipo = ?";
}

// Adicionar filtro de capacidade, se presente
if ($capacidadeFiltro) {
    $sql .= " AND capacidade >= ?";
}

// Preparar a query
$stmt = $conn->prepare($sql);

// Verificar se os parâmetros existem e associá-los corretamente
if ($tipoFiltro && $tipoFiltro !== '' && $capacidadeFiltro) {
    $stmt->bind_param("si", $tipoFiltro, $capacidadeFiltro);
} elseif ($tipoFiltro && $tipoFiltro !== '') {
    $stmt->bind_param("s", $tipoFiltro);
} elseif ($capacidadeFiltro) {
    $stmt->bind_param("i", $capacidadeFiltro);
}

$stmt->execute();
$result = $stmt->get_result();
$salas = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $salas[] = $row;
    }
}

// Obter os tipos distintos de sala
$sqlTipos = "SELECT DISTINCT tipo FROM sala";
$resultTipos = $conn->query($sqlTipos);
$tipos = [];

if ($resultTipos->num_rows > 0) {
    while ($row = $resultTipos->fetch_assoc()) {
        $tipos[] = $row['tipo'];
    }
}

function verificarDisponibilidade($idSala, $conn)
{
    // Define o total de horas disponíveis no dia
    $totalHorasDia = 16;
    $dataFiltro = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d', strtotime('+1 day'));

    $sql = "SELECT estado FROM sala WHERE idSala = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idSala);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $estadoSala = $row['estado'];

        if ($estadoSala === 'Brevemente') {
            return 'BREVEMENTE';
        }
    }

    // Seleciona todas as reservas confirmadas para a data fornecida
    $sql = "SELECT horaInicio, horaFim FROM reserva 
            WHERE idSala = ? 
              AND estado = 'Confirmada'
              AND dataReserva = ?
              AND (
                  (horaInicio < horaFim) 
              )";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $idSala, $dataFiltro);
    $stmt->execute();
    $result = $stmt->get_result();

    $totalHorasReservadas = 0;

    // Calcula o total de horas reservadas
    while ($row = $result->fetch_assoc()) {
        $inicio = strtotime($row['horaInicio']);
        $fim = strtotime($row['horaFim']);
        $duracaoHoras = ($fim - $inicio) / 3600;
        $totalHorasReservadas += $duracaoHoras;
    }

    $horasDisponiveis = $totalHorasDia - $totalHorasReservadas;
    return $horasDisponiveis > 0 ? 'DISPONÍVEL' : 'INDISPONÍVEL';
}

function getSalaImage($tipo)
{
    $imagens = [
        'Informática' => 'media/salaInformatica.jpg',
        'Auditório' => 'media/salaAuditorio.jpg',
        'Arte' => 'media/salaArte.jpg',
        'Biblioteca' => 'media/salaBiblioteca.jpg',
        'Laboratório' => 'media/salaLaboratorio.jpg',
        'Mecânica' => 'media/salaMecanica.jpg',
        'Multimédia' => 'media/salaMultimedia.jpg',
        'Música' => 'media/salaMusica.jpg',
        'Pavilhão' => 'media/salaPavilhao.jpg',
        'Reunião' => 'media/salaReuniao.jpg',
        'Teórica' => 'media/salaTeorica.jpg'
    ];
    return isset($imagens[$tipo]) ? $imagens[$tipo] : 'media/salaDefault.png';
}

$salasPorPagina = 10;
$paginaAtual = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $salasPorPagina;

$sql .= " LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

if ($tipoFiltro && $tipoFiltro !== '' && $capacidadeFiltro) {
    $stmt->bind_param("siii", $tipoFiltro, $capacidadeFiltro, $salasPorPagina, $offset);
} elseif ($tipoFiltro && $tipoFiltro !== '') {
    $stmt->bind_param("sii", $tipoFiltro, $salasPorPagina, $offset);
} elseif ($capacidadeFiltro) {
    $stmt->bind_param("iii", $capacidadeFiltro, $salasPorPagina, $offset);
} else {
    $stmt->bind_param("ii", $salasPorPagina, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
$salas = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $salas[] = $row;
    }
}
?>
<script>

</script>


<!DOCTYPE html>
<html lang="pt-PT">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de salas - ESTG</title>
    <link rel="stylesheet" href="index.css">
</head>

<body>

    <main class="container">
        <div class="filters">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="tipo">Tipo de Sala</label>
                    <select id="tipo" onchange="applyFilters()">
                        <option value="">Todos os tipos</option>
                        <?php foreach ($tipos as $tipo): ?>
                            <option value="<?php echo $tipo; ?>" <?php echo $tipoFiltro == $tipo ? 'selected' : ''; ?>>
                                <?php echo $tipo; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="capacidade">Capacidade Mínima</label>
                    <input type="number" id="capacidade" min="1" onchange="applyFilters()"
                        value="<?php echo $capacidadeFiltro ? $capacidadeFiltro : 0; ?>">

                </div>
                <?php if ($autenticado): ?>
                    <div class="filter-group">
                        <label for="data">Data</label>
                        <input type="date" id="data" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                            value="<?php echo isset($_GET['data']) && $_GET['data'] >= date('Y-m-d', strtotime('+1 day')) ? $_GET['data'] : date('Y-m-d', strtotime('+1 day')); ?>"
                            onchange="applyFilters()">
                    </div>
                <?php endif; ?>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button class="btn" onclick="resetFilters()">Limpar filtros</button>
                    <?php if ($_SESSION['cargo'] == 'Administrador'): ?>
                        <button class="btn" onclick="window.location.href='areaAdmin/adicionarSala.php'">Adicionar Sala</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid">
            <?php foreach ($salas as $sala): ?>
                <div class="card">
                    <img src="<?php echo getSalaImage($sala['tipo']); ?>" alt="Imagem da sala" class="card-image">
                    <div class="card-content">
                        <h3><?php echo $sala['nome']; ?></h3>
                        <div class="room-details">
                            <div class="room-info">
                                <span>Capacidade: <?php echo $sala['capacidade']; ?></span>
                                <span>Tipo: <?php echo $sala['tipo']; ?></span>
                            </div>
                            <?php if ($autenticado): ?>
                                <div class="status">
                                    <?php
                                    $estado = verificarDisponibilidade($sala['idSala'], $conn);
                                    if ($estado === 'DISPONÍVEL') {
                                        echo '<span style="color: green;">' . $estado . '</span>';
                                    } elseif ($estado === 'INDISPONÍVEL') {
                                        echo '<span style="color: red;">' . $estado . '</span>';
                                    } elseif ($estado === 'BREVEMENTE') {
                                        echo '<span style="color: orange;">' . $estado . '</span>';
                                    }
                                    ?>
                                </div>
                                <?php if ($_SESSION['cargo'] == 'Administrador'): ?>
                                    <a href="../areaAdmin/editarSala.php?idSala=<?php echo $sala['idSala']; ?>"
                                        class="btn editar-btn">
                                        Editar
                                    </a>
                                    <a class="btn eliminar-btn" href="?eliminarSala=<?php echo $sala['idSala']; ?>"
                                        onclick="return confirm('Tem a certeza que deseja eliminar esta sala?')">
                                        Eliminar
                                    </a>
                                <?php elseif ($estado === 'DISPONÍVEL'): ?>
                                    <a href="../reservarSala/reservarSala.php?idSala=<?php echo $sala['idSala']; ?>&dataReserva=<?php echo isset($_GET['data']) ? $_GET['data'] : date('Y-m-d', strtotime('+1 day')); ?>"
                                        class="btn reservar-btn">
                                        Reservar
                                    </a>
                                <?php else : ?>
                                    <a
                                        class="btn reservar-btn" style="background-color: grey; cursor: not-allowed;">
                                        Reservar
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script>
        let paginaAtual = <?php echo $paginaAtual; ?>;
        const salasPorPagina = <?php echo $salasPorPagina; ?>;
        let carregando = false;

        window.addEventListener('scroll', () => {
            if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 500 && !carregando) {
                carregando = true;
                paginaAtual++;
                carregarMaisSalas();
            }
        });

        function carregarMaisSalas() {
            const tipo = document.getElementById('tipo').value;
            const capacidade = document.getElementById('capacidade').value;
            const data = document.getElementById('data') ? document.getElementById('data').value : '';

            let url = window.location.pathname + "?pagina=" + paginaAtual + "&";

            if (tipo) url += `tipo=${tipo}&`;
            if (capacidade) url += `capacidade=${capacidade}&`;
            if (data) url += `data=${data}&`;

            url = url.endsWith('&') ? url.slice(0, -1) : url;

            fetch(url)
                .then(response => response.text())
                .then(data => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(data, 'text/html');
                    const novasSalas = doc.querySelectorAll('.card');
                    const grid = document.querySelector('.grid');
                    novasSalas.forEach(sala => grid.appendChild(sala));
                    carregando = false;
                })
                .catch(error => {
                    console.error('Erro ao carregar mais salas:', error);
                    carregando = false;
                });
        }

        function applyFilters() {
            const tipo = document.getElementById('tipo').value;
            const capacidade = document.getElementById('capacidade').value;
            const dataInput = document.getElementById('data');
            const data = dataInput.value;

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const selectedDate = new Date(data);

            if (selectedDate <= today) {
                alert("Você não pode selecionar uma data anterior a amanhã.");

                const tomorrow = new Date();
                tomorrow.setDate(today.getDate() + 1);
                dataInput.value = tomorrow.toISOString().split('T')[0];
                return;
            }

            let url = window.location.pathname + "?";

            if (tipo) url += `tipo=${tipo}&`;
            if (capacidade) url += `capacidade=${capacidade}&`;
            if (data) url += `data=${data}&`;

            url = url.endsWith('&') ? url.slice(0, -1) : url;

            window.location.href = url;
        }

        function resetFilters() {
            window.location.href = window.location.pathname;
        }
    </script>
</body>

</html>

<?php

$stmt->close();
$conn->close();
?>