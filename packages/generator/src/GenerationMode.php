<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator;

/**
 * Énumération des modes de génération disponibles.
 *
 * Chaque mode produit un élément précis ; les modes "bundle" regroupent une stratégie complète.
 */
enum GenerationMode: string
{
    // 🔧 Backend
    case BACKEND_MIGRATION = 'backend_migration';
    case BACKEND_MODEL = 'backend_model';
    case BACKEND_REQUEST = 'backend_request';
    case BACKEND_CONTROLLER = 'backend_controller';
    case BACKEND_ROUTE = 'backend_route';
    case BACKEND_PERMISSIONS = 'backend_permissions';
    case BACKEND_FACTORY = 'backend_factory';
    case BACKEND_TEST = 'backend_test';

    // 🎨 Frontend
    case FRONTEND_TYPES = 'frontend_types';
    case FRONTEND_API = 'frontend_api';
    case FRONTEND_INDEX = 'frontend_index';
    case FRONTEND_ADDOREDIT = 'frontend_addoredit';
    case FRONTEND_MENU = 'frontend_menu';
    case FRONTEND_I18N = 'frontend_i18n';

    // 📦 Modes bundle
    case BACKEND_FULL = 'backend_full';
    case FRONTEND_FULL = 'frontend_full';
    case FULLSTACK = 'fullstack';

    /** Raccourcis acceptés par --modes= */
    public const ALIASES = [
        'migration' => self::BACKEND_MIGRATION,
        'model' => self::BACKEND_MODEL,
        'request' => self::BACKEND_REQUEST,
        'controller' => self::BACKEND_CONTROLLER,
        'route' => self::BACKEND_ROUTE,
        'permissions' => self::BACKEND_PERMISSIONS,
        'factory' => self::BACKEND_FACTORY,
        'test' => self::BACKEND_TEST,
        'types' => self::FRONTEND_TYPES,
        'api' => self::FRONTEND_API,
        'index' => self::FRONTEND_INDEX,
        'addoredit' => self::FRONTEND_ADDOREDIT,
        'form' => self::FRONTEND_ADDOREDIT,
        'menu' => self::FRONTEND_MENU,
        'i18n' => self::FRONTEND_I18N,
        'lang' => self::FRONTEND_I18N,
    ];

    /**
     * Modes d'une stratégie : 'backend', 'frontend' ou 'fullstack'.
     *
     * @return array<GenerationMode>
     */
    public static function forStrategy(string $strategy): array
    {
        return match (strtolower($strategy)) {
            'backend' => self::backendModes(),
            'frontend' => self::frontendModes(),
            'fullstack' => self::expandFullstack(),
            default => [],
        };
    }

    /** @return array<GenerationMode> */
    public static function backendModes(): array
    {
        return [
            self::BACKEND_MIGRATION,
            self::BACKEND_MODEL,
            self::BACKEND_REQUEST,
            self::BACKEND_CONTROLLER,
            self::BACKEND_ROUTE,
            self::BACKEND_PERMISSIONS,
            self::BACKEND_FACTORY,
            self::BACKEND_TEST,
        ];
    }

    /** @return array<GenerationMode> */
    public static function frontendModes(): array
    {
        return [
            self::FRONTEND_TYPES,
            self::FRONTEND_API,
            self::FRONTEND_INDEX,
            self::FRONTEND_ADDOREDIT,
            self::FRONTEND_MENU,
            self::FRONTEND_I18N,
        ];
    }

    /**
     * Les modes exécutés pour FULLSTACK.
     *
     * @return array<self>
     */
    public static function expandFullstack(): array
    {
        return [...self::backendModes(), ...self::frontendModes()];
    }

    /**
     * Convertit une liste CSV d'alias ("model,controller,i18n") ; les alias inconnus sont renvoyés à part.
     *
     * @return array{modes: array<self>, unknown: list<string>}
     */
    public static function parseAliases(string $csv): array
    {
        $modes = [];
        $unknown = [];
        foreach (array_filter(array_map(static fn (string $a) => strtolower(trim($a)), explode(',', $csv))) as $alias) {
            if (isset(self::ALIASES[$alias])) {
                $modes[self::ALIASES[$alias]->value] = self::ALIASES[$alias];
            } else {
                $unknown[] = $alias;
            }
        }

        return ['modes' => array_values($modes), 'unknown' => $unknown];
    }

    /**
     * Ordre d'exécution : chaque mode ne dépend que de modes de rang inférieur.
     */
    public function order(): int
    {
        return match ($this) {
            self::BACKEND_MIGRATION => 1,
            self::BACKEND_MODEL => 2,
            self::BACKEND_REQUEST => 3,
            self::BACKEND_CONTROLLER => 4,
            self::BACKEND_ROUTE => 5,
            self::BACKEND_PERMISSIONS => 6,
            self::BACKEND_FACTORY => 7,
            self::BACKEND_TEST => 8,
            self::FRONTEND_TYPES => 9,
            self::FRONTEND_API => 10,
            self::FRONTEND_INDEX => 11,
            self::FRONTEND_ADDOREDIT => 12,
            self::FRONTEND_MENU => 13,
            self::FRONTEND_I18N => 14,
            default => 99,
        };
    }

    /**
     * Retourne le label d'affichage pour l'utilisateur.
     */
    public function label(): string
    {
        return match ($this) {
            self::BACKEND_MIGRATION => '🗄️ Migration (Backend)',
            self::BACKEND_MODEL => '🏗️ Modèle (Backend)',
            self::BACKEND_REQUEST => '🔍 FormRequest (Backend)',
            self::BACKEND_CONTROLLER => '🎮 Contrôleur (Backend)',
            self::BACKEND_ROUTE => '🛣️ Routes API (Backend)',
            self::BACKEND_PERMISSIONS => '🔐 Permissions/CASL (Backend)',
            self::BACKEND_FACTORY => '🧪 Factory + seeder de démo (Backend)',
            self::BACKEND_TEST => '✅ Test de l’API (Backend)',
            self::FRONTEND_TYPES => '📝 Types TypeScript (Frontend)',
            self::FRONTEND_API => '🌐 Client API (Frontend)',
            self::FRONTEND_INDEX => '📋 Page Index (Frontend)',
            self::FRONTEND_ADDOREDIT => '📝 Page AddOrEdit (Frontend)',
            self::FRONTEND_MENU => '🧭 Entrée de menu (Frontend)',
            self::FRONTEND_I18N => '🌍 Traductions (Frontend)',
            self::BACKEND_FULL => '🔧 Backend complet',
            self::FRONTEND_FULL => '🎨 Frontend complet',
            self::FULLSTACK => '📦 Stack complet (Backend + Frontend)',
        };
    }

    /**
     * Retourne un groupe logique (pour checker si déjà généré).
     */
    public function group(): string
    {
        return match ($this) {
            self::BACKEND_MIGRATION, self::BACKEND_MODEL, self::BACKEND_REQUEST, self::BACKEND_CONTROLLER,
            self::BACKEND_ROUTE, self::BACKEND_PERMISSIONS, self::BACKEND_FACTORY, self::BACKEND_TEST, self::BACKEND_FULL => 'backend',

            self::FRONTEND_TYPES, self::FRONTEND_API, self::FRONTEND_INDEX,
            self::FRONTEND_ADDOREDIT, self::FRONTEND_MENU, self::FRONTEND_I18N, self::FRONTEND_FULL => 'frontend',

            self::FULLSTACK => 'fullstack',
        };
    }
}
