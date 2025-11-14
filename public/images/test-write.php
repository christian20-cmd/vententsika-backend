<?php
$testFile = __DIR__ . '/../storage/app/public/images/test-' . time() . '.txt';
$success = file_put_contents($testFile, 'Test d\'écriture');

if ($success) {
    echo "✅ Écriture réussie: $testFile\n";
    echo "Contenu: " . file_get_contents($testFile) . "\n";
    unlink($testFile);
} else {
    echo "❌ Échec de l'écriture\n";
    echo "Dossier: " . dirname($testFile) . "\n";
    echo "Writable: " . (is_writable(dirname($testFile)) ? 'OUI' : 'NON') . "\n";
}
