<?php
session_start();
error_reporting(0);
include('../logs.php');
include('../conexao.php');
$conn = getDatabaseConnection();

date_default_timezone_set('Europe/Lisbon');

$sqllUtilizador = "SELECT username FROM utilizador WHERE idUtilizador = ?";
$stmtUtilizador = $conn->prepare($sqllUtilizador);
$stmtUtilizador->bind_param("i", $_SESSION['idUtilizador']);
$stmtUtilizador->execute();
$username = $stmtUtilizador->get_result()->fetch_assoc()['username'];

if (!isset($_SESSION['idUtilizador'])) {
    header("Location: ../login/login.php");
    exit();
} else if ($_SESSION['cargo'] !== "Professor") {
    writeAdminLog("Administrador '$username' tentou aceder a reservarSala.php.");
    header("Location: ../error.php?code=403&message=Você não tem permissão para acessar esta área.");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idUtilizador = isset($_SESSION['idUtilizador']) ? $_SESSION['idUtilizador'] : 0;
    $idSala = isset($_POST['idSala']) ? $_POST['idSala'] : 0;
    $reservas = isset($_POST['reservas']) ? json_decode($_POST['reservas'], true) : [];

    if ($idSala && !empty($reservas)) {
        foreach ($reservas as $reserva) {
            $horaInicio = $reserva['horaInicio'];
            $horaFim = $reserva['horaFim'];
            $dataReserva = isset($_POST['dataReserva']) ? $_POST['dataReserva'] : date('Y-m-d');
            $estado = "Confirmada";

            $sql = "INSERT INTO reserva (idSala, idUtilizador, dataReserva, horaInicio, horaFim, estado) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iissss", $idSala, $idUtilizador, $dataReserva, $horaInicio, $horaFim, $estado);

            if ($stmt->execute()) {
                writeUtilizadorLog("Utilizador '$username' reservou a sala com ID '$idSala' para o dia '$dataReserva' das $horaInicio às $horaFim.");
                $response = ['success' => true];
            } else {
                writeUtilizadorLog("Utilizador '$username' tentou reservar a sala com ID '$idSala' para o dia '$dataReserva' das $horaInicio às $horaFim, mas ocorreu um erro: " . $stmt->error);
                $response = ['success' => false];
            }
        }
    } else {
        writeUtilizadorLog("Utilizador '$username' tentou reservar a sala '$idSala' sem especificar uma sala ou horários.");
        $response = ['success' => false];
    }

    echo json_encode($response);
    exit();
}

$idSala = $_GET['idSala'];
$dataReserva = isset($_GET['dataReserva']) ? $_GET['dataReserva'] : date('Y-m-d', strtotime('+1 day'));


if (!DateTime::createFromFormat('Y-m-d', $dataReserva) || DateTime::getLastErrors()['warning_count'] > 0 || DateTime::getLastErrors()['error_count'] > 0 || $dataReserva <= date('Y-m-d')) {
    writeLoginLog("Utilizador '$username' tentou aceder à página de reservar sala com uma data inválida.");
    header("Location: ../error.php?code=400&message=Data de reserva inválida.");
}


// Obter detalhes da sala
$sql = "SELECT idSala, nome, tipo, descricao, capacidade, estado FROM sala WHERE idSala = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idSala);
$stmt->execute();
$result = $stmt->get_result();

$sala = $result->fetch_assoc();

if (!isset($_GET['idSala']) || empty($_GET['idSala'])) {
    writeUtilizadorLog("Utilizador '$username' tentou aceder à página de reservar sala sem especificar a sala.");
    header("Location: ../error.php?code=400&message=Erro ao processar a reserva. Por favor, tente novamente.");
} else if ($result->num_rows === 0) {
    writeUtilizadorLog("Utilizador '$username' tentou aceder à página de reservar sala a partir de um idSala inválido ('" . $_GET['idSala'] . "').");
    header("Location: ../error.php?code=404&message=Sala não encontrada.");
} else if ($sala['estado'] !== "Disponível") {
    writeUtilizadorLog("Utilizador '$username' tentou aceder à página de reservar sala, sala com ID '$idSala' que se encontra indisponível para.");
    header("Location: ../error.php?code=403&message=Sala indisponível por tempo indeterminado para reserva.");
}

