<?php

namespace Modules\UserPreference\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use JsonException;
use RuntimeException;

final readonly class PreferenceValueCodec
{
    public function __construct(private Encrypter $encrypter) {}

    /**
     * Encode a value as JSON and optionally encrypt it at rest.
     *
     * @throws JsonException
     */
    public function encode(mixed $value, bool $encrypted): string
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encrypted ? $this->encrypter->encryptString($json) : $json;
    }

    /**
     * Decode a stored value and surface corrupt data with its identifier.
     */
    public function decode(string $payload, bool $encrypted, string $identifier): mixed
    {
        try {
            $json = $encrypted ? $this->encrypter->decryptString($payload) : $payload;

            return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException $exception) {
            throw new RuntimeException("Corrupt user preference: {$identifier}", previous: $exception);
        }
    }
}
