<?php

namespace App\Services;

class NetworkService
{
    /**
     * Get all LAN IP addresses of this machine.
     *
     * @return array<string>
     */
    public function getLanIps(): array
    {
        $ips = [];

        // Windows: use ipconfig
        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('ipconfig');
            if ($output) {
                preg_match_all('/IPv4.*?:\s*([\d.]+)/', $output, $matches);
                foreach ($matches[1] ?? [] as $ip) {
                    if ($ip !== '127.0.0.1' && !str_starts_with($ip, '169.254.')) {
                        $ips[] = $ip;
                    }
                }
            }
        }

        // macOS / Linux: use ifconfig or ip
        if (PHP_OS_FAMILY === 'Darwin' || PHP_OS_FAMILY === 'Linux') {
            $output = shell_exec('ifconfig 2>/dev/null || ip addr 2>/dev/null');
            if ($output) {
                preg_match_all('/inet\s+([\d.]+)/', $output, $matches);
                foreach ($matches[1] ?? [] as $ip) {
                    if ($ip !== '127.0.0.1' && !str_starts_with($ip, '169.254.')) {
                        $ips[] = $ip;
                    }
                }
            }
        }

        // Fallback: gethostname
        if (empty($ips)) {
            $hostname = gethostname();
            if ($hostname) {
                $resolved = gethostbyname($hostname);
                if ($resolved && $resolved !== $hostname && $resolved !== '127.0.0.1') {
                    $ips[] = $resolved;
                }
            }
        }

        // Common hotspot IPs are typically 192.168.43.x (Android) or 172.20.10.x (iOS)
        // Sort to prioritize hotspot-like IPs
        usort($ips, function ($a, $b) {
            $hotspotPrefixes = ['192.168.43.', '192.168.137.', '172.20.10.'];
            $aIsHotspot = false;
            $bIsHotspot = false;
            foreach ($hotspotPrefixes as $prefix) {
                if (str_starts_with($a, $prefix)) $aIsHotspot = true;
                if (str_starts_with($b, $prefix)) $bIsHotspot = true;
            }
            if ($aIsHotspot && !$bIsHotspot) return -1;
            if (!$aIsHotspot && $bIsHotspot) return 1;
            return 0;
        });

        return array_values(array_unique($ips));
    }

    /**
     * Get the first (most likely hotspot) LAN IP.
     */
    public function getPrimaryLanIp(): ?string
    {
        $ips = $this->getLanIps();
        return $ips[0] ?? null;
    }

    /**
     * Get the server port (default 8000).
     */
    public function getServerPort(): int
    {
        return (int) env('COUP_SERVER_PORT', 8000);
    }

    /**
     * Get the WebSocket port (default 8080).
     */
    public function getWebSocketPort(): int
    {
        return (int) env('REVERB_PORT', 8080);
    }
}
