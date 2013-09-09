<?php
// Lehtml 0.9.11
// Magnus Karlsson

class Lehtml {

	static function parse( $src )
	{
		$out = array();

		$stage = 0;
		$tag = "";
		$setting = "";
		$value = "";
		$readuntil = "";
		$line = 1;
		$content = "";
		$startrow = 0;
		$uniqe_id = 0;
		$settingdict = array();

		
		for( $a__i = 0; $a__i < strlen($src); $a__i ++ )
		{
			$a = $src[$a__i];
		
			# Stage -1: Reading a comment:
			if( $stage == -1 )
			{
				# Reading until "-->" has been added to content:
				if( substr($content, strlen($content)-3) == "-->" )
				{
				
					# Addning to out:
					$tmpout = array();
					$tmpout["istag"] = true;
					$tmpout["content"] = substr( $content, 1, strlen($content)-2 );
					$tmpout["tag"] = "!--";
					$tmpout["settings"] = array();
					$tmpout["row"] = $startrow;
					$tmpout["id"] = $uniqe_id;
					$out[] = $tmpout;
					$uniqe_id ++;
					
					$stage = 0;
					
					# Resetting variables:
					$content = "";
					$startrow = $line;
					$setting = "";
					$value = "";
					$tag = "";
				
				}
			}
		
		
			# Stage 0: Find a tag
			if( $stage == 0 )
			{
				if( $a == "<" )
				{
		
					# Add the text before the tag ******************
					if( strlen($content) > 0 && $content[0] == ">" )
					{
						$content = substr( $content, 1 );
					}
					if( strlen($content) > 0 )
					{
						$tmpout = array();
						$tmpout["istag"] = false;
						$tmpout["content"] = $content;
						$tmpout["tag"] = "";
						$tmpout["settings"] = array();
						$tmpout["row"] = $startrow;
						$tmpout["id"] = $uniqe_id;
						$out[] = $tmpout;
						$uniqe_id ++;
					}
					# **********************************************
						
					$content = "";
					$startrow = $line;
					$setting = "";
					$value = "";
					$stage = 1;
					$tag = "";
				}
			}
			
			
			
			# Stage 1: Read the tag name (eg img)
			elseif( $stage == 1 )
			{
			
				# If we encounter a whitespace:
				if( $a == " " || $a == "\n" || $a == "\t" || $a == "\r" )
				{
					# Next stage, my lord:
					$stage = 2;
				}
				
				# Oh my, it seems to be a comment:
				if( substr($tag, 0, 3) == "!--" )
				{
					$stage = -1;
				}
						
				# If the tag already has ended:
				elseif( $a == ">" )
				{
				
					# Addning to out:
					$tmpout = array();
					$tmpout["istag"] = true;
					$tmpout["content"] = substr( $content, 1 );
					$tmpout["tag"] = strtolower(trim($tag));
					$tmpout["settings"] = array();
					$tmpout["row"] = $startrow;
					$tmpout["id"] = $uniqe_id;
					$out[] = $tmpout;
					$uniqe_id ++;
					
					# Resetting variables:
					$content = "";
					$startrow = $line;
					$stage = 0;
				
				}
				
				# Still reading tag name:
				else
				{
					$tag .= $a;
				}
			
			}
			
			
			# Stage 2: Read the setting name:
			elseif( $stage == 2 )
			{
			
				# If "=" is found, time to move on to stage 3
				if( $a == "=" )
				{
					$stage = 3;
				}
				
				# What the hell, a ">" in the setting name? Nope, we have to end this tag:
				elseif( $a == ">" )
				{
				
					# Add the settings that was not already added:
					$sarr = preg_split('/\s+/',strtolower($setting));
					foreach( $sarr as &$s )
					{
						if( strlen($s) > 0 )
						{
							if( !in_array( $s, array_keys($settingdict) ) )
							{
								$settingdict[$s] = "";
							}
						}
					}

					# Append to out:
					$tmpout = array();
					$tmpout["istag"] = true;
					$tmpout["content"] = substr( $content, 1 );
					$tmpout["tag"] = strtolower(trim($tag));
					$tmpout["settings"] = $settingdict;
					$tmpout["row"] = $startrow;
					$tmpout["id"] = $uniqe_id;
					$out[] = $tmpout;
					$uniqe_id ++;
					
					# Reset variables:
					$stage = 0;
					$content = "";
					$tag = "";
					$startrow = $line;
					$setting = "";
					$value = "";
					$settingdict = array();
				
				}
				
				# Appending the string:
				else
				{
					$setting .= $a;
				}
				
			}
			
			
			# Stage 3: What should we read until? ", ' or whitespace:
			elseif( $stage == 3 && $a != " " && $a != "\n" && $a != "\t" && $a != "\r" )
			{
			
				# A " ofcourse:
				if( $a == "'" || $a == "\"" )
				{
					$readuntil = $a;
					$stage = 4;
				}
				
				# What the hell, should we read until ">"? Nope, ending this tag.
				elseif( $a == ">" )
				{
				
					# Add the settings that was not already added:
					$sarr = preg_split('/\s+/', strtolower($setting));
					foreach( $sarr as &$s )
					{
						if( strlen($s) > 0 )
						{
							if( !in_array($s,array_keys($settingdict)) )
							{
								$settingdict[$s] = "";
							}
						}
					}

					# Append to out:
					$tmpout = array();
					$tmpout["istag"] = true;
					$tmpout["content"] = substr( $content, 1 );
					$tmpout["tag"] = strtolower(trim($tag));
					$tmpout["settings"] = $settingdict;
					$tmpout["row"] = $startrow;
					$tmpout["id"] = $uniqe_id;
					$out[] = $tmpout;
					$uniqe_id ++;
					
					# Reset variables:
					$stage = 0;
					$content = "";
					$tag = "";
					$startrow = $line;
					$setting = "";
					$value = "";
					$settingdict = array();
				
				}
				
				
				# We should read until whitespace:
				else
				{
					$value = $a;
					$readuntil = "whitespace";
					$stage = 4;
				}
			
			}
			
			
			# Stage 4: Read the setting value:
			elseif( $stage == 4 )
			{
			
				# Are we there yet?:
				if( $a == $readuntil || ($readuntil == "whitespace" && ($a == " " || $a == "\t" || $a == "\n" || $a == "\r" || $a == ">")) )
				{
				
					# Adding the settings without values: (eg: <a hello href="kalle">, here we are adding "hello")
					$sarr = preg_split('/\s+/',strtolower($setting));
					for( $i = 0; $i < sizeof($sarr)-1; $i ++ )
					{
						if( strlen($sarr[$i]) > 0 )
						{
							if( !in_array($sarr[$i], array_keys($settingdict)) )
							{
								$settingdict[$sarr[$i]] = "";
							}
						}
					}
					
					# Addning the value
					if( !in_array( $sarr[sizeof($sarr)-1], array_keys($settingdict) ) )
					{
						$settingdict[$sarr[sizeof($sarr)-1]] = $value;
					}

					$setting = "";
					$value = "";
					
					# If readuntil was to whitespace, and the tag has ended (">"):
					if( $a == ">" )
					{
					
						# Appending to out:
						$tmpout = array();
						$tmpout["istag"] = true;
						$tmpout["content"] = substr( $content, 1 );
						$tmpout["tag"] = strtolower(trim($tag));
						$tmpout["settings"] = $settingdict;
						$tmpout["row"] = $startrow;
						$tmpout["id"] = $uniqe_id;
						$out[] = $tmpout;
						$uniqe_id ++;
					
						# Resetting variables:
						$stage = 0;
						$content = "";
						$tag = "";
						$startrow = $line;
						$settingdict = array();
					
					}
					
					# Nope, there is more to read:
					else
					{
						$stage = 2;
					}
				
				}
				
				
				# No, we are not there yet!
				else
				{
					$value .= $a;
				}
			
			}
		
		
			# Keeping track on what line we are working on:
			if( $a == "\n" || $a == "\r" )
			{
				$line ++;
			}
			
			# Every character in the string or tag:
			$content .= $a;
		
		}
		
		
		
		# We are finished working, but "content" it not empty:
		if( strlen($content) > 0 )
		{
		
			# It is text:
			if( $stage == 0 )
			{
				if( $content[0] == ">" )
				{
					$content = substr( $content, 1 );
				}
				if( strlen($content) > 0 )
				{
					$tmpout = array();
					$tmpout["istag"] = false;
					$tmpout["content"] = $content;
					$tmpout["tag"] = "";
					$tmpout["settings"] = array();
					$tmpout["row"] = $startrow;
					$tmpout["id"] = $uniqe_id;
					$out[] = $tmpout;
					$uniqe_id ++;
				}
			}
			
			
			# It is a tag:
			else
			{
			
				# Add the settings that was not already added:
				$sarr = preg_split('/\s+/',strtolower($setting));
				foreach( $sarr as &$s )
				{
					if( strlen($s) > 0 )
					{
						if( !in_array($s,array_keys($settingdict)) )
						{
							$settingdict[$s] = "";
						}
					}
				}
				
				# Addning to out:
				$tmpout = array();
				$tmpout["istag"] = true;
				$tmpout["content"] = substr( $content, 1 );
				$tmpout["tag"] = strtolower(trim($tag));
				$tmpout["settings"] = $settingdict;
				$tmpout["row"] = $startrow;
				$tmpout["id"] = $uniqe_id;
				$out[] = $tmpout;
				$uniqe_id ++;
			
			}
		
		}
			
		
		
		return $out;
	}



