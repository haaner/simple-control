<?php

namespace BirdWorX\SimpleControl\Basic;

use BirdWorX\Env;
use BirdWorX\ModelDb\Basic\ModelBase;
use BirdWorX\ModelDb\Basic\PathUrl;
use BirdWorX\ModelDb\Exceptions\GeneralException;
use BirdWorX\ModelDb\Exceptions\ServiceException;
use BirdWorX\ModelDb\Exceptions\UniqueException;
use BirdWorX\SimpleControl\Exceptions\UploadException;
use OptX\Basic\Session;
use ReflectionException;
use ReflectionMethod;

/**
 * Class Controller
 */
abstract class Controller extends Template {

	protected static ?string $service;

	protected string $cssDir = 'css/';
	protected string $jsDir = 'js/';
	protected string $uploadDir = 'upload/';

	protected array $cssFiles;

	/**
	 * URLs von Javscript-Dateien, die im Kopf- bzw. Footer-Bereich einer Controller-Instanz eingebunden werden
	 *
	 * @var array
	 */
	private array $jsFilesTop;

	/**
	 * Der Dateiname des zu verwendenden Basis- / Rahmen-Templates
	 */
	private string $baseTplFile;

	/**
	 * Der Dateiname des zu verwendenden Haupt-Templates
	 */
	protected string $mainTemplate;

	/**
	 * Die CSS Content-Klasse
	 */
	protected string $cssContentClass;

	/**
	 * Titel (für die Anzeige im Browser)
	 */
	private ?string $title = null;

	/**
	 * Fürs Smarty-Templating relevante Cache-ID
	 */
	protected ?string $cacheId;

	/**
	 * Fürs Smarty-Templating relevanter Hash über die Request-Variable
	 */
	private ?string $requestHash;

	protected function __construct() {
		parent::__construct();

		Session::init();

		$this->cssFiles = array();
		$this->jsFilesTop = array();

		$this->baseTplFile = '';
		$this->mainTemplate = '';
		
		$this->cacheId = null;
		$this->requestHash = null;

		$this->setCaching(); // Default Caching-Verhalten verwenden

		$this->setCssContentClass('');
	}

	/**
	 * Gibt den aus der Verkettung des Pfades mit dem Template-Namen (mit oder ohne .tpl - Endung) resultierenden Pfad zurück
	 */
	final protected static function pathedTemplateName(string $template_name, ?string $path = null): string {

		if ($path && !str_ends_with($path, '/') && (!$template_name || !str_starts_with($template_name, '/'))) {
			$path .= '/';
		}

		if ($template_name) {
			$path .= strtolower($template_name);
			if (!str_ends_with($template_name, '.tpl')) {
				$path .= '.tpl';
			}
		}

		return $path;
	}

	/**
	 * Gibt den Postfix der Klasse zurück
	 */
	public static function classPostfix(): string {
		return 'Controller';
	}

	/**
	 * Gibt die Controller-Klasse zurück, die dem Namen zugeordnet ist
	 *
	 * @param string $name
	 *
	 * @return string|null
	 */
	public static function getControllerClass(string $name): ?string {

		$postfix = static::classPostfix();
		$class_name = $name . $postfix;

		if (class_exists($class_name)) {
			return $class_name;
		} else { // Namespace berücksichtigen
			$class_name = $postfix . '\\' . $class_name;
			if (class_exists($class_name)) {
				return $class_name;
			} else {
				return null;
			}
		}
	}

	/**
	 * Bestimmt den konventionellen Pfad für den übergebenen Template-Namen (mit oder ohne .tpl - Endung) in Abhängigkeit von der aktuellen Controller-Klasse
	 *
	 * @param string|null $template_name
	 *
	 * @return string
	 */
	protected function conventionalTemplatePath(?string $template_name = null): string {

		/** @var Controller $class */
		$class = static::class;

		do {
			$dir = \BirdWorX\Utils::camelCaseToUnderscore(lcfirst($class::classPrefix()));

			if ($dir !== '') {
				$dir .= '/';
			}

			$tpl_name = static::pathedTemplateName($template_name, $dir);
			if (file_exists($this->getTemplateDir() . '/' . $tpl_name)) {
				break;
			}

			$class = get_parent_class($class);

		} while ($class);

		return $tpl_name;
	}

