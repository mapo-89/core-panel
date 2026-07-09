<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\DatabaseBackups;

use HashContext;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class DatabaseBackupEncryptor
{
    private const BLOCK_SIZE = 16;

    private const FORMAT_MAGIC = 'CPDBENC1';

    private const HASH_BYTES = 32;

    private const STREAM_CHUNK_BYTES = 1048576;

    public function isEncrypted(string $path): bool
    {
        return str_ends_with($path, '.enc');
    }

    public function encryptFile(string $sourcePath, string $targetPath, string $code): void
    {
        $source = fopen($sourcePath, 'rb');
        $target = fopen($targetPath, 'wb');

        if ($source === false || $target === false) {
            if (is_resource($source)) {
                fclose($source);
            }

            if (is_resource($target)) {
                fclose($target);
            }

            throw new RuntimeException('Database backup encryption failed.');
        }

        $iv = random_bytes(self::BLOCK_SIZE);
        ['authentication' => $authenticationKey, 'encryption' => $encryptionKey] = $this->keys($code);
        $authenticationContext = hash_init('sha256', HASH_HMAC, $authenticationKey);

        try {
            $this->writeStream($target, self::FORMAT_MAGIC);
            hash_update($authenticationContext, self::FORMAT_MAGIC);
            $this->writeStream($target, $iv);
            hash_update($authenticationContext, $iv);
            $this->encryptStream($source, $target, $encryptionKey, $iv, $authenticationContext);
            $this->writeStream($target, hash_final($authenticationContext, true));
        } finally {
            fclose($source);
            fclose($target);
        }
    }

    public function decryptFile(string $sourcePath, string $targetPath, string $code): void
    {
        $this->decryptFileWithCodes($sourcePath, $targetPath, [$code]);
    }

    /**
     * @param  iterable<string>  $codes
     */
    public function decryptFileWithCodes(string $sourcePath, string $targetPath, iterable $codes): void
    {
        foreach ($codes as $code) {
            if ($code === '') {
                continue;
            }

            if ($this->attemptDecryptFile($sourcePath, $targetPath, $code)) {
                return;
            }
        }

        throw new RuntimeException('Database backup decryption failed.');
    }

    /**
     * @param  resource  $source
     * @param  resource  $target
     */
    private function encryptStream($source, $target, string $key, string $initializationVector, ?HashContext $authenticationContext = null): void
    {
        $buffer = '';
        $currentInitializationVector = $initializationVector;

        while (! feof($source)) {
            $chunk = fread($source, self::STREAM_CHUNK_BYTES);

            if ($chunk === false) {
                throw new RuntimeException('Database backup encryption failed.');
            }

            $buffer .= $chunk;
            $processableLength = $this->encryptProcessableLength($buffer);

            if ($processableLength === 0) {
                continue;
            }

            $plaintext = substr($buffer, 0, $processableLength);
            $buffer = substr($buffer, $processableLength);
            $ciphertext = $this->encryptChunk($plaintext, $key, $currentInitializationVector);

            $this->writeStream($target, $ciphertext);
            if ($authenticationContext !== null) {
                hash_update($authenticationContext, $ciphertext);
            }
            $currentInitializationVector = substr($ciphertext, -self::BLOCK_SIZE);
        }

        $padLength = self::BLOCK_SIZE - (strlen($buffer) % self::BLOCK_SIZE);
        $finalPlaintext = $buffer.str_repeat(chr($padLength), $padLength);
        $finalCiphertext = $this->encryptChunk($finalPlaintext, $key, $currentInitializationVector);

        $this->writeStream($target, $finalCiphertext);
        if ($authenticationContext !== null) {
            hash_update($authenticationContext, $finalCiphertext);
        }
    }

    /**
     * @param  resource  $source
     * @param  resource  $target
     */
    private function decryptStream($source, $target, string $key): void
    {
        $initializationVector = fread($source, self::BLOCK_SIZE);

        if ($initializationVector === false || strlen($initializationVector) !== self::BLOCK_SIZE) {
            throw new RuntimeException('Encrypted database backup is invalid.');
        }

        $buffer = '';
        $currentInitializationVector = $initializationVector;

        while (! feof($source)) {
            $chunk = fread($source, self::STREAM_CHUNK_BYTES);

            if ($chunk === false) {
                throw new RuntimeException('Database backup decryption failed.');
            }

            $buffer .= $chunk;
            $processableLength = $this->decryptProcessableLength($buffer);

            if ($processableLength === 0) {
                continue;
            }

            $ciphertext = substr($buffer, 0, $processableLength);
            $buffer = substr($buffer, $processableLength);
            $plaintext = $this->decryptChunk($ciphertext, $key, $currentInitializationVector);

            $this->writeStream($target, $plaintext);
            $currentInitializationVector = substr($ciphertext, -self::BLOCK_SIZE);
        }

        if ($buffer === '' || strlen($buffer) % self::BLOCK_SIZE !== 0) {
            throw new RuntimeException('Encrypted database backup is invalid.');
        }

        $finalPlaintext = $this->decryptChunk($buffer, $key, $currentInitializationVector);
        $this->writeStream($target, $this->stripPadding($finalPlaintext));
    }

    private function attemptDecryptFile(string $sourcePath, string $targetPath, string $code): bool
    {
        $temporaryPath = $targetPath.'.'.bin2hex(random_bytes(8)).'.tmp';
        $source = fopen($sourcePath, 'rb');
        $target = fopen($temporaryPath, 'wb');

        if ($source === false || $target === false) {
            if (is_resource($source)) {
                fclose($source);
            }

            if (is_resource($target)) {
                fclose($target);
            }

            File::delete($temporaryPath);

            throw new RuntimeException('Database backup decryption failed.');
        }

        try {
            $magic = fread($source, strlen(self::FORMAT_MAGIC));

            if ($magic === false) {
                throw new RuntimeException('Database backup decryption failed.');
            }

            rewind($source);

            if ($magic === self::FORMAT_MAGIC) {
                ['authentication' => $authenticationKey, 'encryption' => $encryptionKey] = $this->keys($code);
                $this->decryptAuthenticatedStream($source, $target, $encryptionKey, $authenticationKey);
            } else {
                $this->decryptStream($source, $target, $this->legacyEncryptionKey($code));
            }

            fclose($source);
            fclose($target);

            File::move($temporaryPath, $targetPath);

            return true;
        } catch (RuntimeException) {
            fclose($source);
            fclose($target);
            File::delete($temporaryPath);

            return false;
        }
    }

    /**
     * @param  resource  $source
     * @param  resource  $target
     */
    private function decryptAuthenticatedStream($source, $target, string $encryptionKey, string $authenticationKey): void
    {
        $streamStats = fstat($source);
        $streamSize = $streamStats['size'] ?? null;

        if (! is_int($streamSize)) {
            throw new RuntimeException('Encrypted database backup is invalid.');
        }

        $minimumSize = strlen(self::FORMAT_MAGIC) + self::BLOCK_SIZE + self::BLOCK_SIZE + self::HASH_BYTES;

        if ($streamSize < $minimumSize) {
            throw new RuntimeException('Encrypted database backup is invalid.');
        }

        $ciphertextLength = $streamSize - strlen(self::FORMAT_MAGIC) - self::BLOCK_SIZE - self::HASH_BYTES;

        if ($ciphertextLength % self::BLOCK_SIZE !== 0) {
            throw new RuntimeException('Encrypted database backup is invalid.');
        }

        $this->assertAuthenticationTag($source, $authenticationKey, $streamSize);
        rewind($source);

        $magic = fread($source, strlen(self::FORMAT_MAGIC));
        $initializationVector = fread($source, self::BLOCK_SIZE);

        if ($magic !== self::FORMAT_MAGIC || $initializationVector === false || strlen($initializationVector) !== self::BLOCK_SIZE) {
            throw new RuntimeException('Encrypted database backup is invalid.');
        }

        $remainingCiphertextBytes = $ciphertextLength;
        $buffer = '';
        $currentInitializationVector = $initializationVector;

        while ($remainingCiphertextBytes > 0) {
            $chunk = fread($source, min(self::STREAM_CHUNK_BYTES, $remainingCiphertextBytes));

            if ($chunk === false) {
                throw new RuntimeException('Database backup decryption failed.');
            }

            $remainingCiphertextBytes -= strlen($chunk);
            $buffer .= $chunk;
            $processableLength = $this->decryptProcessableLength($buffer);

            if ($remainingCiphertextBytes === 0) {
                $processableLength = max(0, $processableLength);
            }

            if ($processableLength === 0) {
                continue;
            }

            $ciphertext = substr($buffer, 0, $processableLength);
            $buffer = substr($buffer, $processableLength);
            $plaintext = $this->decryptChunk($ciphertext, $encryptionKey, $currentInitializationVector);

            $this->writeStream($target, $plaintext);
            $currentInitializationVector = substr($ciphertext, -self::BLOCK_SIZE);
        }

        if ($buffer === '' || strlen($buffer) % self::BLOCK_SIZE !== 0) {
            throw new RuntimeException('Encrypted database backup is invalid.');
        }

        $finalPlaintext = $this->decryptChunk($buffer, $encryptionKey, $currentInitializationVector);
        $this->writeStream($target, $this->stripPadding($finalPlaintext));
    }

    /**
     * @param  resource  $source
     */
    private function assertAuthenticationTag($source, string $authenticationKey, int $streamSize): void
    {
        rewind($source);

        $authenticatedBytes = $streamSize - self::HASH_BYTES;
        $remainingAuthenticatedBytes = $authenticatedBytes;
        $authenticationContext = hash_init('sha256', HASH_HMAC, $authenticationKey);

        while ($remainingAuthenticatedBytes > 0) {
            $chunk = fread($source, min(self::STREAM_CHUNK_BYTES, $remainingAuthenticatedBytes));

            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Encrypted database backup is invalid.');
            }

            $remainingAuthenticatedBytes -= strlen($chunk);
            hash_update($authenticationContext, $chunk);
        }

        $storedAuthenticationTag = fread($source, self::HASH_BYTES);

        if ($storedAuthenticationTag === false || strlen($storedAuthenticationTag) !== self::HASH_BYTES) {
            throw new RuntimeException('Encrypted database backup is invalid.');
        }

        $computedAuthenticationTag = hash_final($authenticationContext, true);

        if (! hash_equals($computedAuthenticationTag, $storedAuthenticationTag)) {
            throw new RuntimeException('Database backup decryption failed.');
        }
    }

    /**
     * @return array{authentication: string, encryption: string}
     */
    private function keys(string $code): array
    {
        $material = hash('sha512', $code, true);

        return [
            'authentication' => substr($material, 32, 32),
            'encryption' => substr($material, 0, 32),
        ];
    }

    private function legacyEncryptionKey(string $code): string
    {
        return hash('sha256', $code, true);
    }

    private function encryptProcessableLength(string $buffer): int
    {
        if (strlen($buffer) <= self::BLOCK_SIZE) {
            return 0;
        }

        return intdiv(strlen($buffer) - self::BLOCK_SIZE, self::BLOCK_SIZE) * self::BLOCK_SIZE;
    }

    private function decryptProcessableLength(string $buffer): int
    {
        if (strlen($buffer) <= self::BLOCK_SIZE) {
            return 0;
        }

        return intdiv(strlen($buffer) - self::BLOCK_SIZE, self::BLOCK_SIZE) * self::BLOCK_SIZE;
    }

    private function encryptChunk(string $plaintext, string $key, string $initializationVector): string
    {
        $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $initializationVector);

        if ($ciphertext === false) {
            throw new RuntimeException('Database backup encryption failed.');
        }

        return $ciphertext;
    }

    private function decryptChunk(string $ciphertext, string $key, string $initializationVector): string
    {
        $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $initializationVector);

        if ($plaintext === false) {
            throw new RuntimeException('Database backup decryption failed.');
        }

        return $plaintext;
    }

    private function stripPadding(string $plaintext): string
    {
        if ($plaintext === '') {
            throw new RuntimeException('Encrypted database backup is invalid.');
        }

        $padLength = ord(substr($plaintext, -1));

        if ($padLength < 1 || $padLength > self::BLOCK_SIZE) {
            throw new RuntimeException('Database backup decryption failed.');
        }

        $padding = substr($plaintext, -$padLength);

        if ($padding !== str_repeat(chr($padLength), $padLength)) {
            throw new RuntimeException('Database backup decryption failed.');
        }

        return substr($plaintext, 0, -$padLength);
    }

    /**
     * @param  resource  $stream
     */
    private function writeStream($stream, string $contents): void
    {
        $remaining = $contents;

        while ($remaining !== '') {
            $written = fwrite($stream, $remaining);

            if ($written === false || $written === 0) {
                throw new RuntimeException('Database backup file operation failed.');
            }

            $remaining = substr($remaining, $written);
        }
    }
}
