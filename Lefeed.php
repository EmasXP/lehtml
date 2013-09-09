<?php

/*
Version 1

Edit history:
2013-08-10
*/

class Lefeed {

	static function cdata( $str )
	{
		$str = trim($str);
		if( strtolower(substr($str, 0, 9)) == "<![cdata[" )
		{
			if( $str[strlen($str)-1] == ">" )
			{
				$a = 3;
			}
			else
			{
				$a = 2;
			}
		
			$str = substr($str,9, strlen($str)-9-$a);
			$start_klammers = 0;
			$end_klammers = 0;
			for( $i = 0; $i < strlen($str); $i++ )
			{
				if( $str[$i] == "[" )
				{
					$start_klammers ++;
				}
				else
				{
					break;
				}
			}
			$b = 0;
			if( isset($str[$start_klammers]) && $str[$start_klammers] == ">" )
			{
				$b = 1;
			}
			
			for( $i = strlen($str)-1; $i >= 0; $i-- )
			{
				if( $str[$i] == "]" )
				{
					$end_klammers ++;
				}
				else
				{
					break;
				}
			}
			
			$klammers = $start_klammers;
			if( $start_klammers > $end_klammers )
			{
				$klammers = $end_klammers;
			}
			
			$str = substr( $str, $klammers+$b, strlen($str)-($klammers*2)-$b );
		}
		
		
		else
		{
			$str = str_replace( array("&#039;","&#60;","&#34;","&#62;","&#034;","&#61;","&#47;","&#33;"), array("\"","<","\"",">","\"","=","/","!"), $str );
			$str = htmlspecialchars_decode( $str );
		}
		
		
		return $str;
	}


