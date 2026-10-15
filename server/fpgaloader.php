<?php
// JSON for the kiosk's background request; HTML for direct links / no-JS clients.
$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
function respond($success, $message, $output = '', $status = 200) {
    global $wantsJson;
    http_response_code($status);
    header('Cache-Control: no-store');
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $success, 'message' => $message, 'output' => $output],
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        $escape = fn($value) => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>FPGA loading result</title>';
        echo '<h1>' . $escape($message) . '</h1><pre>' . $escape($output) . '</pre>';
        echo '<p><a href="./">Return to the game menu</a></p></html>';
    }
    exit;
}
$name = $_GET['name'] ?? null;
if (!is_string($name) || trim($name) === '') {
    respond(false, 'No game was specified.', '', 400);
}
$gameFile = basename($name);
$bitstreamPath = __DIR__ . '/bitstreams/' . $gameFile;
if (strtolower(pathinfo($gameFile, PATHINFO_EXTENSION)) !== 'bit' || !is_file($bitstreamPath)) {
    respond(false, 'The selected game bitstream could not be found.', '', 404);
}
if (!is_callable('exec')) {
    respond(false, 'The FPGA programmer is unavailable. Please ask the cabinet operator for help.', '', 503);
}
// Quote the complete path as one argument, and capture the actual process result.
$command = 'openFPGALoader -b nexysVideo ' . escapeshellarg($bitstreamPath) . ' 2>&1';
$output = [];
$exitCode = 1;
exec($command, $output, $exitCode);
$log = implode("\n", $output);
if ($exitCode !== 0) {
    respond(false, 'The FPGA could not be loaded. Please try again or ask the cabinet operator for help.', $log, 500);
}
respond(true, 'FPGA loaded successfully. Use the silver button to switch the display source to the FPGA.', $log);
