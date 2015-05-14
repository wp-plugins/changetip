<?php
/**
 * Modifield from the original to accept none hex uuid namespaces.
 * For original see:
 * @author Andrew Moore
 * @link http://www.php.net/manual/en/function.uniqid.php#94959
 */
class CTUUID
{
	/**
	 * Generate v5 UUID
	 *
	 * Version 5 UUIDs are named based. They require a namespace (another
	 * valid UUID) and a value (the name). Given the same namespace and
	 * name, the output is always the same.
	 *
	 * @param	uuid	$namespace
	 * @param	string	$name
	 */
	public static function v5($namespace, $name)
	{
		$nhex = $namespace;

		// Binary Value
		$nstr = '';

		// Convert Namespace UUID to bits
		for($i = 0; $i < strlen($nhex); $i+=2)
		{
			$nstr .= chr(hexdec($nhex[$i].$nhex[$i+1]));
		}

		// Calculate hash value
		$hash = sha1($nstr . $name);

		return sprintf('%08s-%04s-%04x-%04x-%12s',

		// 32 bits for "time_low"
		substr($hash, 0, 8),

		// 16 bits for "time_mid"
		substr($hash, 8, 4),

		// 16 bits for "time_hi_and_version",
		// four most significant bits holds version number 5
		(hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,

		// 16 bits, 8 bits for "clk_seq_hi_res",
		// 8 bits for "clk_seq_low",
		// two most significant bits holds zero and one for variant DCE1.1
		(hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,

		// 48 bits for "node"
		substr($hash, 20, 12)
		);
	}
}
?>