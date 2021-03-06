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
if(!isset($config['index']))
	$pm->showError('404', 404);
if(isset($config['param']) && is_array($config['param'])){
	foreach($config['param'] as $param){
		$param = explode('=', $param, 2);
		if(!isset($param[1]))
			$param[1] = '';
		$_GET[$param[0]] = $param[1];
	}
}
if(!$pm->openIndex($config['index']))
	$pm->showError('404', 404);