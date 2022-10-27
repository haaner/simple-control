<?php

namespace BirdWorX\SimpleControl\Basic;

class Param {

	/**
	 * Gibt den ersten, gesetzten Wert aus der Kette $_GET, $_POST, $_COOKIE, $_SESSION zurück.
	 *
	 * @param string $key
	 *
	 * @return string|null
	 */
	public static function get(string $key): ?string {

		foreach ([$_GET, $_POST] as $arr) {
			if (isset($arr[$key])) {
				return $arr[$key];
			}
		}

		/*if(($val = Cookie::get($key)) !== null) {
			return $val;
		}

		return Session::get($key);
		*/

		return null;
	}

	private static function stringToArray(?string $param_string): array {

		$param_string = ltrim($param_string, '?');
		$pairs = explode('&', $param_string);

		$params = [];
		foreach ($pairs as $pair) {
			list($key, $value) = explode('=', $pair, 2);
			$params[$key] = $value;
		}

		return $params;
	}

	/**
	 * Bildet aus dem übergebenen Key-Value Array einen Query-String und hängt ihn unter Zuhilfenahme des
	 * Operators (? bzw. &) an den Url-String an. Alternativ kann auch ein String der Form "key1=value1&key2=value2"
	 * anstatt des Key-Value Arrays übergeben werden. Durch Übergabe von leeren Parametern, in der Form 'param' => null,
	 * bzw. 'param=&next_param=value' können in der URL bereits vorhandene Parameter auch entfernt werde.
	 *
	 * @param string $url
	 * @param string|array $params
	 *
	 * @return string
	 */
	public static function modifyUrl(string $url, mixed $params): string {

		if (!is_array($params)) {
			$params = self::stringToArray($params);
		}

		if (str_contains($url, '?')) {
			$result = explode('?', $url);
			$url = $result[0];

			$params = array_merge(self::stringToArray($result[1]), $params);
		}

		foreach ($params as $key => &$param) {
			if (trim($param) == '') {
				unset($params[$key]);
			} else {
				$param = $key . '=' . $param;
			}
		}

		$query = implode('&', $params);

		if ($query !== '') {
			$url .= '?' . $query;
		}

		return $url;
	}
}