	static function parse( $src, $item_fields = array("title","content","link","guid","pubdate","published"), $channel_fields = array("title","image","link","description","lastbuilddate"), $settings = array() )
	{

		/** Hämtar inställning: auto_strtotime *************************************
		Förinställda inställningar används enbart som inställningen inte finns
		*/
		if( !isset($settings["auto_strtotime"]) )
		{
			$auto_strtotime = array("pubdate","lastbuilddate","published");
		}
		else
		{
			$auto_strtotime = $settings["auto_strtotime"];
		}
		// *************************************************************************
		
		
		/** Hämtar inställning: merging ********************************************
		Merging "content" måste finnas, och content:encoded, content och description
		måste finnas i "content".
		*/
		$merging = array();
		if( isset($settings["merging"]) )
		{
			$merging = $settings["merging"];
		}
		if( !isset($merging["content"]) )
		{
			$merging["content"] = array();
		}
		if( !isset($merging["pubdate"]) )
		{
			$merging["pubdate"] = array();
		}
		foreach( array("content:encoded","content","description") as $c )
		{
			if( !in_array($c,$merging["content"]) )
			{
				$merging["content"][] = $c;
			}
		}
		foreach( array("pubdate","published") as $c )
		{
			if( !in_array($c,$merging["pubdate"]) )
			{
				$merging["pubdate"][] = $c;
			}
		}
		
		
		/* För att fälten som ska användas i merging ska hämtas måste vi lägga till
		dem i item_fields och channel_fields*/
		foreach( array_values($merging) as $fields )
		{
			foreach( $fields as $field )
			{
				if( !in_array($field,$item_fields) )
				{
					$item_fields[] = $field;
				}
				if( !in_array($field,$channel_fields) )
				{
					$channel_fields[] = $field;
				}
			}
		}
		// *************************************************************************
		
		
		/** Hämtar inställning: auto_cdata *****************************************
		content:encoded, content och description måste finnas.
		*/
		$auto_cdata = array();
		if( isset($settings["auto_cdata"]) )
		{
			$auto_cdata = $settings["auto_cdata"];
		}
		foreach( array("content:encoded","content","description","title") as $c )
		{
			if( !in_array($c,$auto_cdata) )
			{
				$auto_cdata[] = $c;
			}
		}
		// *************************************************************************







		$html = Lehtml::parse($src);

		/* $out kommer att returneras i slutet, och fälten "xml_encoding" och "items"
		måste finnas, oavsätt om de hittas i loopen eller inte.
		*/
		$out = array("xml_encoding"=>"","items"=>array());
		$initem = false;
		$readunt = "";
		$channel_readunt = "";


		$items = array();


		foreach( $html as &$curr )
		{
		
		
			/** Ett inlägg hittas **************************************************
			$initem blir true eftersom man är inuti ett inlägg.
			Alla variabler resettas
			*/
			if( $curr["tag"] == "item" || $curr["tag"] == "entry" )
			{
				$initem = true;
				$link = "";
				$raw_link = "";
				$backup_link = "";
				$raw_backup_link = "";
				$current_content = array();
				$stuff = array();
				$tag_content = "";
				$raw_tag_content = "";
			}
			// *********************************************************************
			
			
			
			
			/** Ett inlägg är färdigläst *******************************************
			Efter att ett inlägg är färdigläst går vi igenom merging, auto_cdata och
			auto_strtotime. Dessutom väljer vi en vettig länk av de vi hittade.
			*/
			else if( $curr["tag"] == "/item" || $curr["tag"] == "/entry" )
			{
				
				/** Fixar länken ***************************************************
				Först kollar vi om vi ens ska hämta länken (via item_fields som
				användaren har valt).
				Om ingen riktig länk har hittats så används backuplänken. Sedan
				resettar vi variabler som har med länkar att göra.
				Riktig länk:
					- <link>*länk*</link>
					- <link rel=alternate href="*länk*">
				Backup:
					<link rel="*vad som helst*" href="*länk*">
				*/ 
				if( in_array("link",$item_fields) )
				{
					if( strlen($link) == 0 )
					{
						$link = $backup_link;
						$raw_link = $raw_backup_link;
					}
					$stuff["link"] = $link;
					$stuff["raw_link"] = $raw_link;
				}
				$link = "";
				$raw_link = "";
				$backup_link = "";
				$raw_backup_link = "";
				// *****************************************************************
				
				
				/** Kör auto_strtotime *********************************************
				Går igenom alla fält som finns i $auto_strtotime och kollar om fältet
				finns i $stuff. Om fältet finns skapas "unformatted_*" om det fältet
				inte redan finns. Det fältet är till för att bevara ursprungsinnehållet
				och får inte skrivas över. Sedan skrivs fältet över med strtotime
				*/
				foreach( $auto_strtotime as $as )
				{
					if( in_array($as,array_keys($stuff)) )
					{
						if( !isset($stuff["unformatted_".$as]) )
						{
							$stuff["unformatted_".$as] = $stuff[$as];
						}
						$stuff[$as] = strtotime($stuff[$as]);
					}
				}
				// *****************************************************************
				
				
				/** Kör auto_cdata *************************************************
				Fungerar på exakt samma sätt om auto_strtotime förutom att
				whtml_cdata körs istället för strtotime
				*/
				foreach( $auto_cdata as $as )
				{
					if( in_array($as,array_keys($stuff)) )
					{
						if( !isset($stuff["unformatted_".$as]) )
						{
							$stuff["unformatted_".$as] = $stuff[$as];
						}
						$stuff[$as] = self::cdata($stuff[$as]);
					}
				}
				// *****************************************************************
				
				
				/** Merging ********************************************************
				Förklarar första kodstycket:
				$merging ser ut såhär; $merging["*alias*"] = array(*fält*);
				*alias* är namnet som fälten ska sammanfogas till, *fält* är de fält
				som ska sammanfogas till *alias*.
				Loopar igenom alla mergingar, som i sin tur loopar igenom alla fält.
				Det första fältet som hittas kommer att kopieras till *alias*.
				Dessutom får vi kopiera över raw_* samt eventuell unformatted_*.
				För att användaren ska veta vilket av dessa fält som valdes skapar
				vi även *_field med namnet på ursprungsfältet.
				*/
				$partial_stuff = array();
				foreach( $merging as $alias => $fields )
				{
					foreach( $fields as $field )
					{
						if( in_array($field,array_keys($stuff)) )
						{
							$partial_stuff[$alias] = $stuff[$field];
							$partial_stuff["raw_".$alias] = $stuff["raw_".$field];
							if( isset($stuff["unformatted_".$field]) ){ $partial_stuff["unformatted_".$alias] = $stuff["unformatted_".$field]; }
							$partial_stuff[$alias."_field"] = $field;
							break;
						}
					}
				}

				/* Vi vill inte ha kvar de gamla fälten som blev över efter
				mergingen, därför tar vi bort alla fält i alla mergings. Eftersom vi
				sedan mergar orginal-stuff med vår temporära stuff (partial_stuff)
				behöver vi inte oroa oss över om vi råkar ta bort ett fält som är
				alias.
				*/
				foreach( array_values($merging) as $fields )
				{
					foreach( $fields as $field )
					{
						unset( $stuff[$field] );
						unset( $stuff["raw_".$field] );
						unset( $stuff["unformatted_".$field] );
					}
				}
				$stuff = array_merge( $stuff, $partial_stuff );
				// *****************************************************************
				
			
				// Lägger till $stuff i $items och resettar ************************
				$items[] = $stuff;
				$stuff = array();
				// *****************************************************************
			
				// Resettar lite variabler bara för säkerhets skull ****************
				$readunt = "";
				$tag_content = "";
				$raw_tag_content = "";
				// *****************************************************************
				
				// Och givetvis är vi inte inuti ett inlägg längre:
				$initem = false;

			}
			// *********************************************************************
			
			
			
			
			// Hämtar xml_encoding från <?xml encoding="*encoding*> ****************
			else if( $curr["tag"] == "?xml" )
			{
				$out["xml_encoding"] = Lehtml::getSetting( $curr, "encoding" );
			}
			// *********************************************************************
			
			
			
			
			/** Läser saker inuti ett inlägg ***************************************
			$initem bestäms av en if-sats ovan, det betyder att vi faktiskt kommer
			få <item> eller <entry> här inuti, men det spelar ingen roll för det
			kommer bara att ignoreras. Dock kommer vi varken få </item> eller </entry>
			*/
			if( $initem )
			{
			
				/** En länk ********************************************************
				Om rel="*" hittas i <link> kommer href="*" att användas. Om rel är
				alternate sparas den i $link, annars i $backup_link (som används om
				en riktig länk aldrig hittas).
				Om <link> inte hinnehåller någon rel läses innehållet mellan <link>
				och </link>.
				*/
				if( strlen($readunt) == 0 && $curr["tag"] == "link" )
				{
					$rel = Lehtml::getSetting( $curr, "rel" );
					if( strlen($rel) == 0 )
					{
						$readunt = "/link";
						$tag_content = "";
						$raw_tag_content = "<".$curr["content"].">";
					}
					else
					{
						if( $rel == "alternate" )
						{
							if( strlen($link) == 0 )
							{
								$link = Lehtml::getSetting( $curr, "href" );
								$raw_link = "<".$curr["content"].">";
							}
						}
						else
						{
							$backup_link = Lehtml::getSetting( $curr, "href" );
							$raw_backup_link = "<".$curr["content"].">";
						}
					}
				}
				/* Vi har en $readunt-hanterare senare i koden, men eftersom innehållet
				mellan <link> och </link> ska läggas i $link istället för i $stuff[]
				får vi hantera den innan.
				*/
				else if( $readunt == "/link" && $curr["tag"] == "/link" )
				{
					if( strlen($link) == 0 )
					{
						$link = $tag_content;
						$raw_link = $raw_tag_content."<".$curr["content"].">";
					}
					$readunt = "";
					$tag_content = "";
					$raw_tag_content = "";
				}
				// *****************************************************************
				
			
				/** Hämta ett fält *************************************************
				Om ett fält finns i $item_fields kommer $readunt att bli 
				"/*fält*" så att innehållet mellan <*fält*> och </*fält*> kommer att
				sparas i $tag_content. $raw_tag_content fyller även i innehållet i
				taggen (samt sista när det är dags att sluta läsa).
				*/
				else if( strlen($readunt) == 0 && in_array($curr["tag"],$item_fields) )
				{
					$readunt = "/".$curr["tag"];
					$tag_content = "";
					$raw_tag_content = "<".$curr['content'].">";
				}
				/* Om $readunt är taggnamnet så är det dags att sluta läsa. Själva
				läsningen sker längre ner i koden. Innehållet sparas enbart i stuff
				om nyckeln inte redan finns.
				*/
				else if( strlen($readunt) > 0 && $readunt == $curr["tag"] )
				{
					$tag_name = substr($curr["tag"],1);
					if( !in_array($tag_name,array_keys($stuff)) )
					{
						$raw_tag_content .= "<".$curr['content'].">";
						$stuff[$tag_name] = $tag_content;
						$stuff["raw_".$tag_name] = $raw_tag_content;
					}
					$tag_content = "";
					$raw_tag_content = "";
					$readunt = "";
				}
				// *****************************************************************
			
			
				/** Läser innehåll *************************************************
				Om $readunt har blivit bestämd måste vi läsa innehållet.
				*/
				else if( strlen($readunt) > 0 )
				{
					if( $curr["istag"] )
					{
						$tag_content .= "<".$curr["content"].">";
						$raw_tag_content .= "<".$curr["content"].">";
					}
					else
					{
						$tag_content .= $curr["content"];
						$raw_tag_content .= $curr["content"];
					}
				}
				// *****************************************************************
			
			
			
			}
			// *********************************************************************
			
			
			
			
			/** Vi befinner oss inte unuti ett inlägg ******************************
			När vi läser saker som inte är inuti <item> eller <entry> kallar vi det
			för "channel". Detta är lite ljugeri eftersom vi faktiskt inte behöver
			befinna oss inuti <channel>
			*/
			else
			{
			
				/** Hämta ett fält *************************************************
				Om vi stöter på ett fält som finns i $channel_fields så ska vi läsa
				innehållet i den. Detta kodstycke fungerar på samma sätt om det som 
				tar hand om inlägg - förutom att variablerna heter annorlunda.
				*/
				if( strlen($channel_readunt) == 0 && in_array($curr["tag"],$channel_fields) )
				{
					$channel_readunt = "/".$curr["tag"];
					$channel_tag_content = "";
					$channel_raw_tag_content = "<".$curr['content'].">";
				}
				else if( strlen($channel_readunt) > 0 && $channel_readunt == $curr["tag"] )
				{
					if( !in_array(substr($curr["tag"],1),array_keys($out)) )
					{
						$tag_name = substr($curr["tag"],1);
						
						$channel_raw_tag_content .= "<".$curr['content'].">";
						$out[$tag_name] = $channel_tag_content;
						$out["raw_".$tag_name] = $channel_raw_tag_content;
					}
					$channel_tag_content = "";
					$channel_raw_tag_content = "";
					$channel_readunt = "";
				}
				// *****************************************************************
				
				
				/** Läser innehåll *************************************************
				Även denna kodsnutt fungerar som den som tar hand om inlägg - förutom
				att variablerna heter andra saker.
				*/
				else if( strlen($channel_readunt) > 0 )
				{
					if( $curr["istag"] )
					{
						$channel_tag_content .= "<".$curr["content"].">";
						$channel_raw_tag_content .= "<".$curr["content"].">";
					}
					else
					{
						$channel_tag_content .= $curr["content"];
						$channel_raw_tag_content .= $curr["content"];
					}
				}
				// ******************************************************************
				
				
			}
			// *********************************************************************
		

		}

		
		/** kör auto_strtotime *****************************************************
		Samma kodsnutt går att hitta längre upp i koden, fast denna tar hand om $out
		istället för $stuff. Vi behöver inte oroa oss för att råka paja "items"
		eftersom den läggs till först senare.
		*/
		foreach( $auto_strtotime as $as )
		{
			if( in_array($as,array_keys($out)) )
			{
				if( !isset($out["unformatted_".$as]) )
				{
					$out["unformatted_".$as] = $out[$as];
				}
				$out[$as] = strtotime($out[$as]);
			}
		}
		// *************************************************************************
		
		
		/** Kör auto_cdata *********************************************************
		Samma kodsnutt går att hitta längre upp i koden, fast denna tar hand om $out
		istället för $stuff. Vi behöver inte oroa oss för att råka paja "items"
		eftersom den läggs till först senare.
		*/
		foreach( $auto_cdata as $as )
		{
			if( in_array($as,array_keys($out)) )
			{
				if( !isset($out["unformatted_".$as]) )
				{
					$out["unformatted_".$as] = $out[$as];
				}
				$out[$as] = self::cdata($out[$as]);
			}
		}
		// *************************************************************************
		
		
		/** Merging ****************************************************************
		Detta är en kopia av merging-stycket i inläggen. Denna tar dock hand om $out
		istället för $stuff. Kommentarerna under är kopierade.
		Förklarar första kodstycket:
		$merging ser ut såhär; $merging["*alias*"] = array(*fält*);
		*alias* är namnet som fälten ska sammanfogas till, *fält* är de fält
		som ska sammanfogas till *alias*.
		Loopar igenom alla mergingar, som i sin tur loopar igenom alla fält.
		Det första fältet som hittas kommer att kopieras till *alias*.
		Dessutom får vi kopiera över raw_* samt eventuell unformatted_*.
		För att användaren ska veta vilket av dessa fält som valdes skapar
		vi även *_field med namnet på ursprungsfältet.
		*/
		$partial_out = array();
		foreach( $merging as $alias => $fields )
		{
			foreach( $fields as $field )
			{
				if( in_array($field,array_keys($out)) )
				{
					$partial_out[$alias] = $out[$field];
					$partial_out["raw_".$alias] = $out["raw_".$field];
					if( isset($out["unformatted_".$field]) ){ $partial_out["unformatted_".$alias] = $out["unformatted_".$field]; }
					$partial_out[$alias."_field"] = $field;
					break;
				}
			}
		}

		/* Vi vill inte ha kvar de gamla fälten som blev över efter
		mergingen, därför tar vi bort alla fält i alla mergings. Eftersom vi
		sedan mergar orginal-stuff med vår temporära stuff (partial_stuff)
		behöver vi inte oroa oss över om vi råkar ta bort ett fält som är
		alias.
		*/
		foreach( array_values($merging) as $fields )
		{
			foreach( $fields as $field )
			{
				unset( $out[$field] );
				unset( $out["raw_".$field] );
				unset( $out["unformatted_".$field] );
			}
		}
		$out = array_merge( $out, $partial_out );
		// *************************************************************************
		
		
		// Lägger till $items till $out:
		$out["items"] = $items;
		
		// Kastar tillbaka allting till användaren:
		return $out;
	}



