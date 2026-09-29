<?php

namespace Modules\Admin\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Admin\Models\Setting;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['theme' => 'light', 'language' => 'fr', 'timezone' => 'UTC'] as $key => $value) {
            Setting::query()->firstOrCreate(['type' => 'system', 'key' => $key, 'module' => null, 'user_id' => null], [
                'value' => $value, 'value_type' => 'string', 'label' => $key, 'input_type' => 'text', 'is_public' => true,
            ]);
        }
    }
}
