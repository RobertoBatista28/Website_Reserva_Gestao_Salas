<?php
// Dados de conexão à base de dados
$servername = "localhost";
$username = "root";
$password_db = "";
$dbname = "saw";

// Função para estabelecer conexão
function getDatabaseConnection() {
    global $servername, $username, $password_db, $dbname;
    $conn = new mysqli($servername, $username, $password_db, $dbname);
    if ($conn->connect_error) {
        die("Erro na conexão: " . $conn->connect_error);
    }
    return $conn;
}
?>
