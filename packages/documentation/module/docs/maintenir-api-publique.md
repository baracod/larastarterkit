# Maintenir : API publique et versions

Une application ne modifie jamais le socle : elle dépend de ses points d'extension. Les casser sans prévenir casse toutes les applications. Cette page fixe ce qui est public et comment le faire évoluer.

## Ce qui est public

| Domaine | Éléments |
| --- | --- |
| Frontend | Clés de `starter.ts` (`menus`, `catalog`, `slots`, `redirects`, `pages`, `configure`), types de `@/extension`, noms de slots et leur contexte, noms de redirections, noms de routes du socle, convention `resources/ts/overrides/<racine>/<chemin>` |
| Backend | `Larastarterkit::*`, contrats et classes documentés, noms de classes des contrôleurs et services liables, alias de middlewares, événements, clés de configuration, clés de traduction (`auth::messages.*`, `admin::messages.*`…) |
| Outillage | Commandes `larastarterkit:*`, `auth:super-admin:*`, leurs options, le format de `module.json` |

Tout le reste est interne : le contenu des composants, des pages et des méthodes, les fichiers non documentés. Une application qui surcharge un fichier interne (`resources/ts/overrides`) accepte de le revoir à chaque version.

## Règles de version

- **Semver strict** : aucun changement cassant d'un élément public hors version majeure.
- **Neutralité par défaut** : un point d'extension non utilisé ne change rien ; ses valeurs par défaut reproduisent le comportement antérieur.
- **Dépréciation** : un élément public est d'abord déprécié (avertissement en développement et dans le CHANGELOG) pendant au moins une version mineure, puis retiré seulement dans une version majeure.
- **Documentation** : toute évolution d'un élément public met à jour [Points d'extension](./extension) et le CHANGELOG.

## Concevoir un point d'extension

Choisissez le mécanisme le plus léger qui couvre le besoin :

| Besoin | Mécanisme |
| --- | --- |
| Ajouter un élément dans un écran | Slot (`StarterSlot`) |
| Choisir une destination | Redirection (`redirects`) |
| Remplacer un écran entier | `pages` ou layout de même nom |
| Remplacer un comportement PHP | Contrat lié par défaut avec `bindIf`, ou classe résolue par le conteneur |
| Réagir à une action | Événement |
| Régler une valeur | Clé de configuration ou paramètre Admin |
| Changer un texte | Clé de traduction |

Liste de contrôle avant de livrer :

1. Le comportement par défaut est strictement identique à avant.
2. L'élément est typé (TypeScript) ou documenté par une interface (PHP).
3. Un test prouve à la fois le comportement par défaut et la personnalisation.
4. Le cœur ne référence aucun module métier ou optionnel pour le fournir.
5. La sécurité reste côté serveur : une personnalisation frontend ne contourne aucune autorisation.
6. La page [Points d'extension](./extension) et le CHANGELOG sont à jour.

## Modèle à reproduire : contrat et implémentation par défaut

```php
// Dans le module qui expose le besoin
interface AvailabilityProvider
{
    public function isAvailable(int $id): bool;
}

// Son provider lie une implémentation neutre, remplaçable
$this->app->bindIf(AvailabilityProvider::class, UnintegratedAvailabilityProvider::class);

// Le module ou l'application qui fournit la vraie implémentation
$this->app->bind(AvailabilityProvider::class, CirculationAvailabilityProvider::class);
```

Le module consommateur fonctionne seul ; le fournisseur se branche sans que l'un importe l'autre.