	/**
	 * Gibt die vollständige angefragte URL (ohne Query-Parameter) zurück
	 */
	final protected function currentSchemeUrl(): string {
		return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
	}

	final public function addCssFile($filename) {
		$path = Env::getPublicPath() . $this->cssDir . $filename;

		if (file_exists($path)) {
			$this->cssFiles[$filename] = filemtime($path);
		}
	}

	public function addJsFile($filename) {
		$path = Env::getPublicPath() . $this->jsDir . $filename;

		if (file_exists($path)) {
			$this->jsFilesTop[$filename] = filemtime($path);
		}
	}

	protected function addCss() {
	}

	protected function addJs() {
	}

	protected function setBaseTemplateVars() {

		$this->assign('title', $this->getTitle());

		$css_stylesheets = '';
		foreach ($this->cssFiles as $name => $ts) {
			$css_stylesheets .= '<link type="text/css" rel="stylesheet" href="' . $this->cssDir . $name . '?ts=' . $ts . '">' . "\n\t";
		}

		$this->assign('cssStylesheets', $css_stylesheets);

		$js_scripts_top = '';
		foreach ($this->jsFilesTop as $name => $ts) {
			$js_scripts_top .= '<script src="' . $this->jsDir . $name . '?ts=' . $ts . '"></script>' . "\n\t";
		}

		$this->assign('jsScriptsTop', $js_scripts_top);
	}

	/**
	 * Erzeugt eine eindeutige Caching-Id
	 *
	 * @return string
	 */
	protected function generateCacheId(): string {

		if ($this->cacheId === null) {
			$this->cacheId = static::class;
		}

		return $this->cacheId;
	}

	/**
	 * Ergänzt die generierte Cache-Id noch um einen Hash-Wert, der aus den Request-Variable erzeugt wird
	 */
	protected function requestCacheId(): string {

		if ($this->requestHash === null) {
			$request = '';

			foreach ($_REQUEST as $key => $value) {
				$request .= $key . '=' . json_encode($value) . '|';
			}

			$this->requestHash = md5($request);
		}

		return $this->generateCacheId() . '|' . $this->requestHash;
	}

	/**
	 * Löscht zugehörige Smarty-Caches
	 */
	final public function clearCache() {
		die('TODO');
		//Smarty_Internal_Extension_Clear::clear($this->templateSmarty, null, $this->generateCacheId(), null, null);
	}

	/**
	 * Definiert welche Datei innerhalb einer speziellen Template-Sektion unter Verwendug der übergebenen Variablen zum Einsatz kommt.
	 */
	final protected function setTemplate(string &$template_area, string $template_rel_path, array $template_vars = array()) {

		if ($template_rel_path != '' && !str_contains($template_rel_path, '/')) {
			$template_rel_path = $this->conventionalTemplatePath($template_rel_path);
		} else {
			$template_rel_path = static::pathedTemplateName($template_rel_path);
		}

		$template_area = $template_rel_path;
		$this->assign($template_vars); // TODO: Sub-Template-Variable separat vorhalten
	}

	protected function getBaseTplFile(): ?string {
		return $this->baseTplFile;
	}

	/**
	 * Setzt das Basis- / Rahmen-Template
	 *
	 * Hinweis: Diese Funktion überprüft zunächst, ob sie ausgehend von einem Konstruktor aufgerufen wird. Ist dies nicht der Fall, dann wird nicht das Basis-Template gesetzt, sondern das Main-Template - es wird aber eine Emulations-Variable ans Basis-Template übergeben, womit dieses effektiv das Main-Template als grundlegende Rahmenstruktur verwendet. Diese Vorgehensweis ist notwendig, damit die Caching-Mechanismen @param string $base_template
	 * @param array $template_vars
	 * @see isCached(), serve() nicht negativ tangiert werden.
	 *
	 */
	final protected function setBaseTemplate(string $base_template, array $template_vars = array()) {
		$caller = debug_backtrace()[1]['function'];

		if ($caller === '__construct') {
			$this->setTemplate($this->baseTplFile, $base_template, $template_vars);
		} else {
			$this->setMainTemplate($base_template, $template_vars);
			$this->setTemplate($this->baseTplFile, $this->baseTplFile, ['showMainAsBaseTemplate' => true]);
		}
	}

