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
function password_check_intreg($var, $data){
	if(!is_string($var))
	return false;
	if(isset($data['extra']) && is_int($data['extra'])){
		if(strlen($var) > $data['extra']) //Bei string maximale Zeichenzahl pruefen
		return false;
	}
	return $var;
}
function password_gen_form($name, $var, $data, $pm, $package, $class = 1){
	(isset($data['extra']) && intval($data['extra']))?$extra = ' maxlength="' . $data['extra'] . '"':$extra = '';
	$return  = "\n" . '  <tr class="c' . $class . '">';
	$return .= "\n" . '   <td>';
	$return .= "\n" . '    <p class="tab">';
	$return .= "\n" . '     <label>' . $pm->parsePackLangConst('LAB_' . $name, $package) . ': </label>';
	$return .= "\n" . '    </p>';
	$return .= "\n" . '   </td>';
	$return .= "\n" . '   <td>';
	$return .= "\n" . '    <p class="tab">';
	$return .= "\n" . '     <input name="' . $name . '" id="' . $name . '" type="password" value="' . $var . '"' . $extra . ' />';
	$return .= "\n" . '    </p>';
	$return .= "\n" . '   </td>';
	$return .= "\n" . '  </tr>';
	return $return;
}