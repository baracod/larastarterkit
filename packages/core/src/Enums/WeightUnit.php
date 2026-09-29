<?php

namespace Baracod\Larastarterkit\Core\Enums;

enum WeightUnit: string
{
    // --- Système International (SI) ---
    case MILLIGRAM = 'mg';  // Pour l'analyse labo / chimie
    case GRAM = 'g';   // Standard production artisanale
    case KILOGRAM = 'kg';  // Standard transport / export

    // --- Standards Marché Or (Non-SI mais critiques) ---
    case TROY_OUNCE = 'oz'; // Once Troy (≈ 31.10g) - Référence Bourse (LBMA)
    case TOLA = 'tola'; // Utilisé souvent en Asie/Afrique de l'Est (≈ 11.66g)

    //     ALTER TABLE `extraction_daily_productions`
    // ADD COLUMN `weight_unit` ENUM('mg', 'kg', 'g', 'tola', 'oz') NOT NULL DEFAULT 'mg';

    public function label(): string
    {
        return match ($this) {
            self::MILLIGRAM => 'Milligramme (mg)',
            self::GRAM => 'Gramme (g)',
            self::KILOGRAM => 'Kilogramme (kg)',
            self::TROY_OUNCE => 'Once Troy (oz t)',
            self::TOLA => 'Tola',
        };
    }

    /**
     * Facteur de conversion vers le Gramme (Unité de référence STARTER)
     * Utile pour normaliser les stocks en DB.
     */
    public function toGrams(float $weight): float
    {
        return match ($this) {
            self::MILLIGRAM => $weight * 0.001,
            self::GRAM => $weight,
            self::KILOGRAM => $weight * 1000,
            self::TROY_OUNCE => $weight * 31.1034768, // Précision ISO
            self::TOLA => $weight * 11.6638038,
        };
    }

    /**
     * Facteur de conversion depuis le Gramme
     */
    public static function fromGrams(float $grams, self $targetUnit): float
    {
        return match ($targetUnit) {
            self::MILLIGRAM => $grams / 0.001,
            self::GRAM => $grams,
            self::KILOGRAM => $grams / 1000,
            self::TROY_OUNCE => $grams / 31.1034768,
            self::TOLA => $grams / 11.6638038,
        };
    }

    /**
     * Convertit n'importe quelle valeur+unité en grammes, sans lever
     * d'erreur si l'unité est inconnue (fallback: grammes).
     */
    public static function toGramsAny(float|int|string|null $weight, string|self|null $unit): float
    {
        $value = (float) ($weight ?? 0);

        if ($unit instanceof self) {
            return $unit->toGrams($value);
        }

        $key = strtolower(trim((string) ($unit ?? 'g')));

        $case = self::tryFrom($key);

        if ($case !== null) {
            return $case->toGrams($value);
        }

        // Tolérance : libellés fréquents non standards
        return match ($key) {
            'grammes', 'gramme', 'gram', 'grams' => self::GRAM->toGrams($value),
            'kilo', 'kilos', 'kilogramme', 'kilogrammes' => self::KILOGRAM->toGrams($value),
            'milligramme', 'milligrammes' => self::MILLIGRAM->toGrams($value),
            'once', 'onces', 'ounce', 'ounces' => self::TROY_OUNCE->toGrams($value),
            default => $value, // on assume grammes si unité inconnue
        };
    }
}
