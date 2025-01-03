<?php

declare(strict_types=1);

namespace App\Health\Checks;

use App\Health\Enums\HealthStatus;
use App\Health\Exceptions\HealthCheckException;
use App\Health\Traits\HealthCheckTrait;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3v3\AwsS3Adapter;

/**
 * AWS S3 health check implementation
 *
 * Checks:
 * - Bucket accessibility
 * - Read/Write operations
 * - Object lifecycle
 * - Bucket metrics
 * - IAM permissions
 */
class S3HealthCheck extends BaseHealthCheck
{
    use HealthCheckTrait;

    /**
     * Health check file name
     */
    private const HEALTH_CHECK_FILE = '.health-check';

    /**
     * Default thresholds for bucket size (bytes)
     */
    private const DEFAULT_SIZE_WARNING_THRESHOLD = 5 * 1024 * 1024 * 1024 * 1024; // 5TB

    private const DEFAULT_SIZE_ERROR_THRESHOLD = 8 * 1024 * 1024 * 1024 * 1024;   // 8TB

    /**
     * Default thresholds for object count
     */
    private const DEFAULT_OBJECTS_WARNING_THRESHOLD = 50000000; // 50M objects

    private const DEFAULT_OBJECTS_ERROR_THRESHOLD = 80000000;   // 80M objects

    /**
     * Required IAM permissions for health check
     *
     * @var array<string>
     */
    private const REQUIRED_PERMISSIONS = [
        's3:ListBucket',
        's3:GetObject',
        's3:PutObject',
        's3:DeleteObject',
        's3:GetBucketLocation',
    ];

    /**
     * Get check name
     */
    public function name(): string
    {
        return 's3';
    }

    /**
     * Get check display name
     */
    public function displayName(): string
    {
        return 'Amazon S3 Storage';
    }

    /**
     * Get check description
     */
    public function description(): string
    {
        return 'Checks Amazon S3 bucket accessibility, permissions, and performance';
    }

    /**
     * Is this a critical check?
     */
    public function isCritical(): bool
    {
        return true;
    }

    /**
     * Get maximum execution time for this check
     */
    public function timeout(): int
    {
        return max(parent::timeout(), 30); // Ensure at least 30 seconds for S3 operations
    }

    /**
     * Get minimum interval between check executions
     */
    public function minimumInterval(): int
    {
        return 300; // 5 minutes
    }

    /**
     * Get check tags
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return ['storage', 'aws', 's3', 'core'];
    }

    /**
     * Check if this check is enabled
     */
    public function isEnabled(): bool
    {
        return Config::get('filesystems.disks.s3.bucket') !== null
            && Config::get('health.checks.s3.enabled', true);
    }

    /**
     * Get check dependencies
     *
     * @return array<string>
     */
    public function dependencies(): array
    {
        return [];
    }