	/**
	 * Template-Setter
	 *
	 * @param string $main_template
	 * @param array $template_vars
	 */
	final public function setMainTemplate(string $main_template, array $template_vars = array()) {
		$this->setTemplate($this->mainTemplate, $main_template, $template_vars);

		if (file_exists($this->getTemplateDir() . $this->mainTemplate)) {
			$this->assign('mainTemplate', $this->mainTemplate);
		}
	}

	public function setCssContentClass(string $content_class) {
		$this->cssContentClass = $content_class;
	}

	public function setCssDir(string $css_dir) {
		$this->cssDir = rtrim($css_dir, '/') . '/';
	}

	public function setJsDir(string $js_dir) {
		$this->jsDir = rtrim($js_dir, '/') . '/';
	}

	public function setUploadDir(string $upload_dir) {
		$this->uploadDir = rtrim($upload_dir, '/') . '/';
	}

	protected function getTitle(): ?string {
		return $this->title;
	}

	public function setTitle(string $title) {
		$this->title = $title;
	}

	/**
	 * Gibt den Modul-Bezeichner zurück (ohne Controller, Backend, Frontend bzw. Module-Endung und mit kleingeschriebenem ersten Buchstaben)
	 *
	 * @return string
	 */
	public static function moduleIdentifier(): string {
		return lcfirst(preg_replace('/' . static::classPostfix() . '$/', '', static::className(), 1));
	}

	/**
	 * Gibt die URL des Moduls zurück
	 *
	 * @return string
	 */
	public static function moduleUrl(): string {
		return Env::getBaseUrl() . static::moduleIdentifier();
	}

	/**
	 * Gibt die URL des gewünschten Service zurück
	 *
	 * @param string $service_name
	 *
	 * @return string
	 */
	public static function serviceUrl(string $service_name): string {
		$service_name = lcfirst(str_ireplace('Service', '', $service_name));
		return Param::modifyUrl(static::moduleUrl(), array('service' => $service_name));
	}

	/**
	 * Handler zur Abwicklung von Uploads in das Standard-Uploadverzeichnis des jeweiligen Vertriebspartners
	 *
	 * @param string|null $naming_prefix Die hochgeladenen Dateien mit diesen String prefixen
	 * @param array $filenames Ermöglicht eine Vorgabe von Dateinamen (ohne Endung). Die Keys entsprechen dem name-Attribut des File-Inputs.
	 *  Nach erfolgten Upload, wird der Value durch den vollständigen Dateinamen (inklusive Endung) ersetzt.
	 * @param string|null $destination_dir Ein Zielverzeichnis relativ zum Projekt-Verzeichnis
	 *
	 * @throws UploadException
	 */
	final protected function uploadHandler(?string $naming_prefix = null, array &$filenames = array(), ?string $destination_dir = null) {

		if ($destination_dir === null) {
			$path_url = Env::getPublicPath() . $this->uploadDir;
		} else {
			$path_url = new PathUrl($destination_dir);
		}

		if (!$path_url->handleUploadFiles($filenames, $naming_prefix)) {
			throw new UploadException(error_get_last()['message']);
		}
	}

	/**
	 * Service-Funktion zur Abwicklung von Downloads aus dem Standard-Uploadverzeichnis
	 *
	 * @param string $path Pfad zur gewünschten Datei relativ zum Upload-Verzeichnis
	 * @param bool $force_dialog Soll zwingend ein Download-Dialog angezeigt werden?
	 * @param string|null $source_dir Das Quellverzeichnis relativ zum Projekt-Verzeichnis
	 * @see Controller::serve()
	 *
	 */
	final protected function downloadResourceService(string $path, bool $force_dialog = true, ?string $source_dir = null) {
		if ($source_dir === null) {
			$path_url = Env::getPublicPath() . $this->uploadDir;
		} else {
			$path_url = new PathUrl($source_dir);
		}

		Utils::initiateFileDownload($path_url->absolutePath . $path, '', $force_dialog);
	}

