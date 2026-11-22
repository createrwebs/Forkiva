<?php

namespace Modules\Notification\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Models\NotificationTemplateMessage;

abstract class NotificationTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $template = $this->template();
        if ($template === []) {
            return;
        }

        $this->seedTemplate($template);
    }

    /**
     * Persist one template and its missing messages.
     *
     * @param  array<string, mixed>  $template
     */
    protected function seedTemplate(array $template): void
    {
        $legacyKeys = $template['legacy_keys'] ?? [];
        $messages = $template['messages'];
        unset($template['legacy_keys']);
        unset($template['messages']);

        $model = NotificationTemplate::query()
            ->where('key', $template['key'])
            ->first();

        if (! $model && $legacyKeys !== []) {
            $model = NotificationTemplate::query()
                ->whereIn('key', $legacyKeys)
                ->first();

            if ($model) {
                NotificationTemplateMessage::query()
                    ->where('template_key', $model->key)
                    ->update(['template_key' => $template['key']]);

                $model->update(['key' => $template['key']]);
            }
        }

        if ($model) {
            $model->update([
                'supported_variables' => $template['supported_variables'],
            ]);
        } else {
            NotificationTemplate::query()->create($template);
        }

        $channelKeys = array_values(array_unique(array_map(
            fn (array $message) => (string) $message['channel_key'],
            $messages
        )));
        $messageKeys = array_values(array_unique(array_map(
            fn (array $message) => $this->messageKey($template['key'], $message),
            $messages
        )));

        NotificationTemplateMessage::query()
            ->where('template_key', $template['key'])
            ->whereNotIn('channel_key', $channelKeys)
            ->delete();

        NotificationTemplateMessage::query()
            ->where('template_key', $template['key'])
            ->get()
            ->reject(fn (NotificationTemplateMessage $message) => in_array($this->messageKey($template['key'], [
                'channel_key' => $message->channel_key,
                'recipient_role_key' => $message->recipient_role_key,
            ]), $messageKeys, true))
            ->each->delete();

        foreach ($messages as $message) {
            $model = NotificationTemplateMessage::query()
                ->firstOrNew([
                    'template_key' => $template['key'],
                    'channel_key' => $message['channel_key'],
                    'recipient_role_key' => $message['recipient_role_key'] ?? NotificationTemplateMessage::DEFAULT_RECIPIENT_ROLE,
                ]);

            if (! $model->exists) {
                $model->fill([
                    'title' => $message['title'] ?? null,
                    'subject' => $message['subject'] ?? null,
                    'content' => $message['content'],
                    'content_type' => $message['content_type'],
                ]);
                $model->save();

                continue;
            }

            if ($this->isUntouchedSeededMessage($model)) {
                $model->fill([
                    'title' => $message['title'] ?? null,
                    'subject' => $message['subject'] ?? null,
                    'content' => $message['content'],
                    'content_type' => $message['content_type'],
                ]);
                $model->save();

                continue;
            }

            if (blank($model->title) && ! blank($message['title'] ?? null)) {
                $model->title = $message['title'];
                $model->save();
            }
        }
    }

    /**
     * Determine whether a message row still looks like untouched seeded data.
     */
    protected function isUntouchedSeededMessage(NotificationTemplateMessage $message): bool
    {
        if (! $message->created_at || ! $message->updated_at) {
            return false;
        }

        return $message->created_at->equalTo($message->updated_at);
    }

    /**
     * Build a stable message identity for one role variant.
     *
     * @param  array<string, mixed>  $message
     */
    protected function messageKey(string $templateKey, array $message): string
    {
        return implode('|', [
            $templateKey,
            (string) $message['channel_key'],
            (string) ($message['recipient_role_key'] ?? NotificationTemplateMessage::DEFAULT_RECIPIENT_ROLE),
        ]);
    }

    /**
     * Get template data.
     *
     * @return array<string, mixed>
     */
    protected function template(): array
    {
        return [];
    }
}
