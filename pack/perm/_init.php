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
$permcfg = $config;
include("perm.php");
global $perm;
if(isset($_GET['perm_action']))
	$action = $_GET['perm_action'];
else
	$action = 'main';
switch($action){
	case 'main':
		break;
	case 'login':
		$pm->openIndex('users_login');
		echo $pm->getTpl();
		exit;
		break;
	default:
		$pm->showError('UNKNOWN_ACTION');
		break;
}
$perm = new perm($config);