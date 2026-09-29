<?php

namespace Baracod\Larastarterkit\Core\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait GeneratesCode
{
    protected static function bootGeneratesCode(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->code)) {
                $model->code = static::generateCode($model);
            }
        });
    }

    protected static function generateCode(Model $model): string
    {
        $prefix = static::codePrefix($model);
        $lastRecord = static::query()
            ->where('code', 'LIKE', "{$prefix}-%")
            ->orderByDesc('id')
            ->first();

        $sequence = 1;

        if ($lastRecord?->code) {
            $lastSegment = Str::afterLast($lastRecord->code, '-');

            if (is_numeric($lastSegment)) {
                $sequence = (int) $lastSegment + 1;
            }
        }

        return $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    protected static function codePrefix(Model $model): string
    {
        $base = filled($model->name ?? null) ? (string) $model->name : class_basename($model);
        $letters = Str::upper(Str::slug($base, ''));

        return Str::limit($letters, 6, '') ?: 'CODE';
    }
}
