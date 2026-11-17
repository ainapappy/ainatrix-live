<?php
/**
 * Limiteur de tentatives de connexion pour wp-login.php.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\LoginExperience;

if (!defined('ABSPATH')) {
    exit;
}

final class LoginRateLimiter {

    private const TRANSIENT_PREFIX = 'aix_live_login_rl_';
    private const DEFAULT_MAX_ATTEMPTS = 5;
    private const DEFAULT_WINDOW_SECONDS = 900;
    private const DEFAULT_LOCK_SECONDS = 900;

    public function isLocked(string $username, string $ipAddress): bool {
        return $this->getLockRemainingSeconds($username, $ipAddress) > 0;
    }

    public function getLockRemainingSeconds(string $username, string $ipAddress): int {
        $bucket = $this->getBucket($this->buildBucketKey($username, $ipAddress));
        $remaining = (int) ($bucket['locked_until'] ?? 0) - time();

        return $remaining > 0 ? $remaining : 0;
    }

    public function recordFailure(string $username, string $ipAddress): void {
        $now = time();
        $bucketKey = $this->buildBucketKey($username, $ipAddress);
        $bucket = $this->getBucket($bucketKey);
        $windowSeconds = $this->getWindowSeconds();
        $lockSeconds = $this->getLockSeconds();
        $maxAttempts = $this->getMaxAttempts();

        $lockedUntil = (int) ($bucket['locked_until'] ?? 0);
        if ($lockedUntil > $now) {
            $this->setBucket($bucketKey, $bucket);

            return;
        }

        $firstAttempt = (int) ($bucket['first_attempt'] ?? 0);
        $attempts = (int) ($bucket['attempts'] ?? 0);

        if ($firstAttempt === 0 || ($now - $firstAttempt) >= $windowSeconds) {
            $firstAttempt = $now;
            $attempts = 0;
        }

        $attempts++;

        $bucket = [
            'attempts'      => $attempts,
            'first_attempt' => $firstAttempt,
            'locked_until'  => 0,
        ];

        if ($attempts >= $maxAttempts) {
            $bucket['attempts'] = $maxAttempts;
            $bucket['locked_until'] = $now + $lockSeconds;
        }

        $this->setBucket($bucketKey, $bucket);
    }

    public function reset(string $username, string $ipAddress): void {
        delete_transient($this->buildTransientKey($this->buildBucketKey($username, $ipAddress)));
    }

    private function getMaxAttempts(): int {
        $value = (int) apply_filters('aix_live_login_max_attempts', self::DEFAULT_MAX_ATTEMPTS);

        return $value > 0 ? $value : self::DEFAULT_MAX_ATTEMPTS;
    }

    private function getWindowSeconds(): int {
        $value = (int) apply_filters('aix_live_login_window_seconds', self::DEFAULT_WINDOW_SECONDS);

        return $value > 0 ? $value : self::DEFAULT_WINDOW_SECONDS;
    }

    private function getLockSeconds(): int {
        $value = (int) apply_filters('aix_live_login_lock_seconds', self::DEFAULT_LOCK_SECONDS);

        return $value > 0 ? $value : self::DEFAULT_LOCK_SECONDS;
    }

    /**
     * @return array{attempts?: int, first_attempt?: int, locked_until?: int}
     */
    private function getBucket(string $bucketKey): array {
        $value = get_transient($this->buildTransientKey($bucketKey));

        return is_array($value) ? $value : [];
    }

    /**
     * @param array{attempts?: int, first_attempt?: int, locked_until?: int} $bucket
     */
    private function setBucket(string $bucketKey, array $bucket): void {
        $ttl = max($this->getWindowSeconds(), $this->getLockSeconds());
        $lockedUntil = (int) ($bucket['locked_until'] ?? 0);
        $remaining = $lockedUntil - time();
        if ($remaining > 0) {
            $ttl = max($ttl, $remaining);
        }

        set_transient($this->buildTransientKey($bucketKey), $bucket, $ttl);
    }

    private function buildTransientKey(string $bucketKey): string {
        return self::TRANSIENT_PREFIX . $bucketKey;
    }

    private function buildBucketKey(string $username, string $ipAddress): string {
        return hash('sha256', $this->normalizeIpAddress($ipAddress) . '|' . $this->normalizeUsername($username));
    }

    private function normalizeUsername(string $username): string {
        $username = trim(sanitize_text_field($username));
        if ($username === '') {
            return '__empty__';
        }

        return function_exists('mb_strtolower') ? mb_strtolower($username) : strtolower($username);
    }

    private function normalizeIpAddress(string $ipAddress): string {
        $ipAddress = trim(sanitize_text_field($ipAddress));

        return filter_var($ipAddress, FILTER_VALIDATE_IP) ? $ipAddress : '0.0.0.0';
    }
}
