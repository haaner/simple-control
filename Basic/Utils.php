<?php

namespace BirdWorX\SimpleControl\Basic;

use BirdWorX\ModelDb\Basic\PathUrl;

abstract class Utils extends \BirdWorX\Utils {

	/**
	 * Gibt den übergeben Parameter als getrimmten, UTF8-kodierten String zurück
	 */
	private static function toUtf8(string $str): string {

		$str = trim($str);
		if (!mb_detect_encoding($str, 'utf-8', true)) {
			$str = utf8_encode($str);
		}

		return $str;
	}

	/**
	 * Beendet den PHP-Prozess und gibt neben der übergebenen Meldung zuvor
	 * einen Header aus, der das Encoding auf utf8 einstellt.
	 *
	 * TODO: In Controller-Klasse migrieren
	 *
	 * @param string $msg
	 * @param int $http_status_code
	 * @param string $content_type
	 */
	public static function dieWithUtf8(string $msg = '', int $http_status_code = 200, string $content_type = 'text/plain') {

		$msg = self::toUtf8($msg);

		if ($_SERVER !== null) {
			if ($http_status_code != 200) {
				http_response_code($http_status_code);
			}

			if ($msg !== '') {
				header('Content-Type: ' . $content_type . '; charset=utf-8');
			}
		}

		die($msg);
	}

	/**
	 * Beendet den PHP-Prozess und gibt eine JSON-Antwort zurück,
	 * die den Inhalt des übergebenen Arrays enthält.
	 *
	 * TODO: In Controller-Klasse migrieren
	 *
	 * @param array $response
	 * @param int $http_status_code
	 */
	public static function dieWithJson(array $response = array(), int $http_status_code = 200) {

		if ($http_status_code != 200) {
			http_response_code($http_status_code);
		}

		header('Content-Type: application/json');

		$msg = json_encode($response);

		if (!mb_detect_encoding($msg, 'utf-8', true)) {
			$msg = utf8_encode($msg);
		}

		die($msg);
	}

	/**
	 * Initiiert eine Datei-Download
	 *
	 * @param string $file_path Absoluter Pfad zur Datei
	 * @param string|null $file_name Wenn gleich null oder '' wird der Dateiname der per Dateipfad referenzierten Datei verwendet
	 *
	 * @param bool $force_download Wenn TRUE wird zwingend ein Download-Dialog geöffnet
	 */
	public static function initiateFileDownload(string $file_path, ?string $file_name = null, bool $force_download = true) {

		if (file_exists($file_path)) {

			if (is_dir($file_path)) {
				$path_url = new PathUrl($file_path, $file_path[0] === '/');
				Utils::dieWithUtf8('Der Zugriff auf die URL "' . $path_url->absoluteUrl . '" ist untersagt! ', 403);
			}

			$file_name = trim($file_name);
			$file_path_name = basename($file_path);

			if ($file_name == '') {
				$file_name = $file_path_name;
			}

			if ($force_download) {
				// Die Dateiendung anhand des Dateipfades ermitteln
				$pos = strrpos($file_path_name, '.');

				if ($pos !== false) {
					$ext = '.' . substr($file_path_name, $pos + 1);
				} else {
					$pos = strrpos($file_name, '.');

					if ($pos !== false) {
						$ext = '.' . substr($file_name, $pos + 1);
					} else {
						$ext = '';
					}
				}

				// Die Endung des Dateipfades verwenden
				$file_name = preg_replace('/\.[^.]+$/', '', $file_name) . $ext;

				if (str_contains($file_name, ' ')) {
					$file_name = '"' . $file_name . '"';
				}

				header('Content-Disposition: attachment; filename=' . $file_name);

				if ($ext == 'zip') {
					$content_type = 'application/zip';
				} else {
					$content_type = 'application/octet-stream';
				}

			} else {
				$content_type = mime_content_type($file_path);
			}

			header('Content-Type: ' . $content_type);
			header('Content-Length: ' . filesize($file_path));
			header('Connection: close');

			readfile($file_path);
			die();

		} else {
			Utils::dieWithUtf8('Die gewünschte Datei "' . $file_path . '" ist nicht vorhanden!', 404);
		}
	}

	public static function redirect(string $url, $http_status = 302) {

		if ($_SERVER['HTTP_X_FETCH'] && $http_status === 302) {
			// Leider ist das Redirect-Handling von fetch "fucked up", deshalb Workaround, @see: https://github.com/whatwg/fetch/issues/763#issuecomment-1430650132
			$http_status = 204;
		}

		header('Location: ' . $url, true, $http_status);
		die();
	}

	/**
	 * Löscht den spezifizierten Pfad rekursiv
	 */
	public static function runlink(string $path) {

		if (is_dir($path)) {
			$objects = scandir($path);
			foreach ($objects as $object) {
				if ($object != '.' && $object != '..') {
					if (is_dir($path . '/' . $object) && !is_link($path . '/' . $object)) {
						self::runlink($path . '/' . $object);
					} else {
						unlink($path . '/' . $object);
					}
				}
			}
			rmdir($path);
		} else {
			unlink($path);
		}
	}
}