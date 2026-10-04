<?php

namespace App\Support\Domains;

use InvalidArgumentException;

final class DomainFqdn
{
    /**
     * Validate a domain label (SLD only — no extension).
     *
     * @return array{value: string, error: string|null, detected_tld: string|null}
     */
    public static function validateLabel(string $input): array
    {
        $raw = trim($input);
        $value = strtolower($raw);
        $error = null;
        $detectedTld = null;

        if ($value === '') {
            return ['value' => '', 'error' => 'Enter a domain name.', 'detected_tld' => null];
        }

        if (preg_match('/\s/u', $value)) {
            $error = 'Spaces are not allowed. Enter only the domain name.';
            $value = preg_replace('/\s+/u', '', $value) ?? $value;
        }

        if (str_contains($value, '.')) {
            $parts = explode('.', $value, 2);
            $value = preg_replace('/[^a-z0-9-]/', '', $parts[0]) ?? $parts[0];
            $maybeTld = preg_replace('/[^a-z0-9-]/', '', $parts[1] ?? '') ?? '';
            if ($maybeTld !== '') {
                $detectedTld = $maybeTld;
            }
            $error = $error ?? 'Do not include an extension here. Choose it from the dropdown.';
        } else {
            $sanitized = preg_replace('/[^a-z0-9-]/', '', $value);
            if ($sanitized !== $value) {
                $error = $error ?? 'Use letters, numbers, and hyphens only.';
                $value = $sanitized ?? $value;
            }
        }

        if (strlen($value) > 63) {
            $value = substr($value, 0, 63);
            $error = $error ?? 'Domain name cannot exceed 63 characters.';
        }

        if ($value === '') {
            return ['value' => '', 'error' => $error ?? 'Enter a domain name.', 'detected_tld' => $detectedTld];
        }

        if (str_starts_with($value, '-') || str_ends_with($value, '-')) {
            return [
                'value' => $value,
                'error' => $error ?? 'Domain name cannot start or end with a hyphen.',
                'detected_tld' => $detectedTld,
            ];
        }

        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $value)) {
            return [
                'value' => $value,
                'error' => $error ?? 'Domain name contains invalid characters.',
                'detected_tld' => $detectedTld,
            ];
        }

        return ['value' => $value, 'error' => $error, 'detected_tld' => $detectedTld];
    }

    /**
     * @return array{sld: string, tld: string, fqdn: string}
     */
    public static function parse(string $sld, string $tld): array
    {
        $sld = strtolower(trim($sld));
        $tld = strtolower(ltrim(trim($tld), '.'));

        if ($sld === '' || $tld === '') {
            throw new InvalidArgumentException('Enter a domain name and extension.');
        }

        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $sld)) {
            throw new InvalidArgumentException('Domain name contains invalid characters.');
        }

        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $tld)) {
            throw new InvalidArgumentException('Extension is invalid.');
        }

        return [
            'sld' => $sld,
            'tld' => $tld,
            'fqdn' => $sld.'.'.$tld,
        ];
    }

    /**
     * Normalize a hostname.
     *
     * @param  bool  $apexOnly  When true (buy/register), only example.com is allowed.
     *                          When false (connect existing), subdomains and multi-part
     *                          hosts like app.example.com or example.co.uk are allowed.
     */
    public static function normalizeFqdn(string $input, bool $apexOnly = true): string
    {
        $value = strtolower(trim($input));
        $value = preg_replace('#^https?://#', '', $value) ?? $value;
        $value = rtrim($value, '/');
        $value = explode('/', $value)[0];
        $value = explode('?', $value)[0];
        $value = explode('#', $value)[0];
        $value = rtrim($value, '.');

        if ($value === '' || ! str_contains($value, '.')) {
            throw new InvalidArgumentException('Enter a valid domain name (e.g. example.com).');
        }

        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $value)) {
            throw new InvalidArgumentException('Domain format is invalid.');
        }

        if ($apexOnly) {
            self::assertApexOnly($value);
        }

        return $value;
    }

    /**
     * @return array{sld: string, tld: string, fqdn: string}
     */
    public static function fromFqdn(string $fqdn, bool $apexOnly = true): array
    {
        $fqdn = self::normalizeFqdn($fqdn, $apexOnly);
        $parts = explode('.', $fqdn);
        $tld = array_pop($parts);
        $sld = implode('.', $parts);

        if ($sld === '' || $tld === '') {
            throw new InvalidArgumentException('Domain format is invalid.');
        }

        return ['sld' => $sld, 'tld' => $tld, 'fqdn' => $fqdn];
    }

    private static function assertApexOnly(string $fqdn): void
    {
        if (substr_count($fqdn, '.') !== 1) {
            throw new InvalidArgumentException('Enter an apex domain only (e.g. example.com). Subdomains and multi-part extensions like example.co.uk are not supported yet.');
        }
    }
}