	static function getSetting( $item, $setting )
	{
		$setting = strtolower($setting);
		if( in_array($setting,array_keys($item["settings"])) )
		{
			return $item["settings"][$setting];
		}
		return "";
	}



	static function readUntil( $html, $until, $startfrom = 0 )
	{
		$content = "";
		for( $i = $startfrom; $i < sizeof($html); $i++ )
		{
			$curr = $html[$i];
			if( $curr["tag"] == $until )
			{
				return array( $content, $i );
			}
			
			elseif( $curr["istag"] )
			{
				$content .= "<".$curr["content"].">";
			}
			
			else
			{
				$content .= $curr["content"];
			}
		}
		
		return array( $content, $i );
	}


	static function makeTag( $curr )
	{
		$out = "<".$curr['tag'];
		$keys = array_keys($curr["settings"]);
		foreach( $keys as &$s )
		{
			if( $s != "/" )
			{
				$out .= " " . $s . "=\"".$curr['settings'][$s]."\"";
			}
		}
		$out .= ">";

		return $out;
	}


	static function makeDocument( $html )
	{
		$out = "";
		foreach( $html as &$curr )
		{
			if( $curr['istag'] )
			{
				$out .= self::makeTag( $curr );
			}
			else
			{
				$out .= $curr['content'];
			}
		}
		return $out;
	}


