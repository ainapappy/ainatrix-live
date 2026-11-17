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
    private const DEFAULT_IP_MAX_ATTEMPTS = 20;
    private const DEFAULT_IP_WINDOW_SECONDS = 900;
    private const DEFAULT_IP_LOCK_SECONDS = 900;

    public function isLocked(string $username, string $ipAddress): bool {
        return $this->getLockRemainingSeconds($username, $ipAddress) > 0;
    }

    public function getLockRemainingSeconds(string $username, string $ipAddress): int {
        $userBucket = $this->getBucket($this->buildBucketKey($username, $ipAddress));
        $ipBucket = $this->getBucket($this->buildIpBucketKey($ipAddress));
        $userRemaining = (int) ($userBucket['locked_until'] ?? 0) - time();
        $ipRemaining = (int) ($ipBucket['locked_until'] ?? 0) - time();
        $remaining = max($userRemaining, $ipRemaining);

        return $remaining > 0 ? $remaining : 0;
    }

    public function recordFailure(string $username, string $ipAddress): void {
        $now = time();
        $this->updateBucket(
            $this->buildBucketKey($username, $ipAddress),
            $this->getMaxAttempts(),
            $this->getWindowSeconds(),
            $this->getLockSeconds(),
            $now
        );
        $this->updateBucket(
            $this->buildIpBucketKey($ipAddress),
            $this->getIpMaxAttempts(),
            $this->getIpWindowSeconds(),
            $this->getIpLockSeconds(),
            $now
        );
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

    private function getIpMaxAttempts(): int {
        $value = (int) apply_filters('aix_live_login_ip_max_attempts', self::DEFAULT_IP_MAX_ATTEMPTS);

        return $value > 0 ? $value : self::DEFAULT_IP_MAX_ATTEMPTS;
    }

    private function getIpWindowSeconds(): int {
        $value = (int) apply_filters('aix_live_login_ip_window_seconds', self::DEFAULT_IP_WINDOW_SECONDS);

        return $value > 0 ? $value : self::DEFAULT_IP_WINDOW_SECONDS;
    }

    private function getIpLockSeconds(): int {
        $value = (int) apply_filters('aix_live_login_ip_lock_seconds', self::DEFAULT_IP_LOCK_SECONDS);

        return $value > 0 ? $value : self::DEFAULT_IP_LOCK_SECONDS;
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

    private function buildIpBucketKey(string $ipAddress): string {
        return hash('sha256', $this->normalizeIpAddress($ipAddress) . '|__ip_only__');
    }

    private function updateBucket(string $bucketKey, int $maxAttempts, int $windowSeconds, int $lockSeconds, int $now): void {
        $bucket = $this->getBucket($bucketKey);
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
