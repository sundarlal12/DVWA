<?php

namespace Src;

use OpenApi\Attributes as OAT;

class Login
{
	private const ACCESS_TOKEN_LIFE = 180;
	private const ACCESS_TOKEN_SECRET = "12345";
	private const REFRESH_TOKEN_LIFE = 240;
	private const REFRESH_TOKEN_SECRET = "98765";
	
	public static function create_token() {
		$now = time();
		$tokenObj = new Token();
		$token = json_encode (array (
			"access_token" => $tokenObj->create_token(self::ACCESS_TOKEN_SECRET, $now + self::ACCESS_TOKEN_LIFE),
			"refresh_token" => $tokenObj->create_token(self::REFRESH_TOKEN_SECRET, $now + self::REFRESH_TOKEN_LIFE),
			"token_type" => "bearer",
			"expires_in" => self::ACCESS_TOKEN_LIFE)
		);
		return $token;
	}

 /**
  * Checks the validity of an access token.
  * @example
  * $isValid = check_access_token('exampleToken123');
  * echo $isValid // true or false;
  * @param {string} $token - The access token to be validated.
  * @returns {bool} True if the token is valid, false otherwise.
  * @description
  *   - Relies on a separate Token object to decrypt the token.
  *   - A valid token requires a matching secret and an expiration date in the future.
  */
	public static function check_access_token($token) {
		$tokenObj = new Token();
		$decrypted = $tokenObj->decrypt_token ($token);

		if ($decrypted === false) {
			return false;
		}
		if ($decrypted['secret'] == self::ACCESS_TOKEN_SECRET && $decrypted['expires'] > time()) {
			return true;
		}
		return false;
	}

	public static function check_refresh_token($token) {
		$tokenObj = new Token();
		$decrypted = $tokenObj->decrypt_token ($token);

		if ($decrypted['secret'] == self::REFRESH_TOKEN_SECRET && $decrypted['expires'] > time()) {
			return true;
		}
		return false;
	}
}
