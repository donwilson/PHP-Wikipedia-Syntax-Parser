<?php
	class JungleDB_Utils {
		public static function mb_substr_replace($output, $replace, $posOpen, $posClose) { 
			return mb_substr($output, 0, $posOpen) . $replace . mb_substr($output, $posClose+1);
		}
		public static function prepare_url_string($string) {
			$string = trim($string);
			
			if(!preg_match("#^https?\:\/\/#si", $string)) {
				$string = "http://". $string;
			}
			
			return $string;
		}
		
		public static function remove_emptied_sentence_bits($string) {
			return preg_replace(array("#\(\s*?;\s+#si", "#\s*?\(\s*?\)\s*?#si"), array("(", " "), $string);
		}
		
		public static function trim_it(&$var) {
			if(is_array($var)) {
				foreach($var as $k => $v) {
					$var[$k] = self::trim_it($v);
				}
				
				return $var;
			}
			
			return trim(str_replace("&nbsp;", " ", $var));
		}
	}