<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of util
 *
 * @author menno
 */
class Util {
	public static function multiDimArrayToSimpleArray($aIn, $sPrefix='') {
		if (is_array($aIn)) {
			$aOut = Array();
			if ($sPrefix) $sPrefix.='_';
			foreach ($aIn as $sKey=>$mValue) {
				if (is_array($mValue)) {

					$aTmp = self::multiDimArrayToSimpleArray($mValue, $sPrefix.$sKey);
					foreach ($aTmp as $k=>$v) {
						$aOut[$k]=$v;
					}
				} elseif (!is_object($mValue)) {
					$aOut[$sPrefix.$sKey] = $mValue;
				} else {
		      trigger_error(sprintf('Invalid source type ('.gettype($mValue).') for %s::%s', __CLASS__, __METHOD__));
				}
			}
			return $aOut;
		}
		trigger_error(sprintf('Invalid source type ('.gettype($mValue).') for %s::%s', __CLASS__, __METHOD__));
	}

	public static function simpleArrayToMultiDimArray($aIn) {
		if (is_array($aIn)) {
			foreach ($aIn as $sKey=>$mValue)
			if (strstr($sKey,'_')) {
				$aKeyParts = explode('_', $sKey);
				$s = '$aIn[\''.implode('\'][\'', $aKeyParts).'\'] = $mValue;';
				eval($s);
				unset($aIn[$sKey]);
			}
			return $aIn;
		}
		trigger_error(sprintf('Invalid source type ('.gettype($mValue).') for %s::%s', __CLASS__, __METHOD__));
	}
}

?>
