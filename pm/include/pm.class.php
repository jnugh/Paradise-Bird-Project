<?php
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
	private $_full_backup_files = array();
	//Funktionen
	/**
	 * Konstruktor laed KOnfigurationen
	 * @return bool
	 */
	public function pm(){
		if(!$this->_errcodes = $this->_load_lang_file(ROOTPATH."pm/lang/de/error.ini.php"))
		die('Errorfile cannot be loaded.');
		if(!$this->_installed = $this->_load_pack_list(ROOTPATH."pm/config/installed.ini.php")){
			$this->show_error('PACKLIST_WRONG');
			return false;
		}
		if(!$this->_load_packages($this->_installed)){
			$this->show_error('PACKLIST_WRONG');
			return false;
		}
		$backup = parse_ini_file(ROOTPATH . 'pm/config/backup.ini.php', false);
		foreach($backup['file'] as $file){
			if(!file_exists(ROOTPATH . $file)){
				$this->_errcodes .= "\n<p>" . $this->parse_lang_const('BACKUP_FILE_NOT_EXISTS') . $file . "</p>";
				return false;
			}
			$this->_full_backup_files[] = ROOTPATH . $file;
		}
	}
	/**
	 * Laed eine Paketliste wie beispielsweise eine Liste der Installierten Pakete
	 * @param $cfgfile Listendatei
	 * @return bool/array
	 */
	private function _load_pack_list($cfgfile){
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
	private function _load_packages($packlist){
		if(!is_array($packlist))
		return false;
		foreach($packlist as $id => $cfg){ //Liste durcharbeiten...
			if($cfg['active'] != true)
			continue;
			if($cfg['type'] != 'init')
			continue;
			if(!$this->_load_pack($id, $packlist))
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
	private function _load_pack($packname, $packlist){
		if($this->_is_loaded($packname))
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
				if(!$this->_load_pack($depend, $packlist))
				return false;
			}
		}
		if(isset($packlist[$packname]['depend_recommend']) && is_array($packlist[$packname]['depend_recommend'])){
			foreach($packlist[$packname]['depend_recommend'] as $depend){
				$this->_load_pack($depend, $packlist);
			}
		}
		if(!is_dir(ROOTPATH.'pack/' . $packname) || !file_exists('pack/' . $packname . '/_init.php'))
		return false;
		$modpath = 'pack/' . $packname . '/';
		require(ROOTPATH.'pack/' . $packname . '/_init.php');
		$this->_loaded[] = $packname;
		return true;
	}
	/**
	 * Ueberprueft ob ein Paket geladen ist
	 * @param $packname string
	 * @return bool
	 */
	private function _is_loaded($packname){
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
	private function _sync_list($list1, $list2){
		if(!is_array($list1) || !is_array($list2))
		return false;
		$listed = array();
		foreach($list1 as $id => $cfg){
			$listed[] = $id;
		}
		foreach($list2 as $id => $cfg){
			if(!in_array($id, $listed, true))
			continue;
			if(($compare = $this->_compare_version($cfg['version'], $list1[$id]['version'])) == 2)
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
	private function _compare_version($version1, $version2){
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
	private function _load_lang_file($langfile){
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
	public function open_index($index){
		if(!isset($this->_installed[$index]['type']) || !$this->_installed[$index]['type'] == 'index'){
			$this->show_error('404', 404);
			return false;
		}
		if($this->_load_pack($index, $this->_installed))
		return true;
		$this->show_error('404', 404);
		return false;
	}
	/**
	 * Gibt einen Fehlercode aus und setzt ggf den HTTP Status
	 * @param $errorcode string Fehlercode
	 * @param $http int HTTP Status (e.g. 200)
	 * @return void
	 */
	public function show_error($errorcode, $http = 200){
		header('HTTP/ '.$http);
		$errorcode = $this->_parse_lang_const($errorcode);
		include(ROOTPATH.'pm/include/tpl/error.tpl.php');
		exit();
	}
	/**
	 * Sprachkonstante "uebersetzen"
	 * @param $errorcode Sprachkonstante
	 * @return string
	 */
	public function parse_lang_const($errorcode){
		if(!isset($this->_errcodes[$errorcode]))
		$errorcode = "n/a";
		else
		$errorcode = $this->_errcodes[$errorcode];
		return $errorcode;
	}
	/**
	 * Installation eines Paketes
	 * @param $packname string Paketname
	 * @param $packfile string Datei (ZIP Format)
	 * @param $remote bool Datei von remote Server kopieren
	 * @param $force bool Fehler teilweise ignorieren
	 * @return bool
	 */
	public function install_pack($packname, $packfile = '', $remote = false, $force = false){
		//Paket entpacken und pruefen
		if($packfile){
			require_once(ROOTPATH.'pm/include/pclzip.lib.php');
			if(!file_exists($packfile)){
				$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('PACK_NOT_EXISTS') . $packname . '</p>';
				return false;
			}
			$zip = new PclZip($packfile);
			if ($zip->extract(PCLZIP_OPT_PATH, ROOTPATH.'pm/tmp') == 0) {
				$this->_debugcodes("\n<p>" . $this->parse_lang_const('EXTRACT_ERROR') .$zip->errorInfo(true) . '</p>');
			}
		}
		if(!is_dir(ROOTPATH.'pm/tmp/' . $packname)){
			$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('UNPACK_NOT_FOUND') . '</p>';
			return false;
		}
		$packdir = ROOTPATH.'pm/tmp/' . $packname . '/';
		$srcdir = $packdir . 'src/';
		if(!file_exists($packdir . 'pack.ini.php') || !($install_ini = parse_ini_file($packdir . 'pack.ini.php', true))){
			$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('CORRUPT_SETUP_FILE') . '</p>';
			return false;
		}
		if(!is_dir($srcdir) || !file_exists($srcdir . '_init.php')){
			$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('CORRUPT_PACK') . '</p>';
			return false;
		}
		if(!isset($install_ini[$packname])){
			$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('CORRUPT_PACK') . '</p>';
			return false;
		}
		//Paket sollte OK sein.
		$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('INSTALL') . $packname . '</p>';
		$directini = $install_ini[$packname];
		//Abhaengigkeiten laden...
		if(isset($directini['depend_install'])){
			foreach($directini['depend_install'] as $depend){
				if($this->_is_loaded($depend)){
					continue;
				}else if($this->_is_installed($depend)){
					$this->_load_pack($depend, $this->_installed);
					continue;
				}
				$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('INSTALL_DEP') . $depend . '</p>';
				$this->_debugcodes .= '<blockquote>';
				if(!$this->install_pack($depend, false, false))
				{
					$this->_debugcodes .= '</blockquote>';
					$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('DEPEND_LOAD_ERROR') . '</p>';
					if(!$force)
					return false;
				}
				$this->_load_pack($depend, $this->_installed);
				$this->_debugcodes .= '</blockquote>';
			}
		}
		if(isset($directini['depend_runtime'])){
			foreach($directini['depend_runtime'] as $depend){
				if(!$this->_is_installed($depend))
				continue;
				$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('INSTALL_DEP') . $depend . '</p>';
				$this->_debugcodes .= '<blockquote>';
				if(!$this->install_pack($depend, false, false))
				{
					$this->_debugcodes .= '</blockquote>';
					$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('DEPEND_LOAD_ERROR') . '</p>';
					if(!$force)
					return false;
				}
				$this->_debugcodes .= '</blockquote>';
			}
		}
		//Ueberpruefen ob Paket installiert ist
		if($this->_is_installed($packname) && is_dir(ROOTPATH.'pack/' . $packname)){
			$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('ALREADY_INSTALLED') . '</p>';
			//Versionsnummern vergleichen
			$compared = $this->_compare_version($directini['version'], $this->_installed[$packname]['version']);
			if($compared == 2 || $compared == 0){
				//Neuer oder gleich
				//Vergleichen per funktion damit auch fuer UI nutzbar
				if($install_ini['SETUP']){
					if(!$this->check_intregrit($packname, $install_ini['SETUP'])){
						$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('INSTALLED_FILES_CHANGED') . '</p>';
						if(!$force)
						return false;
					}
				}
			} else {
				//Aelter
				$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('ALREADY_INSTALLED_NEWER') . '</p>';
				if(!$force)
				return false;
			}
		} else if(is_dir(ROOTPATH.'pack/' . $packname)){
			$this->_debugcodes .= "\n<p><a href=\"?p=clean_pm\">" . $this->parse_lang_const('ALREADY_EXISTING') . '</a></p>';
			if(!$force)
			return false;
		} else if($this->_is_installed($packname)){
			$this->_debugcodes .= "\n<p><a href=\"?p=clean_pm\">" . $this->parse_lang_const('ALREADY_INSTALLED_NO_FILES') . '</a></p>';
			if(!$force)
			return false;
		}
		if(!$this->_copy_req($srcdir, ROOTPATH.'pack/' . $packname)){
			$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('COPY_FAILED') . '</p>';
			return false;
		}
		$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('COPY_COMPLETED') . '</p>';
		if(!$this->_add_pack($packname, $directini)){
			$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('INI_ERROR') . '</p>';
			return false;
		}
		//In die INI eintragen
		if(!$this->_write_ini_file($this->_installed, ROOTPATH . 'pm/config/installed.ini.php', true)){
			$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('INI_ERROR') . '</p>';
			return false;
		}
		$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('INSTALL_COMPLETE') . '</p>';
		return true;
	}
	private function _tolerance_parse_ini_file($file, $has_sections = false){
		if(!file_exists($file))
			return false;
		$file = fopen($file, 'r');
		if(!$file)
			return false;
		$return = array();
		$section = 0;
		while(!feof($file)){
			$line = fgets($file, 1000);
			if(preg_match("!^;!", $line))
				continue;
			if($has_sections && preg_match("!^\[.*\]$!", $line)){
				$section = preg_replace("!^\[(.*)\]$", "$1", $line);
				continue;
			}
			$line = explode("=", $line);	
			if(count($line) != 2)
				continue;
			$line[0] = preg_replace("!^ !", '', $line[0]);
			$line[0] = preg_replace("! $!", '', $line[0]);
			$line[1] = preg_replace("!^ !", '', $line[1]);
			$line[1] = preg_replace("! $!", '', $line[1]);
			$line[1] = preg_replace("!\n$!", '', $line[1]);
			if(!$has_sections){
				if(preg_match('!\[\]$!', $line[0])){
					if(!isset($return[preg_replace("!\[\]$!", '', $line[0])]))
						$return[preg_replace("!\[\]$!", '', $line[0])] = array();
					$return[preg_replace("!\[\]$!", '', $line[0])][] = preg_replace("!^\"(.*)\"!", "$1", $line[1]);
				} else {
					$return[preg_replace("!\[\]$!", '', $line[0])] = preg_replace("!^\"(.*)\"!", "$1", $line[1]);
				}
			} else {
				if(preg_match('!\[\]$!', $line[0])){
					if(!isset($return[$section][preg_replace("!\[\]$!", '', $line[0])]))
						$return[$section][preg_replace("!\[\]$!", '', $line[0])] = array();
					$return[$section][preg_replace("!\[\]$!", '', $line[0])][] = preg_replace("!^\"(.*)\"!", "$1", $line[1]);
				} else {
					$return[$section][preg_replace("!\[\]$!", '', $line[0])] = preg_replace("!^\"(.*)\"!", "$1", $line[1]);
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
	private function _write_ini_file($assoc_arr, $path, $has_sections=FALSE) {
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

        if (!$handle = fopen($path, 'w')) {
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
    private function _add_pack($packname, $settings){
    	if(!isset($settings) || !is_array($settings))
    		return false;
    	$packname = strtolower($packname);
    	if(!preg_match('![a-z_0-9]!', $packname))
    		return false;
    	else if(!$this->_is_installed($packname)){
    		$this->_installed[$packname] = $settings;
    	}else if($this->_is_installed($packname)){
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
	public function get_debug_code(){
		return $this->_debugcodes;
	}
	/**
	 * TODO Paket deinstallieren
	 */
	public function remove_pack($package, $remove_deps = false, $take_backup = true){
		if(!$this->_is_installed($package)){
			$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('NOT_INSTALLED') . "</p>";
			return false;
		}
		$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('REMOVE_PACK') . " <b>" . $package . "</b></p>";
		//TODO Backup
		if($depend = $this->_depend_exists($package)){
			$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('DEPEND_EXISTS') . "</p>";
			foreach($depend as $pack){
				$this->_debugcodes .= "\n<blockquote>";
				if($remove_deps){
					$this->remove_pack($pack, true, false);
				}
				$this->_debugcodes .= "\n</blockquote>";
			}
			if(!$remove_deps)
			return false;
		}
	}
	public function add_repo(){

	}
	public function remove_repo(){

	}
	public function refresh_repo(){

	}
	public function check_update(){

	}
	public function install_repo(){

	}
	public function search(){

	}
	public function active_pack(){

	}
	public function deactive_pack(){

	}
	/**
	 * Ueberprueft ob Abhaengigkeiten existieren
	 * @param $package string Paketname
	 * @return array/bool
	 */
	private function _depend_exists($package){
		if(!$this->_is_installed($package))
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
	private function _is_installed($packname){
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
	private function _copy_req($src, $dest, $silence = false){
		//Komplettes verzeichniss rekursiv kopieren
		if(!$silence){
			$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('SOURCE') ." " . $src . "</p>";
			$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('DESTINATION') . " " . $dest . "</p>";
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
				if(!$this->_copy_req($src . '/' . $item, $dest . '/' . $item))
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
	public function check_intregrit($packname, $cfg){
		if(!$this->_is_installed($packname))
			return true;
		if(!is_array($cfg))
			return false;
		if(isset($cfg['md5']) && is_array($cfg['md5'])){
			foreach($cfg['md5'] as $hash){
				$hash = explode(";", $hash);
				if($this->_compare_version($hash[0], $this->_installed[$packname]['version']) != 0)
					continue;
				if(!file_exists(ROOTPATH . 'pack/' . $packname . '/' . $hash[1]))
					continue;
				if(md5(file_get_contents(ROOTPATH . 'pack/' . $packname . '/' . $hash[1])) != $hash[2])
					return false;
			}
		}
		return true;
	}
	/*
	 * Debug Routine...
	 */
	public function backup($full){
		if(!$full){
			return $this->_backup_dep();
		} else {
			return $this->_backup_full();
		}
	}
	
	private function _backup_dep(){
		$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('BUP_MAKE_DEP') .  "</p>";
		$file_hash = $this->_get_md5_fs();
		if(!$file_hash){
			$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('BUP_NO_DEP_EXISTS') .  "</p>";
			$this->_debugcodes .= "\n<blockquote>";
			if(file_exists(ROOTPATH . 'pm/backup/dep/bup/1.zip')){
				$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('BUP_DEP_CORRUPT') .  "</p>";
				return false;
			}
			$list = $this->_backup_full(ROOTPATH . 'pm/backup/dep/bup/1.zip'); //TODO Zugriff blockieren
			$this->_debugcodes .= "\n</blockquote>";
			$id = 1;
		} else {
			$merged = $this->_merge_md5_fs($file_hash, ROOTPATH);
			$dir = opendir(ROOTPATH . 'pm/backup/dep/bup');
			if(!$dir){
				$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('BUP_DEP_BUP_NOT_OPENED') .  "</p>";
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
		$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('BUP_WRITE_MD5_FS') .  "</p>";
		if(false === $changes = $this->_write_md5_fs(ROOTPATH . 'pm/backup/dep/data/md5fs.ini.php', ROOTPATH . 'pm/backup/dep/data/' . $id . '.ini.php', $list, $this->_tolerance_parse_ini_file(ROOTPATH . 'pm/backup/dep/data/md5fs.ini.php'))){
			$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('BUP_WRITE_MD5_FS_FAILED') . "</p>";
			return false;
		}
		$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('BUP_EDITED_FILES') . $changes .  "</p>";
	}
	private function _merge_md5_fs($md5fs, $filename, $return = array()){
		if(!file_exists($filename))
			return false;
			
		$filename = preg_replace("!\/$!", '', $filename);
		if(is_dir($filename)){
			$dir = opendir($filename);
			while($file = readdir($dir)){
				if($file == '.' || $file == '..')
					continue;
				$return = $this->_merge_md5_fs($md5fs, $filename . '/' . $file, $return);
			}
			closedir($dir);
		} else {
			$filename = preg_replace("!^\.\/!", '', $filename);
			if(preg_match("!^pm\/backup!", $filename))
				return $return;
			if(!in_array(ROOTPATH . preg_replace("!^(.*?)\/.*!", "$1", $filename), $this->_full_backup_files))
				return $return;
			if(!isset($md5fs[$filename])){
				$return[] = $filename;
				return $return;
			} else if($md5fs[$filename] != md5(file_get_contents($filename))){
				echo '<p>' . $md5fs[$filename] . ' : ' . md5(file_get_contents($filename)) . '</p>';
				$return[] = $filename;
				return $return;
			} else 
				return $return;
		}
		return $return;
	}
	private function _write_md5_fs($file, $bup_file, $list, $mergewith = array()){
		if(!$mergewith)
			$mergewith = array();
		if(!is_array($list) || !is_array($mergewith))
			return false;
		if(file_exists($bup_file)){
			$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('BUP_DEP_CORRUPT_MD5_FS') .  "</p>";
			return false;
		}
		$deleted = array();
		$count = 0;
		foreach($mergewith as $filename => $file_merge){
			if(!file_exists($filename)){
				$deleted[] = $filename;
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
			$hash = md5(file_get_contents($file_merge['filename']));
			if(isset($mergewith[$file_merge['filename']]) && $mergewith[$file_merge['filename']] == $hash)
				continue;
			$mergewith[$file_merge['filename']] = $hash;
			$count ++;
		}
		$this->_write_ini_file($mergewith, $file, false);
		$this->_write_ini_file($deleted, $bup_file, false);
		return $count;
	}
	private function _get_md5_fs(){
		if(!file_exists(ROOTPATH . 'pm/backup/dep/data/md5fs.ini.php'))
			return false;
		$ini = $this->_tolerance_parse_ini_file(ROOTPATH . 'pm/backup/dep/data/md5fs.ini.php');
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
	private function _backup_full($bup_file = ''){
		$date = date('Y_m_d_H_i_s', time());
		if(!$bup_file)
			$bup_file = ROOTPATH . 'pm/backup/full/' . $date . '.zip';
		require_once(ROOTPATH.'pm/include/pclzip.lib.php');
		$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('BUP_FULL') . $bup_file .  "</p>";
		if(file_exists($bup_file)){
			$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('BUP_FULL_EXISTS') . $bup_file .  "</p>";
			return false;
		}
		$zip = new PclZip($bup_file);
		if(!$list = $zip->create($this->_full_backup_files)){
			$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('BUP_ERROR_PCLZIP') . $zip->errorInfo(true) .  "</p>";
			return false;
		}
		$this->_debugcodes .= "\n<p>" . $this->parse_lang_const('BUP_DONE') . "</p>";
		if($bup_file != ROOTPATH . 'pm/backup/full/' . $date . '.zip')
			$this->_debugcodes .= "\n<p><a href=\"" . $bup_file . "\">" . $this->parse_lang_const('BUP_DOWNLOAD') . "</a></p>";
		else
			$this->_debugcodes .= "\n<p><a href=\"index.php?action=dl&filename=" . $date . "\">" . $this->parse_lang_const('BUP_DOWNLOAD') . "</a></p>";
		return $list;
	}
}
?>