	static function formatRssText( $str )
	{
		$str = self::cdata($str);
		
		$illegal_tags_and_content = array("style","script");
		$illegal_tags = array("pre","/pre","form","/form");
		//Borttaget: display, font-weight
		$allowed_css = array("azimuth", "background", "background-color", "border", "border-bottom", "border-bottom-color", "border-bottom-style", "border-bottom-width", "border-collapse", "border-color", "border-left", "border-left-color", "border-left-style", "border-left-width", "border-right", "border-right-color", "border-right-style", "border-right-width", "border-spacing", "border-style", "border-top", "border-top-color", "border-top-style", "border-top-width", "border-width", "clear", "color", "cursor", "direction", "elevation", "float", "font", "font-family", "font-size", "font-style", "font-variant", "height", "letter-spacing", "line-height", "margin", "margin-bottom", "margin-left", "margin-right", "margin-top", "overflow", "padding", "padding-bottom", "padding-left", "padding-right", "padding-top", "pause", "pause-after", "pause-before", "pitch", "pitch-range", "richness", "speak", "speak-header", "speak-numeral", "speak-punctuation", "speech-rate", "stress", "text-align", "text-decoration", "text-indent", "unicode-bidi", "vertical-align", "voice-family", "volume", "white-space", "width");
		$autoclose_tags = array("div","span","p","b","strong","i","u","h1","h2","h3","h4","h5","h6","a","table","big","small","tt");
		$illegal_settings = array("class","onclick","onmouseout","onmouseover","onmousemove");

		$html = Lehtml::parse( $str );
		$str = "";
		$inside_pre = false;
		$all_tags = array();
		foreach( $html as &$curr )
		{
			// Readunt används till om man är tvungen att readera större stycken som till exempel innehållet i <script>
			if( strlen($readunt) == 0 )
			{
				if( $curr['istag'] )
				{
					
					// <pre> ska formateras om, därför måste vi veta att vi är inuti en <pre>
					// Detta kodstycke håller bara reda  på $inside_pre
					if( $curr['tag'] == "pre" )
					{
						$inside_pre = true;
					}
					else if( $curr['tag'] == "/pre" )
					{
						$inside_pre = false;
					}
					// *************************************************************
					
					// Taggen och dess innehåll får inte komma med i output ********
					if( in_array( $curr['tag'], $illegal_tags_and_content ) )
					{
						$readunt = "/".$curr['tag'];
					}
					// *************************************************************
					
					// Taggen är okej om den kommer med, därför kär vi på **********
					else if( !in_array($curr['tag'], $illegal_tags) )
					{
					
						// Lägger till taggen i $all_tags **************************
						if( !in_array($curr['tag'], array_keys($all_tags) ) )
						{
							$all_tags[$curr['tag']] = 0;
						}
						// *********************************************************
						
						$ok_to_rock = false;
						
						// Taggen är en avslutare, så vi får kolla om det faktiskt
						// finns någonting att avsluta.
						if( $curr['tag'][0] == "/" )
						{
							$f = substr( $curr['tag'], 1);
							if( !in_array($f,array_keys($all_tags)) )
							{
								$all_tags[$f] = 0;
							}
							if( $all_tags[$f] > $all_tags[$curr['tag']] )
							{
								$ok_to_rock = true;
							}
						}
						// *********************************************************
						
						// Det är inte en avslutare, men vi måste dona lite ********
						else
						{
							$ok_to_rock = true;
							$setclass = "";
							
							// Vi tillåter bara vissa CSS-regler *******************
							$css = Lehtml::getSetting($curr,"style");
							if( strlen(trim($css)) > 0 )
							{
								$css = Lehtml::parseCssClass( $css );
								$ooo = "";
								$keys = array_keys( $css );
								foreach( $keys as  &$cc )
								{
									// Kollar om inställningen är okej *************
									if( in_array(strtolower($cc),$allowed_css) )
									{
										$ooo .= $cc.": ".$css[$cc]." ";
									}
									// *********************************************
									
									// font-weight är inte okej, för vi vill sätta en speciell klass på den
									else if( strtolower($cc) == "font-weight" )
									{
										if( strtolower($css[$cc]) != "normal" )
										{
											$setclass = "bold_text";
										}
									}
									// *********************************************
								}
								$curr['settings']['style'] = $ooo;
							}
							// *****************************************************
							
							// Tar bort "class", "onclick" osv *********************
							foreach( $illegal_settings as &$sette )
							{
								unset($curr['settings'][$sette]);
							}
							// *****************************************************
							
							// Lägger tillaka "class" om man så önskar *************
							if( strlen($setclass) > 0 )
							{
								$curr['settings']['class'] = $setclass;
							}
							// *****************************************************
						}
						// *********************************************************
						
						
						// Lägger till taggen till output **************************
						if( $ok_to_rock )
						{
							$all_tags[$curr['tag']] ++;
							
							
							// Om det är en länk måste vi dona lite först **********
							if( $curr['tag'] == "a" )
							{
								$url = Lehtml::getSetting( $curr, "href" );
								if( !Levalid::httpInTheBeginning( $url ) )
								{
									$url = "http://" . $url;
								}
								$curr['settings']['href'] = $url;
								$curr['settings']['target'] = "_blank";
							}
							// *****************************************************
							
							
							$str .= Lehtml::makeTag( $curr );
						}
						// *********************************************************
						
						
					}
					// *************************************************************
				}
				
				
				// Det är inte en tagg vi donar med just nu ************************
				else
				{
					if( $inside_pre )
					{
						$curr['content'] = str_replace( "\n", "<br>", $curr['content'] );
					}
					$str .= $curr['content'];
				}
				// *****************************************************************
				
			}
			
			// Så länge vi inte är inuti en "farlig" tagg som <script> är det okej att rocka
			else if( $curr['tag'] == $readunt )
			{
				$readunt = "";
			}
			// *********************************************************************
		}
		
		
		// Autoavslutar taggar som fattas ******************************************
		foreach( $autoclose_tags as $tag )
		{
			if( !isset($alltags[$tag]) )
			{
				$alltags[$tag] = 0;
			}
			if( !isset($alltags["/".$tag]) )
			{
				$alltags["/".$tag] = 0;
			}
			for( $i = 0; $i < $alltags[$tag]-$alltags["/".$tag]; $i++ )
			{
				$str .= "</$tag>";
			}
		}
		// *************************************************************************
		
		
		return $str;
	}

