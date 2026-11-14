<?php

namespace Tests\Unit;

use App\Support\BackupEncrypter;
use Tests\TestCase;

/**
 * B1 — criptografia autenticada (AES-256-GCM) dos backups.
 */
class BackupEncrypterTest extends TestCase
{
    public function test_encrypts_and_decrypts_a_file_with_authentication(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'spi_plain_');
        file_put_contents($source, str_repeat('conteudo-do-backup-', 500));

        $encrypted = $source.'.enc';
        $decrypted = $source.'.dec';

        BackupEncrypter::encryptFile($source, $encrypted);

        $this->assertTrue(BackupEncrypter::isEncryptedFile($encrypted));
        $this->assertFalse(BackupEncrypter::isEncryptedFile($source));
        $this->assertNotSame(file_get_contents($source), file_get_contents($encrypted));

        BackupEncrypter::decryptFile($encrypted, $decrypted);

        $this->assertSame(file_get_contents($source), file_get_contents($decrypted));

        @unlink($source);
        @unlink($encrypted);
        @unlink($decrypted);
    }

    public function test_decryption_fails_with_a_wrong_key(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'spi_plain_');
        file_put_contents($source, 'segredo-do-backup');

        $encrypted = $source.'.enc';
        BackupEncrypter::encryptFile($source, $encrypted);

        config(['app.key' => 'base64:'.base64_encode(str_repeat('z', 32))]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('chave incorreta ou arquivo corrompido');

        BackupEncrypter::decryptFile($encrypted, $source.'.bad');
    }

    public function test_a_tampered_ciphertext_is_rejected(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'spi_plain_');
        file_put_contents($source, 'segredo-autenticado');

        $encrypted = $source.'.enc';
        BackupEncrypter::encryptFile($source, $encrypted);

        // Corrompe o último byte do ciphertext: a tag de autenticação deve invalidar.
        $data = file_get_contents($encrypted);
        $data[strlen($data) - 1] = $data[strlen($data) - 1] === 'a' ? 'b' : 'a';
        file_put_contents($encrypted, $data);

        $this->expectException(\RuntimeException::class);

        BackupEncrypter::decryptFile($encrypted, $source.'.bad');
    }
}
