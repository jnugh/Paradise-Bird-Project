<?php
/*
 * This file is part of Paradise-Bird-Project.

 * Paradise-Bird-Project is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * Paradise-Bird-Project is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with Paradise-Bird-Project.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * Paketmanager des Paradise Bird Project
 * @author: Jonas Schwabe
 * @copyright: 2009
 * @license: GPL
 * @version: 0.1.0
 */
if(!defined('ROOTPATH'))
define('ROOTPATH', './');
/**
 * Paketmanager Klasse
 * @author Jonas Schwabe
 */
class pm{
	/**
	 * Geladene Pakete
	 * @var array
	 */
	private $_loaded = array();
	/**
	 * Installierte Pakete werden aus der Konfigurationsdatei geladen
	 * @var array
	 */
	private $_installed = array();
	/**
	 * Fehlercodes cachen
	 * @var array
	 */
	private $_errcodes = array();
	/**
	 * Fehlercodes oder Aehnliches
	 * @var string
	 */
	private $_debugcodes = '';
	/**
	 * Ordner und Dateien für komplettes Backup
	 * @var array
	 */
	private $_fullBackupFiles = array();
	/**
	 * Backup Methode true->volles Backup false->Abhaengiges Backup
	 * @var bool
	 */
	private $_backupType = false;
	/**
	 * Sourcecode of template
	 * @var string
	 */
	private $_tplSource = '';
	//Funktionen
	/**
	 * Konstruktor laed KOnfigurationen
	 * @return bool
	 */
	public function pm(){
		if(!$this->_errcodes = $this->_loadLangFile(ROOTPATH."pm/lang/de/error.ini.php"))
		die('Errorfile cannot be loaded.');
		if(count(parse_ini_file(ROOTPATH."pm/config/installed.ini.php")) != 0){
			if(!$this->_installed = $this->_loadPackList(ROOTPATH."pm/config/installed.ini.php")){
				$this->showError('PACKLIST_WRONG');
				return false;
			}
			if(!$this->_loadPackages($this->_installed)){
				$this->showError('PACKLIST_WRONG');
				return false;
			}
		}
		$backup = parse_ini_file(ROOTPATH . 'pm/config/backup.ini.php', false);
		foreach($backup['file'] as $file){
			if(!file_exists(ROOTPATH . $file)){
				$this->_errcodes .= "\n<p>" . $this->parseLangConst('BACKUP_FILE_NOT_EXISTS') . $file . "</p>";
				return false;
			}
			$this->_fullBackupFiles[] = ROOTPATH . $file;
		}
	}
	/**
	 * Laed eine Paketliste wie beispielsweise eine Liste der Installierten Pakete
	 * @param $cfgfile Listendatei
	 * @return bool/array
	 */
	private function _loadPackList($cfgfile){
		if(!file_exists($cfgfile))
		return false;
		if(!$installed = parse_ini_file($cfgfile, true))
		return false;
		$installed = array_change_key_case($installed, CASE_LOWER); //Nur Kleinbuchstaben
		foreach($installed as $id => $cfg){
			if(!preg_match("![a-z_0-9]!", $id))
			echo $id;
		}
		return $installed;
	}
	/**
	 * Paketliste laden (nur init pakete)
	 * @param $packlist array
	 * @return bool
	 */
	private function _loadPackages($packlist){
		if(!is_array($packlist))
		return false;
		foreach($packlist as $id => $cfg){ //Liste durcharbeiten...
			if($cfg['active'] != true)
			continue;
			if($cfg['type'] != 'init')
			continue;
			if(!$this->_loadPack($id, $packlist))
			return false;
		}
		return true;
	}
	/**
	 * Paket einbinden
	 * @param $packname string Paketname
	 * @param $packlist array Paketliste
	 * @return bool
	 */
	private function _loadPack($packname, $packlist){
		if($this->_isLoaded($packname))
		return true;
		if(!is_array($packlist))
		return false;
		if(!isset($packlist[$packname]))
		return false;
		if($packlist[$packname]['active'] != true)
		return false;
		//Abhaengigkeiten laden...
		if(isset($packlist[$packname]['depend_runtime']) && is_array($packlist[$packname]['depend_runtime'])){
			foreach($packlist[$packname]['depend_runtime'] as $depend){
				if(!$this->_loadPack($depend, $packlist))
				return false;
			}
		}
		if(isset($packlist[$packname]['depend_recommend']) && is_array($packlist[$packname]['depend_recommend'])){
			foreach($packlist[$packname]['depend_recommend'] as $depend){
				$this->_loadPack($depend, $packlist);
			}
		}
		if(!is_dir(ROOTPATH.'pack/' . $packname) || !file_exists('pack/' . $packname . '/_init.php'))
		return false;
		$modpath = 'pack/' . $packname . '/';
		$config = $this->_parseConfig($packname);
		$pm = $this;
		require(ROOTPATH.'pack/' . $packname . '/_init.php');
		$this->_loaded[] = $packname;
		return true;
	}
	/**
	 * Ueberprueft ob ein Paket geladen ist
	 * @param $packname string
	 * @return bool
	 */
	private function _isLoaded($packname){
		if(in_array($packname, $this->_loaded))
		return true;
		return false;
	}
	/**
	 * Kombiniert 2 Listen
	 * @param $list1 erste Liste
	 * @param $list2 zweite Liste
	 * @return bool/array
	 */
	private function _syncList($list1, $list2){
		if(!is_array($list1) || !is_array($list2))
		return false;
		$listed = array();
		foreach($list1 as $id => $cfg){
			$listed[] = $id;
		}
		foreach($list2 as $id => $cfg){
			if(!in_array($id, $listed, true))
			continue;
			if(($compare = $this->_compareVersion($cfg['version'], $list1[$id]['version'])) == 2)
			$list1[$id] = $cfg;
		}
		return $list1;
	}
	/**
	 * 1 => Version1 < Version2, 2 => Version2 < Version1, 0 => gleich
	 * @param $version1 versionsnummer mit "."
	 * @param $version2 versionsnummer mit "."
	 * @return int
	 */
	private function _compareVersion($version1, $version2){
		$version1 = explode('.', $version1);
		$version2 = explode('.', $version2);
		if($version1[0] < $version2[0])
		return 1;
		else if($version1[0] > $version2[0])
		return 2;
		if($version1[1] < $version2[1])
		return 1;
		else if($version1[1] > $version2[1])
		return 2;
		if($version1[2] < $version2[2])
		return 1;
		else if($version1[2] > $version2[2])
		return 2;
		return 0;
	}
	/**
	 * Lead eine Sprachdatei (errorcodes)
	 * @param $langfile string Dateiname
	 * @return bool/array
	 */
	private function _loadLangFile($langfile){
		if(!file_exists($langfile))
		return false;
		$return = parse_ini_file($langfile);
		if(!$return)
		return false;
		return $return;
	}
	/**
	 * Laed ein Index-Paket
	 * @param $index string Paketname
	 * @return bool
	 */
	public function openIndex($index){
		if(!isset($this->_installed[$index]['type']) || !$this->_installed[$index]['type'] == 'index'){
			$this->showError('404', 404);
			return false;
		}
		if($this->_loadPack($index, $this->_installed))
		return true;
		$this->showError('404', 404);
		return false;
	}
	/**
	 * Gibt einen Fehlercode aus und setzt ggf den HTTP Status
	 * @param $errorcode string Fehlercode
	 * @param $http int HTTP Status (e.g. 200)
	 * @return void
	 */
	public function showError($errorcode, $http = 200){
		header('HTTP/ '.$http);
		$errorcode = $this->parseLangConst($errorcode);
		include(ROOTPATH.'pm/include/tpl/error.tpl.php');
		exit();
	}
	public function showPackError($errorcode, $package, $http = 200, $referBack = false, $referUrl = false){
		header('HTTP/ '.$http);
		$errorcode = $this->parsePackLangConst($errorcode, $package);
		include(ROOTPATH.'pm/include/tpl/error.tpl.php');
		exit();
	}
	/**
	 * Sprachkonstante "uebersetzen"
	 * @param $errorcode Sprachkonstante
	 * @return string
	 */
	public function parseLangConst($errorcode){
		if(!isset($this->_errcodes[$errorcode]))
		$errorcode = "n/a";
		else
		$errorcode = $this->_errcodes[$errorcode];
		return $errorcode;
	}
	public function parsePackLangConst($code, $package){
		if(!isset($this->_errcodes[$package]))
		$this->_errcodes[$package] = $this->_loadLangFile(ROOTPATH . 'pack/' . $package . '/_lang/de/lang.ini.php');
		if(!isset($this->_errcodes[$package][$code]))
		$errorcode = "n/a";
		else
		$errorcode = $this->_errcodes[$package][$code];
		return $errorcode;
	}
	/**
	 * Gibt ein Array mit allen Sprachkonstanten des Paketes zurück
	 * @param $package
	 * @return unknown_type
	 */
	public function listPackLangConst($package){
		if(!isset($this->_errcodes[$package]))
		$this->_errcodes[$package] = $this->_loadLangFile(ROOTPATH . 'pack/' . $package . '/_lang/de/lang.ini.php');
		if(!isset($this->_errcodes[$package]))
		return false;
		return $this->_errcodes[$package];
	}
	/**
	 * Installation eines Paketes
	 * @param $packname string Paketname
	 * @param $packfile string Datei (ZIP Format)
	 * @param $remote bool Datei von remote Server kopieren
	 * @param $force bool Fehler teilweise ignorieren
	 * @return bool
	 */
	public function installPack($packname, $packfile = '', $remote = false, $force = false){
		//Paket entpacken und pruefen
		if($packfile){
			$this->backup($this->_backupType, $this->parseLangConst('BACKUP_INSTALL') . $packname);
			require_once(ROOTPATH.'pm/include/pclzip.lib.php');
			if(!file_exists($packfile)){
				$this->_debugcodes .= "\n<p>" . $this->parseLangConst('PACK_NOT_EXISTS') . $packname . '</p>';
				return false;
			}
			$zip = new PclZip($packfile);
			if ($zip->extract(PCLZIP_OPT_PATH, ROOTPATH.'pm/tmp') == 0) {
				$this->_debugcodes("\n<p>" . $this->parseLangConst('EXTRACT_ERROR') .$zip->errorInfo(true) . '</p>');
			}
		}
		if(!is_dir(ROOTPATH.'pm/tmp/' . $packname)){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('UNPACK_NOT_FOUND') . '</p>';
			return false;
		}
		$packdir = ROOTPATH.'pm/tmp/' . $packname . '/';
		$srcdir = $packdir . 'src/';
		if(!file_exists($packdir . 'pack.ini.php') || !($install_ini = parse_ini_file($packdir . 'pack.ini.php', true))){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('CORRUPT_SETUP_FILE') . '</p>';
			return false;
		}
		if(!is_dir($srcdir) || !file_exists($srcdir . '_init.php')){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('CORRUPT_PACK') . '</p>';
			return false;
		}
		if(!isset($install_ini[$packname])){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('CORRUPT_PACK') . '</p>';
			return false;
		}
		//Paket sollte OK sein.
		$this->_debugcodes .= "\n<p>" . $this->parseLangConst('INSTALL') . $packname . '</p>';
		$directini = $install_ini[$packname];
		//Abhaengigkeiten laden...
		if(isset($directini['depend_install'])){
			foreach($directini['depend_install'] as $depend){
				if($this->_isLoaded($depend)){
					continue;
				}else if($this->_isInstalled($depend)){
					$this->_loadPack($depend, $this->_installed);
					continue;
				}
				$this->_debugcodes .= "\n<p>" . $this->parseLangConst('INSTALL_DEP') . $depend . '</p>';
				$this->_debugcodes .= '<blockquote>';
				if(!$this->installPack($depend, false, false))
				{
					$this->_debugcodes .= '</blockquote>';
					$this->_debugcodes .= "\n<p>" . $this->parseLangConst('DEPEND_LOAD_ERROR') . '</p>';
					if(!$force)
					return false;
				}
				$this->_loadPack($depend, $this->_installed);
				$this->_debugcodes .= '</blockquote>';
			}
		}
		if(isset($directini['depend_runtime'])){
			foreach($directini['depend_runtime'] as $depend){
				if(!$this->_isInstalled($depend))
				continue;
				$this->_debugcodes .= "\n<p>" . $this->parseLangConst('INSTALL_DEP') . $depend . '</p>';
				$this->_debugcodes .= '<blockquote>';
				if(!$this->installPack($depend, false, false))
				{
					$this->_debugcodes .= '</blockquote>';
					$this->_debugcodes .= "\n<p>" . $this->parseLangConst('DEPEND_LOAD_ERROR') . '</p>';
					if(!$force)
					return false;
				}
				$this->_debugcodes .= '</blockquote>';
			}
		}
		//Ueberpruefen ob Paket installiert ist
		if($this->_isInstalled($packname) && is_dir(ROOTPATH.'pack/' . $packname)){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('ALREADY_INSTALLED') . '</p>';
			//Versionsnummern vergleichen
			$compared = $this->_compareVersion($directini['version'], $this->_installed[$packname]['version']);
			if($compared == 2 || $compared == 0){
				//Neuer oder gleich
				//Vergleichen per funktion damit auch fuer UI nutzbar
				if($install_ini['SETUP']){
					if(!$this->checkIntregrit($packname, $install_ini['SETUP'])){
						$this->_debugcodes .= "\n<p>" . $this->parseLangConst('INSTALLED_FILES_CHANGED') . '</p>';
						if(!$force)
						return false;
					}
				}
			} else {
				//Aelter
				$this->_debugcodes .= "\n<p>" . $this->parseLangConst('ALREADY_INSTALLED_NEWER') . '</p>';
				if(!$force)
				return false;
			}
		} else if(is_dir(ROOTPATH.'pack/' . $packname)){
			$this->_debugcodes .= "\n<p><a href=\"?p=clean_pm\">" . $this->parseLangConst('ALREADY_EXISTING') . '</a></p>';
			if(!$force)
			return false;
		} else if($this->_isInstalled($packname)){
			$this->_debugcodes .= "\n<p><a href=\"?p=clean_pm\">" . $this->parseLangConst('ALREADY_INSTALLED_NO_FILES') . '</a></p>';
			if(!$force)
			return false;
		}
		if(!$this->_copyReq($srcdir, ROOTPATH.'pack/' . $packname)){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('COPY_FAILED') . '</p>';
			return false;
		}
		$this->_debugcodes .= "\n<p>" . $this->parseLangConst('COPY_COMPLETED') . '</p>';
		if(!$this->_addPack($packname, $directini)){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('INI_ERROR') . '</p>';
			return false;
		}
		//In die INI eintragen
		if(!$this->_writeIniFile($this->_installed, ROOTPATH . 'pm/config/installed.ini.php', true)){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('INI_ERROR') . '</p>';
			return false;
		}
		$this->_debugcodes .= "\n<p>" . $this->parseLangConst('INSTALL_COMPLETE') . '</p>';
		return true;
	}
	/**
	 * Parst eine INI Datei, ohne ueber Zeichen wie / oder aehnlichem "zu stolpern"
	 * Parst ausserdem auch mehrdimensionale Arrays fehlerfrei
	 * Dennoch sollte parse_ini_file, grade wegen der niedrigeren Tolleranzgrenze verwendet werden
	 * Diese Funktion nur verwenden wenn nicht anders moeglich, andere Funktion ist besser erprobt.
	 * @param $file string Dateiname
	 * @param $has_sections bool Sektionen
	 * @return array/bool
	 */
	private function _toleranceParseIniFile($file, $has_sections = false){
		if(!file_exists($file))
		return false;
		$file = fopen($file, 'r');
		if(!$file)
		return false;
		$return = array();
		$section = 0; //Section ([*] inder ini Datei) initialisieren
		while(!feof($file)){
			$line = fgets($file, 1000);
			if(preg_match("!^;!", $line))
			continue;
			$line = preg_replace("!\r|\n$!", '', $line);
			if($has_sections && preg_match("!^\[.*\]$!", $line)){ //Ueberpruefen ob eine Section geparst wurde
				$section = preg_replace("!^\[(.*)\]$!", "$1", $line);
				continue;
			}
			$line = explode("=", $line);
			if(count($line) != 2)
			continue;
			/*
			 * Strings Formatieren
			 */
			$line[0] = preg_replace("!^ !", '', $line[0]);
			$line[0] = preg_replace("! $!", '', $line[0]);
			$line[1] = preg_replace("!^ !", '', $line[1]);
			$line[1] = preg_replace("! $!", '', $line[1]);
			$line[1] = preg_replace("!\r|\n$!", '', $line[1]);
			$line[1] = preg_replace("!^\"!", '', $line[1]);
			$line[1] = preg_replace("!\"$!", '', $line[1]);
			/*
			 * Ende
			 */
			$array_names = array(); //Variable fuer mehrdimensionale arrays in INI Dateien
			$var = '';
			while(true){
				if(!$has_sections)
				$var = '$return';
				else
				$var = '$return["'  .$section . '"]';
				if(!preg_match('!\[.*\]!', $line[0])){
					eval($var . "['" . str_replace("'", "\\'", $line[0]) . "']='" . str_replace("'", "\\'", $line[1]) . "';");
				}
				$var .= '["' . preg_replace("!\[.*\]!", '', $line[0]) . '"]';
				$array_names[] = preg_replace('!.*?\[(.*?)\]\[.*\].*!', "$1", $line[0], 1);
				if(in_array($line[0], $array_names)){
					$array_names[array_search($line[0], $array_names)] = preg_replace('!.*\[(.*?)\].*!', "$1", $line[0], 1);
				}
				foreach($array_names as $name){
					if($name == '')
					$var .= '[]';
					else
					$var .= '["' . $name . '"]';
				}
				$buffer = $line[0];
				$line[0] = preg_replace('!\[.*?\](\[.*\].*)!', "$1", $line[0], 1);
				if($buffer == $line[0])
				$line[0] = preg_replace('!\[.*?\]!', '', $line[0], 1);
				if(!preg_match('!\[.*\]!', $line[0])){
					eval($var . "='" . str_replace("'", "\\'", $line[1]) . "';");
					break;
				}
			}
		}
		fclose($file);
		return $return;
	}
	/**
	 * INI Datei schreiben
	 * @param $assoc_arr array Zu schreibende Werte
	 * @param $path string Speicherpfad
	 * @param $has_sections bool Sektionen verwenden
	 * @return bool
	 */
	private function _writeIniFile($assoc_arr, $path, $has_sections=FALSE) {
		$content = ";<?php die('PDBP'); ?>\n";

		if ($has_sections) {
			foreach ($assoc_arr as $key=>$elem) {
				$content .= "[".$key."]\n";
				foreach ($elem as $key2=>$elem2)
				{
					if(is_array($elem2))
					{
						for($i=0;$i<count($elem2);$i++)
						{
							$content .= $key2."[] = \"".$elem2[$i]."\"\n";
						}
					}
					else if($elem2=="") $content .= $key2." = \n";
					else $content .= $key2." = \"".$elem2."\"\n";
				}
			}
		}
		else {
			foreach ($assoc_arr as $key=>$elem) {
				if(is_array($elem))
				{
					for($i=0;$i<count($elem);$i++)
					{
						$content .= $key."[] = \"".$elem[$i]."\"\n";
					}
				}
				else if($elem=="") $content .= $key." = \n";
				else $content .= $key." = \"".$elem."\"\n";
			}
		}

		if (!$handle = @fopen($path, 'w')) {
			return false;
		}
		if (!fwrite($handle, $content)) {
			return false;
		}
		fclose($handle);
		return true;
	}
	/**
	 * Paket in die INI hinzufuegen
	 * @param $packname string Paketname
	 * @param $settings array Einstellungen fuer die Installation
	 * @return unknown_type bool
	 */
	private function _addPack($packname, $settings){
		if(!isset($settings) || !is_array($settings))
		return false;
		$packname = strtolower($packname);
		if(!preg_match('![a-z_0-9]!', $packname))
		return false;
		else if(!$this->_isInstalled($packname)){
			$this->_installed[$packname] = $settings;
		}else if($this->_isInstalled($packname)){
			//Mehr beim Update?!
			$this->_installed[$packname] = $settings;
		} else
		return false;
		return true;
	}
	/**
	 * Ausagbe der Debug Variable
	 * @return string
	 */
	public function getDebugCode(){
		return $this->_debugcodes;
	}
	/**
	 * Entfernt ein Paket vom Server
	 * @param $package string Paketname
	 * @param $remove_deps bool Abhaengigkeiten automatisch loeschen
	 * @param $take_backup bool Backup erstellen
	 * @return bool
	 */
	public function removePack($package, $remove_deps = false, $take_backup = true){
		$this->_debugcodes .= "\n<p>" . $this->parseLangConst('REMOVE_PACK') . " <b>" . $package . "</b></p>";
		if(!$this->_isInstalled($package)){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('NOT_INSTALLED') . "</p>";
			return false;
		}
		if($take_backup)
		$this->backup($this->_backupType, $this->parseLangConst('BACKUP_REMOVE') . $package);
		if($depend = $this->_dependExists($package)){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('DEPEND_EXISTS') . "</p>";
			foreach($depend as $pack){
				$this->_debugcodes .= "\n<blockquote>";
				if($remove_deps){
					$this->removePack($pack, true, false);
				}
				$this->_debugcodes .= "\n</blockquote>";
			}
			if(!$remove_deps)
			return false;
		}
		if(!is_dir(ROOTPATH . 'pack/' . $package)){
			$this->_debugcodes .= "\n<p><a href=\"?p=clean_pm\">" . $this->parseLangConst('ALREADY_INSTALLED_NO_FILES') . '</a></p>';
			return false;
		}
		if(file_exists(ROOTPATH . 'pack/' . $package . '/remove.php')){
			include(ROOTPATH . 'pack/' . $package . '/remove.php');
			$package_remove = $package . '_remove';
			if(function_exists($package_remove) && $return = $package_remove() !== true){
				$this->_debugcodes .= "\n<p>" . $this->parseLangConst('REMOVE_ROUTINE_FAILED') . $return . '</p>';
				return false;
			}
		}
		if(!$this->_reqRemove(ROOTPATH . 'pack/' . $package)){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('REMOVE_FILES_FAILED') . '</p>';
			return false;
		}
		unset($this->_installed[$package]);
		if(!$this->_writeIniFile($this->_installed, ROOTPATH . 'pm/config/installed.ini.php', true)){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('REMOVE_INI_FAILED') . '</p>';
			return false;
		}
		$this->_debugcodes .= "\n<p>" . $this->parseLangConst('REMOVE_COMPLETED') . '</p>';
		return true;
	}
	/**
	 * Rekursives Dateien entfernen
	 * @param $filename string Dateiname
	 * @return bool
	 */
	private function _reqRemove($filename){
		$filename = preg_replace("!\/$!", '', $filename);
		if(!file_exists($filename))
		return false;
		if(!is_dir($filename)){
			return @unlink($filename);
		} else {
			$dir = opendir($filename);
			if(!$dir)
			return false;
			while($file = readdir($dir)){
				if($file == '.' || $file == '..')
				continue;
				if(!$this->_reqRemove($filename . '/' . $file))
				return false;
			}
			closedir($dir);
			return @rmdir($filename);
		}
	}
	public function addRepo(){

	}
	public function removeRepo(){

	}
	public function refreshRepo(){

	}
	public function checkUpdate(){

	}
	public function installRepo(){

	}
	public function search(){

	}
	public function activePack(){

	}
	public function deactivePack(){

	}
	/**
	 * Ueberprueft ob Abhaengigkeiten existieren
	 * @param $package string Paketname
	 * @return array/bool
	 */
	private function _dependExists($package){
		if(!$this->_isInstalled($package))
		return false;
		$return = array();
		foreach($this->_installed as $name => $pack){
			if(isset($pack['depend_runtime']) && is_array($pack['depend_runtime'])){
				foreach($pack['depend_runtime'] as $dep){
					if($dep == $package)
					$return[] = $name;
				}
			}
		}
		if(!count($return))
		return false;
		return $return;
	}
	/**
	 * Ueberpruefen ob ein Paket installiert ist
	 * @param $packname string Paketname
	 * @return bool
	 */
	private function _isInstalled($packname){
		if(isset($this->_installed[$packname]))
		return true;
		return false;
	}
	/**
	 * Rekursives kopieren von Ordnern und Dateien
	 * @param $src string Ausgangspfad
	 * @param $dest string Einfuegungspfad
	 * @param $silence bool debugconstanten setzen
	 * @return bool
	 */
	private function _copyReq($src, $dest, $silence = false){
		//Komplettes verzeichniss rekursiv kopieren
		if(!$silence){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('SOURCE') ." " . $src . "</p>";
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('DESTINATION') . " " . $dest . "</p>";
		}
		//Endenden Slash entfernen
		$src = preg_replace('!\/$!', '', $src);
		$dest = preg_replace('!\/$!', '', $dest);
		//Ordner erzeugen falls nicht existent
		if(!is_dir(dirname($dest)) && file_exists(dirname($dest)))
		return false; //Das ist kein Ordner...
		if(is_dir($src) && !is_dir($dest)){
			if(file_exists($dest))
			return false; //Ist eine Datei und kein Ordner...
			if(!@mkdir($dest))
			return false;
		}
		if(is_dir($src)){
			//Es handelt sich um ein Verzeichniss... kopieren :)
			if(!$dir = opendir($src))
			return false;
			$this->_debugcodes .= '<blockquote>';
			while($item = readdir($dir)){
				if($item == '.' || $item == '..')
				continue;
				if(!$this->_copyReq($src . '/' . $item, $dest . '/' . $item))
				return false;
			}
			$this->_debugcodes .= '</blockquote>';
			closedir($dir);
		} else if(file_exists($src)) {
			if(!@copy($src, $dest))
			return false;
		} else
		return false;
		return true;
	}
	/**
	 * Testet ob Dateien die ueberschrieben werden sollen veraendert wurden
	 * @param $packname string Paketname
	 * @param $cfg array Konfigurationsdatei
	 * @return bool
	 */
	public function checkIntregrit($packname, $cfg){
		if(!$this->_isInstalled($packname))
		return true;
		if(!is_array($cfg))
		return false;
		if(isset($cfg['md5']) && is_array($cfg['md5'])){
			foreach($cfg['md5'] as $hash){
				$hash = explode(";", $hash);
				if($this->_compareVersion($hash[0], $this->_installed[$packname]['version']) != 0)
				continue;
				if(!file_exists(ROOTPATH . 'pack/' . $packname . '/' . $hash[1]))
				continue;
				if(md5_file(ROOTPATH . 'pack/' . $packname . '/' . $hash[1]) != $hash[2])
				return false;
			}
		}
		return true;
	}
	/**
	 * Backup Routine aufrufen
	 * @param $full bool volles backup?
	 * @param $comment string Kommentar fuer backup_dep
	 * @return bool
	 */
	public function backup($full, $comment = 'Backup'){
		if(!$full){
			return $this->_backupDep($comment);
		} else {
			return $this->_backupFull();
		}
	}
	/**
	 * Inkomplettes Backup
	 * @param $comment string Kommentar
	 * @return bool
	 */
	private function _backupDep($comment = 'Backup'){
		$this->_debugcodes .= "\n<p>" . $this->parseLangConst('BUP_MAKE_DEP') .  "</p>";
		$file_hash = $this->_getMd5Fs();
		if(!$file_hash){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('BUP_NO_DEP_EXISTS') .  "</p>";
			$this->_debugcodes .= "\n<blockquote>";
			if(file_exists(ROOTPATH . 'pm/backup/dep/bup/1.zip')){
				$this->_debugcodes .= "\n<p>" . $this->parseLangConst('BUP_DEP_CORRUPT') .  "</p>";
				return false;
			}
			$list = $this->_backupFull(ROOTPATH . 'pm/backup/dep/bup/1.zip');
			$this->_debugcodes .= "\n</blockquote>";
			$id = 1;
		} else {
			$merged = $this->_mergeMd5Fs($file_hash, ROOTPATH);
			$dir = opendir(ROOTPATH . 'pm/backup/dep/bup');
			if(!$dir){
				$this->_debugcodes .= "\n<p>" . $this->parseLangConst('BUP_DEP_BUP_NOT_OPENED') .  "</p>";
				return false;
			}
			$ids = array();
			while($file = readdir($dir)){
				if($file == '.' || $file == '..')
				continue;
				if(!preg_match("!^[0-9]*\.zip$!", $file))
				continue;
				$ids[] = preg_replace("!^([0-9]*)\.zip$!", "$1", $file);
			}
			sort($ids);
			$id = $ids[count($ids) -1] +1;
			closedir($dir);
			require_once(ROOTPATH.'pm/include/pclzip.lib.php');
			$zip = new PclZip(ROOTPATH . 'pm/backup/dep/bup/' . $id . '.zip');
			$list = $zip->create($merged);
		}
		$this->_debugcodes .= "\n<p>" . $this->parseLangConst('BUP_WRITE_MD5_FS') .  "</p>";
		if(false === $changes = $this->_writeMd5Fs(ROOTPATH . 'pm/backup/dep/data/md5fs.ini.php', ROOTPATH . 'pm/backup/dep/data/' . $id . '.ini.php', $list, $this->_toleranceParseIniFile(ROOTPATH . 'pm/backup/dep/data/md5fs.ini.php'), $comment)){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('BUP_WRITE_MD5_FS_FAILED') . "</p>";
			return false;
		}
		$this->_debugcodes .= "\n<p>" . $this->parseLangConst('BUP_EDITED_FILES') . $changes .  "</p>";
	}
	/**
	 * Erstellt eine Dateiliste mit geaenderten Dateien
	 * @param $md5fs array letzte Liste
	 * @param $filename string Dateiname oder Ordner zum starten
	 * @param $return array Temporaer (Rekursivitaet)
	 * @return array/bool
	 */
	private function _mergeMd5Fs($md5fs, $filename, $return = array()){
		if(!file_exists($filename))
		return false;
		if(!is_readable($filename))
		return $return;
		$filename = preg_replace("!\/$!", '', $filename);
		if(is_dir($filename)){
			$dir = opendir($filename);
			while($file = readdir($dir)){
				if($file == '.' || $file == '..')
				continue;
				$return = $this->_mergeMd5Fs($md5fs, $filename . '/' . $file, $return);
			}
			closedir($dir);
		} else {
			$filename = preg_replace("!^\.\/!", '', $filename);
			if(preg_match("!^pm\/backup!", $filename))
			return $return;
			if(!in_array(ROOTPATH . preg_replace("!^(.*?)\/.*!", "$1", $filename), $this->_fullBackupFiles))
			return $return;
			if(!isset($md5fs[$filename])){
				$return[] = $filename;
				return $return;
			} else if($md5fs[$filename] != md5_file($filename)){
				$return[] = $filename;
				return $return;
			} else
			return $return;
		}
		return $return;
	}
	/**
	 * Schreibt Daten zu inkompletten Backups
	 * @param $file string Dateiname der Dateiliste
	 * @param $bup_file string Dateiname der Backup-informationsdatei
	 * @param $list array Aktuelle Dateiliste
	 * @param $mergewith array Liste (vorzugsweise aus $file erstellt)
	 * @param $comment string Kommentar
	 * @return bool/int
	 */
	private function _writeMd5Fs($file, $bup_file, $list, $mergewith = array(), $comment = 'Backup'){
		if(!$mergewith)
		$mergewith = array();
		$info = array();
		$info['time'] = time();
		$info['comment'] = $comment;
		if(!is_array($list) || !is_array($mergewith))
		return false;
		if(file_exists($bup_file)){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('BUP_DEP_CORRUPT_MD5_FS') .  "</p>";
			return false;
		}
		$count = 0;
		foreach($mergewith as $filename => $file_merge){
			if(!file_exists($filename)){
				$info['deleted'][] = $filename;
				unset($mergewith[$filename]);
				$count++;
			}
		}
		foreach($list as $file_merge){
			if(is_dir($file_merge['filename'])){
				if(!isset($mergewith[$file_merge['filename']]))
				$count++;
				$mergewith[$file_merge['filename']] = 'dir';
				continue;
			}
			$hash = md5_file($file_merge['filename']);
			if(isset($mergewith[$file_merge['filename']]) && $mergewith[$file_merge['filename']] == $hash)
			continue;
			$mergewith[$file_merge['filename']] = $hash;
			$info['edited'][] = $file_merge['filename'];
			$count ++;
		}
		$this->_writeIniFile($mergewith, $file, false);
		$this->_writeIniFile($info, $bup_file, false);
		return $count;
	}
	/**
	 * Parst die aktuelle Liste aller Dateien
	 * @return array/bool
	 */
	private function _getMd5Fs(){
		if(!file_exists(ROOTPATH . 'pm/backup/dep/data/md5fs.ini.php'))
		return false;
		$ini = $this->_toleranceParseIniFile(ROOTPATH . 'pm/backup/dep/data/md5fs.ini.php');
		if(!$ini)
		return false;
		return $ini;
	}
	/**
	 * Erstellt ein Backup aller Dateien, die in backup.ini.php eingestellt sind (rekursiv)
	 * /pm/backup* wird ignoriert, da die Dateigröße der Backups sonst exponentiell ansteigen wuerde.
	 * Diese "Blacklist" wurde direkt in pclzip.lib.php intregriert, ein direktes Update ist so nicht mehr ohne weiteres moeglich.
	 * @param $bup_file string Dateiname zum speichern
	 * @return bool/array
	 */
	private function _backupFull($bup_file = ''){ //TODO Datenbankbackup
	$date = date('Y_m_d_H_i_s', time());
	if(!$bup_file)
	$bup_file = ROOTPATH . 'pm/backup/full/' . $date . '.zip';
	require_once(ROOTPATH.'pm/include/pclzip.lib.php');
	$this->_debugcodes .= "\n<p>" . $this->parseLangConst('BUP_FULL') . $bup_file .  "</p>";
	if(file_exists($bup_file)){
		$this->_debugcodes .= "\n<p>" . $this->parseLangConst('BUP_FULL_EXISTS') . $bup_file .  "</p>";
		return false;
	}
	$zip = new PclZip($bup_file);
	if(!$list = $zip->create($this->_fullBackupFiles)){
		$this->_debugcodes .= "\n<p>" . $this->parseLangConst('BUP_ERROR_PCLZIP') . $zip->errorInfo(true) .  "</p>";
		return false;
	}
	$this->_debugcodes .= "\n<p>" . $this->parseLangConst('BUP_DONE') . "</p>";
	if($bup_file != ROOTPATH . 'pm/backup/full/' . $date . '.zip')
	$this->_debugcodes .= "\n<p><a href=\"" . $bup_file . "\">" . $this->parseLangConst('BUP_DOWNLOAD') . "</a></p>";
	else{
		if($this->_isInstalled('backup_loader'))
		$this->_debugcodes .= "\n<p><a href=\"index.php?p=backup_loader&filename=" . $date . "\">" . $this->parseLangConst('BUP_DOWNLOAD') . "</a></p>";
		else
		$this->_debugcodes .= "\n<p>" . $this->parseLangConst('BUP_DOWNLOAD_NO_EXT') . "</p>";
	}
	return $list;
	}
	private function _genMd5Fs($filename = ROOTPATH, $return = array()){
		if(!file_exists($filename))
		return false;
		if(!is_readable($filename))
		return $return;
		$filename = preg_replace("!\/$!", '', $filename);
		if(is_dir($filename)){
			$filename = preg_replace("!^\.\/!", '', $filename);
			if(preg_match("!^pm\/backup!", $filename))
			return $return;
			$return[$filename] = 'dir';
			$dir = opendir($filename);
			while($file = readdir($dir)){
				if($file == '.' || $file == '..')
				continue;
				$return = $this->_genMd5Fs($filename . '/' . $file, $return);
			}
			closedir($dir);
		} else {
			$filename = preg_replace("!^\.\/!", '', $filename);
			if(preg_match("!^pm\/backup!", $filename))
			return $return;
			$return[$filename] = md5_file($filename);
			return $return;
		}
		return $return;
	}
	/**
	 * Setzt Dateien auf den Zustand der ID zurueck
	 * @param $id int Zuruecksetzen bis
	 * @return bool
	 */
	public function revertChanges($id){
		require_once(ROOTPATH.'pm/include/pclzip.lib.php');
		if(!file_exists(ROOTPATH . 'pm/backup/dep/bup/' . $id . '.zip') || !file_exists(ROOTPATH . 'pm/backup/dep/data/' . $id . '.ini.php')){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('BUP_REVERT_INCOMPLETE_ERROR') . "</p>";
			return false;
		}
		//Erst veraenderungen herausfinden
		$last_bup = $this->_getMd5Fs();
		if(!$last_bup){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('BUP_REVERT_INCOMPLETE_ERROR') . "</p>";
			return false;
		}
		$act_md5 = $this->_genMd5Fs(ROOTPATH);
		$diff = array_diff($last_bup, $act_md5);
		$dir = opendir(ROOTPATH . 'pm/backup/dep/bup');
		if(!$dir){
			$this->_debugcodes .= "\n<p>" . $this->parseLangConst('BUP_DEP_BUP_NOT_OPENED') .  "</p>";
			return false;
		}
		$ids = array();
		while($file = readdir($dir)){
			if($file == '.' || $file == '..')
			continue;
			if(!preg_match("!^[0-9]*\.zip$!", $file))
			continue;
			$ids[] = preg_replace("!^([0-9]*)\.zip$!", "$1", $file);
		}
		sort($ids);
		$id_max = $ids[count($ids) -1];
		closedir($dir);
		for($i = $id_max; $i>0; $i--){
			if(!file_exists(ROOTPATH . 'pm/backup/dep/bup/' . $i . '.zip') || !file_exists(ROOTPATH . 'pm/backup/dep/data/' . $i . '.ini.php'))
			continue;
			$current_ini = parse_ini_file(ROOTPATH . 'pm/backup/dep/data/' . $i . '.ini.php');
			if(!isset($current_ini['deleted']))
			$current_ini['deleted'] = array();
			if(!isset($current_ini['edited']))
			$current_ini['edited'] = array();
			$change = array_merge($current_ini['edited'], $current_ini['deleted']);
			if($id > $i){
				if(count($diff) <= 0)
				return true;
				//ID ist groesser
				//Also muss noch was eingespielt werden, aber nicht alles...
				foreach($diff as $file){
					if(isset($change[$file])){
						if(file_exists($file) && !is_dir($file)){
							unlink($file);
						}
						unset($change[$file]);
						if(count($diff) <= 0)
						return true;
					}
				}
				continue;
			}
			foreach($change as $file){
				if(file_exists($file) && !is_dir($file)){
					if(is_writable($file))
					unlink($file);
				}
				if(isset($diff[$file])){
					unset($diff[$file]);
				}
			}
			$zip = new PclZip(ROOTPATH . 'pm/backup/dep/bup/' . $i . '.zip');
			$extracted = $zip->extract(PCLZIP_OPT_PATH, ROOTPATH);
		}
		return true;
	}
	/*
	 * Konfigurationsroutinen
	 */
	private function _parseConfig($package){
		$return = array();
		if(!is_dir(ROOTPATH . 'pack/' . $package . '/_cfg')){
			return false;
		}
		if(!file_exists(ROOTPATH . 'pack/' . $package . '/_cfg/cfinf.ini.php') || !file_exists(ROOTPATH . 'pack/' . $package . '/_cfg/cfdata.ini.php'))
		return false;
		$cfinf = $this->_toleranceParseIniFile(ROOTPATH . 'pack/' . $package . '/_cfg/cfinf.ini.php');
		$cfdata = parse_ini_file(ROOTPATH . 'pack/' . $package . '/_cfg/cfdata.ini.php');
		foreach($cfdata as $name => $var){
			if(!isset($cfinf[$name])){
				continue;
			}
			$var = $this->_checkIntregConfig($var, $cfinf[$name]);
			$return[$name] = $var;
		}
		foreach($cfinf as $name => $var){
			if(!isset($return[$name])){
				$return[$name] = NULL;
			}
		}
		return $return;
	}
	private function _checkIntregConfig($var, $data){
		if(!isset($data['dtype']))
		return false;
		if(!file_exists(ROOTPATH . 'pm/plugins/cfg/' . $data['dtype'] . '.php'))
		return false;
		include_once(ROOTPATH . 'pm/plugins/cfg/' . $data['dtype'] . '.php');
		if(!function_exists($data['dtype'] . '_check_intreg'))
		return false;
		$funcname = $data['dtype'] . '_check_intreg';
		return $funcname($var, $data);
	}
	public function generateCfgForm($package){
		if(!$this->_isInstalled($package))
		return false;
		$packname = $this->_installed[$package]['real_name'];
		if(!file_exists(ROOTPATH . 'pack/' . $package . '/_cfg/cfinf.ini.php') || !file_exists(ROOTPATH . 'pack/' . $package . '/_cfg/cfdata.ini.php'))
		return false;
		$form = '<table class="cfgform">';
		$cfinf = $this->_toleranceParseIniFile(ROOTPATH . 'pack/' . $package . '/_cfg/cfinf.ini.php');
		$cfdata = $this->_parseConfig($package);
		$class = 1;
		$js = '';
		foreach($cfinf as $name => $data){
			if($class >= 3)
			$class = 1;
			if(!isset($data['dtype']))
			continue;
			if(!file_exists(ROOTPATH . 'pm/plugins/cfg/' . $data['dtype'] . '.php'))
			continue;
			include_once(ROOTPATH . 'pm/plugins/cfg/' . $data['dtype'] . '.php');
			if(!function_exists($data['dtype'] . '_gen_form'))
			continue;
			$funcname = $data['dtype'] . '_gen_form';
			$form .= ($add = $funcname($name, $cfdata[$name], $data, $this, $package, $class))?$add:'';
			if(function_exists($data['dtype'] . '_gen_js')){
				$funcname = $data['dtype'] . '_gen_js';
				$js .= $funcname($this);
			}
			$class++;
		}
		$form .= "\n" . ' </table>';
		$form .= "\n" . ' <input type="hidden" name="pdbpforms" value="true" />';
		return array($js, $form);
	}
	public function cfgFormOut($cfgForm = false, $pack = false){
		if(!$cfgForm)
		$cfgForm = $this->generateCfgForm($pack);
		if(!$cfgForm)
		return false;
		$js = $cfgForm[0];
		$form = $cfgForm[1];
		include(ROOTPATH . 'pm/include/tpl/form.tpl.php');
	}
	public function saveForm($package){
		if(!isset($_POST['pdbpforms']))
		return false;
		return $this->saveDataIni($package, $_POST);
	}
	public function saveDataIni($package, $data_arr){
		$cfinf = $this->_toleranceParseIniFile(ROOTPATH . 'pack/' . $package . '/_cfg/cfinf.ini.php');
		$save = array();
		foreach($cfinf as $data => $info){
			if(isset($data_arr[$data])){
				$save[$data] = $this->_checkIntregConfig($data_arr[$data], $info);
			}
		}
		if(in_array(false, $save))
		return false;
		return $this->_writeIniFile($save, ROOTPATH . 'pack/' . $package . '/_cfg/cfdata.ini.php');
	}
	public function setTpl($tplSource){
		$this->_tplSource = $tplSource;
		return true;
	}
	public function getTpl(){
		return $this->_tplSource;
	}
	public function listPackagesInstalled(){
		return $this->_installed;
	}
	public function cfgExists($package){
		if(!$this->_isInstalled($package))
		return false;
		if(!file_exists(ROOTPATH . 'pack/' . $package . '/_cfg/cfinf.ini.php') || !file_exists(ROOTPATH . 'pack/' . $package . '/_cfg/cfdata.ini.php'))
		return false;
		return true;
	}
}
?>