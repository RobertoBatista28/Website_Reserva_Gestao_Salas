<?php
session_start();
include_once 'logs.php';
include_once 'conexao.php';
$conn = getDatabaseConnection();

$idUtilizador = $_SESSION['idUtilizador'];

$sql = "SELECT username FROM utilizador WHERE idUtilizador = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idUtilizador);
$stmt->execute();
$result = $stmt->get_result();
$username = $result->fetch_assoc()['username'];

session_unset();
session_destroy();
setcookie('remember_me', '', time() - 3600, '/');
setcookie('PHPSESSID', '', time() - 3600, '/');

writeLoginLog("Utilizador '$username' terminou sessão.");
header('Location: index.php');
exit();
?>
