<?php
/**
 * Local IPv4 and IPv6 country lookup for DB-IP Country Lite CSV data.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\service;

class country_index
{
	const MAX_UPLOAD = 67108864;
	protected const HEADER = "ASGIP01\n";
	protected const RECORD_SIZE = 35;
	protected const MAX_RECORDS = 2500000;
	protected const MAX_EXPANDED = 268435456;

	protected $path;
	protected $reader;
	protected $size = 0;

	public function __construct($root_path)
	{
		$this->path = $root_path . 'store/antispamguard-country.dat';
	}

	public function available()
	{
		if ($this->reader === null)
		{
			$this->reader = @fopen($this->path, 'rb');
			if ($this->reader !== false)
			{
				$file = fstat($this->reader);
				$data_size = $file['size'] - strlen(self::HEADER);
				if ($data_size <= 0 || $data_size % self::RECORD_SIZE !== 0
					|| fread($this->reader, strlen(self::HEADER)) !== self::HEADER)
				{
					fclose($this->reader);
					$this->reader = false;
				}
				else
				{
					$this->size = intdiv($data_size, self::RECORD_SIZE);
				}
			}
		}
		return $this->reader !== false;
	}

	public function updated_at()
	{
		return $this->available() ? (int) fstat($this->reader)['mtime'] : 0;
	}

	public function country($ip)
	{
		if (stripos($ip, '::ffff:') === 0)
		{
			$mapped = inet_pton($ip);
			if ($mapped !== false && substr($mapped, 0, 12) === str_repeat("\0", 10) . "\xff\xff")
			{
				$ip = inet_ntop(substr($mapped, 12));
			}
		}
		if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) || !$this->available())
		{
			return '';
		}

		$key = $this->encode_ip($ip);
		$low = 0;
		$high = $this->size - 1;
		while ($low <= $high)
		{
			$middle = intdiv($low + $high, 2);
			if (fseek($this->reader, strlen(self::HEADER) + ($middle * self::RECORD_SIZE)) !== 0)
			{
				return '';
			}
			$record = fread($this->reader, self::RECORD_SIZE);
			if ($record === false || strlen($record) !== self::RECORD_SIZE)
			{
				return '';
			}

			if (strcmp($key, substr($record, 0, 17)) < 0)
			{
				$high = $middle - 1;
			}
			else if (strcmp($key, $record[0] . substr($record, 17, 16)) > 0)
			{
				$low = $middle + 1;
			}
			else
			{
				$country = substr($record, -2);
				return $country === 'ZZ' ? '' : $country;
			}
		}
		return '';
	}

	public function import($source)
	{
		$file = @fopen($source, 'rb');
		if ($file === false)
		{
			return false;
		}
		$compressed = fread($file, 2) === "\x1f\x8b";
		$checksum = null;
		$footer = '';
		if ($compressed)
		{
			if (!in_array('compress.zlib', stream_get_wrappers(), true))
			{
				fclose($file);
				return false;
			}
			if (fseek($file, -8, SEEK_END) !== 0 || strlen($footer = fread($file, 8)) !== 8)
			{
				fclose($file);
				return false;
			}
			$checksum = hash_init('crc32b');
			fclose($file);
			$file = @fopen('compress.zlib://' . $source, 'rb');
			if ($file === false)
			{
				return false;
			}
		}
		else
		{
			rewind($file);
		}

		$ipv4 = tmpfile();
		$ipv6 = tmpfile();
		$output = @tempnam(dirname($this->path), 'asg-geo-');
		try
		{
			if ($ipv4 === false || $ipv6 === false || $output === false
				|| realpath(dirname($output)) !== realpath(dirname($this->path)))
			{
				return false;
			}
			$streams = array('4' => $ipv4, '6' => $ipv6);
			$count = array('4' => 0, '6' => 0);
			$previous = array('4' => null, '6' => null);

			$first_line = true;
			$expanded = 0;
			while (($line = fgets($file, 512)) !== false)
			{
				$expanded += strlen($line);
				if ($checksum !== null)
				{
					hash_update($checksum, $line);
				}
				if ($expanded > self::MAX_EXPANDED || strlen($line) >= 511)
				{
					return false;
				}
				if ($first_line && substr($line, 0, 3) === "\xef\xbb\xbf")
				{
					$line = substr($line, 3);
				}
				$first_line = false;
				if (trim($line) === '')
				{
					continue;
				}
				// The three supported CSV fields cannot contain a comma or line break.
				$row = array_map(function ($value)
				{
					return trim($value, "\" \t\r\n");
				}, explode(',', $line));
				if (count($row) !== 3 || !preg_match('/^[A-Z]{2}$/D', $row[2]))
				{
					return false;
				}
				$from = $this->encode_ip($row[0]);
				$to = $this->encode_ip($row[1]);
				if ($from === '' || $to === '' || $from[0] !== $to[0] || strcmp($from, $to) > 0)
				{
					return false;
				}

				$family = $from[0];
				if (($previous[$family] !== null && strcmp($from, $previous[$family]) <= 0)
					|| ++$count[$family] > self::MAX_RECORDS
					|| fwrite($streams[$family], $from . substr($to, 1) . $row[2]) !== self::RECORD_SIZE)
				{
					return false;
				}
				$previous[$family] = $to;
			}

			$total = array_sum($count);
			// Some zlib wrappers accept a truncated gzip footer as an ordinary EOF.
			if ($checksum !== null && (bin2hex(strrev(substr($footer, 0, 4))) !== hash_final($checksum)
				|| unpack('Vlength', substr($footer, 4, 4))['length'] !== $expanded))
			{
				return false;
			}
			if (!feof($file) || $total < 1 || !$this->write_index($output, $streams, $count)
				|| !@rename($output, $this->path))
			{
				return false;
			}
			if (is_resource($this->reader))
			{
				fclose($this->reader);
			}
			$this->reader = null;
			return $total;
		}
		finally
		{
			fclose($file);
			foreach (array($ipv4, $ipv6) as $stream)
			{
				if (is_resource($stream))
				{
					fclose($stream);
				}
			}
			if ($output !== false && is_file($output))
			{
				@unlink($output);
			}
		}
	}

	protected function write_index($output, array $streams, array $count)
	{
		$target = @fopen($output, 'wb');
		if ($target === false)
		{
			return false;
		}
		try
		{
			if (fwrite($target, self::HEADER) !== strlen(self::HEADER))
			{
				return false;
			}
			foreach ($streams as $family => $stream)
			{
				rewind($stream);
				if (stream_copy_to_stream($stream, $target) !== $count[$family] * self::RECORD_SIZE)
				{
					return false;
				}
			}
			return fflush($target);
		}
		finally
		{
			fclose($target);
		}
	}

	protected function encode_ip($ip)
	{
		if (!filter_var($ip, FILTER_VALIDATE_IP))
		{
			return '';
		}
		$packed = inet_pton($ip);
		return strlen($packed) === 4 ? '4' . str_repeat("\0", 12) . $packed : '6' . $packed;
	}
}