//$sala = $result->fetch_assoc();

// Obter reservas para a data selecionada
$sqlReservas = "SELECT TIME_FORMAT(horaInicio, '%H:%i') AS horaInicio, TIME_FORMAT(horaFim, '%H:%i') AS horaFim FROM reserva WHERE idSala = ? AND dataReserva = ? AND estado = 'Confirmada'";
$stmtReservas = $conn->prepare($sqlReservas);
$stmtReservas->bind_param("is", $idSala, $dataReserva);
$stmtReservas->execute();
$reservasResult = $stmtReservas->get_result();

// Processar horários reservados
$reservas = [];
while ($row = $reservasResult->fetch_assoc()) {
    $horaAtual = strtotime($row['horaInicio']);
    $horaFim = strtotime($row['horaFim']);

    while ($horaAtual < $horaFim) {
        $reservas[] = date('H:i', $horaAtual);
        $horaAtual = strtotime("+1 hour", $horaAtual);
    }
}

$stmtReservas->close();


function getSalaImage($tipo)
{
    $imagens = [
        'Informática' => '../media/salaInformatica.jpg',
        'Auditório' => '../media/salaAuditorio.jpg',
        'Arte' => '../media/salaArte.jpg',
        'Biblioteca' => '../media/salaBiblioteca.jpg',
        'Laboratório' => '../media/salaLaboratorio.jpg',
        'Mecânica' => '../media/salaMecanica.jpg',
        'Multimédia' => '../media/salaMultimedia.jpg',
        'Música' => '../media/salaMusica.jpg',
        'Pavilhão' => '../media/salaPavilhao.jpg',
        'Reunião' => '../media/salaReuniao.jpg',
        'Teórica' => '../media/salaTeorica.jpg'
    ];
    return $imagens[$tipo] ?? '../media/salaDefault.png';
}
?>

<!DOCTYPE html>
<html lang="pt-PT">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Sala - <?php echo htmlspecialchars($sala['nome']); ?></title>
    <link rel="stylesheet" href="reservarSala.css">
</head>

