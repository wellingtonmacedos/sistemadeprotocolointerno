<?php

namespace App\Support;

/**
 * Criptografia autenticada (AES-256-GCM) dos arquivos de backup.
 *
 * A chave é derivada da APP_KEY do ambiente; nenhuma senha é armazenada em banco.
 * Formato do arquivo: MAGIC(8) | IV(12) | TAG(16) | ciphertext.
 */
class BackupEncrypter
{
    private const MAGIC = 'SPIENC01';

    private const CIPHER = 'aes-256-gcm';

    private const IV_LENGTH = 12;

    private const TAG_LENGTH = 16;

    /** Limite de memória para criptografia em bloco único (512 MB). */
    private const MAX_BYTES = 536870912;

    public static function key(): string
    {
        return hash('sha256', (string) config('app.key'), true);
    }

    public static function isEncryptedFile(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, strlen(self::MAGIC));
        fclose($handle);

        return $header === self::MAGIC;
    }

    public static function encryptFile(string $source, string $destination): void
    {
        $size = filesize($source);

        if ($size !== false && $size > self::MAX_BYTES) {
            throw new \RuntimeException('Arquivo excede o limite suportado para criptografia em memória.');
        }

        $plain = file_get_contents($source);

        if ($plain === false) {
            throw new \RuntimeException('Não foi possível ler o arquivo a ser criptografado.');
        }

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);

        if ($cipher === false) {
            throw new \RuntimeException('Falha ao criptografar o backup.');
        }

        if (file_put_contents($destination, self::MAGIC.$iv.$tag.$cipher) === false) {
            throw new \RuntimeException('Não foi possível gravar o backup criptografado.');
        }
    }

    public static function decryptFile(string $source, string $destination): void
    {
        $data = file_get_contents($source);

        if ($data === false) {
            throw new \RuntimeException('Não foi possível ler o arquivo criptografado.');
        }

        if (! str_starts_with($data, self::MAGIC)) {
            throw new \RuntimeException('Arquivo informado não está em formato criptografado.');
        }

        $offset = strlen(self::MAGIC);
        $iv = substr($data, $offset, self::IV_LENGTH);
        $tag = substr($data, $offset + self::IV_LENGTH, self::TAG_LENGTH);
        $cipher = substr($data, $offset + self::IV_LENGTH + self::TAG_LENGTH);

        $plain = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($plain === false) {
            throw new \RuntimeException('Falha ao descriptografar: chave incorreta ou arquivo corrompido.');
        }

        if (file_put_contents($destination, $plain) === false) {
            throw new \RuntimeException('Não foi possível gravar o arquivo descriptografado.');
        }
    }
}
