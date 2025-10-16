<?php

namespace App\Service\Logging;

class AnonymizeIpProcessor
{
    private const IPV4_PATTERN = '/\b((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/';
    private const IPV6_PATTERN = '/\b([0-9a-fA-F]{0,4}:){2,7}[0-9a-fA-F]{0,4}\b/';

    public function __invoke(array $record): array
    {
        $record['message'] = $this->anonymizeString($record['message']);
        $record['context'] = $this->anonymizeArray($record['context']);
        $record['extra'] = $this->anonymizeArray($record['extra']);

        return $record;
    }

    private function anonymizeArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = $this->anonymizeString($value);
            } elseif (is_array($value)) {
                $values[$key] = $this->anonymizeArray($value);
            }
        }

        return $values;
    }

    private function anonymizeString(string $value): string
    {
        $ipv4Masked = preg_replace_callback(self::IPV4_PATTERN, fn ($matches) => $this->maskIpv4($matches[0]), $value);
        if (is_string($ipv4Masked)) {
            $value = $ipv4Masked;
        }

        $ipv6Masked = preg_replace_callback(self::IPV6_PATTERN, fn ($matches) => $this->maskIpv6($matches[0]), $value);

        return is_string($ipv6Masked) ? $ipv6Masked : $value;
    }

    private function maskIpv4(string $ip): string
    {
        $parts = explode('.', $ip);
        $parts[3] = '0';

        return implode('.', $parts);
    }

    private function expandIpv6(string $ip): string
    {
        if (str_contains($ip, '::')) {
            [$start, $end] = explode('::', $ip) + [1 => ''];
            $startSegments = $start === '' ? [] : explode(':', $start);
            $endSegments = $end === '' ? [] : explode(':', $end);
            $missingSegments = array_fill(0, 8 - (count($startSegments) + count($endSegments)), '0');
            $segments = array_merge($startSegments, $missingSegments, $endSegments);
        } else {
            $segments = explode(':', $ip);
        }

        $segments = array_map(static fn ($segment) => str_pad($segment, 4, '0', STR_PAD_LEFT), $segments);

        return implode(':', $segments);
    }

    private function maskIpv6(string $ip): string
    {
        $expanded = $this->expandIpv6($ip);
        $segments = explode(':', $expanded);
        $segments[count($segments) - 1] = '0000';

        $binary = '';
        foreach ($segments as $segment) {
            $binary .= pack('H*', $segment);
        }

        $compressed = @inet_ntop($binary);

        return is_string($compressed) ? $compressed : implode(':', $segments);
    }
}