	private function callMethod(string $method_name) {
		$ret = false;

		try {
			$reflection = new ReflectionMethod($this, $method_name);
			$comment = $reflection->getDocComment();

			$service_args = array();
			// POST- bzw. GET-Parameter auf die Parameter der Service-Funktion übertragen
			foreach ($reflection->getParameters() as $arg) {
				if (isset($_REQUEST[$arg->name])) {
					$param_type = $arg->getType();
					if ($param_type !== null) {
						$type = str_replace('?', '', ModelBase::getType($param_type->getName()));
					} else {
						$type = ModelBase::getTypeForAnnotation($comment, $arg->name);
					}

					$service_args[$arg->name] = ModelBase::convertToType($_REQUEST[$arg->name], $type);
				} else {
					try {
						$default_value = $arg->getDefaultValue();
					} catch (ReflectionException) {
						$default_value = null;
					}
					$service_args[$arg->name] = $default_value;
				}
			}

			$ret = call_user_func_array(array($this, $method_name), $service_args);

		} catch (GeneralException|ReflectionException $ex) {

			if ($ex instanceof ServiceException) {
				$errors = $ex->getErrors();

				$http_status = ($ex instanceof UniqueException) ? 409 : 406;

				if (count($errors)) {
					Utils::dieWithJson($errors, $http_status);
				} else {
					Utils::dieWithUtf8($ex->getMessage(), $http_status);
				}
			}

			/*if (LIVE_SYSTEM) {
				ErrorLog::logException($ex);
			} else {*/
			Utils::dieWithUtf8(print_r($ex, true));
			//}
		}

		return $ret;
	}

	/**
	 * Prüft, ob eine Service-Funktion des Objekts aufgerufen werden soll und gibt gg. falls deren Resultat zurück
	 */
	private function checkServiceAndCall(): mixed {
		$ret = false;

		if (self::$service !== null) {
			$service = \BirdWorX\Utils::separatorToCamelCase(self::$service, '-');

			// Service-Methoden müssen mit 'Service' enden
			$service .= 'Service';

			if (is_callable(array($this, $service))) {
				$ret = $this->callMethod($service);
			} else {
				Utils::dieWithUtf8('Unknown service!', 404);
			}
		}

		return $ret;
	}

	/**
	 * Leitet den Client auf die angegebene URL um
	 *
	 * @param string $url Wenn leer, wird zur aktuellen Seite umgeleitet
	 */
	public static function redirect(string $url = '') {

		if ($url === '') {
			$url = trim($_SERVER['REQUEST_URI']);
		}

		Utils::redirect($url);
	}

	/**
	 * Leitet den Client permanent auf die angegebene URL um
	 */
	public static function redirectPermanent(?string $url = null) {
		header("HTTP/1.1 301 Moved Permanently");
		static::redirect($url);
	}

	public function render() {

		$this->addCss();
		$this->addJs();

		$this->setBaseTemplateVars();

		$this->display($this->baseTplFile);
	}

	/**
	 * Liefert das Resultat des gewünschten Service bzw. des Renderings an den Browser aus
	 */
	public function serve() {

		// Wenn das Template bereits gerendert im Cache liegt, sind alle nachfolgenden Operationen unnötig - einzige Ausnahme wären evtl. {nocache}-Blöcke innerhalb des Templates bzw. der darin eingebunden Sub-Templates ... die Template-Variable, die für die {nocache}-Blöcke benötigt werden, müssten dann (natürlich) trotzdem zuvor gesetzt werden ...
		if ($this->isCached()) {
			$this->display($this->baseTplFile);
		}

		if (($ret = $this->checkServiceAndCall()) === null || is_bool($ret)) {

			if ($ret === false) {
				$this->callMethod('render');
			}

			die();

		} else {

			if (is_array($ret)) {
				Utils::dieWithJson($ret);
			} else {
				Utils::dieWithUtf8($ret);
			}
		}
	}

	public static function init() {

		self::$service = null;

		// Falls JSON-Daten gesendet wurde, diese ins $_POST-Array übertragen
		if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json') {
			$raw_post_data = file_get_contents("php://input");
			if ($raw_post_data != "") {
				$_POST = json_decode($raw_post_data, true);

				if (is_array($_POST)) {
					$_REQUEST = array_merge($_REQUEST, $_POST);
				}
			}

			unset($raw_post_data);
		}
/*
		if (isset($_SERVER['HTTP_ORIGIN'])) {
			$url = parse_url($_SERVER['HTTP_ORIGIN']);

			if ($url !== false) {
				$origin = $url['scheme'] . '://' . $url['host'];
				if (array_key_exists('port', $url)) {
					$origin .= ':' . $url['port'];
				}

				// CORS - Zugriffsschutz wird nicht benötigt
				header('Access-Control-Allow-Origin: ' . $origin);

				unset($origin);
			}

			unset($url);
		}
*/
	}
}

Controller::init();