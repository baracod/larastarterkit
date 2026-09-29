# Documents

Le socle partagé expose `/api/v1/documents` : politiques, réglages administratifs, liste des documents d'un dossier, import, aperçu, génération et téléchargement. Les fichiers restent privés. Chaque accès vérifie les droits sur le dossier ; les documents remplacés restent conservés.

## Intégrer un dossier

Implémentez `Baracod\Larastarterkit\Core\Documents\Contracts\DocumentContext` :

- `resolve(int $id)` retourne le modèle du dossier ou une 404.
- `authorize(User $user, Model $record, string $action)` contrôle `browse` et `edit`.
- `documents()` définit les titres, vues Blade autorisées, versions, champs et signataires.
- `context(Model $record)` retourne la référence et les sections figées dans le PDF.

Enregistrez votre contexte dans le `boot()` du provider consommateur en utilisant `Baracod\\Larastarterkit\\Core\\Documents\\Services\\DocumentRegistry` :

```php
app(DocumentRegistry::class)->register('example', new ExampleDocumentContext);
```

Exemple de définition neutre :

```php
return ['summary' => [
    'title' => 'Summary',
    'view' => 'documents::generic',
    'version' => '1',
    'fields' => ['note' => ['string', false]],
    'signatories' => [],
]];
```

Le contexte peut retourner `['reference' => 'EX-1', 'sections' => [['title' => 'Details', 'rows' => [['key' => 'Name', 'value' => 'Example']]]]]`.

Les modules consommateurs contrôlent la sélection des données et les autorisations. Aucun nom de classe, chemin de fichier ou vue Blade arbitraire n'est accepté depuis le navigateur. Sans contexte enregistré, la configuration affiche un état vide.

## API

- `GET /policies` : modes effectifs.
- `GET|PUT /settings` : configuration administrative des étapes et documents.
- `GET /{type}/{record}` : exigences, contexte et versions.
- `POST /{type}/{record}` : import multipart (`document_key`, `reference`, `issuer`, `issued_at`, `file`).
- `POST /{type}/{record}/preview?format=json` : aperçu et jeton de confirmation.
- `POST /{type}/{record}/generate` : génération avec les mêmes champs et `preview_token`.
- `GET /{type}/{record}/{document}` : téléchargement autorisé avec contrôle SHA-256.

Les modes sont `upload`, `generate` et `both`. Le défaut est `upload`. Les générations portent la date du jour ; les aperçus expirent après 30 minutes. Une modification des données invalide la confirmation. `supersedes_id` permet de détecter une version devenue obsolète.

Les services PHP se trouvent dans `app/Documents` et les composants réutilisables dans `resources/ts/components`. Les réglages sont disponibles dans Admin → Paramètres → Documents. La migration est portée par Admin, avec son nom historique conservé. Documents ne figure pas dans le catalogue des modules et ne peut pas être activé ou désactivé séparément.
