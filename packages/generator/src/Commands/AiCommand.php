<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Commands;

use Baracod\Larastarterkit\Generator\Ai\Assistant;
use Baracod\Larastarterkit\Generator\Ai\GeneratorAi;
use Illuminate\Console\Command;

use function Laravel\Prompts\table;

/**
 * État de l'assistance IA du générateur (Laravel AI SDK) et test d'appel facultatif.
 */
final class AiCommand extends Command
{
    protected $signature = 'larastarterkit:ai {--test : Effectue un appel réel (libellés d\'une entité d\'exemple)}';

    /** Ancien nom, conservé pour les scripts existants. */
    protected $aliases = ['generator:ai'];

    protected $description = 'Affiche la configuration IA du générateur et teste le fournisseur';

    public function handle(GeneratorAi $ai, Assistant $assistant): int
    {
        table(['Paramètre', 'Valeur'], array_map(null, array_keys($ai->status()), array_values($ai->status())));

        if (! $ai->enabled()) {
            $this->line('');
            $this->line('Le générateur fonctionne sans IA (règles déterministes). Pour l\'activer :');
            $this->line('  1. ajoutez une clé dans .env, par ex. ANTHROPIC_API_KEY=…, OPENAI_API_KEY=… ou GEMINI_API_KEY=…');
            $this->line('  2. facultatif : GENERATOR_AI_PROVIDER=anthropic et GENERATOR_AI_MODEL=… (sinon choix automatique)');
            $this->line('  3. php artisan config:clear --no-interaction');

            return self::SUCCESS;
        }

        if (! $this->option('test')) {
            $this->line('Ajoutez --test pour vérifier la connexion au fournisseur.');

            return self::SUCCESS;
        }

        $started = microtime(true);
        $outcome = $assistant->translations('SupplierInvoice', 'Purchasing', ['reference', 'amount', 'due_date', 'is_paid'], ['fr', 'en']);
        $elapsed = round(microtime(true) - $started, 1);

        if (! $outcome->fromAi) {
            $this->error("Échec de l'appel IA ({$elapsed} s) : {$outcome->reason}");

            return self::FAILURE;
        }
        $this->info("Réponse de {$ai->provider()} en {$elapsed} s :");
        $this->line((string) json_encode($outcome->value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
