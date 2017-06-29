<?php


class Barcode {
	static
	function padSerial($format, $prefix, $serial) {
		switch ($format) {
		case 'ean13':
			$want = 12;
			break;
		default:
			throw new Exception(sprintf('unsupported format `%s`', $format)); }

		$padTo = $want - strlen($prefix);
		if ($padTo < 1)
			throw new Exception(sprintf('code format error: tried to pad to %d (%s, %s)',
				$padTo, $prefix, $serial));
		if ($padTo < strlen($serial))
			throw new Exception(sprintf('code format error: tried to pad to %d, but serial is %d already (%s, %s)',
				$padTo, strlen($serial), $prefix, $serial));
		return str_pad($serial, $padTo, '0', STR_PAD_LEFT);
	}


	/**	\p $prefix may be of any length (up to and including full code length-CSUM_LENGTH)
		\p $serial may be of any length (up to and including full code length-CSUM_LENGTH)
		\ret $serial .$csum */
	static
	function appendChecksum($format, $prefix, $serial) {
		$want = -1;

		switch ($format) {
		case 'ean13':
			$want = 12;
			$code = $prefix .$serial;
			$a = str_split($code);
			if (count($a) !== $want)
				throw new Exception(sprintf('wrong code length: got %d, want %d (code: %s)',
					count($a), $want, $code));
			foreach ($a as $key => &$digit) {
				if (!is_numeric($digit))
					throw new Exception(sprintf('want numeric code, got "%s" at offset %d (code: "%s")',
						$digit, $key, $code));
				$pos = $key + 1;
				if ($pos % 2)
					;	// take odd digits as-is
				else
					$digit *= 3; }	// weight even digits by `3'
			$base = array_sum($a) %10;
			$csum = (10 - $base) % 10;
			$code .= $csum;
			$ret = $serial .$csum;
			$want += 1;
			if (strlen($code) !== $want)
				throw new Exception(sprintf('internal error: calculated code has len %d, want %d (full code: %s)',
					strlen($code), $want, $code));
			return $ret;
		default:
			throw new Exception(sprintf('unsupported format `%s`', $format)); }
	}
}