	static function isRss( $src, $feedurl )
	{
		$html = Lehtml::parse( $src );
		$xml = false;
		$urls = array();
		$urls_title = array();

		
		# Going through the document ***********************************************
		foreach( $html as $curr )
		{
		
			# The document sure is RSS *********************************************
			if( $curr["tag"] == "?xml" || $curr["tag"] == "rss" )
			{
				$xml = true;
				break;
			}
			# **********************************************************************
			
			# The document have a link-tag to rss-stream ***************************
			else if( $curr["tag"] == "link" )
			{
				$typ = strtolower(Lehtml::getSetting( $curr, "type" ));
				if( $typ == "application/atom+xml" || $typ == "application/rss+xml")
				{
					$link = trim(Lehtml::getSetting( $curr, "href" ));
					if( strtolower(substr($link,0,7)) != "http://" && strtolower(substr($link,0,8)) != "https://" )
					{
						$link = $feedurl."/".$link;
					}
					
					if( strlen($link) > 0 )
					{
						$urls[] = $link;
						$urls_title[] = trim(Lehtml::getSetting( $curr, "title" ));
					}
				}
			}
			# **********************************************************************
		}
		# **************************************************************************
	
		if( sizeof($urls) == 0 )
		{
			$ff = array();
		}
		else
		{
			$ff = array_combine($urls, $urls_title);
		}
			
		return array($xml, $ff );
	}


}