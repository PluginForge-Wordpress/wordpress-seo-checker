<?php
/**
 * Copyright (c) Since 2024 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Plugin\PluginForgeTools\Services;

use InvalidArgumentException;

class UrlSecurityValidator
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    private const ALLOWED_PORTS = [80, 443];

    private const HOSTNAME_DENY_PATTERNS = [
        '/(^|\.)localhost$/i',
        '/(^|\.)local$/i',
        '/(^|\.)internal$/i',
        '/(^|\.)home$/i',
        '/(^|\.)lan$/i',
        '/(^|\.)corp$/i',
        '/(^|\.)site$/i',
        '/(^|\.)test$/i',
        '/^127(\.\d{1,3}){3}$/',
        '/^0\./',
    ];

    public function validate(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            throw new InvalidArgumentException('url_required');
        }

        if (function_exists('idn_to_ascii')) {
            $parts = parse_url($trimmed);
            if (! empty($parts['host'])) {
                $encodedHost   = idn_to_ascii($parts['host'], IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $parts['host'];
                $parts['host'] = $encodedHost;
                $trimmed       = $this->buildUrl($parts);
            }
        }

        $parts = parse_url($trimmed);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('url_invalid');
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new InvalidArgumentException('url_invalid');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('url_domain_invalid');
        }

        if (isset($parts['port']) && ! in_array((int) $parts['port'], self::ALLOWED_PORTS, true)) {
            throw new InvalidArgumentException('url_domain_invalid');
        }

        $host = strtolower(trim($parts['host'], '.'));
        if ($host === '') {
            throw new InvalidArgumentException('url_domain_invalid');
        }

        foreach (self::HOSTNAME_DENY_PATTERNS as $pattern) {
            if (preg_match($pattern, $host)) {
                throw new InvalidArgumentException('url_domain_invalid');
            }
        }

        if (! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new InvalidArgumentException('url_domain_invalid');
        }

        if (! $this->isAllowedHost($host)) {
            throw new InvalidArgumentException('url_ip_reserved');
        }

        $normalized = $this->buildUrl($parts);
        if (mb_strlen($normalized) > 2048) {
            throw new InvalidArgumentException('url_invalid');
        }

        return $normalized;
    }

    private function buildUrl(array $parts): string
    {
        $scheme   = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $host     = $parts['host'] ?? '';
        $port     = isset($parts['port']) ? ':'.$parts['port'] : '';
        $user     = $parts['user'] ?? '';
        $pass     = isset($parts['pass']) ? ':'.$parts['pass'] : '';
        $pass     = ($user || $pass) ? $pass.'@' : '';
        $userpass = $user.$pass;
        $path     = $parts['path'] ?? '';
        $query    = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        if ($path === '') {
            $path = '/';
        }

        return $scheme.$userpass.$host.$port.$path.$query.$fragment;
    }

    private function isAllowedHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isAllowedIp($host);
        }

        if (! function_exists('dns_get_record')) {
            return true;
        }

        $recordsA    = @dns_get_record($host.'.', DNS_A);
        $recordsAAAA = @dns_get_record($host.'.', DNS_AAAA);
        $ips         = [];
        foreach (is_array($recordsA) ? $recordsA : [] as $row) {
            if (! empty($row['ip'])) {
                $ips[] = (string) $row['ip'];
            }
        }
        foreach (is_array($recordsAAAA) ? $recordsAAAA : [] as $row) {
            if (! empty($row['ipv6'])) {
                $ips[] = (string) $row['ipv6'];
            }
        }
        if ($ips === []) {
            return true;
        }
        foreach ($ips as $ip) {
            if (! $this->isAllowedIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private function isAllowedIp(string $ip): bool
    {
        $ipVersion = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 4 : (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 6 : 0);
        if ($ipVersion === 0) {
            return false;
        }

        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        if ($ipVersion === 4) {
            return (bool) filter_var($ip, FILTER_VALIDATE_IP, $flags);
        }

        $normalized = (string) inet_ntop((string) inet_pton($ip));
        if (str_starts_with($normalized, '::') || str_starts_with(strtolower($normalized), 'fe80') || str_starts_with(strtolower($normalized), 'fc') || str_starts_with(strtolower($normalized), 'fd')) {
            return false;
        }

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, $flags);
    }
}
