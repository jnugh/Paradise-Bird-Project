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
function select_check_intreg($var, $data){
	if(isset($data['extra']) && is_array($data['extra']))
	foreach($data['extra'] as $key => $dat){
		$data['extra'][$key] = preg_replace("!\|.*?$!", '', $dat);
	}
	if(isset($data['extra']) && is_array($data['extra']) && !in_array($var, $data['extra']))
	return false;
	return $var;
}
function select_gen_form($name, $var, $data, $pm, $package, $class = 1){
	$info = ' <i>('. $pm->parseLangConst('SELECT') . ')</i>';
	$return = "\n" . '  <tr class="c' . $class . '">';
	$return .= "\n" . '   <td>';
	$return .= "\n" . '    <p class="tab">';
	$return .= "\n" . '     <label>' . $pm->parsePackLangConst('LAB_' . $name, $package) . $info . ': </label>';
	$return .= "\n" . '    </p>';
	$return .= "\n" . '   </td>';
	$return .= "\n" . '   <td>';
	$return .= "\n" . '    <select name="'.$name.'" id="'.$name.'">';
	if(isset($data['extra']) && is_array($data['extra'])){
		foreach($data['extra'] as $value){
			$extra = '';
			$value = explode('|', $value);
			if($value[0] == $var)
			$extra = ' selected="selected"';
			$outputval = $pm->parsePackLangConst('SEL_' . $value[1], $package) != 'n/a'?$pm->parsePackLangConst('SEL_' . $value[1], $package):$value[1];
			$return .= "\n" . '     <option value="'.$value[0].'"'.$extra.'>' . $outputval . '</option>';
		}
	}
	$return .= "\n" . '    </select>';
	$return .= "\n" . '   </td>';
	$return .= "\n" . '  </tr>';
	return $return;
}