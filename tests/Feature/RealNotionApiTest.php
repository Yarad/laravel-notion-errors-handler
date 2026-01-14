<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Tests\Feature;

use Exception;
use Yarad\NotionExceptionHandler\ExceptionReporter;
use Yarad\NotionExceptionHandler\Tests\TestCase;

/**
 * Test against real Notion API.
 *
 * Run with: NOTION_API_KEY=xxx NOTION_DATABASE_ID=xxx ./vendor/bin/phpunit --filter RealNotionApiTest
 *
 * @group real-api
 */
class RealNotionApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip tests if real API credentials are not provided
        if (empty(env('NOTION_API_KEY')) || env('NOTION_API_KEY') === 'test_api_key') {
            $this->markTestSkipped('Real Notion API credentials required. Set NOTION_API_KEY and NOTION_DATABASE_ID environment variables.');
        }
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        // Use real credentials from environment
        $app['config']->set('notion-exceptions.api_key', env('NOTION_API_KEY'));
        $app['config']->set('notion-exceptions.database_id', env('NOTION_DATABASE_ID'));
        $app['config']->set('notion-exceptions.enabled', true);
        $app['config']->set('notion-exceptions.environment', 'testing');
        $app['config']->set('cache.default', 'array');

        // Use default field names that match the plan
        $app['config']->set('notion-exceptions.fields', [
            'title' => 'Title',
            'first_seen' => 'First Seen',
            'last_seen' => 'Last Seen',
            'occurrences' => 'Occurrences',
            'environment' => 'Environment',
            'fingerprint' => 'Fingerprint',
            'exception_class' => 'Exception Class',
            'file' => 'File',
            'line' => 'Line',
        ]);
    }

    public function testCanReportExceptionToRealNotion(): void
    {
        // Given
        $exception = new Exception('Test exception from PHPUnit at ' . date('Y-m-d H:i:s'));

        // When
        $reporter = $this->app->make(ExceptionReporter::class);
        $result = $reporter->report($exception);

        // Then
        $this->assertTrue($result, 'Exception should be reported successfully');
    }

    public function testDuplicateExceptionUpdatesOccurrenceCount(): void
    {
        // Given - create two identical exceptions
        $exception1 = $this->createFixedException('Duplicate test exception', '/test/path.php', 100);
        $exception2 = $this->createFixedException('Duplicate test exception', '/test/path.php', 100);

        // When
        $reporter = $this->app->make(ExceptionReporter::class);
        $result1 = $reporter->report($exception1);
        $result2 = $reporter->report($exception2);

        // Then
        $this->assertTrue($result1, 'First exception should be reported successfully');
        $this->assertTrue($result2, 'Second exception should update the existing entry');
    }

    /**
     * Create an exception with fixed file and line for testing.
     */
    private function createFixedException(string $message, string $file, int $line): Exception
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
}
