<?php
namespace ATA\Services;

use ATA\Contracts\Telegram\TelegramProviderInterface;
use ATA\Contracts\AI\AIProviderInterface;
use ATA\Contracts\AI\AIRequest;
use ATA\Contracts\AI\AIResult;
use ATA\Contracts\Log\LoggerInterface;

defined('ABSPATH') || exit;

class ContentService
{
    private TelegramProviderInterface $telegram;
    private AIProviderInterface $aiProvider;

    public function __construct(
        TelegramProviderInterface $telegram,
        AIProviderInterface $aiProvider
    ) {
        $this->telegram = $telegram;
        $this->aiProvider = $aiProvider;
    }

    /**
     * Generate AI content (POST /ai/generate).
     * Uses the AI provider configured and returns the result as draft.
     */
    public function generate(array $context = []): AIResult
    {
        $provider = \ATA\AI\AIProviderRegistry::default();
        if ($provider instanceof AIProviderInterface) {
            $request = new AIRequest(
                operations: ['generate'],
                config: $context['config'] ?? [],
                messages: [],
                context: $context
            );
            $result = $provider->generate($request);
            if ($result->success) {
                // Store draft automatically after generation.
                $this->storeDraft($result->content, $context);
            }
            return $result;
        }
        return AIResult::fail('no_provider', 'هیچ Provider AI تنظیم نشده.');
    }

    /**
     * Approve human-approved content and mark as approved.
     */
    public function approve(string $postId): bool
    {
        // Update post status via post meta or DB.
        $post = get_post($postId);
        if (!$post) {
            return false;
        }
        return update_post_status($postId, 'draft') ? true : false;
    }

    /**
     * Publish now — send Telegram message.
     */
    public function publishNow(string $postId): array
    {
        $post = get_post($postId);
        if (!$post) {
            return ['success' => false, 'error' => 'پست یافت نشد'];
        }
        // Build payload from post data.
        $text = get_post_meta($postId, 'post_text', true);
        $channelId = get_post_meta($postId, 'post_channel_id', true);

        if (!$channelId || !$text) {
            return ['success' => false, 'error' => 'متن یا کانال تنظیم نشده'];
        }

        $provider = new BotApiTelegramProvider(\ATA\Infrastructure\Http\WpHttpTransport::make(), \ATA\Logging\Logger::instance());
        $payload = ['chat_id' => $channelId, 'text' => $text];

        try {
            $sent = $provider->sendMessage($payload);
            return ['success' => true, 'message_id' => $sent['message_id'] ?? null, 'link' => 'https://t.me/' . $sent['chat']['username'] . '/' . $sent['message_id']];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Store generated draft in post meta + log.
     */
    private function storeDraft(string $content, array $context): void
    {
        $postId = wp_insert_post([
            'post_title' => $context['title'] ?? '',
            'post_content' => $content,
            'post_status' => 'draft',
            'post_type' => 'ata_post',
        ]);

        update_post_meta($postId, 'post_text', $content);
        update_post_meta($postId, 'post_channel_id', $context['channel_id'] ?? null);
        update_post_meta($postId, 'post_config_json', json_encode($context['config'] ?? []));

        $logger = \ATA\Logging\Logger::instance();
        $logger->info('Draft stored', ['post_id' => $postId, 'scope' => 'content']);
    }
}