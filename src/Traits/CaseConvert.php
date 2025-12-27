<?php

namespace Baracod\Larastarterkit\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Traversable;

/**
 * Class CaseConvert
 *
 * Un utilitaire pour convertir récursivement les clés des tableaux et des objets
 * entre snake_case, camelCase et PascalCase (StudlyCase).
 */
trait CaseConvert
{
    /**
     * Convertit récursivement les clés des données en camelCase.
     *
     * @param  mixed  $data  Les données à convertir (tableau, objet, etc.).
     * @return mixed
     */
    public static function toCamel($data)
    {
        return self::convertKeys($data, 'camel');
    }

    /**
     * Convertit récursivement les clés des données en snake_case.
     *
     * @param  mixed  $data  Les données à convertir (tableau, objet, etc.).
     * @return mixed
     */
    public static function toSnake($data)
    {
        return self::convertKeys($data, 'snake');
    }

    /**
     * Convertit récursivement les clés des données en PascalCase (StudlyCase).
     *
     * @param  mixed  $data  Les données à convertir (tableau, objet, etc.).
     * @return mixed
     */
    public static function toPascal($data)
    {
        // Dans Laravel, le PascalCase est appelé StudlyCase.
        return self::convertKeys($data, 'studly');
    }

    /**
     * La fonction récursive principale pour convertir les clés.
     *
     * @param  mixed  $data  Les données à traiter.
     * @param  string  $case  Le cas cible ('camel', 'snake', 'studly').
     * @return mixed
     */
    private static function convertKeys($data, string $case)
    {
        // Si ce n'est pas un tableau ou un objet, on le retourne tel quel.
        if (! is_array($data) && ! is_object($data)) {
            return $data;
        }

        // Gère les modèles Eloquent en récupérant leurs attributs.
        if ($data instanceof Model) {
            $data = $data->getAttributes();
        }
        // Gère les objets itérables (comme les Collections) en les convertissant en tableau.
        elseif ($data instanceof Traversable) {
            $data = iterator_to_array($data);
        }

        $result = [];
        foreach ((array) $data as $key => $value) {
            // Convertit la clé vers le cas cible en utilisant le helper Str.
            $newKey = Str::$case((string) $key);

            // Appelle récursivement la fonction pour les valeurs qui sont des tableaux ou des objets.
            $result[$newKey] = self::convertKeys($value, $case);
        }

        // Retourne un objet si l'entrée originale était un objet, sinon un tableau.
        return is_object($data) ? (object) $result : $result;
    }
}
