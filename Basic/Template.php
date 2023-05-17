<?php

namespace BirdWorX\SimpleControl\Basic;

use BirdWorX\Env;
use Exception;
use BirdWorX\ModelDb\Basic\ClassBase;
use Smarty;

class Template extends ClassBase {

	protected Smarty $smarty;

	public function __construct(?Smarty $smarty = null) {

		if ($smarty) {
			$this->smarty = clone $smarty;
		} else {
			$this->smarty = new Smarty();

			$this->smarty->debug_tpl = 'file:' . Env::PROJECT_PATH . 'vendor-mod/smarty/debug.tpl';

			$this->smarty->setErrorReporting(E_ALL & ~E_NOTICE);
			$this->smarty->muteUndefinedOrNullWarnings();

			$this->smarty->setCacheDir(Env::getVarPath() . 'smarty/cache');
			$this->smarty->setCompileDir(Env::getVarPath() . 'smarty/compile');

			$this->smarty->addPluginsDir(Env::PROJECT_PATH . 'vendor-mod/smarty/plugins');
		}
	}

	public function assign($name_or_array, $value = null) {
		$this->smarty->assign($name_or_array, $value);
	}

	protected function getTemplateVars(?string $varName = null) {
		return $this->smarty->getTemplateVars($varName);
	}

	protected function getTemplateDir(): string {
		return $this->smarty->getTemplateDir(0);
	}

	public function fetch(string $tpl_file_path): ?string {

		try {
			return $this->smarty->fetch($tpl_file_path, $this->requestCacheId());
		} catch (Exception $ex) {
			if (Env::isProdSystem()) {
				error_log($ex->__toString());
			} else {
				Utils::dieWithUtf8(print_r($ex, true), 500);
			}

			return null;
		}
	}

	/**
	 * Das Caching-Verhalten festlegen
	 *
	 * @param bool $enable
	 * @param bool $renew_on_tpl_changes
	 * @param int $lifetime Angabe in Sekunden
	 */
	protected function setCaching(bool $enable = true, bool $renew_on_tpl_changes = true, int $lifetime = 3600) {

		if (Env::isDevSystem()) {
			$enable = false;
		}

		$this->smarty->caching = $enable;
		$this->smarty->compile_check = $renew_on_tpl_changes;
		$this->smarty->cache_lifetime = $lifetime;
	}

	public function setTemplateDir(string $template_dir) {
		$this->smarty->setTemplateDir($template_dir);
	}

	protected function getBaseTplFile(): ?string {
		return null;
	}

	protected function requestCacheId(): ?string {
		return null;
	}

	/**
	 * Prüft, ob für das gg.wärtige Basis-Template bereits ein gerenderte Version im Cache vorhanden ist
	 *
	 * @return bool
	 */
	protected function isCached(): bool {

		if (($cache_id = $this->requestCacheId()) !== null) {
			if (($base_tpl_file = $this->getBaseTplFile()) !== null) {

				try {
					if ($this->smarty->isCached($base_tpl_file, $cache_id)) {
						// Der auskommentierte Code ist sinnvoll fürs Debugging, um unerklärliches Verhalten bei der Verwendung von {nocache}-Blöcken nachvollziehen zu können ...

						//if(array_key_exists('isCached', $this->templateSmarty->_cache)) {
						//    foreach($this->templateSmarty->_cache['isCached'] as $cached) {
						//        /* @var Smarty_Internal_Template $cached */
						//        if($cached->cached->has_nocache_code) {
						//            return false;
						//        }
						//    }
						//}

						return true;
					}

				} catch (Exception) {
				}
			}
		}

		return false;
	}

	final protected function display(string $tpl_file_path) {
		die($this->fetch($tpl_file_path));
	}
}