<?php
// Bestand: beheer/includes/tenant_crypto.php
// Symmetrische encryptie voor tenant_secrets (sleutel uit .env: TENANT_SECRETS_KEY).

if (!function_exists('tenant_secrets_binary_key')) {
    /**
     * Leest TENANT_SECRETS_KEY uit .env (via env_value).
     *
     * Toegestane formaten:
     * - base64:...  (aanbevolen: `php -r "echo 'base64:'.base64_encode(random_bytes(32));"`)
     * - 64 hextekens (256-bit sleutel)
     * - exact 32 bytes raw string (alleen als je weet wat je doet)
     *
     * @throws RuntimeException bij ontbrekende of ongeldige sleutel
     */
    function tenant_secrets_binary_key(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $raw = trim((string) env_value('TENANT_SECRETS_KEY', ''));
        if ($raw === '') {
            throw new RuntimeException(
                'TENANT_SECRETS_KEY ontbreekt in .env. Genereer bv.: php -r "echo \'base64:\'.base64_encode(random_bytes(32));"'
            );
        }

        if (str_starts_with($raw, 'base64:')) {
            $decoded = base64_decode(substr($raw, 8), true);
        } elseif (preg_match('/^[a-fA-F0-9]{64}$/', $raw) === 1) {
            $decoded = hex2bin($raw);
        } else {
            $decoded = $raw;
        }

        if ($decoded === false || strlen($decoded) !== 32) {
            throw new RuntimeException('TENANT_SECRETS_KEY moet na decodering exact 32 bytes zijn (AES-256).');
        }

        $cached = $decoded;
        return $cached;
    }
}

if (!function_exists('tenant_secret_encrypt_plaintext')) {
    /**
     * @return string Base64 payload met v1-prefix (iv + tag + ciphertext)
     */
    function tenant_secret_encrypt_plaintext(string $plaintext): string
    {
        $key = tenant_secrets_binary_key();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );
        if ($cipher === false || strlen($tag) !== 16) {
            throw new RuntimeException('Encryptie (AES-256-GCM) is mislukt.');
        }
        $packed = 'v1' . $iv . $tag . $cipher;
        return base64_encode($packed);
    }
}

if (!function_exists('tenant_secret_decrypt_plaintext')) {
    function tenant_secret_decrypt_plaintext(string $storedBase64): string
    {
        $key = tenant_secrets_binary_key();
        $bin = base64_decode($storedBase64, true);
        if ($bin === false || strlen($bin) < 2 + 12 + 16 + 1) {
            throw new RuntimeException('Ongeldige ciphertext (tenant secret).');
        }
        if (substr($bin, 0, 2) !== 'v1') {
            throw new RuntimeException('Onbekende ciphertext-versie.');
        }
        $iv = substr($bin, 2, 12);
        $tag = substr($bin, 14, 16);
        $cipher = substr($bin, 30);
        $plain = openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            ''
        );
        if ($plain === false) {
            throw new RuntimeException('Decryptie mislukt (verkeerde sleutel of beschadigde data).');
        }
        return $plain;
    }
}
