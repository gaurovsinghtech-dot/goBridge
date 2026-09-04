<?php

namespace App\Modules\AI\Services\Llm;

use App\Models\Workspace;
use App\Modules\AI\Models\AiProviderConfig;
use App\Modules\Integrations\Services\CredentialResolver;

class LlmManager
{
    /** Providers that support embeddings natively. */
    private const EMBED_CAPABLE = ['openai', 'gemini'];

    /** Resolve a provider for chat completions (all providers supported). */
    public static function forWorkspace(int $workspaceId): LlmProviderInterface
    {
        $config = AiProviderConfig::where('workspace_id', $workspaceId)
            ->where('enabled', true)
            ->orderByRaw("CASE provider WHEN 'openai' THEN 1 WHEN 'anthropic' THEN 2 WHEN 'gemini' THEN 3 ELSE 4 END")
            ->first();

        if ($config && ! empty($config->credentials['api_key'] ?? '')) {
            return static::build($config->provider, $config->credentials ?? [], [
                'chat' => $config->default_model_chat,
                'embed' => $config->default_model_embed,
            ]);
        }

        $workspace = app(Workspace::class)->find($workspaceId);
        foreach (['openai', 'anthropic', 'gemini'] as $provider) {
            $creds = CredentialResolver::for($workspace)->llm($provider);
            if ($creds) {
                return static::build($provider, $creds->toArray());
            }
        }

        throw new \RuntimeException('No AI provider configured for workspace '.$workspaceId);
    }

    /**
     * Resolve a provider for embeddings only.
     * Anthropic does not support embeddings — it is skipped automatically.
     * Falls back across OpenAI → Gemini in workspace config, then system defaults.
     */
    public static function forWorkspaceEmbed(int $workspaceId): LlmProviderInterface
    {
        // Workspace-level: prefer embed-capable providers, then fall back to any enabled one
        $configs = AiProviderConfig::where('workspace_id', $workspaceId)
            ->where('enabled', true)
            ->orderByRaw("CASE provider WHEN 'openai' THEN 1 WHEN 'gemini' THEN 2 WHEN 'anthropic' THEN 3 ELSE 4 END")
            ->get();

        foreach ($configs as $config) {
            if (! in_array($config->provider, self::EMBED_CAPABLE, true)) {
                continue;
            }
            if (empty($config->credentials['api_key'] ?? '')) {
                continue;
            }
            return static::build($config->provider, $config->credentials ?? [], [
                'chat' => $config->default_model_chat,
                'embed' => $config->default_model_embed,
            ]);
        }

        // System-level fallback (embed-capable only)
        $workspace = app(Workspace::class)->find($workspaceId);
        foreach (self::EMBED_CAPABLE as $provider) {
            $creds = CredentialResolver::for($workspace)->llm($provider);
            if ($creds) {
                return static::build($provider, $creds->toArray());
            }
        }

        throw new \RuntimeException(
            'No embedding-capable AI provider (OpenAI or Gemini) configured for workspace '.$workspaceId.
            '. Anthropic does not support embeddings.'
        );
    }

    public static function build(string $provider, array $creds, array $models = []): LlmProviderInterface
    {
        return match ($provider) {
            'openai' => new OpenAiProvider(
                $creds['api_key'] ?? '',
                $models['chat'] ?? 'gpt-4o-mini',
                $models['embed'] ?? 'text-embedding-3-small',
                $creds['organization_id'] ?? null,
            ),
            'anthropic' => new AnthropicProvider($creds['api_key'] ?? '', $models['chat'] ?? 'claude-3-haiku-20240307'),
            'gemini' => new GeminiProvider($creds['api_key'] ?? '', $models['chat'] ?? 'gemini-1.5-flash', $models['embed'] ?? 'text-embedding-004'),
            default => throw new \RuntimeException("Unknown LLM provider: {$provider}"),
        };
    }
}
