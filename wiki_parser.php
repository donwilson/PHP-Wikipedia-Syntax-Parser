<?php
	
	/**
	 * PHP Wikipedia Syntax Parser
	 *
	 * @link		https://github.com/donwilson/PHP-Wikipedia-Syntax-Parser
	 *
	 * @package		Jungle
	 * @subpackage	Wikipedia Syntax Parser
	 *
	 * @todo		Citations: http://en.wikipedia.org/wiki/Wikipedia:Citation_templates
	 */
	
	class Jungle_WikiSyntax_Parser {
		private $text = "";
		private $title = "";
		private $cargo = array();
		
		public function __construct($text, $title="") {
			$this->text = $text;
			$this->title = $title;
			
			$this->cargo['page_attributes'] = array();
		}
		
		public function parse() {
			$this->initial_clean();
			
			$this->page_type();
			$this->major_sections();
			$this->person_data();
			$this->external_links();
			$this->categories();
			$this->infoboxes();
			$this->foreign_wikis();
			//$this->citations();
			
			$this->pack_cargo();
			
			return $this->cargo;
		}
		
		
		// Extract information
		
		private function page_type() {
			if(preg_match("#^(Template|Wikipedia|Portal|User|File|MediaWiki|Template|Category|Book|Help|Course|Institution)\:#si", $this->title, $match)) {
				// special wikipedia pages
				
				$this->cargo['page_type'] = strtolower($match['1']);
				
				return;
			}
			
			if(preg_match("#\#REDIRECT(?:\s*?)\[\[([^\]]+?)\]\]#si", $this->text, $match)) {
				// redirection
				
				$this->cargo['page_type'] = "redirect";
				$this->cargo['child_of'] = $match[1];
				
				return;
			}
			
			if(preg_match("#\{\{(disambig|hndis|disamb|hospitaldis|geodis|disambiguation|mountainindex|roadindex|school disambiguation|hospital disambiguation|mathdab|math disambiguation)((\|[^\}]+?)?)\}\}#si", $this->text, $match)) {
				// disambiguation file
				
				$this->cargo['page_type'] = "disambiguation";
				$this->cargo['page_attributes']['disambiguation_key'] = $match[1];
				
				if(!empty($match[2])) {
					$this->cargo['page_attributes']['disambiguation_value'] = $match[2];
				}
				
				return;
			}
			
			// just a normal page
			$this->cargo['page_type'] = "main";
		}
		
		private function infoboxes() {
			$infobox = array();
			
			preg_match_all("#\{\{Infobox(?:\s*?)(.+?)". PHP_EOL ."(.+?)". PHP_EOL ."\}\}". PHP_EOL ."#si", $this->text, $matches);
			
			if(!empty($matches[0])) {
				foreach($matches[0] as $key => $nil) {
					$infobox_values = array();
					$infobox_tmp = $matches[2][$key];
					
					$infobox_tmp = explode(PHP_EOL, $infobox_tmp);
					
					foreach($infobox_tmp as $line) {
						$line = preg_replace("#^\|(\s*?)#si", "", $line);
						$bits = explode("=", $line, 2);
						$line_key = trim(preg_replace("#[^A-Za-z0-9]#si", "_", strtolower($bits[0])), "_");
						$line_value = trim($bits[1]);
						
						$infobox_values[] = array(
							 'key' => $line_key
							,'value' => $line_value
						);
					}
					
					$infobox[] = array(
						 'type' => $matches[1][$key]
						,'type_key' => trim(preg_replace("#[^A-Za-z0-9]#si", "_", strtolower($matches[1][$key])), "_")
						//,'raw_insides' => $matches[2][$key]
						,'contents' => $infobox_values
					);
				}
			}
			
			$this->cargo['infoboxes'] = $infobox;
		}
		
		private function major_sections() {
			$major_sections = array();
			
			// ... preg_split ...
			
			preg_match_all("#==([^=]+?)==". PHP_EOL ."#si", $this->text, $matches);
			
			if(!empty($matches[0])) {
				foreach($matches[1] as $section_title) {
					$major_sections[] = trim($section_title);
				}
			}
			
			
			$this->cargo['major_sections'] = $major_sections;
		}
		
		private function person_data() {
			$person_data = array();
			
			preg_match("#\{\{(?:\s*?)Persondata". PHP_EOL ."(.+?)". PHP_EOL ."\}\}". PHP_EOL ."#si", $this->text, $match);
			
			if(!empty($match[0])) {
				$person_data_tmp = $match[1];
				
				$person_data_tmp = explode(PHP_EOL, $person_data_tmp);
				
				foreach($person_data_tmp as $line) {
					$line = preg_replace("#^\|(\s*?)#si", "", $line);
					$bits = explode("=", $line, 2);
					$line_key = trim(preg_replace("#[^A-Za-z0-9]#si", "_", strtolower($bits[0])), "_");
					$line_value = trim($bits[1]);
					
					$person_data[ $line_key ] = $line_value;
				}
			}
			
			$this->cargo['person_data'] = $person_data;
		}
		
		private function external_links() {
			$external_links = array();
			
			preg_match("#". PHP_EOL ."==(?:\s*?)External links(?:\s*?)==(.+?)". PHP_EOL . PHP_EOL ."#si", $this->text, $matches);
			
			if(!empty($matches[1])) {
				$lines = explode("\n", $matches[1]);
				
				$this->cargo['debug_el'] = $lines;
				
				if(!empty($lines)) {
					foreach($lines as $line) {
						if(preg_match("#^\*(.+?)$#si", trim($line), $match)) {
							$line_value = trim($match[1]);
							
							switch(true) {
								case preg_match("#\[(?:\s*?)http(s?)\:\/\/([^\s]+?)(?:\s+?)([^\]]+?)(?:\s*?)]#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => 'url'
										,'attributes' => array(
											 'url' => "http". $lmatch[1] ."://". $lmatch[2]
											,'text' => trim($lmatch[3])
										)
									);
									break;
								case preg_match("#\[(?:\s*?)([^\s]+?)(\s+?)http(s?)\:\/\/([^\s]+?)(?:\s*?)]#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => 'url'
										,'attributes' => array(
											 'url' => "http". $lmatch[2] ."://". $lmatch[3]
											,'text' => trim($lmatch[1])
										)
									);
									break;
								case preg_match("#\{\{(?:\s*?)official(?:\s+?)([^\|]+?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => "official"
										,'attributes' => array(
											 'type' => trim($lmatch[1])
											,'value' => trim($lmatch[2])
										)
									);
									break;
								case preg_match("#\{\{(?:\s*?)(myspace|facebook|twitter)(?:\s*?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => strtolower(trim($lmatch[1]))
										,'attributes' => array(
											 'username' => trim($lmatch[2])
										)
									);
									break;
								case preg_match("#\{\{(?:\s*?)reverbnation(?:\s*?)\|([^\|]+?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => "reverbnation"
										,'attributes' => array(
											 'username' => $lmatch[1]
											,'name' => $lmatch[2]
										)
									);
									break;
								case preg_match("#\{\{(?:\s*?)spotify(?:\s*?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
									$bits = explode("|", $lmatch[1]);
									
									$spotify_attrs = array(
										 'code' => $bits[0]
									);
									
									if(count($bits) > 1) {
										$spotify_attrs['name'] = $bits[1];
									}
									
									if(count($bits) > 2) {
										$spotify_attrs['type'] = $bits[2];
									}
									
									$external_links[] = array(
										 'type' => "spotify"
										,'attributes' => $spotify_attrs
									);
									
									unset($bits, $spotify_attrs);
									break;
								case preg_match("#\{\{(?:\s*?)dmoz(?:\s*?)\|([^\|]+?)\|([^\|]+?)\|(?:\s*?)user(?:\s*?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => "dmoz"
										,'attributes' => array(
											 'username' => trim($lmatch[1])
											,'name' => trim($lmatch[2])
										)
									);
									break;
								case preg_match("#\{\{(?:\s*?)dmoz(?:\s*?)\|([^\|]+?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => "dmoz"
										,'attributes' => array(
											 'category' => trim($lmatch[1])
											,'title' => trim($lmatch[2])
										)
									);
									break;
								case preg_match("#\{\{(?:\s*?)imdb(?:\s*?)([^\|]+?)\|(?:\s*?)([0-9]+?)(?:\s*?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => "imdb"
										,'attributes' => array(
											 'type' => trim($lmatch[1])
											,'id' => ltrim(trim($lmatch[2]), "0")
										)
									);
									break;
								case preg_match("#\{\{(?:\s*?)mtv(?:\s*?)([^\|]+?)\|(?:\s*?)([A-Za-z0-9\-\_]+?)(?:\s*?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => "mtv"
										,'attributes' => array(
											 'type' => trim($lmatch[1])
											,'id' => trim($lmatch[2])
										)
									);
									break;
								case preg_match("#\{\{(?:\s*?)amg(?:\s*?)([^\|]+?)\|(?:\s*?)([0-9]+?)(?:\s*?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => 'amg'
										,'attributes' => array(
											 'type' => trim($lmatch[1])
											,'id' => trim($lmatch[2])
										)
									);
									break;
								case preg_match("#\{\{(?:\s*?)allmusic(?:\s*?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
									$tmp = array(
										 'type' => 'allmusic'
										,'attributes' => array()
									);
									
									$tmp_attrs = explode("|", $lmatch[1]);
									
									if(!empty($tmp_attrs)) {
										$tmp['attributes'] = array();
										
										foreach($tmp_attrs as $tmp_value_raw) {
											$tmp_value = explode("=", $tmp_value_raw, 2);
											
											$tmp['attributes'][ trim( $tmp_value[0] ) ] = trim( $tmp_value[1] );
										}
										
										unset($tmp_value_raw, $tmp_value);
									}
									
									$external_links[] = $tmp;
									
									unset($tmp, $tmp_attrs);
									break;
								case preg_match("#\{\{(?:\s*?)(imdb|discogs|musicbrainz)(?:\s*?)([^\|]+?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
									$tmp = array(
										 'type' => strtolower(trim($lmatch[1]))
										,'attributes' => array(
											'type' => strtolower(trim($lmatch[2]))
										)
									);
									
									$tmp_attrs = explode("|", $lmatch[3]);
									
									if(!empty($tmp_attrs)) {
										foreach($tmp_attrs as $tmp_value_raw) {
											$tmp_value = explode("=", $tmp_value_raw, 2);
											
											$tmp['attributes'][ trim( $tmp_value[0] ) ] = trim( $tmp_value[1] );
										}
										
										unset($tmp_value_raw, $tmp_value);
									}
									
									$external_links[] = $tmp;
									
									unset($tmp, $tmp_attrs);
									break;
								default:
									$external_links[] = array(
										 'type' => 'raw'
										,'text' => $line_value
									);
									
									// ............... eventually ignore any malformed text ............... //
							}
						}
					}
				}
			}
			
			$this->cargo['external_links'] = $external_links;
		}
		
		private function categories() {
			$categories = array();
			
			preg_match_all("#\[\[Category\:([^\]]+?)\]\]#si", $this->text, $matches);
			
			if(!empty($matches[0])) {
				$categories[] = $matches[1];
			}
			
			$this->cargo['categories'] = $categories;
		}
		
		private function foreign_wikis() {
			$foreign_wikis = array();
			
			preg_match_all("#\[\[([a-z]{2}|simple):([^\]]+?)\]\]#si", $this->text, $matches);
			
			if(!empty($matches[0])) {
				foreach($matches[0] as $mkey => $nil) {
					$foreign_wikis[ $matches[1][$mkey] ] = trim($matches[2][$mkey]);
				}
				
				unset($mkey, $nil);
			}
			
			$this->cargo['foreign_wiki'] = $foreign_wikis;
		}
		
		private function citations() {
			$citations = array();
			
			// ...
			
			$this->cargo['citations'] = $citations;
		}
		
		
		// Preparation utilities
		
		private function initial_clean() {
			// strip out the crap we don't need
			
			$this->cargo['title'] = $this->title;
			
			$this->text = PHP_EOL . PHP_EOL . preg_replace("#<!\-\- (.+?) \-\->#si", "", $this->text) . PHP_EOL . PHP_EOL;   // the wrapped PHP_EOL adds for easier regex matches
		}
		
		private function pack_cargo() {
			//$this->cargo['raw_text'] = $this->text;
		}
	}