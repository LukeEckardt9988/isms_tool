<?php
require_once 'functions.php';
requireLogin();

$document_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
// Neuer Parameter, um zwischen Ansicht und Download zu unterscheiden
$disposition = filter_input(INPUT_GET, 'disposition', FILTER_SANITIZE_SPECIAL_CHARS); 
if ($disposition !== 'inline') {
    $disposition = 'attachment'; // Standard ist Download
}


if (!$document_id) {
    http_response_code(400);
    die("Ungültige Dokumenten-ID.");
}

$pdo = getPDO();
$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$document_id]);
$document = $stmt->fetch();

if (!$document) {
    http_response_code(404);
    die("Dokument nicht gefunden.");
}

// Annahme: 'file_path' in Ihrer DB enthält den Pfad UND den gespeicherten Dateinamen
// z.B. "uploads/documents/gespeicherter_eindeutiger_name.pdf"
// ODER 'file_path' ist nur der Pfad und 'stored_filename' der Dateiname.
// Passen Sie das hier an Ihre Struktur an!
$file_on_server = $document['file_path']; // Oder UPLOAD_DIR_DOCS . $document['stored_filename'];

if (file_exists($file_on_server)) {
    $mime_type = $document['mime_type'] ?: 'application/octet-stream';
    $original_filename = basename($document['original_filename'] ?: 'datei.dat'); // Fallback für Originalnamen

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: ' . $disposition . '; filename="' . $original_filename . '"'); // Hier wird der Modus gesetzt
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_on_server));
    
    if (ob_get_level()) {
        ob_end_clean();
    }
    readfile($file_on_server);
    exit;
} else {
    error_log("Download-Fehler (via download_document.php): Datei nicht auf Server: " . $file_on_server);
    http_response_code(404);
    die("Datei nicht auf dem Server gefunden.");
}
?>