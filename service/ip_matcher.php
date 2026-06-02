<?php
/**
 * AntiSpam Guard — IP list matching (exact, wildcard, CIDR).
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\service;

class ip_matcher
{
    /**
     * @param string $ip
     * @param string $list  Newline- or comma-separated entries
     */
    public function matches_list($ip, $list)
    {
        $ip = trim((string) $ip);

        if ($ip === '' || trim((string) $list) === '')
        {
            return false;
        }

        $items = preg_split('/[\r\n,]+/', (string) $list);

        foreach ($items as $item)
        {
            $item = trim($item);

            if ($item === '')
            {
                continue;
            }

            if ($this->entry_matches($ip, $item))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $ip
     * @param string $list
     * @return array{matched:bool,entry:string}
     */
    public function whitelist_match($ip, $list)
    {
        $ip = trim((string) $ip);

        if ($ip === '' || trim((string) $list) === '')
        {
            return array('matched' => false, 'entry' => '');
        }

        $entries = preg_split('/\r\n|\r|\n/', (string) $list);

        foreach ($entries as $entry)
        {
            $entry = trim($entry);

            if ($entry === '' || strpos($entry, '#') === 0)
            {
                continue;
            }

            if ($this->entry_matches($ip, $entry))
            {
                return array('matched' => true, 'entry' => $entry);
            }
        }

        return array('matched' => false, 'entry' => '');
    }

    public function entry_matches($ip, $entry)
    {
        $ip = trim((string) $ip);
        $entry = trim((string) $entry);

        if ($entry === '' || $ip === '')
        {
            return false;
        }

        if ($entry === $ip)
        {
            return true;
        }

        if (strpos($entry, '*') !== false)
        {
            $pattern = '/^' . str_replace('\\*', '.*', preg_quote($entry, '/')) . '$/i';

            return (bool) preg_match($pattern, $ip);
        }

        if (strpos($entry, '/') !== false)
        {
            return $this->cidr_matches($ip, $entry);
        }

        return false;
    }

    public function cidr_matches($ip, $cidr)
    {
        if (strpos($cidr, '/') === false)
        {
            return false;
        }

        $parts = explode('/', $cidr, 2);

        if (count($parts) !== 2)
        {
            return false;
        }

        $subnet = trim($parts[0]);
        $bits = (int) trim($parts[1]);

        $ip_bin = @inet_pton($ip);
        $subnet_bin = @inet_pton($subnet);

        if ($ip_bin === false || $subnet_bin === false || strlen($ip_bin) !== strlen($subnet_bin))
        {
            return false;
        }

        $max_bits = strlen($ip_bin) * 8;

        if ($bits < 0 || $bits > $max_bits)
        {
            return false;
        }

        $full_bytes = (int) floor($bits / 8);
        $remaining_bits = $bits % 8;

        if ($full_bytes > 0 && substr($ip_bin, 0, $full_bytes) !== substr($subnet_bin, 0, $full_bytes))
        {
            return false;
        }

        if ($remaining_bits === 0)
        {
            return true;
        }

        $mask = (0xff << (8 - $remaining_bits)) & 0xff;

        return ((ord($ip_bin[$full_bytes]) & $mask) === (ord($subnet_bin[$full_bytes]) & $mask));
    }

    public function normalize_list($raw_ips)
    {
        $items = preg_split('/[\r\n,]+/', (string) $raw_ips);
        $ips = array();

        foreach ($items as $item)
        {
            $ip = trim($item);

            if ($ip === '')
            {
                continue;
            }

            if (strpos($ip, '/') !== false)
            {
                list($address, $prefix) = explode('/', $ip, 2);
                $address = trim($address);
                $prefix = trim($prefix);

                if (filter_var($address, FILTER_VALIDATE_IP) && ctype_digit($prefix))
                {
                    $max_prefix = (strpos($address, ':') !== false) ? 128 : 32;
                    $prefix = (int) $prefix;

                    if ($prefix >= 0 && $prefix <= $max_prefix)
                    {
                        $ips[$address . '/' . $prefix] = $address . '/' . $prefix;
                    }
                }
            }
            else if (filter_var($ip, FILTER_VALIDATE_IP))
            {
                $ips[$ip] = $ip;
            }
        }

        ksort($ips);

        return implode("\n", $ips);
    }
}