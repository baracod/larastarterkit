<?php

namespace Modules\Documentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Documentation\Models\SiteSetting;

class SiteSettingsController extends Controller
{
    public function show()
    {
        return response()->json(SiteSetting::current());
    }

    public function update(Request $request)
    {
        // Only local paths/fragments and explicit HTTP(S) URLs are allowed.
        $link = ['required', 'string', 'max:500', function ($attribute, $value, $fail) {
            if (preg_match('/[\x00-\x20\\\\]/', $value) || ! preg_match('~^(?:/(?!/)|\#|https?://[^/]+)~i', $value)) {
                $fail('Utilisez un chemin commençant par / ou une URL HTTP(S).');
            }
        }];
        $data = $request->validate([
            'version' => 'required|integer|min:1',
            'content' => 'required|array:title,hero_name,hero_text,tagline,description,locale,logo_image_id,hero_image_id,actions,features,nav,github_url,footer,theme',
            'content.title' => 'required|string|max:100',
            'content.hero_name' => 'required|string|max:100',
            'content.hero_text' => 'required|string|max:200',
            'content.tagline' => 'nullable|string|max:500',
            'content.description' => 'nullable|string|max:300',
            'content.locale' => ['sometimes', Rule::in(config('documentation.locales'))],
            'content.logo_image_id' => 'nullable|integer|exists:documentation_images,id',
            'content.hero_image_id' => 'nullable|integer|exists:documentation_images,id',
            'content.actions' => 'present|array|max:4',
            'content.actions.*' => 'array:text,link,theme',
            'content.actions.*.text' => 'required|string|max:60',
            'content.actions.*.link' => $link,
            'content.actions.*.theme' => 'required|in:brand,alt',
            'content.features' => 'present|array|max:12',
            'content.features.*' => 'array:icon,title,details,link',
            'content.features.*.icon' => 'nullable|string|max:12',
            'content.features.*.title' => 'required|string|max:100',
            'content.features.*.details' => 'required|string|max:500',
            'content.features.*.link' => ['nullable', ...array_slice($link, 1)],
            'content.nav' => 'present|array|max:6',
            'content.nav.*' => 'array:text,link',
            'content.nav.*.text' => 'required|string|max:60',
            'content.nav.*.link' => $link,
            'content.github_url' => ['nullable', 'url:http,https', 'max:500'],
            'content.footer' => 'nullable|string|max:500',
            'content.theme' => 'sometimes|array:brand_color,hero_from,hero_to,appearance,code_theme,layout',
            'content.theme.brand_color' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'content.theme.hero_from' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'content.theme.hero_to' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'content.theme.appearance' => ['sometimes', Rule::in(SiteSetting::APPEARANCES)],
            'content.theme.code_theme' => ['sometimes', Rule::in(SiteSetting::CODE_THEMES)],
            'content.theme.layout' => ['sometimes', Rule::in(SiteSetting::LAYOUTS)],
        ]);
        SiteSetting::current();

        return DB::transaction(function () use ($data) {
            $settings = SiteSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            $settings->content = SiteSetting::normalize($settings->content ?? []);
            abort_if($settings->version !== $data['version'], 409, 'Les réglages ont été modifiés. Rechargez avant de continuer.');
            $content = [...$settings->content, ...$data['content']];
            $content['theme'] = [...$settings->content['theme'], ...($data['content']['theme'] ?? [])];
            $settings->update(['content' => SiteSetting::normalize($content), 'version' => $settings->version + 1]);

            return $settings;
        });
    }
}
