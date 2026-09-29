<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Ai;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Point d'entrée unique de l'IA du générateur.
 *
 * - L'IA est facultative : désactivée globalement, par fonctionnalité, par option `--no-ai`
 *   ou simplement faute de fournisseur configuré.
 * - Tout échec (réseau, quota, réponse invalide) bascule silencieusement sur la règle déterministe.
 */
final class GeneratorAi
{
    public const FEATURES = ['translations', 'design', 'validation', 'fake_data'];

    /** Fournisseurs essayés, dans l'ordre, quand aucun n'est imposé (ollama et services locaux doivent être choisis explicitement). */
    private const AUTO_PROVIDERS = ['anthropic', 'openai', 'gemini', 'mistral', 'groq', 'deepseek', 'xai', 'azure'];

    private bool $disabledAtRuntime = false;

    /** @var list<array{feature:string,fromAi:bool,reason:?string}> */
    private array $journal = [];

    /**
     * Désactive l'IA pour le reste de l'exécution (option --no-ai).
     */
    public function disable(): void
    {
        $this->disabledAtRuntime = true;
    }

    public function enable(): void
    {
        $this->disabledAtRuntime = false;
    }

    /**
     * L'IA est-elle utilisable pour cette fonctionnalité ?
     */
    public function enabled(?string $feature = null): bool
    {
        return $this->unavailableReason($feature) === null;
    }

    /**
     * Raison pour laquelle l'IA n'est pas utilisée (null si elle l'est).
     */
    public function unavailableReason(?string $feature = null): ?string
    {
        if ($this->disabledAtRuntime) {
            return 'désactivée pour cette exécution (--no-ai)';
        }
        if (! config('generator.ai.enabled', true)) {
            return 'désactivée (GENERATOR_AI_ENABLED=false)';
        }
        if ($feature !== null && ! config("generator.ai.features.{$feature}", true)) {
            return "fonctionnalité « {$feature} » désactivée";
        }
        if (! class_exists(\Laravel\Ai\Ai::class)) {
            return 'Laravel AI SDK absent (composer require laravel/ai)';
        }
        if ($this->provider() === null) {
            return 'aucun fournisseur IA configuré (clé API manquante)';
        }

        return null;
    }

    /**
     * Fournisseur retenu : celui imposé par la configuration, sinon le défaut de laravel/ai s'il a une clé,
     * sinon le premier fournisseur courant disposant d'une clé.
     */
    public function provider(): ?string
    {
        $forced = config('generator.ai.provider');
        if (is_string($forced) && $forced !== '') {
            return $this->providerConfigured($forced, explicit: true) ? $forced : null;
        }

        $default = config('ai.default');
        if (is_string($default) && $this->providerConfigured($default)) {
            return $default;
        }

        foreach (self::AUTO_PROVIDERS as $provider) {
            if ($this->providerConfigured($provider)) {
                return $provider;
            }
        }

        return null;
    }

    public function model(): ?string
    {
        $model = config('generator.ai.model');

        return is_string($model) && $model !== '' ? $model : null;
    }

    public function timeout(): int
    {
        return max(5, (int) config('generator.ai.timeout', 90));
    }

    /**
     * Interroge un agent structuré avec le fournisseur, le modèle et le délai configurés.
     *
     * @return array<string, mixed>
     */
    public function ask(\Laravel\Ai\Contracts\Agent $agent, string $prompt): array
    {
        $response = $agent->prompt($prompt, provider: $this->provider(), model: $this->model(), timeout: $this->timeout());

        return $response instanceof \Illuminate\Contracts\Support\Arrayable ? $response->toArray() : [];
    }

    /**
     * Exécute la tâche IA si possible, sinon (ou en cas d'échec) la règle déterministe.
     *
     * @template T
     *
     * @param  callable(): T  $ai
     * @param  callable(): T  $fallback
     * @return AiOutcome<T>
     */
    public function attempt(string $feature, callable $ai, callable $fallback): AiOutcome
    {
        $reason = $this->unavailableReason($feature);
        if ($reason === null) {
            try {
                $outcome = new AiOutcome($ai(), true);
                $this->journal[] = ['feature' => $feature, 'fromAi' => true, 'reason' => null];

                return $outcome;
            } catch (Throwable $e) {
                $reason = 'échec de l\'appel IA : '.mb_substr($e->getMessage(), 0, 300);
                Log::warning("[generator] IA « {$feature} » indisponible, règle déterministe utilisée.", ['exception' => $e]);
            }
        }
        $this->journal[] = ['feature' => $feature, 'fromAi' => false, 'reason' => $reason];

        return new AiOutcome($fallback(), false, $reason);
    }

    /**
     * Historique des décisions IA/règles de l'exécution courante (affiché en fin de génération).
     *
     * @return list<array{feature:string,fromAi:bool,reason:?string}>
     */
    public function journal(): array
    {
        return $this->journal;
    }

    /**
     * État lisible pour la commande larastarterkit:ai.
     *
     * @return array<string, string>
     */
    public function status(): array
    {
        $status = [
            'Assistance IA' => $this->enabled() ? 'active' : 'inactive — '.$this->unavailableReason(),
            'Fournisseur' => $this->provider() ?? '—',
            'Modèle' => $this->model() ?? 'défaut du fournisseur',
            'Délai max.' => $this->timeout().' s',
        ];
        foreach (self::FEATURES as $feature) {
            $status["Fonction « {$feature} »"] = $this->enabled($feature) ? 'IA' : 'règles déterministes';
        }

        return $status;
    }

    private function providerConfigured(string $provider, bool $explicit = false): bool
    {
        $config = config("ai.providers.{$provider}");
        if (! is_array($config)) {
            return false;
        }
        $driver = $config['driver'] ?? $provider;
        // Serveurs locaux ou identifiants ambiants : acceptés uniquement s'ils sont choisis explicitement.
        if (in_array($driver, ['ollama', 'openai-compatible', 'bedrock'], true)) {
            return $explicit && ($driver !== 'openai-compatible' || ! empty($config['url']));
        }

        return is_string($config['key'] ?? null) && trim($config['key']) !== '';
    }
}
