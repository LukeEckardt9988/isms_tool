<?php
require_once 'functions.php';
requireLogin();

$document_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
// Parameter, um zwischen Ansicht und Download zu unterscheiden
$view_mode = strtolower(trim(filter_input(INPUT_GET, 'view', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'attachment'));

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

// Annahme: 'file_path' in Ihrer DB enthält den relativen Pfad + den gespeicherten Dateinamen
// z.B. "uploads/documents/gespeicherter_eindeutiger_name.pdf"
$file_on_server = $document['file_path'];

// Prüfen, ob die Datei existiert
if (file_exists($file_on_server)) {
    $mime_type = $document['mime_type'] ?: 'application/octet-stream'; // Fallback MIME-Typ
    $original_filename = basename($document['original_filename'] ?: 'datei.dat'); // Fallback für Originalnamen

    // HTTP-Header setzen
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime_type);

    // *** HIER IST DIE ENTSCHEIDENDE LOGIK ***
    if (
        $view_mode === 'inline' &&
        (strpos($mime_type, 'image/') === 0 ||
            $mime_type === 'application/pdf' ||
            strpos($mime_type, 'text/') === 0) // Auch text/plain, text/html etc. inline versuchen
    ) {
        header('Content-Disposition: inline; filename="' . $original_filename . '"');
    } else {
        // Standardmäßig oder wenn 'attachment' explizit gewünscht wird, oder wenn Typ nicht sicher inline darstellbar ist
        header('Content-Disposition: attachment; filename="' . $original_filename . '"');
    }

    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_on_server));

    // Output Buffer löschen, um sicherzustellen, dass keine unerwünschten Daten gesendet werden
    if (ob_get_level()) {
        ob_end_clean();
    }

    readfile($file_on_server); // Sende die Datei an den Browser
    exit;
} else {
    error_log("Serve/Download-Fehler: Datei nicht auf Server gefunden: " . $file_on_server . " für Doc ID: " . $document_id);
    http_response_code(404);
    die("Datei nicht auf dem Server gefunden. Bitte kontaktieren Sie den Administrator.");
}
