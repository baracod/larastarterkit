<?php

namespace Modules\Documentation\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $table = 'documentation_site_settings';

    protected $guarded = [];

    protected $casts = ['content' => 'array', 'version' => 'integer'];

    public const APPEARANCES = ['auto', 'light', 'dark', 'force-dark', 'force-light'];

    public const CODE_THEMES = ['auto', 'dark', 'light'];

    public const LAYOUTS = ['default', 'wide'];

    public static function current(): self
    {
        $settings = static::firstOrCreate(['id' => 1], ['content' => static::defaults(), 'version' => 1]);
        // Rows saved before a setting existed still expose every key.
        $settings->content = static::normalize($settings->content ?? []);
        $settings->syncOriginalAttribute('content');

        return $settings;
    }

    public static function normalize(array $content): array
    {
        $defaults = static::defaults();
        $content = array_replace($defaults, array_intersect_key($content, $defaults));
        $content['theme'] = array_replace($defaults['theme'], array_intersect_key((array) ($content['theme'] ?? []), $defaults['theme']));

        return $content;
    }

    public static function defaults(): array
    {
        return [
            'title' => 'Documentation', 'hero_name' => 'Documentation',
            'hero_text' => 'Tout pour bien démarrer.',
            'tagline' => 'Guides, références et ressources pour vos projets.',
            'description' => 'Guides et références de la documentation.',
            'locale' => 'fr',
            'logo_image_id' => null, 'hero_image_id' => null,
            'actions' => [],
            'features' => [
                ['icon' => '📚', 'title' => 'Guides pratiques', 'details' => 'Retrouvez les étapes pour installer, configurer et utiliser votre application.', 'link' => '#catalogue'],
                ['icon' => '⚡', 'title' => 'Exemples de code', 'details' => 'Des exemples clairs et faciles à copier pour avancer rapidement.', 'link' => null],
                ['icon' => '🧩', 'title' => 'Schémas et ressources', 'details' => 'Explorez les concepts avec des illustrations et des diagrammes.', 'link' => null],
                ['icon' => '🚀', 'title' => 'Chaque version', 'details' => 'Consultez la documentation correspondant à votre édition.', 'link' => '#catalogue'],
            ], 'nav' => [],
            'github_url' => null, 'footer' => '',
            'theme' => [
                'brand_color' => '#696cff', 'hero_from' => '#50bdff', 'hero_to' => '#b932fa',
                'appearance' => 'auto', 'code_theme' => 'auto', 'layout' => 'default',
            ],
        ];
    }
}
