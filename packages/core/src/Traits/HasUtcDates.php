<?php

namespace Baracod\Larastarterkit\Core\Traits;

use Carbon\CarbonImmutable;
use DateTimeInterface;

trait HasUtcDates
{
    /**
     * Intercepte l'écriture des attributs et force l'UTC pour ceux listés.
     */
    public function setAttribute($key, $value)
    {
        if ($this->isUtcDateAttribute($key)) {
            $value = $this->castToUtcCarbon($value); // CarbonImmutable|null
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Sérialise les dates pour toArray()/toJson().
     * - Fuseau JSON: 'UTC' par défaut (surchargable via propriété/constante, cf. getJsonDatesTimezone()).
     * - Format: on respecte le getDateFormat() du modèle (Y-m-d H:i:s par défaut Eloquent).
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return CarbonImmutable::parse($date)
            ->setTimezone($this->getJsonDatesTimezone())
            ->format($this->getDateFormat());
    }

    /**
     * Helper d'affichage local (sans impacter la valeur en base/JSON).
     */
    public function asLocal(string $attribute, string $tz = 'Africa/Lubumbashi', string $format = 'Y-m-d H:i:s'): ?string
    {
        $value = $this->getAttribute($attribute);

        if (! $value instanceof DateTimeInterface) {
            return null;
        }

        return CarbonImmutable::parse($value)->setTimezone($tz)->format($format);
    }

    /**
     * Vrai si l'attribut fait partie des dates à forcer en UTC.
     */
    protected function isUtcDateAttribute(string $key): bool
    {
        return in_array($key, $this->getUtcDateAttributes(), true);
    }

    /**
     * Retourne la liste des attributs dates à forcer en UTC.
     * Ordre de priorité:
     *   1) constante de classe: const UTC_DATE_ATTRIBUTES = [...]
     *   2) propriété du modèle: protected array $utcDateAttributes = [...]
     *   3) []
     */
    protected function getUtcDateAttributes(): array
    {
        // 1) Constante (recommandé si tu veux éviter toute collision)
        if (defined(static::class.'::UTC_DATE_ATTRIBUTES')) {
            /** @phpstan-ignore-next-line */
            $const = constant(static::class.'::UTC_DATE_ATTRIBUTES');

            return is_array($const) ? $const : [];
        }

        // 2) Propriété du modèle
        if (property_exists($this, 'utcDateAttributes') && is_array($this->utcDateAttributes)) {
            return $this->utcDateAttributes;
        }

        // 3) défaut
        return [];
    }

    /**
     * Fuseau utilisé pour la sérialisation JSON. Par défaut: UTC.
     * Tu peux:
     *  - définir une constante: const JSON_DATES_TZ = 'Africa/Kinshasa';
     *  - ou une propriété: protected string $jsonDatesTimezone = 'Africa/Kinshasa';
     */
    protected function getJsonDatesTimezone(): string
    {
        if (defined(static::class.'::JSON_DATES_TZ')) {
            /** @phpstan-ignore-next-line */
            $const = constant(static::class.'::JSON_DATES_TZ');

            return is_string($const) && $const !== '' ? $const : 'UTC';
        }

        if (property_exists($this, 'jsonDatesTimezone') && is_string($this->jsonDatesTimezone) && $this->jsonDatesTimezone !== '') {
            /** @phpstan-ignore-next-line */
            return $this->jsonDatesTimezone;
        }

        return 'UTC';
    }

    /**
     * Normalise une valeur vers CarbonImmutable en UTC (ou null).
     * Accepte ISO-8601 (...Z), SQL, DateTime/Carbon, timestamp…
     */
    protected function castToUtcCarbon($value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc();
        }

        return CarbonImmutable::parse($value)->utc();
    }
}