	static function parseCssClass( $css )
	{
		$out = array();

		$rows = explode(";",$css);
		foreach( $rows as &$row )
		{
			$s = explode(":",$row,2);
			$setting = $s[0];
			$value = $s[1];
			
			$out[ trim(strtolower($setting)) ] = trim($value);
		}
		return $out;
	}


	static function makeCssClass( $css )
	{
		$out = "";
		foreach( $css as $css_setting => $css_value )
		{
			if( strlen($css_setting) > 0 )
			{
				$out .= $css_setting.": ".$css_value."; ";
			}
		}
		return $out;
	}


	static function formatLink( $link, $parent )
	{
		if( $link != null && strlen($link) > 0 )
		{
			$link = explode("#",$link);
			$link = $link[0];

			if( strtolower(substr($link,0,7)) == "http://" || strtolower(substr($link,0,8)) == "https://" )
			{
				return $link;
			}
			else
			{
				if( substr($link,0,11) == "javascript:" )
				{
					return null;
				}
				if( substr($link,0,7) == "mailto:" )
				{
					return null;
				}
			
				if( $link[0] == "?" )
				{
					$a = explode("?",$parent);
					return $a[0] . $link;
				}

				# Tar bort protokollet fran parernt
				$protocol = "";
				if( strtolower(substr($parent,0,7)) == "http://" )
				{
					$protocol = "http://";
					$parent = substr($parent,7);
				}
				else if( strtolower($parent,0,8) == "https://" )
				{
					$protocol = "https://";
					$parent = substr($parent,8);
				}
				# ********************************

				# Om det bara finns domanen, men inget snedstreck
				$a = explode("/",$parent);
				if( strlen($a) == 1 )
				{
					$parent = $parent . "/";
					$a[] = "";
				}
				# ***********************************************

				# Om nya lanken borjar pa / ska vi ga till rood
				if( $link[0] == "/" )
				{
					$link = $a[0] . $link;
				}
				# ********************************************


				else
				{
		
					$b = explode("/",$link);

					$i = 0;
					foreach( $b as $c )
					{
						if( $c == ".." )
						{
							$i = $i + 1;
						}
					}

					$ai = $i;
					if( $i+2 > sizeof( $a ) )
					{
						$ai = sizeof($a)-2;
					}

					$endw = false;
					if( $link[strlen($link)-1] == "/" )
					{
						$endw = true;
					}
		
					$link = "";
					for( $ib = 0; $ib < sizeof($a)-$ai-1; $ib++ )
					{
						$c = $a[$ib];
						if( strlen($c) > 0 )
						{
							$link = $link . $c . "/";
						}
					}
					for( $ib = $i; $i < sizeof($b); $ib ++ )
					{
						$c = $b[$ib];
						if( strlen($c) > 0 )
						{
							$link = $link . $c . "/";
						}
					}
					

					if( $endw == false )
					{
						$link = substr($link,0,strlen($link)-1);
					}
				}
		

				$link = $protocol . $link;
			}

			$b = explode("?",$link,3);
			if( sizeof($b) > 1 )
			{
				$link = $b[0]."?".$b[1];
			}
			return $link;
		}



		else
		{
			return null;
		}

	}

}