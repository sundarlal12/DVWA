<?php

namespace Src;

use OpenApi\Attributes as OAT;

#[OAT\Schema(required: ['token'])]
class Token {
	private const ENCRYPTION_CIPHER = "aes-128-gcm";
	private const ENCRYPTION_KEY = "Paintbrush";

    # Not sure if this is needed
    #[OAT\Property(example: "11111")]
	public string $token;

	private string $secret;
	private int $expires;

	public function __construct () {
	}

	private static function encrypt($cleartext) {
		$ivlen = openssl_cipher_iv_length(self::ENCRYPTION_CIPHER);
		$iv = openssl_random_pseudo_bytes($ivlen);
		$ciphertext = openssl_encrypt($cleartext, self::ENCRYPTION_CIPHER, self::ENCRYPTION_KEY, $options=0, $iv, $tag);
		$ret = base64_encode ($tag . ":::::" . $iv . ":::::" . $ciphertext);
		return $ret;
	}

 /**
  * Decrypts a given ciphertext using OpenSSL and verifies its integrity.
  * @param {string} ciphertext - The encrypted text in base64 format, constructed with components separated by ":::::".
  * @returns {string|boolean} Returns the decrypted cleartext if successful, or false on failure.
  * @description
  *   - The function expects the ciphertext to contain exactly three components: tag, iv, and value, separated by ":::::".
  *   - Utilizes OpenSSL's AES-256-GCM encryption cipher for decryption.
  *   - May return false if the components of the ciphertext are not as expected or if decryption fails.
  */
	private static function decrypt($ciphertext) {
		$str = base64_decode ($ciphertext);
		$bits = explode (":::::", $str);
		if (count ($bits) != 3) {
			return false;
		}
		$value = $bits[2];
		$iv = $bits[1];
		$tag = $bits[0];
		$cleartext = openssl_decrypt($value, self::ENCRYPTION_CIPHER, self::ENCRYPTION_KEY, $options=0, $iv, $tag);
		return $cleartext;
	}
	public function create_token($secret, $expires) {
		$token = self::encrypt (json_encode (array (
						"secret" => $secret,
						"expires" => $expires,
					)));
		return $token;
	}

	public function decrypt_token($token) {
		$decrypted = self::decrypt($token);

		if ($decrypted === false) {
			return false;
		}

		$token = json_decode ($decrypted, true);
		return $token;
	}
}

?>
