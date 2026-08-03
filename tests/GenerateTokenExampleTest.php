<?php

declare(strict_types=1);

namespace Omnetic\JwtGenerator\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Smoke tests for examples/generate-token.php — the example ships with the SDK,
 * so its argument handling and exit codes are behavior worth pinning down.
 */
final class GenerateTokenExampleTest extends TestCase
{
    private const KID = 'kid-uuid-1';

    /** @var list<string> */
    private array $temporaryKeys = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryKeys as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->temporaryKeys = [];
    }

    public function testPrintsASignedTokenOnStdout(): void
    {
        $result = $this->runExample([$this->writeTemporaryKey(), self::KID, '600']);

        self::assertSame(0, $result['status'], $result['stderr']);
        self::assertCount(3, explode('.', trim($result['stdout'])));
    }

    public function testFallsBackToTheEnvironmentWhenArgumentsAreEmpty(): void
    {
        // `make example` always passes both arguments, empty ones included.
        $result = $this->runExample(['', ''], [
            'OMNETIC_SA_KEY_PATH' => $this->writeTemporaryKey(),
            'OMNETIC_SA_KID' => self::KID,
        ]);

        self::assertSame(0, $result['status'], $result['stderr']);
        self::assertCount(3, explode('.', trim($result['stdout'])));
    }

    public function testPrintsUsageWithoutArguments(): void
    {
        $result = $this->runExample([]);

        self::assertSame(1, $result['status']);
        self::assertSame('', $result['stdout']);
        self::assertStringContainsString('Usage: php examples/generate-token.php', $result['stderr']);
    }

    public function testReportsAKeyPathThatIsNotAReadableFile(): void
    {
        $result = $this->runExample([sys_get_temp_dir(), self::KID]);

        self::assertSame(1, $result['status']);
        self::assertStringContainsString('does not exist or is not readable', $result['stderr']);
    }

    public function testReportsTheSdkValidationMessage(): void
    {
        $result = $this->runExample([$this->writeTemporaryKey(), self::KID, '9999']);

        self::assertSame(1, $result['status']);
        self::assertStringContainsString('The lifetime must be between 1 and 3600 seconds', $result['stderr']);
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string>|null $environment Inherits the current environment when null
     * @return array{status: int, stdout: string, stderr: string}
     */
    private function runExample(array $arguments, ?array $environment = null): array
    {
        $command = [PHP_BINARY, dirname(__DIR__) . '/examples/generate-token.php', ...$arguments];
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, null, $environment);
        if (!is_resource($process)) {
            self::fail('Could not start examples/generate-token.php.');
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'status' => proc_close($process),
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    private function writeTemporaryKey(): string
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($resource === false) {
            self::fail('Could not generate an RSA key pair: ' . openssl_error_string());
        }

        openssl_pkey_export($resource, $privateKey);
        assert(is_string($privateKey));

        $path = tempnam(sys_get_temp_dir(), 'sa-key-');
        assert(is_string($path));
        file_put_contents($path, $privateKey);
        $this->temporaryKeys[] = $path;

        return $path;
    }
}
