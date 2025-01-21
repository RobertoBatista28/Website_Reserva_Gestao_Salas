<?php

date_default_timezone_set('Europe/Lisbon');

function writeLoginLog($message, $level = 'INFO') {
    $logFile = __DIR__ . '../../../logs/loginLogs.log';
    $currentDateTime = date('Y-m-d H:i:s');
    $logMessage = "[$currentDateTime] $level: $message" . PHP_EOL;

    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

function writeAdminLog($message, $level = 'INFO') {
    $adminFile = __DIR__ . '../../../logs/administradorLogs.log';
    $currentDateTime = date('Y-m-d H:i:s');
    $logMessage = "[$currentDateTime] $level: $message" . PHP_EOL;

    file_put_contents($adminFile, $logMessage, FILE_APPEND | LOCK_EX);
}

function writeUtilizadorLog($message, $level = 'INFO') {
    $utilizadorFile = __DIR__ . '../../../logs/utilizadorLogs.log';
    $currentDateTime = date('Y-m-d H:i:s');
    $logMessage = "[$currentDateTime] $level: $message" . PHP_EOL;

    file_put_contents($utilizadorFile, $logMessage, FILE_APPEND | LOCK_EX);
}

function cleanOldLogs($logFile, $adminFile, $utilizadorFile, $daysToKeep) {
    $thresholdDate = time() - ($daysToKeep * 24 * 60 * 60);

    // Função auxiliar para processar e limpar logs antigos
    function processLogFile($sourceFile, $thresholdDate) {
        $logs = file($sourceFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $remainingLogs = [];

        foreach ($logs as $log) {
            // Extraia a data do log
            preg_match('/\[(.*?)\]/', $log, $matches);
            $logDate = isset($matches[1]) ? strtotime($matches[1]) : false;

            if ($logDate && $logDate >= $thresholdDate) {
                $remainingLogs[] = $log;
            }
        }

        file_put_contents($sourceFile, implode("\n", $remainingLogs) . "\n");
    }

    processLogFile($logFile, $thresholdDate);
    processLogFile($adminFile, $thresholdDate);
    processLogFile($utilizadorFile, $thresholdDate);
}

function shouldCleanLogs($markerFile) {
    $lastRun = file_exists($markerFile) ? file_get_contents($markerFile) : 0;
    $currentTime = time();

    if ($currentTime - $lastRun > 86400) {
        file_put_contents($markerFile, $currentTime);
        return true;
    }

    return false;
}
?>
