<?php
require_once '../conexao.php';
require '../../vendor/autoload.php';
require_once '../logs.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = ""; // Inicializa a variável da mensagem

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDatabaseConnection();
    $recaptchaSecret = "6Lf2W64qAAAAALWmQJqWCy72_lwhKnm0KAsUlpx1";
    $recaptchaResponse = $_POST['g-recaptcha-response'];

    // Verifica o reCAPTCHA
    $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=" . $recaptchaSecret . "&response=" . $recaptchaResponse);
    $responseKeys = json_decode($response, true);


    if (!$responseKeys["success"]) {
        writeLoginLog("Tentativa de recuperação de password sem reCAPTCHA com o seguinte email: " . $_POST['email'] . ".", "WARNING");
        $message = "<p class='message error'>Por favor, complete o reCAPTCHA para enviar o email de recuperação.</p>";
    } else {
        $email = $_POST['email'];

        $sql = "SELECT * FROM utilizador WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            $token = bin2hex(random_bytes(30));
            $tokenHASH = password_hash($token, PASSWORD_DEFAULT);
            $expiration = date("Y-m-d H:i:s", strtotime("+1 hour"));

            $update_sql = "UPDATE utilizador SET resetPasswordToken = ?, expirePasswordToken = ? WHERE email = ?";
            $stmt_update = $conn->prepare($update_sql);
            $stmt_update->bind_param("sss", $tokenHASH, $expiration, $email);
            $stmt_update->execute();

            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'projetorocular@gmail.com';
                $mail->Password = 'hnsj hewy rlsy mujh';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('jr.bricolage-suporte@hotmail.com', 'SAW');
                $mail->addAddress($email, $user['nome']);

                $reset_link = "https://saw.pt/recuperarPassword/redefinirPassword.php?token=$token";
                $mail->CharSet = 'UTF-8';
                $mail->isHTML(true);
                $mail->Subject = "Recuperação de password";
                $mail->Body = "
                <p>Olá, <strong>" . htmlspecialchars($user['nome']) . "</strong>!</p>
                <p>Clique no link abaixo para redefinir a sua password. Este link é válido por 1 hora:</p>
                <p><a href='$reset_link'>$reset_link</a></p>
                <p>Se você não solicitou a redefinição, ignore este email.</p>
                <p>Obrigado!</p>
            ";

                $mail->send();
                writeLoginLog("Email de recuperação de password enviado para o email: " . $email, "INFO");
                $message = "<p class='message success'>Se o email estiver associado a uma conta, receberá um email com instruções para redefinir a password.</p>";
            } catch (Exception $e) {
                writeLoginLog("Erro ao enviar email de recuperação de password: {$mail->ErrorInfo}", "ERROR");
                $message = "<p class='message error'>Erro ao enviar o email. Verifique as configurações: {$mail->ErrorInfo}</p>";
            }
        } else {
            writeLoginLog("Tentativa de recuperação de password com o seguinte email: " . $_POST['email'] . " que não está associado a nenhuma conta.", "WARNING");
            $message = "<p class='message success'>Se o email estiver associado a uma conta, receberá um email com instruções para redefinir a password.</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Password</title>
    <link rel="stylesheet" href="recuperarPassword.css">
    <style>
        .message {
            max-width: 80%;
            margin: 10px auto;
            text-align: center;
            word-wrap: break-word;
        }

        .message.success {
            color: green;
        }

        .message.error {
            color: red;
        }
    </style>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>

<body>
    <div class="container">
        <div class="login-box">
            <h1>Recuperar password</h1>
            <form method="POST" action="">
                <label for="email">Insira seu email de recuperação de conta:</label>
                <input type="email" id="email" name="email" required>
                <div class="g-recaptcha" data-sitekey="6Lf2W64qAAAAAA6vRFRJc_C5sr3ZokWjRUwJIrEm"></div>
                <button type="submit">Enviar</button>
                <a class="voltar" href="../login/login.php ">◄ Voltar</a>
            </form>
            <?php echo $message; ?>

        </div>
    </div>
</body>

</html>