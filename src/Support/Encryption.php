<?php

declare(strict_types=1);

namespace TaskQueue\Support;

class Encryption
{
    private string $key;
    private string $cipher;

    public function __construct(string $key, string $cipher = 'AES-256-GCM')
    {
        $this->key = $key;
        $this->cipher = $cipher;
    }

    public function encrypt(string $data): string
    {
        if (empty($this->key)) {
            return $data;
        }

        $iv = random_bytes(16);
        $tag = '';
        
        $encrypted = openssl_encrypt($data, $this->cipher, $this->key, 0, $iv, $tag);
        
        if ($encrypted === false) {
            throw new \RuntimeException('Failed to encrypt data');
        }

        return base64_encode($iv . $tag . $encrypted);
    }

    public function decrypt(string $encryptedData): string
    {
        if (empty($this->key)) {
            return $encryptedData;
        }

        $data = base64_decode($encryptedData, true);
        if ($data === false) {
            throw new \RuntimeException('Invalid encrypted data');
        }

        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $encrypted = substr($data, 32);

        $decrypted = openssl_decrypt($encrypted, $this->cipher, $this->key, 0, $iv, $tag);
        
        if ($decrypted === false) {
            throw new \RuntimeException('Failed to decrypt data');
        }

        return $decrypted;
    }

    public function isEncrypted(string $data): bool
    {
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return false;
        }

        return strlen($decoded) >= 32; // iv + tag + encrypted data
    }
}
