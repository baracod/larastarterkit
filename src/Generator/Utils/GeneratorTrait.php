<?php

namespace Baracod\Larastarterkit\Generator\Utils;

use Illuminate\Support\Str;

trait GeneratorTrait
{

    public function tableNameToModelName(string $tableName): string
    {
        $nameElements = explode('_', $tableName);
        if (count($nameElements) === 1) {
            return ucfirst(Str::singular($nameElements[0]));
        }
        unset($nameElements[0]);
        $tableName = implode(' ', $nameElements);
        $tableName = ucwords($tableName);
        $tableName = str_replace(' ', '', $tableName);

        return Str::singular($tableName);
    }

    /**
     * Lit un fichier JS, extrait le tableau (délimité par des crochets),
     * convertit ce tableau en tableau PHP, réalise une manipulation (ajout, modification ou suppression),
     * puis reconvertit le tableau en JSON (avec les clés entre guillemets) pour le réinjecter dans le fichier.
     *
     * @param string $jsFilePath Chemin vers le fichier JS.
     * @param string $operation  Opération à effectuer : 'add' (ajouter), 'modify' (modifier) ou 'delete' (supprimer).
     * @param mixed  $element    Élément à ajouter ou données de modification (selon l'opération).
     *
     * @return bool Retourne true en cas de succès.
     */
    function manipulerTableauJS($jsFilePath, $operation, $element = null)
    {
        if (!file_exists($jsFilePath)) {
            die("Le fichier $jsFilePath n'existe pas.");
        }

        $content = file_get_contents($jsFilePath);

        // Extraction du premier tableau délimité par [ ... ]
        if (preg_match('/(\[.*\])/sU', $content, $matches)) {
            $jsArrayStr = $matches[1];

            dd($jsArrayStr);

            // On suppose que le tableau extrait est au format JSON valide
            $dataArray = json_decode($jsArrayStr, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                die("Erreur de décodage JSON : " . json_last_error_msg());
            }

            // Réalisation de la manipulation sur le tableau PHP
            switch (strtolower($operation)) {
                case 'add':
                case 'ajouter':
                    if ($element !== null) {
                        $dataArray[] = $element;
                    }
                    break;
                case 'modify':
                case 'modifier':
                    if (!empty($dataArray) && is_array($element)) {
                        // Exemple : modification du premier élément en fusionnant avec $element
                        $dataArray[0] = array_merge($dataArray[0], $element);
                    }
                    break;
                case 'delete':
                case 'supprimer':
                    if (!empty($dataArray)) {
                        array_pop($dataArray);
                    }
                    break;
                default:
                    // Si l'opération n'est pas reconnue, aucune modification n'est effectuée
                    break;
            }

            // Conversion du tableau PHP en JSON (les clés restent entre guillemets, format standard)
            $newJsonArray = json_encode($dataArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($newJsonArray === false) {
                die("Erreur lors de l'encodage JSON.");
            }

            // Remplacement de l'ancien tableau dans le contenu par le nouveau JSON
            $newContent = preg_replace('/(\[.*\])/sU', $newJsonArray, $content, 1);

            // Écriture du contenu modifié dans le fichier JS
            file_put_contents($jsFilePath, $newContent);

            return true;
        } else {
            die("Aucun tableau n'a été trouvé dans le fichier JS.");
        }
    }
}
