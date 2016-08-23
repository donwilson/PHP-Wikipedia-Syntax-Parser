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
		private static $raw_text;
		private $text = "";
		private $title = "";
		private $cargo = array();
		private $options = array();
		
		/**
		 * @param string $text Raw Wikipedia syntax (found via a Page's Edit textarea or from the Wikiepdia Data Dump page->revision->text)
		 */
		
		public function __construct($text, $title="", $user_options=false) {
			$this->title = $title;
			
			// remove comments
			$text = preg_replace("#". preg_quote("<!--", "#") ."(.*?)". preg_quote("-->", "#") ."#si", "", $text);
			$this->text = $text;
			
			$this->raw_text = $text;
			
			$this->cargo['page_attributes'] = array();
			
			if(!empty($user_options) && is_array($user_options)) {
				$this->options = $user_options + $this->options;
			}
		}
		
		/**
		 * @return array Contents of $this->cargo
		 */
		
		public function parse() {
			$this->initial_clean();
			
			$this->templates();
			
			$this->page_attributes();
			$this->sections();
			$this->person_data();
			$this->external_links();
			$this->categories();
			$this->infoboxes();
			$this->foreign_wikis();
			$this->citations();
			//$this->mentions();
			$this->attachments();
			
			$this->pack_cargo();
			
			return $this->cargo;
		}
		
		
		// Information extractors
		
		/**
		 * Extract all Wiki templates used
		 */
		
		private function templates() {
			
			$templates = array();
			
			//preg_match_all("#(". preg_quote("{{", "#") .")(.*?)(". preg_quote("}}", "#") .")#si", $this->text, $matches);
			$text_templates = $this->_find_sub_templates($this->text);
			
			foreach($text_templates[0] as $text_template) {
				preg_match("#^\{\{([^\|]+)(.*?)\}\}$#si", $text_template, $match);
				
				$template_name = trim($match[1]);   // wikisyntax is very case sensitive, so don't use strtolower or similar
				
				if(!empty($this->options['ignore_template_matches'])) {
					foreach($this->options['ignore_template_matches'] as $ignore_template_match_regex) {
						if(preg_match($ignore_template_match_regex, $template_name)) {
							continue 2;
						}
					}
				}
				
				$template_attributes = trim(ltrim(trim($match[2]), "|"));   // remove trailing spaces and starting pipe
				
				$template = array(
					 'original'		=> $text_template
					,'template'		=> $template_name
					,'attributes'	=> false
				);
				
				if(strlen($template_attributes) > 0) {
					$template['attributes']	= $template_attributes;
				}
				
				//$sub_templates = $this->_find_sub_templates($template['original']);   // eventually...
				
				$templates[] = $template;
			}
			
			if(!empty($templates)) {
				$this->cargo['templates'] = $templates;
			}
		}
		
			private function _find_sub_templates($string) {
				preg_match_all("/\{\{(?:[^{}]|(?R))*}}/", $string, $matches);
				
				return $matches;
			}
		
		
		/**
		 * Determine page attributes [if available]
		 */
		
		private function page_attributes() {
			$page_attributes = array(
				 'type' => false
				,'child_of' => false
				,'context_type' => false
				,'date_format' => false
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
			}
			
			if($page_attributes['type'] === false) {
				// just a normal page
				$page_attributes['type'] = "main";
			}
			
			if(preg_match("#\{\{(?:\s*?)Use(?:\s+?)([ymdhs]{3,5})(?:\s+?)date#si", $this->text, $match)) {
				$page_attributes['date_format'] = $match[1];
			}
			
			if(!empty($page_attributes)) {
				$this->cargo['page_attributes'] = $page_attributes;
			}
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
							$line = ltrim(ltrim($line, "|"));
							$bits = explode("=", $line, 2);
							
							$line_key = $this->compact_to_slug($bits[0]);
							$line_value = (isset($bits[1])?trim($bits[1]):"");
							
							if(preg_match("#<(?:\s*?)br(?:\s*?)(/?)(?:\s*?)>#si", $line_value)) {
								$line_value = preg_split("#<(?:\s*?)br(?:\s*?)(/?)(?:\s*?)>#si", $line_value, -1, PREG_SPLIT_NO_EMPTY);
							}
							
							if(!is_array($line_value)) {
								if(strlen($line_value) < 1) {
									continue;
								}
							}
							
							$infobox_values[$line_key] = $line_value;
							
							$last_line_key = $line_key;
							continue;
						}
						
						if(!isset($infobox_values[$last_line_key])) {
							continue;   // this is likely an editor message of some sort
						}
						
						if(strlen($line) < 1) {
							continue;
						}
						
						// multi-line infobox value
						$line_key = $last_line_key;
						$line_value = $line;
						
						$line_values = preg_split("#<(?:\s*?)br(?:\s*?)(/?)(?:\s*?)>#si", $line_value, -1, PREG_SPLIT_NO_EMPTY);
						
						if(!is_array($infobox_values[$line_key])) {
							$infobox_values[$line_key] = array($infobox_values[$line_key]);
						}
						
						$infobox_values[$line_key] = array_merge($infobox_values[$line_key], $line_values);
						
						$last_line_key = $line_key;
					}
					
					if(empty($infobox_values)) {
						continue;
					}
					
					$infobox[] = array_merge(
						 array('_type' => $this->compact_to_slug($matches[1][$key]))
						,$infobox_values
					);
				}
			}
			
			if(!empty($infobox)) {
				$this->cargo['infoboxes'] = $infobox;
			}
		}
		
		private function sections() {
			$section_titles = array();
			$sections = array();
			
			$sections_splits = preg_split("#(?:\s{1,})==(?:\s*?)([^=]+?)(?:\s*?)==#si", $this->text, -1, PREG_SPLIT_DELIM_CAPTURE);
			
			if(empty($sections_splits)) {
				return;
			}
			
			$_intro_section = array_shift($sections_splits);
			
			
			//$sections['_intro'] = $this->clean_wiki_text($_intro_section);
			
			$sections['_intro'] = array(
				'old' => $_intro_section
				,'cleaned' => $this->clean_wiki_text($_intro_section)
			);
			
			if(!empty($sections_splits)) {
				foreach($sections_splits as $key => $text) {
					if(($key % 2) !== 1) {
						continue;
					}
					
					$section_key = $this->compact_to_slug($sections_splits[($key - 1)]);
					
					if(empty($section_key)) {
						continue;
					}
					
					$section_remove_templates = false;
					$section_remove_footer_garb = false;
					
					if($section_key == "references") {
						$section_remove_templates = true;
					}
					
					$section_title = trim($sections_splits[($key - 1)]);
					
					
					//$section_content = $this->clean_wiki_text($sections_splits[$key]);
					$section_content = array(
						 'old' => $sections_splits[$key]
						,'cleaned' => $this->clean_wiki_text($sections_splits[$key])
					);
					
					if(empty($section_title) || empty($section_content)) {
						continue;
					}
					
					$section_titles[ $section_key ] = $section_title;
					$sections[ $section_key ] = $section_content;
				}
			}
			
			if(!empty($section_titles) && !empty($sections)) {
				$this->cargo['section_titles'] = $section_titles;
				$this->cargo['sections'] = $sections;
			}
		}
		
		/*
		private function sections() {
			$this->cargo['sections'] = $this->pull_sections($this->text);
		}
		
			private function pull_sections($text_to_parse, $level=0) {
				$sections = array();
				$section_num = 0;
				
				$sections_splits = preg_split("#(?:\s{1,})(?:={". (2 + $level) ."})(?:\s*?)([^=]+?)(?:\s*?)(?:={". (2 + $level) ."})#si", $text_to_parse, -1, PREG_SPLIT_DELIM_CAPTURE);
				
				if(empty($sections_splits)) {
					return false;
				}
				
				
				if(count($sections_splits) > 1) {
					$sections['_intro'] = array_shift($sections_splits);
					$sections['_intro'] = trim($sections['_intro']);
				}
				
				foreach($sections_splits as $key => $contents) {
					if(($key % 2) == 0) {
						continue;
					}
					
					$title = $sections_splits[($key - 1)];
					
					$sections['sections'][$section_num] = array(
						 'title' => $title
						,'text' => trim($contents)
					);
					
					$contents = PHP_EOL . $contents . PHP_EOL;   // just in case
					
					if(preg_match("#(?:\s{1,})(?:={". (2 + $level + 1) ."})(?:\s*?)([^=]+?)(?:\s*?)(?:={". (2 + $level + 1) ."})#si", $contents)) {
						$sections['children'] = $this->pull_sections($contents, ($level + 1));
					}
					
					$section_num++;
				}
				
				return $sections;
			}
		*/
		
		private function person_data() {
			$person_data = array();
			
			preg_match("#\{\{(?:\s*?)Persondata". PHP_EOL ."(.+?)". PHP_EOL ."\}\}". PHP_EOL ."#si", $this->text, $match);
			
			if(!empty($match[0])) {
				$person_data_tmp = $match[1];
				
				$person_data_tmp = explode(PHP_EOL, $person_data_tmp);
				
				foreach($person_data_tmp as $line) {
					$line = preg_replace("#^\|(\s*?)#si", "", $line);
					$bits = explode("=", $line, 2);
					$line_key = $this->compact_to_slug($bits[0]);
					$line_value = trim($bits[1]);
					
					$person_data[ $line_key ] = $line_value;
				}
			}
			
			if(!empty($person_data)) {
				$this->cargo['person_data'] = $person_data;
			}
		}
		
		private function external_links() {
			$external_links = array();
			
			preg_match("#". PHP_EOL ."==(?:\s*?)External(?:\s{1,}?)links(?:\s*?)==(.+?)". PHP_EOL . PHP_EOL ."#si", $this->text, $matches);
			
			if(!empty($matches[1])) {
				$lines = explode("\n", trim($matches[1]));   // \n is better than PHP_EOL as the line separators in the wiki syntax might not be the same as the OS-specific PHP_EOL [maybe there's a better way of doing this?]
				
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
									$el_type = $this->compact_to_slug($lmatch[1]);
									
									$external_links[] = array(
										 'type' => "official". ((strlen($el_type) > 0)?"_". $el_type:"")
										,'attributes' => trim($lmatch[2])
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
											
											$tmp['attributes'][ trim( @$tmp_value[0] ) ] = trim( @$tmp_value[1] );
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
			
			if(!empty($external_links)) {
				$this->cargo['external_links'] = $external_links;
			}
		}
		
		private function categories() {
			$categories = array();
			
			preg_match_all("#\[\[(?:\s*?)Category\:([^\]\]]+?)\]\]#si", $this->text, $matches);
			
			if(!empty($matches[0])) {
				foreach($matches[1] as $nil => $mvalue) {
					$categories[] = trim($mvalue);
				}
			}
			
			if(!empty($categories)) {
				$this->cargo['categories'] = $categories;
			}
		}
		
		private function foreign_wikis() {
			$foreign_wikis = array();
			
			preg_match_all("#\[\[([a-z]{2,3}|simple):([^\]]+?)\]\]#si", $this->text, $matches);
			
			if(!empty($matches[0])) {
				foreach($matches[0] as $mkey => $nil) {
					$foreign_wikis[ $matches[1][$mkey] ] = trim($matches[2][$mkey]);
				}
			}
			
			if(!empty($foreign_wikis)) {
				$this->cargo['foreign_wiki'] = $foreign_wikis;
			}
		}
		
		private function citations() {
			$citations = array();
			
			preg_match_all("#<ref([^/>]*?)>(.+?)</ref>#si", $this->text, $matches);
			
			if(!empty($matches[0])) {
				foreach($matches[0] as $cite_text) {
					$cite_text = trim(preg_replace("#<(?:\s*?)/?(?:\s*?)ref(?:[^>]*?)>#si", "", $cite_text));
					
					if(strlen($cite_text) > 0) {
						$citations[] = $cite_text;
					}
				}
			}
			
			if(!empty($citations)) {
				$this->cargo['citations'] = $citations;
			}
		}
		
		private function mentions() {
			$mentions = array();
			
			preg_match_all("#\[\[([^\]\]]+?)\]\]#si", $this->text, $matches);
			
			$mentions = @$matches[0];
			
			if(!empty($mentions)) {
				$this->cargo['mentions'] = $mentions;
			}
		}
		
		private function attachments() {
			$attachments = array();
			
			preg_match_all("#\[\[(?:\s*?)(Image|File)\:([^\]\]]+?)\]\]#si", $this->text, $matches);
			
			if(empty($matches)) {
				return;
			}
			
			foreach($matches[0] as $mkey => $mraw) {
				$attachment_details = explode("|", $matches[2][$mkey], 2);
				
				$attachment = array(
					 'type' => $matches[1][$mkey]
					,'filename' => $attachment_details[0]
				);
				
				if(!empty($attachment_details[1])) {
					$attachment['attributes'] = explode("|", $attachment_details[1]);
				}
				
				$attachments[] = $attachment;
				
				unset($attachment);
			}
			
			if(!empty($attachments)) {
				$this->cargo['attachments'] = $attachments;
			}
		}
		
		
		// Preparation utilities
		
		private function initial_clean() {
			// strip out the crap we don't need
			
			$this->cargo['title'] = trim($this->title);
			
			$this->text = preg_replace("#<!\-\-(.+?)\-\->#si", "", $this->text);   // get rid of unneeded Editor comments
			$this->text = PHP_EOL . PHP_EOL . $this->text . PHP_EOL . PHP_EOL;   // the wrapped PHP_EOL adds for easier regex matches
		}
		
		private function clean_wiki_text($wiki_text) {
			$wiki_text = "\n". $wiki_text ."\n";
			
			//$wiki_text = preg_replace("/\n\[\[(?:[^[\]]|(?R))*?\]\]\n/s", "\n\n", $wiki_text);
			
			$wiki_text_replacements = array(
				 //"/(\r?\n){1,}\{\{(?:[^\}\}]|(?R))*\}\}(\r?\n){1,}/"	=>	"\n\n"
				 "/((\r?\n)+?)(\s*?)\{\{(?:[^\}\}]|(?R))*\}\}(\s*?)((\r?\n)+?)/"	=> "\n\n"
				,"/(\r?\n){1,}\[\[(?:[^\[\]]|(?R))*\]\](\r?\n){1,}/"	=>	"\n\n"
				,"#<(?:\s*?)/?(?:\s*?)ref(?:[^>]*?)>#si"		=>	""
				,"#('{2,5})([^\\1]+?)(?:\\1)#si"				=>	"\\2"
				,"#\{\{(cite|citation)(.+?)\}\}#si"				=>	""
			);
			
			foreach($wiki_text_replacements as $wiki_text_replacement_regex => $wiki_text_replacements_replacer) {
				while(preg_match($wiki_text_replacement_regex, $wiki_text)) {
					$wiki_text = PHP_EOL . preg_replace($wiki_text_replacement_regex, $wiki_text_replacements_replacer, $wiki_text) . PHP_EOL;
				}
			}
			
			return trim($wiki_text);
		}
		
		private function pack_cargo() {
			//$this->cargo['raw_text'] = $this->raw_text;
		}
		
		private function compact_to_slug($string, $delim="_") {
			return trim(preg_replace("#[^A-Za-z0-9]#si", $delim, trim(strtolower(str_replace("'", "", $string)))), $delim);
		}
	}
	
	
	