<body>
    <?php include('../header/header.php'); ?>
    <main class="container">
        <div class="room-details">
            <h1><?php echo htmlspecialchars($sala['nome']); ?></h1>
            <div class="image-container">
                <img src="<?php echo getSalaImage($sala['tipo']); ?>" alt="Imagem da sala">
            </div>
            <div class="info">
                <p><strong>Tipo:</strong> <?php echo htmlspecialchars($sala['tipo']); ?></p>
                <p><strong>Capacidade:</strong> <?php echo htmlspecialchars($sala['capacidade']); ?></p>
                <p><strong>Descrição:</strong> <?php echo htmlspecialchars($sala['descricao']); ?></p>
            </div>
        </div>

        <div class="reservations">
            <div class="date-picker">
                <label for="dataReserva">Data:</label>
                <input type="date" id="dataReserva" value="<?php echo $dataReserva; ?>"
                    min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" onchange="updateDataReserva()">
            </div>

            <div class="table-container">
                <div class="morning-afternoon">
                    <h2>Manhã</h2>
                    <table class="time-table">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Disponibilidade</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $horasManha = range(7, 14);
                            foreach ($horasManha as $hora) {
                                $horaFormatada = str_pad($hora, 2, '0', STR_PAD_LEFT) . ":00";
                                $horaReservada = in_array($horaFormatada, $reservas);

                                echo "<tr>";
                                echo "<td>" . $horaFormatada . "</td>";

                                if ($horaReservada) {
                                    echo "<td style='color: red;'>Reservado</td>";
                                    echo "<td><input type='checkbox' disabled></td>";
                                } else {
                                    echo "<td style='color: green;'>Disponível</td>";
                                    echo "<td><input type='checkbox' class='checkbox' data-id-sala='$idSala' data-hora='$horaFormatada'></td>";
                                }

                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>

                    <h2>Tarde</h2>
                    <table class="time-table">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Disponibilidade</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $horasTarde = range(15, 22);
                            foreach ($horasTarde as $hora) {
                                $horaFormatada = str_pad($hora, 2, '0', STR_PAD_LEFT) . ":00";
                                $horaReservada = in_array($horaFormatada, $reservas);

                                echo "<tr>";
                                echo "<td>" . $horaFormatada . "</td>";

                                if ($horaReservada) {
                                    echo "<td style='color: red;'>Reservado</td>";
                                    echo "<td><input type='checkbox' disabled></td>";
                                } else {
                                    echo "<td style='color: green;'>Disponível</td>";
                                    echo "<td><input type='checkbox' class='checkbox' data-id-sala='$idSala' data-hora='$horaFormatada'></td>";
                                }

                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div>
                <button class="btn" onclick="confirmarReserva()">Reservar Sala</button>
            </div>
        </div>
    </main>

    <script>
        function updateDataReserva() {
            var dataReserva = document.getElementById('dataReserva').value;
            var today = new Date();
            var selectedDate = new Date(dataReserva);

            today.setHours(0, 0, 0, 0);

            if (selectedDate <= today) {
                alert("Você não pode reservar para hoje ou uma data passada.");

                var tomorrow = new Date();
                tomorrow.setDate(today.getDate() + 1);
                document.getElementById('dataReserva').value = tomorrow.toISOString().split('T')[0];
                return;
            }

            window.location.href = "?idSala=<?php echo $idSala; ?>&dataReserva=" + dataReserva;
        }

        function confirmarReserva() {
            var checkboxes = document.querySelectorAll('.checkbox:checked');
            if (checkboxes.length > 0) {
                var horariosSelecionados = [];
                var horaInicio = "";
                var horaFim = "";

                checkboxes.forEach(function(checkbox, index) {
                    var hora = checkbox.getAttribute('data-hora');
                    if (horaInicio === "") {
                        horaInicio = hora;
                    }

                    if (index === checkboxes.length - 1 || !isNextHour(checkboxes[index], checkboxes[index + 1])) {
                        horaFim = incrementHour(hora);
                        horariosSelecionados.push({
                            horaInicio: horaInicio,
                            horaFim: horaFim
                        });
                        horaInicio = "";
                    }
                });

                if (confirm("Tem a certeza de que deseja reservar para as seguintes horas: " + horariosSelecionados.map(r => r.horaInicio + " - " + r.horaFim).join(', ') + "?")) {
                    var xhr = new XMLHttpRequest();
                    xhr.open("POST", window.location.href, true);
                    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

                    var data = new URLSearchParams();
                    data.append("idSala", "<?php echo $idSala; ?>");
                    var dataReservaSelecionada = document.getElementById('dataReserva').value;
                    data.append("dataReserva", dataReservaSelecionada);
                    data.append("reservas", JSON.stringify(horariosSelecionados));

                    xhr.onload = function() {
                        if (xhr.status === 200) {

                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                alert("Reserva realizada com sucesso!");
                                window.location.reload();
                            } else {
                                alert("Erro ao realizar a reserva. Tente novamente.");
                            }
                        } else {
                            alert("Erro ao realizar a reserva. Tente novamente.");
                        }
                    };

                    xhr.send(data.toString());
                }
            } else {
                alert("Selecione ao menos uma hora para reservar.");
            }
        }

        function isNextHour(currentCheckbox, nextCheckbox) {
            var currentHour = currentCheckbox.getAttribute('data-hora');
            var nextHour = nextCheckbox.getAttribute('data-hora');
            return parseInt(nextHour.split(':')[0]) === parseInt(currentHour.split(':')[0]) + 1;
        }

        function incrementHour(hora) {
            var parts = hora.split(':');
            var hours = parseInt(parts[0]);
            var minutes = parts[1];
            hours = (hours + 1) % 24;
            return ("0" + hours).slice(-2) + ":" + minutes;
        }
    </script>

</body>

</html>

<?php
$stmt->close();
$conn->close();
?>