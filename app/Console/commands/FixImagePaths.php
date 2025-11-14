<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Media;
use App\Models\Produit;
use Illuminate\Support\Facades\DB;

class FixImagePaths extends Command
{
    protected $signature = 'images:fix';
    protected $description = 'Corriger les chemins des images pour utiliser /storage/media';

    public function handle()
    {
        $this->info('🔧 Correction des chemins d\'images...');

        DB::beginTransaction();

        try {
            // 1. Corriger les médias
            $this->info("\n📸 Correction de la table 'media'...");
            $medias = Media::all();
            $mediaFixed = 0;

            foreach ($medias as $media) {
                $oldPath = $media->chemin_fichier;
                $newPath = $this->convertPath($oldPath);

                if ($newPath !== $oldPath) {
                    $media->chemin_fichier = $newPath;
                    $media->save();
                    $this->line("  ✅ Média #{$media->idMedia}: $oldPath → $newPath");
                    $mediaFixed++;
                }
            }

            $this->info("  💾 Médias corrigés: $mediaFixed");

            // 2. Corriger les produits - image_principale
            $this->info("\n🛍️  Correction de la table 'produits'...");
            $produits = Produit::whereNotNull('image_principale')->get();
            $produitFixed = 0;

            foreach ($produits as $produit) {
                $oldPath = $produit->image_principale;
                $newPath = $this->convertPath($oldPath);

                if ($newPath !== $oldPath) {
                    $produit->image_principale = $newPath;
                    $produit->save();
                    $this->line("  ✅ Produit #{$produit->idProduit}: image principale corrigée");
                    $produitFixed++;
                }
            }

            $this->info("  💾 Produits (image principale) corrigés: $produitFixed");

            // 3. Corriger les produits - images_supplementaires
            $this->info("\n🖼️  Correction des images supplémentaires...");
            $produitsAvecImages = Produit::whereNotNull('images_supplementaires')->get();
            $imagesSupplFixed = 0;

            foreach ($produitsAvecImages as $produit) {
                $images = $produit->images_supplementaires;

                if (is_array($images) && count($images) > 0) {
                    $newImages = array_map(function($path) {
                        return $this->convertPath($path);
                    }, $images);

                    if ($newImages !== $images) {
                        $produit->images_supplementaires = $newImages;
                        $produit->save();
                        $this->line("  ✅ Produit #{$produit->idProduit}: " . count($images) . " images supplémentaires corrigées");
                        $imagesSupplFixed++;
                    }
                }
            }

            $this->info("  💾 Produits (images supplémentaires) corrigés: $imagesSupplFixed");

            DB::commit();

            // Résumé
            $this->info("\n" . str_repeat('=', 60));
            $this->info('🎉 CORRECTION TERMINÉE AVEC SUCCÈS!');
            $this->info(str_repeat('=', 60));
            $this->table(
                ['Type', 'Nombre corrigé'],
                [
                    ['Médias', $mediaFixed],
                    ['Produits (image principale)', $produitFixed],
                    ['Produits (images supplémentaires)', $imagesSupplFixed],
                    ['TOTAL', $mediaFixed + $produitFixed + $imagesSupplFixed]
                ]
            );

            $this->info("\n📝 Prochaines étapes:");
            $this->line("  1. Vérifiez que les images sont dans: storage/app/public/media");
            $this->line("  2. Accédez à votre application React");
            $this->line("  3. Les images devraient maintenant s'afficher correctement!");
            $this->line("\n🌐 Format des URLs: http://localhost:8000/storage/media/nom_fichier.jpg");

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Erreur lors de la correction: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Convertir les anciens chemins vers le nouveau format
     */
    private function convertPath($path)
    {
        if (empty($path)) {
            return $path;
        }

        // Si c'est déjà au bon format, ne rien faire
        if (preg_match('#^/storage/media/.+$#', $path)) {
            return $path;
        }

        // Extraire le nom du fichier depuis différents formats

        // Format: /storage/images/fichier.jpg ou /storage/produits/fichier.jpg
        if (preg_match('#^/storage/(images|produits|logos)/(.+)$#', $path, $matches)) {
            return '/storage/media/' . $matches[2];
        }

        // Format: http://localhost:8000/storage/images/fichier.jpg
        if (preg_match('#^https?://[^/]+/storage/(images|produits|logos|media)/(.+)$#', $path, $matches)) {
            return '/storage/media/' . $matches[2];
        }

        // Format: storage/images/fichier.jpg (sans slash initial)
        if (preg_match('#^storage/(images|produits|logos)/(.+)$#', $path, $matches)) {
            return '/storage/media/' . $matches[2];
        }

        // Format: juste le nom de fichier
        if (!str_contains($path, '/')) {
            return '/storage/media/' . $path;
        }

        // Si aucun pattern ne correspond, retourner tel quel
        return $path;
    }
}
