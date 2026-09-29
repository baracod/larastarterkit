<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Ai\Fallback;

use Illuminate\Support\Str;

/**
 * Libellés d'interface sans IA : dictionnaire des noms de champs courants, sinon mise en forme du nom.
 */
final class Labels
{
    /** @var array<string, array{fr:string,en:string}> */
    private const WORDS = [
        'id' => ['fr' => 'Identifiant', 'en' => 'ID'],
        'uuid' => ['fr' => 'UUID', 'en' => 'UUID'],
        'name' => ['fr' => 'Nom', 'en' => 'Name'],
        'first_name' => ['fr' => 'Prénom', 'en' => 'First name'],
        'last_name' => ['fr' => 'Nom de famille', 'en' => 'Last name'],
        'full_name' => ['fr' => 'Nom complet', 'en' => 'Full name'],
        'title' => ['fr' => 'Titre', 'en' => 'Title'],
        'label' => ['fr' => 'Libellé', 'en' => 'Label'],
        'code' => ['fr' => 'Code', 'en' => 'Code'],
        'reference' => ['fr' => 'Référence', 'en' => 'Reference'],
        'description' => ['fr' => 'Description', 'en' => 'Description'],
        'content' => ['fr' => 'Contenu', 'en' => 'Content'],
        'body' => ['fr' => 'Corps', 'en' => 'Body'],
        'summary' => ['fr' => 'Résumé', 'en' => 'Summary'],
        'notes' => ['fr' => 'Notes', 'en' => 'Notes'],
        'comment' => ['fr' => 'Commentaire', 'en' => 'Comment'],
        'email' => ['fr' => 'Adresse e-mail', 'en' => 'Email address'],
        'phone' => ['fr' => 'Téléphone', 'en' => 'Phone'],
        'mobile' => ['fr' => 'Mobile', 'en' => 'Mobile'],
        'address' => ['fr' => 'Adresse', 'en' => 'Address'],
        'city' => ['fr' => 'Ville', 'en' => 'City'],
        'country' => ['fr' => 'Pays', 'en' => 'Country'],
        'postal_code' => ['fr' => 'Code postal', 'en' => 'Postal code'],
        'zip_code' => ['fr' => 'Code postal', 'en' => 'ZIP code'],
        'website' => ['fr' => 'Site web', 'en' => 'Website'],
        'url' => ['fr' => 'URL', 'en' => 'URL'],
        'status' => ['fr' => 'Statut', 'en' => 'Status'],
        'type' => ['fr' => 'Type', 'en' => 'Type'],
        'category' => ['fr' => 'Catégorie', 'en' => 'Category'],
        'price' => ['fr' => 'Prix', 'en' => 'Price'],
        'amount' => ['fr' => 'Montant', 'en' => 'Amount'],
        'quantity' => ['fr' => 'Quantité', 'en' => 'Quantity'],
        'total' => ['fr' => 'Total', 'en' => 'Total'],
        'currency' => ['fr' => 'Devise', 'en' => 'Currency'],
        'weight' => ['fr' => 'Poids', 'en' => 'Weight'],
        'date' => ['fr' => 'Date', 'en' => 'Date'],
        'start_date' => ['fr' => 'Date de début', 'en' => 'Start date'],
        'end_date' => ['fr' => 'Date de fin', 'en' => 'End date'],
        'birth_date' => ['fr' => 'Date de naissance', 'en' => 'Birth date'],
        'published_at' => ['fr' => 'Publié le', 'en' => 'Published at'],
        'created_at' => ['fr' => 'Créé le', 'en' => 'Created at'],
        'updated_at' => ['fr' => 'Modifié le', 'en' => 'Updated at'],
        'deleted_at' => ['fr' => 'Supprimé le', 'en' => 'Deleted at'],
        'is_active' => ['fr' => 'Actif', 'en' => 'Active'],
        'active' => ['fr' => 'Actif', 'en' => 'Active'],
        'enabled' => ['fr' => 'Activé', 'en' => 'Enabled'],
        'position' => ['fr' => 'Position', 'en' => 'Position'],
        'order' => ['fr' => 'Ordre', 'en' => 'Order'],
        'color' => ['fr' => 'Couleur', 'en' => 'Color'],
        'image' => ['fr' => 'Image', 'en' => 'Image'],
        'photo' => ['fr' => 'Photo', 'en' => 'Photo'],
        'file' => ['fr' => 'Fichier', 'en' => 'File'],
        'user_id' => ['fr' => 'Utilisateur', 'en' => 'User'],
        'owner_id' => ['fr' => 'Propriétaire', 'en' => 'Owner'],
        'author_id' => ['fr' => 'Auteur', 'en' => 'Author'],
        'parent_id' => ['fr' => 'Parent', 'en' => 'Parent'],
        'password' => ['fr' => 'Mot de passe', 'en' => 'Password'],
        'slug' => ['fr' => 'Identifiant d’URL', 'en' => 'Slug'],
    ];

    public static function field(string $field, string $locale): string
    {
        $key = Str::snake($field);
        if (isset(self::WORDS[$key][$locale])) {
            return self::WORDS[$key][$locale];
        }
        // Clé étrangère : libellé de l'entité liée ("category_id" → "Catégorie").
        if (str_ends_with($key, '_id') && isset(self::WORDS[substr($key, 0, -3)][$locale])) {
            return self::WORDS[substr($key, 0, -3)][$locale];
        }
        $words = str_ends_with($key, '_id') ? substr($key, 0, -3) : $key;
        // Booléens "is_xxx" / "has_xxx" : le préfixe n'apporte rien dans un libellé.
        $words = (string) preg_replace('/^(is|has)_(?=.)/', '', $words);

        return Str::ucfirst(str_replace('_', ' ', $words));
    }

    public static function entity(string $model, string $locale, bool $plural = false): string
    {
        // Les noms de modèles sont en anglais : leur pluriel anglais reste le plus juste sans traduction (IA).
        $words = Str::lower(Str::headline($plural ? Str::pluralStudly($model) : $model));

        return Str::ucfirst($words);
    }

    /**
     * Charge utile i18n complète pour une entité (même structure que la réponse IA).
     *
     * @param  list<string>  $fields
     * @return array<string, array{title:string,titlePlural:string,menuTitle:string,menuDescription:string,field:array<string,string>}>
     */
    public static function translations(string $model, array $fields, array $locales): array
    {
        $out = [];
        foreach ($locales as $locale) {
            $plural = self::entity($model, $locale, true);
            $out[$locale] = [
                'title' => self::entity($model, $locale),
                'titlePlural' => $plural,
                'menuTitle' => $plural,
                'menuDescription' => $locale === 'fr' ? "Gérer les {$plural}" : "Manage {$plural}",
                'field' => array_combine(
                    array_map(static fn (string $f) => Str::camel($f), $fields),
                    array_map(static fn (string $f) => self::field($f, $locale), $fields),
                ) ?: [],
            ];
        }

        return $out;
    }
}
