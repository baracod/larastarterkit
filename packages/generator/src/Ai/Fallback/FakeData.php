<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Ai\Fallback;

use Baracod\Larastarterkit\Generator\DefinitionFile\Enums\FieldType;
use Baracod\Larastarterkit\Generator\DefinitionFile\FieldDefinition;

/**
 * Données de démonstration des factories : choix d'un générateur Faker par champ.
 * L'IA ne choisit que parmi cette liste fermée : aucun code arbitraire n'est jamais écrit.
 */
final class FakeData
{
    /** Formateurs autorisés → expression PHP générée. */
    public const FORMATTERS = [
        'name' => 'fake()->name()',
        'firstName' => 'fake()->firstName()',
        'lastName' => 'fake()->lastName()',
        'company' => 'fake()->company()',
        'jobTitle' => 'fake()->jobTitle()',
        'word' => 'fake()->word()',
        'words' => 'fake()->words(3, true)',
        'sentence' => 'fake()->sentence()',
        'paragraph' => 'fake()->paragraph()',
        'text' => 'fake()->text(400)',
        'safeEmail' => 'fake()->unique()->safeEmail()',
        'phoneNumber' => 'fake()->phoneNumber()',
        'url' => 'fake()->url()',
        'slug' => 'fake()->unique()->slug()',
        'streetAddress' => 'fake()->streetAddress()',
        'city' => 'fake()->city()',
        'country' => 'fake()->country()',
        'postcode' => 'fake()->postcode()',
        'currencyCode' => 'fake()->currencyCode()',
        'colorName' => 'fake()->safeColorName()',
        'hexColor' => 'fake()->hexColor()',
        'ean13' => 'fake()->ean13()',
        'uuid' => 'fake()->uuid()',
        'code' => 'strtoupper(fake()->unique()->bothify(\'??-####\'))',
        'price' => 'fake()->randomFloat(2, 1, 1000)',
        'percentage' => 'fake()->numberBetween(0, 100)',
        'smallInteger' => 'fake()->numberBetween(1, 100)',
        'integer' => 'fake()->numberBetween(1, 10000)',
        'float' => 'fake()->randomFloat(2, 0, 10000)',
        'boolean' => 'fake()->boolean()',
        'pastDate' => 'fake()->dateTimeBetween(\'-1 year\')->format(\'Y-m-d\')',
        'futureDate' => 'fake()->dateTimeBetween(\'now\', \'+1 year\')->format(\'Y-m-d\')',
        'dateTime' => 'fake()->dateTimeBetween(\'-1 year\')',
        'json' => '[]',
        'ipv4' => 'fake()->ipv4()',
    ];

    /** Entités dont le champ "name" désigne une personne ou une organisation. */
    private const PERSON_ENTITIES = ['user', 'customer', 'client', 'employee', 'author', 'contact', 'member', 'person', 'student', 'teacher', 'agent', 'owner', 'patient'];

    private const ORGANIZATION_ENTITIES = ['company', 'supplier', 'vendor', 'organization', 'organisation', 'partner', 'provider', 'manufacturer', 'brand', 'agency'];

    public static function formatterFor(FieldDefinition $field, ?string $entity = null): string
    {
        $name = strtolower($field->name);
        $entityWord = strtolower((string) preg_replace('/.*(?=[A-Z][a-z]+$)/', '', (string) $entity));

        return match (true) {
            $field->type === FieldType::Boolean => 'boolean',
            $field->type === FieldType::Uuid || $name === 'uuid' => 'uuid',
            $field->type === FieldType::Json => 'json',
            $field->type === FieldType::DateTime => 'dateTime',
            $field->type === FieldType::Date => str_contains($name, 'end') || str_contains($name, 'due') || str_contains($name, 'expir') ? 'futureDate' : 'pastDate',
            $field->type === FieldType::Float => in_array($name, ['price', 'amount', 'total', 'cost'], true) ? 'price' : 'float',
            $field->type === FieldType::Integer => in_array($name, ['position', 'order', 'rank', 'quantity', 'stock'], true) ? 'smallInteger' : (str_contains($name, 'percent') ? 'percentage' : 'integer'),
            $field->type === FieldType::Text => in_array($name, ['description', 'content', 'body', 'notes'], true) ? 'paragraph' : 'text',
            str_contains($name, 'email') => 'safeEmail',
            str_contains($name, 'phone') || str_contains($name, 'mobile') => 'phoneNumber',
            $name === 'first_name' || $name === 'firstname' => 'firstName',
            $name === 'last_name' || $name === 'lastname' => 'lastName',
            $name === 'name' && in_array($entityWord, self::ORGANIZATION_ENTITIES, true) => 'company',
            $name === 'name' && ! in_array($entityWord, self::PERSON_ENTITIES, true) => 'words',
            in_array($name, ['name', 'full_name', 'author'], true) => 'name',
            in_array($name, ['company', 'organization', 'organisation'], true) => 'company',
            in_array($name, ['title', 'subject', 'label'], true) => 'sentence',
            in_array($name, ['url', 'website', 'link'], true) || str_ends_with($name, '_url') => 'url',
            $name === 'slug' => 'slug',
            in_array($name, ['code', 'reference', 'sku'], true) => 'code',
            str_contains($name, 'address') => 'streetAddress',
            $name === 'city' => 'city',
            $name === 'country' => 'country',
            in_array($name, ['postal_code', 'zip_code', 'postcode'], true) => 'postcode',
            $name === 'currency' => 'currencyCode',
            str_contains($name, 'color') || str_contains($name, 'colour') => 'hexColor',
            $name === 'job' || $name === 'job_title' => 'jobTitle',
            default => 'words',
        };
    }

    /**
     * Expression PHP pour un champ ; les énumérations utilisent toujours leurs propres valeurs.
     */
    public static function expression(FieldDefinition $field, ?string $formatter = null, ?string $entity = null): string
    {
        if ($field->type === FieldType::Enum && $field->enumValues !== []) {
            return 'fake()->randomElement(['.implode(', ', array_map(static fn (string $v) => var_export($v, true), $field->enumValues)).'])';
        }
        $formatter = $formatter !== null && isset(self::FORMATTERS[$formatter]) ? $formatter : self::formatterFor($field, $entity);
        $expression = self::FORMATTERS[$formatter];
        if ($field->type === FieldType::String && $field->length !== null && ! in_array($formatter, ['uuid', 'safeEmail', 'url', 'slug', 'code'], true)) {
            $expression = "mb_substr({$expression}, 0, {$field->length})";
        }
        if ($field->unique && ! str_contains($expression, 'unique()')) {
            $expression = str_replace('fake()->', 'fake()->unique()->', $expression);
        }

        return $expression;
    }
}
