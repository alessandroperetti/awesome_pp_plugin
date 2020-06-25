<?php
class Utility {
	
	static function peretti_debug($var){
		$string_debugged = print_r($var, true);
		$dir = plugin_dir_path( __FILE__ );
		$mydebug = fopen($dir."debug.txt", "a");
		fwrite($mydebug, $string_debugged . "\n");
		fclose($mydebug);
	}
}


?>