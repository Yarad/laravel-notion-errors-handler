<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Tests\Unit;

use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yarad\NotionExceptionHandler\Fingerprint\FingerprintGenerator;

class FingerprintGeneratorTest extends TestCase
{
    private FingerprintGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new FingerprintGenerator();
    }

    public function testGenerateCreatesConsistentFingerprintForSameException(): void
    {
        // Given
        $exception = $this->givenException('Test message', '/path/to/file.php', 42);

        // When
        $fingerprint1 = $this->whenGenerateFingerprint($exception);
        $fingerprint2 = $this->whenGenerateFingerprint($exception);

        // Then
        $this->thenFingerprintsShouldBeEqual($fingerprint1, $fingerprint2);
    }

    public function testGenerateCreatesDifferentFingerprintsForDifferentMessages(): void
    {
        // Given
        $exception1 = $this->givenException('Message 1', '/path/to/file.php', 42);
        $exception2 = $this->givenException('Message 2', '/path/to/file.php', 42);

        // When
        $fingerprint1 = $this->whenGenerateFingerprint($exception1);
        $fingerprint2 = $this->whenGenerateFingerprint($exception2);

        // Then
        $this->thenFingerprintsShouldBeDifferent($fingerprint1, $fingerprint2);
    }

    public function testGenerateCreatesDifferentFingerprintsForDifferentFiles(): void
    {
        // Given
        $exception1 = $this->givenException('Same message', '/path/to/file1.php', 42);
        $exception2 = $this->givenException('Same message', '/path/to/file2.php', 42);

        // When
        $fingerprint1 = $this->whenGenerateFingerprint($exception1);
        $fingerprint2 = $this->whenGenerateFingerprint($exception2);

        // Then
        $this->thenFingerprintsShouldBeDifferent($fingerprint1, $fingerprint2);
    }

    public function testGenerateCreatesDifferentFingerprintsForDifferentLines(): void
    {
        // Given
        $exception1 = $this->givenException('Same message', '/path/to/file.php', 42);
        $exception2 = $this->givenException('Same message', '/path/to/file.php', 100);

        // When
        $fingerprint1 = $this->whenGenerateFingerprint($exception1);
        $fingerprint2 = $this->whenGenerateFingerprint($exception2);

        // Then
        $this->thenFingerprintsShouldBeDifferent($fingerprint1, $fingerprint2);
    }

    public function testGenerateCreatesDifferentFingerprintsForDifferentExceptionTypes(): void
    {
        // Given
        $exception1 = new RuntimeException('Same message');
        $exception2 = new InvalidArgumentException('Same message');

        // When
        $fingerprint1 = $this->whenGenerateFingerprint($exception1);
        $fingerprint2 = $this->whenGenerateFingerprint($exception2);

        // Then
        $this->thenFingerprintsShouldBeDifferent($fingerprint1, $fingerprint2);
    }

    public function testGenerateShortReturns16CharacterString(): void
    {
        // Given
        $exception = $this->givenException('Test message', '/path/to/file.php', 42);

        // When
        $shortFingerprint = $this->whenGenerateShortFingerprint($exception);

        // Then
        $this->thenStringShouldHaveLength($shortFingerprint, 16);
    }

    public function testCreateFingerprintDataReturnsExpectedStructure(): void
    {
        // Given
        $exception = $this->givenException('Test message', '/path/to/file.php', 42);

        // When
        $data = $this->whenCreateFingerprintData($exception);

        // Then
        $this->thenDataShouldHaveRequiredKeys($data);
        $this->assertEquals('Test message', $data['message']);
        $this->assertEquals(Exception::class, $data['class']);
    }

    public function testNormalizesUuidsInMessages(): void
    {
        // Given
        $exception1 = $this->givenException(
            'User 123e4567-e89b-12d3-a456-426614174000 not found',
            '/path/to/file.php',
            42,
        );
        $exception2 = $this->givenException(
            'User 987fcdeb-51a2-3c4d-b5e6-789012345678 not found',
            '/path/to/file.php',
            42,
        );

        // When
        $fingerprint1 = $this->whenGenerateFingerprint($exception1);
        $fingerprint2 = $this->whenGenerateFingerprint($exception2);

        // Then - UUIDs should be normalized, so fingerprints should be equal
        $this->thenFingerprintsShouldBeEqual($fingerprint1, $fingerprint2);
    }

    // Given methods

    private function givenException(string $message, string $file, int $line): Exception
    {
        $exception = new Exception($message);
        $reflection = new \ReflectionClass($exception);

        $fileProperty = $reflection->getProperty('file');
        $fileProperty->setAccessible(true);
        $fileProperty->setValue($exception, $file);

        $lineProperty = $reflection->getProperty('line');
        $lineProperty->setAccessible(true);
        $lineProperty->setValue($exception, $line);

        return $exception;
    }

    // When methods

    private function whenGenerateFingerprint(\Throwable $exception): string
    {
        return $this->generator->generate($exception);
    }

    private function whenGenerateShortFingerprint(\Throwable $exception): string
    {
        return $this->generator->generateShort($exception);
    }

    /**
     * @return array<string, mixed>
     */
    private function whenCreateFingerprintData(\Throwable $exception): array
    {
        return $this->generator->createFingerprintData($exception);
    }

    // Then methods

    private function thenFingerprintsShouldBeEqual(string $fingerprint1, string $fingerprint2): void
    {
        $this->assertEquals($fingerprint1, $fingerprint2);
    }

    private function thenFingerprintsShouldBeDifferent(string $fingerprint1, string $fingerprint2): void
    {
        $this->assertNotEquals($fingerprint1, $fingerprint2);
    }

    private function thenStringShouldHaveLength(string $string, int $expectedLength): void
    {
        $this->assertEquals($expectedLength, strlen($string));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function thenDataShouldHaveRequiredKeys(array $data): void
    {
        $this->assertArrayHasKey('fingerprint', $data);
        $this->assertArrayHasKey('short', $data);
        $this->assertArrayHasKey('class', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('file', $data);
        $this->assertArrayHasKey('line', $data);
    }
}
