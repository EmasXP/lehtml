<?php

class Levalid {

	static function httpInTheBeginning( $str )
	{
		$str = strtolower($str);
		if( substr($str,0,7) == "http://" || substr($str,0,7) == "https://" )
		{
			return true;
		}
		return false;
	}
	
}