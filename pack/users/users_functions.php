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
//Überprüfen ob Daten geschrieben wurden, sonst automatisch genereieren, falls genSalt aufgerufen wird.
global $configuredSalt, $staticSalt, $dynamicSalt, $dynamicSaltLen, $useSalt, $useDynamic;
if(isset($config['static']) && isset($config['signs']) && isset($config['dynlen'])){
	$staticSalt = $config['static'];
	$dynamicSalt = explode(',', $config['signs']);
	$dynamicSaltLen = $config['dynlen'];
	$configuredSalt = true;
	if(!isset($config['usesalt']) || !$config['usesalt'])
	$useSalt = false;
	else
	$useSalt = true;
	if(!isset($config['usedyn']) || !$config['usedyn'])
	$useDynamic = false;
	else
	$useDynamic = true;
}else{
	$configuredSalt = false;
}
global $saltCfg;
$saltCfg = $config;
/**
 * Erstellt einen öffentlichen Salt Key für sh1 hash
 * @return string
 */
function genPublicKey($signs, $length){
	$key = '';
	for(; $length > 0; $length--){
		$key .= $signs[array_rand($signs)];
	}
	return $key;
}
/**
 * Setzt Parameter bei der ersten Benutzung der Salt Hashes
 * @return void
 */
function configureSalt(){
	global $saltCfg, $pm;
	$dynamicSalt = array('{','-','=','e','_','$','%','&','(',')');
	$staticSalt = uniqid(sha1(rand()));
	$saltCfg['static'] = $staticSalt;
	$saltCfg['signs'] = implode(',', $dynamicSalt);
	$saltCfg['dynlen'] = 13;
	$pm->saveDataIni('users', $saltCfg);
}
/**
 * Diese Funktion erstellt einen sh1 salt
 * @param $tosalt string zu verschlüsselnder String
 * @return array public Key, sh1 salt
 */
function genSalt($tosalt){
	global $saltCfg, $pm;
	global $configuredSalt, $staticSalt, $dynamicSalt, $dynamicSaltLen, $useSalt, $useDynamic;
	if(!$useSalt)
	return sha1($tosalt);
	if(!isset($saltCfg['used']) || $saltCfg['used'] != true){
		$saltCfg['used'] = 'true';
		$pm->saveDataIni('users', $saltCfg);
	}
	if(!$configuredSalt){
		configureSalt();
	}
	//So, jetzt salzen wir :)
	$salt = $tosalt;
	//dazu den statischen.
	$salt .= $staticSalt;
	//Und noch nen Dynamischen generieren...
	$salt .= $public = genPublicKey($dynamicSalt, $dynamicSaltLen);
	return array(sha1($salt), $public);
}
function genSaltPublicKey($tosalt, $publicKey){
	global $saltCfg, $pm;
	global $configuredSalt, $staticSalt, $dynamicSalt, $dynamicSaltLen, $useSalt, $useDynamic;
	if(!$useSalt)
	return sha1($tosalt);
	if(!isset($saltCfg['used']) || $saltCfg['used'] != true){
		$saltCfg['used'] = 'true';
		$pm->saveDataIni('users', $saltCfg);
	}
	if(!$configuredSalt){
		configureSalt();
	}
	$salt = $tosalt;
	$salt .= $staticSalt;
	$salt .= $publicKey;
	return sha1($salt);
}
/**
 * Vergleicht 2 Werte, Wert 2 wird dabei als Klartext übergeben, Wert 1 als sh1 Key
 * @param $s1 SHA1 Key
 * @param $s2 Klar Text
 * @return bool
 */
function compareSaltString($s1, $s2, $publicKey){
	if($s1 == genSaltPublicKey($s2, $publicKey))
		return true;
	return false;
}