    /**
     * Execute S3 health checks
     *
     * @return array{status: string, metadata?: array<string, mixed>}
     * @throws HealthCheckException
     */
    protected function executeCheck(): array
    {
        try {
            // Get S3 client and disk
            $disk = Storage::disk('s3');
            $client = $this->getS3Client();
            $bucket = Config::get('filesystems.disks.s3.bucket');

            if (empty($bucket)) {
                throw new HealthCheckException('S3 bucket not configured');
            }

            // Check bucket existence and accessibility
            if (! $this->checkBucketAccess($client, $bucket)) {
                return [
                    'status' => HealthStatus::UNHEALTHY->value,
                    'metadata' => [
                        'bucket' => $bucket,
                        'error' => 'Bucket not accessible',
                    ],
                ];
            }

            // Test read/write operations
            $operationsTest = $this->testReadWriteOperations($disk);
            if (! $operationsTest['success']) {
                return [
                    'status' => HealthStatus::UNHEALTHY->value,
                    'metadata' => [
                        'bucket' => $bucket,
                        'error' => $operationsTest['error'],
                    ],
                ];
            }

            // Gather bucket metrics
            $metrics = $this->gatherBucketMetrics($client, $bucket);

            // Check bucket size
            if (isset($metrics['size_bytes'])) {
                $sizeStatus = $this->checkThreshold(
                    $metrics['size_bytes'],
                    Config::get('health.checks.s3.size_warning_threshold', self::DEFAULT_SIZE_WARNING_THRESHOLD),
                    Config::get('health.checks.s3.size_error_threshold', self::DEFAULT_SIZE_ERROR_THRESHOLD)
                );

                if ($sizeStatus === HealthStatus::UNHEALTHY->value) {
                    return [
                        'status' => HealthStatus::UNHEALTHY->value,
                        'metadata' => [
                            ...$metrics,
                            'error' => 'Bucket size exceeds error threshold',
                        ],
                    ];
                }

                if ($sizeStatus === HealthStatus::WARNING->value) {
                    return [
                        'status' => HealthStatus::WARNING->value,
                        'metadata' => [
                            ...$metrics,
                            'warning' => 'Bucket size exceeds warning threshold',
                        ],
                    ];
                }
            }

            // Check permissions
            $permissionsCheck = $this->checkIamPermissions($client, $bucket);
            if (! empty($permissionsCheck['missing'])) {
                return [
                    'status' => HealthStatus::WARNING->value,
                    'metadata' => [
                        ...$metrics,
                        'warning' => 'Missing required permissions',
                        'missing_permissions' => $permissionsCheck['missing'],
                    ],
                ];
            }

            // All checks passed
            return [
                'status' => HealthStatus::HEALTHY->value,
                'metadata' => [
                    ...$metrics,
                    'operations_test' => 'passed',
                    'permissions' => 'all_required_permissions_granted',
                ],
            ];
        } catch (HealthCheckException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('S3 health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new HealthCheckException(
                "S3 check failed: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Get S3 client instance
     *
     * @throws HealthCheckException
     */
    private function getS3Client(): S3Client
    {
        try {
            $adapter = Storage::disk('s3')->getAdapter();

            if (! $adapter instanceof AwsS3Adapter) {
                throw new HealthCheckException('S3 adapter not configured correctly');
            }

            return $adapter->getClient();
        } catch (\Throwable $e) {
            throw new HealthCheckException(
                "Failed to get S3 client: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Check if bucket exists and is accessible
     */
    private function checkBucketAccess(S3Client $client, string $bucket): bool
    {
        try {
            // Try to get bucket location as a lightweight accessibility check
            $client->getBucketLocation(['Bucket' => $bucket]);

            return true;
        } catch (S3Exception $e) {
            Log::warning('S3 bucket access check failed', [
                'bucket' => $bucket,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Test basic read/write operations
     *
     * @param \Illuminate\Filesystem\FilesystemAdapter $disk
     * @return array{success: bool, error?: string}
     */
    private function testReadWriteOperations($disk): array
    {
        $testFile = self::HEALTH_CHECK_FILE.'-'.time();
        $content = 'health-check-'.uniqid();

        try {
            // Test write
            if (! $disk->put($testFile, $content)) {
                return [
                    'success' => false,
                    'error' => 'Failed to write test file',
                ];
            }

            // Test read
            $readContent = $disk->get($testFile);
            if ($readContent !== $content) {
                return [
                    'success' => false,
                    'error' => 'Content verification failed',
                ];
            }

            // Test delete
            if (! $disk->delete($testFile)) {
                return [
                    'success' => false,
                    'error' => 'Failed to delete test file',
                ];
            }

            return ['success' => true];
        } catch (\Throwable $e) {
            // Cleanup on failure
            try {
                $disk->delete($testFile);
            } catch (\Throwable) {
                // Ignore cleanup errors
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Gather bucket metrics
     *
     * @return array<string, mixed>
     */
    private function gatherBucketMetrics(S3Client $client, string $bucket): array
    {
        $metrics = [
            'bucket' => $bucket,
            'region' => Config::get('filesystems.disks.s3.region'),
        ];

        try {
            // Get bucket metrics
            $objects = $client->listObjects([
                'Bucket' => $bucket,
            ]);

            $metrics['object_count'] = count($objects['Contents'] ?? []);
            $metrics['size_bytes'] = array_reduce(
                $objects['Contents'] ?? [],
                fn ($total, $object) => $total + ($object['Size'] ?? 0),
                0
            );
            $metrics['size_human'] = $this->formatBytes($metrics['size_bytes']);

            // Get versioning status
            $versioning = $client->getBucketVersioning([
                'Bucket' => $bucket,
            ]);
            $metrics['versioning_enabled'] = $versioning->get('Status') === 'Enabled';

            // Get encryption status
            $encryption = $client->getBucketEncryption([
                'Bucket' => $bucket,
            ]);
            $metrics['encryption_enabled'] = ! empty($encryption['ServerSideEncryptionConfiguration']);
        } catch (\Throwable $e) {
            Log::warning('Failed to gather complete S3 metrics', [
                'error' => $e->getMessage(),
            ]);
        }

        return $metrics;
    }

    /**
     * Check required IAM permissions
     *
     * @return array{success: bool, missing?: array<string>}
     */
    private function checkIamPermissions(S3Client $client, string $bucket): array
    {
        $missingPermissions = [];

        foreach (self::REQUIRED_PERMISSIONS as $permission) {
            try {
                switch ($permission) {
                    case 's3:ListBucket':
                        $client->listObjects(['Bucket' => $bucket, 'MaxKeys' => 1]);
                        break;
                    case 's3:GetObject':
                        $client->headObject([
                            'Bucket' => $bucket,
                            'Key' => self::HEALTH_CHECK_FILE,
                        ]);
                        break;
                    case 's3:PutObject':
                        $client->putObject([
                            'Bucket' => $bucket,
                            'Key' => self::HEALTH_CHECK_FILE,
                            'Body' => 'test',
                        ]);
                        break;
                    case 's3:DeleteObject':
                        $client->deleteObject([
                            'Bucket' => $bucket,
                            'Key' => self::HEALTH_CHECK_FILE,
                        ]);
                        break;
                    case 's3:GetBucketLocation':
                        $client->getBucketLocation(['Bucket' => $bucket]);
                        break;
                }
            } catch (S3Exception $e) {
                if ($e->getAwsErrorCode() === 'AccessDenied') {
                    $missingPermissions[] = $permission;
                }
            }
        }

        return [
            'success' => empty($missingPermissions),
            'missing' => $missingPermissions,
        ];
    }

    /**
     * Format bytes to human readable string
     */
    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $level = 0;

        while ($bytes > 1024 && $level < count($units) - 1) {
            $bytes /= 1024;
            $level++;
        }

        return round($bytes, 2).' '.$units[$level];
    }

    /**
     * Get the severity level of the check
     */
    public function severity(): int
    {
        return 2; // High severity as S3 is often critical for file storage
    }
}
