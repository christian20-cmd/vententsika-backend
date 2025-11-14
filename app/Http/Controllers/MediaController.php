<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,webp,mp4,avi,mov|max:10240',
            'type_media' => 'required|in:image,video'
        ]);

        try {
            $file = $request->file('file');

            // Générer un nom de fichier unique
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            // Stocker dans storage/app/public/media
            $path = $file->storeAs('public/media', $fileName);

            // Générer l'URL publique
            $url = Storage::url($path);

            // Enregistrer dans la table media
            $media = Media::create([
                'chemin_fichier' => $url,
                'type_media' => $request->type_media
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Fichier uploadé avec succès',
                'media' => $media
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload du fichier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index(): JsonResponse
    {
        try {
            $medias = Media::orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $medias
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des médias',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $media = Media::findOrFail($id);

            // Supprimer le fichier physique
            $filePath = str_replace('/storage/', 'public/', $media->chemin_fichier);
            if (Storage::exists($filePath)) {
                Storage::delete($filePath);
            }

            // Supprimer de la base de données
            $media->delete();

            return response()->json([
                'success' => true,
                'message' => 'Média supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du média',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
