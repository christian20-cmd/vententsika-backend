<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'API Vententsika Backend',
        'version' => '1.0',
        'status' => 'online'
    ]);
});

// Route pour tester le stockage (gardez-la si vous en avez besoin)
Route::get('/test-storage', function() {
    $mediaPath = storage_path('app/public/media');
    $files = [];

    if (is_dir($mediaPath)) {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($mediaPath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = [
                    'name' => $file->getFilename(),
                    'size' => round($file->getSize() / 1024, 2) . ' KB',
                    'url' => asset('storage/media/' . $file->getFilename())
                ];
            }
        }
    }

    return response()->json([
        'success' => true,
        'total_files' => count($files),
        'symlink_exists' => file_exists(public_path('storage')),
        'media_path' => $mediaPath,
        'files' => $files,
        'test_urls' => [
            'example' => $files[0]['url'] ?? 'N/A'
        ]
    ], 200, [], JSON_PRETTY_PRINT);
});

