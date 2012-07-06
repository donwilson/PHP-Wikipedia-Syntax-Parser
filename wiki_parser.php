<?php
	
	/**
	 * Jungle Wikipedia Syntax Parser
	 * 
	 * @link https://github.com/donwilson/PHP-Wikipedia-Syntax-Parser
	 * 
	 * @author Don Wilson <donwilson@gmail.com>
	 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
	 * @package Jungle
	 * @subpackage Wikipedia Syntax Parser
	 * 
	 * @todo Add option for extracting only specific types of information - Jungle_WikiSyntax_Parser::set_extract(array('page_attributes', 'major_sections', 'external_links'))
	 * @todo Toggle debug mode - Jungle_WikiSyntax_Parser::show_debug() and Jungle_WikiSyntax_Parser::hide_debug()
	 */
	
	class Jungle_WikiSyntax_Parser {
		private $text = "";
		private $title = "";
		private $cargo = array();
		
		/**
		 * @param string $text Raw Wikipedia syntax (found via a Page's Edit textarea or from the Wikiepdia Data Dump page->revision->text)
		 */
		
		public function __construct($text, $title="") {
			$this->text = $text;
			$this->title = $title;
			
			$this->cargo['page_attributes'] = array();
		}
		
		/**
		 * @return array Contents of $this->cargo
		 */
		
		public function parse() {
			$this->initial_clean();
			
			$this->page_attributes();
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
		
		
		// Information extractors
		
		/**
		 * 
		 */
		
		private function page_attributes() {
			$page_attributes = array(
				 'type' => false
				,'child_of' => ""
			);
			
			if($page_attributes['type'] === false && (preg_match("#^(Template|Wikipedia|Portal|User|File|MediaWiki|Template|Category|Book|Help|Course|Institution)\:#si", $this->title, $match))) {
				// special wikipedia pages
				
				$page_attributes['type'] = strtolower($match['1']);
			}
			
			if($page_attributes['type'] === false && (preg_match("#\#REDIRECT(?:\s*?)\[\[([^\]]+?)\]\]#si", $this->text, $match))) {
				// redirection
				
				$page_attributes['type'] = "redirect";
				$page_attributes['child_of'] = $match[1];
			}
			
			if($page_attributes['type'] === false && (preg_match("#\{\{(disambig|hndis|disamb|hospitaldis|geodis|disambiguation|mountainindex|roadindex|school disambiguation|hospital disambiguation|mathdab|math disambiguation)((\|[^\}]+?)?)\}\}#si", $this->text, $match))) {
				// disambiguation file
				
				$page_attributes['type'] = "disambiguation";
				$page_attributes['disambiguation_key'] = $match[1];
				
				if(!empty($match[2])) {
					$page_attributes['disambiguation_value'] = $match[2];
				}
				
				return;
			}
			
			if($page_attributes['type'] === false) {
				// just a normal page
				$page_attributes['type'] = "main";
			}
			
			$this->cargo['page_attributes'] = $page_attributes;
		}
		
		/**
		 * @todo Some pages use {{MLB infobox ... }} instead of {{Infobox MLB ... }} [ex: http://en.wikipedia.org/wiki/Texas_Rangers_(baseball)]. I think {{MLB ...}} is an actual Wikipedia template and not distinctly an Infobox template
		 */
		
		private function infoboxes() {
			$infobox = array();
			
			preg_match_all("#\{\{(?:\s*?)Infobox(?:\s*?)(.+?)". PHP_EOL ."(.+?)". PHP_EOL ."\}\}". PHP_EOL ."#si", $this->text, $matches);
			
			if(!empty($matches[0])) {
				foreach($matches[0] as $key => $nil) {
					$infobox_values = array();
					$infobox_tmp = $matches[2][$key];
					
					$infobox_tmp = explode("\n", $infobox_tmp);
					$last_line_key = "";
					
					foreach($infobox_tmp as $line) {
						$line = trim($line);
						
						if(preg_match("#^\|#si", $line)) {
							$line = preg_replace("#^\|(\s*?)#si", "", $line);
							$bits = explode("=", $line, 2);
							
							$line_key = trim(preg_replace("#[^A-Za-z0-9]#si", "_", strtolower($bits[0])), "_");
							$line_value = trim($bits[1]);
							
							$infobox_values[$line_key] = array();
						} else {
							if(!isset($infobox_values[$last_line_key])) {
								continue;   // this is likely an editor message of some sort
							}
							
							$line_key = $last_line_key;
							$line_value = $line;
						}
						
						$line_values = preg_split("#<(?:\s*?)br(?:\s*?)(/?)(?:\s*?)>#si", $line_value, -1, PREG_SPLIT_NO_EMPTY);
						
						$infobox_values[$line_key] = array_merge($infobox_values[$line_key], $line_values);
						
						$last_line_key = $line_key;
					}
					
					$infobox[] = array(
						 'type' => $matches[1][$key]
						,'contents' => $infobox_values
					);
				}
			}
			
			$this->cargo['infoboxes'] = $infobox;
		}
		
		private function major_sections() {
			$major_sections = array();
			
			$major_sections_splits = preg_split("#(?:\s{1,})==(?:\s*?)([^=]+?)(?:\s*?)==#si", $this->text, -1, PREG_SPLIT_DELIM_CAPTURE);
			
			$this->cargo['intro_section'] = array_shift($major_sections_splits);
			
			if(!empty($major_sections_splits)) {
				foreach($major_sections_splits as $key => $text) {
					if(($key % 2) == 1) {
						$major_sections[] = array(
							 'title' => $major_sections_splits[($key - 1)]
							,'text' => $major_sections_splits[$key]
						);
					}
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
			
			preg_match("#". PHP_EOL ."==(?:\s*?)External(?:\s{1,}?)links(?:\s*?)==(.+?)". PHP_EOL . PHP_EOL ."#si", $this->text, $matches);
			
			if(!empty($matches[1])) {
				$lines = explode("\n", $matches[1]);   // \n is better than PHP_EOL as the line separators in the wiki syntax might not be the same as the OS-specific PHP_EOL [maybe there's a better way of doing this?]
				
				$this->cargo['debug_el'] = $lines;   // temporary
				
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
			
			preg_match_all("#\[\[(?:\s*?)Category\:([^\]\]]+?)\]\]#si", $this->text, $matches);
			
			if(!empty($matches[0])) {
				foreach($matches[1] as $nil => $mvalue) {
					$categories[] = trim($mvalue);
				}
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
			}
			
			$this->cargo['foreign_wiki'] = $foreign_wikis;
		}
		
		/**
		 * @todo http://en.wikipedia.org/wiki/Wikipedia:Citation_templates
		 */
		
		private function citations() {
			$citations = array();
			
			// ...
			
			$this->cargo['citations'] = $citations;
		}
		
		
		// Preparation utilities
		
		private function initial_clean() {
			// strip out the crap we don't need
			
			$this->cargo['title'] = trim($this->title);
			
			$this->text = preg_replace("#<!\-\-(.+?)\-\->#si", "", $this->text);   // get rid of unneeded Editor comments
			$this->text = PHP_EOL . PHP_EOL . $this->text . PHP_EOL . PHP_EOL;   // the wrapped PHP_EOL adds for easier regex matches
		}
		
		private function pack_cargo() {
			//$this->cargo['raw_text'] = $this->text;
		}
	}