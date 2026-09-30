<?php

namespace Modules\Admin\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Admin\Models\Setting;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'theme' => ['light', 'Thème'],
            'language' => ['fr', 'Langue'],
            'timezone' => ['UTC', 'Fuseau horaire'],
        ];
        foreach ($defaults as $key => [$value, $label]) {
            $setting = Setting::query()->firstOrCreate(['type' => 'system', 'key' => $key, 'module' => null, 'user_id' => null], [
                'value' => $value, 'value_type' => 'string', 'label' => $label, 'input_type' => 'text', 'is_public' => true,
            ]);
            // Earlier releases stored the key as label: replace it without touching customized labels.
            if ($setting->label === $key) {
                $setting->update(['label' => $label]);
            }
        }
    }
}
