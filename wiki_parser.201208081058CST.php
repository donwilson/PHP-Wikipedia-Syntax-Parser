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
		private $options = array(
			 'page_attributes' => array()
			,'wikipedia_meta' => array()
		);
		
		// list of subdomains that WikiPedia uses for foreign wikis
		private $foreign_wiki_regex = "(en|de|fr|nl|it|pl|es|ru|ja|pt|zh|sv|vi|uk|ca|no|fi|cs|hu|fa|ko|ro|id|tr|ar|sk|eo|da|sr|lt|kk|ms|he|eu|bg|sl|vo|hr|war|hi|et|az|gl|nn|simple|la|el|th|new|sh|roa\-rup|oc|mk|ka|tl|ht|pms|te|ta|be\-x\-old|be|br|lv|ceb|sq|jv|mg|cy|mr|lb|is|bs|my|uz|yo|an|lmo|hy|ml|fy|bpy|pnb|sw|bn|io|af|gu|zh\-yue|ne|nds|ur|ku|ast|scn|su|qu|diq|ba|tt|ga|cv|ie|nap|bat\-smg|map\-bms|wa|als|am|kn|gd|bug|tg|zh\-min\-nan|sco|mzn|yi|yec|hif|roa\-tara|ky|arz|os|nah|sah|mn|ckb|sa|pam|hsb|li|mi|si|co|gan|glk|bar|bo|fo|bcl|ilo|mrj|se|nds\-nl|fiu\-vro|tk|vls|ps|gv|rue|dv|nrm|pag|pa|koi|xmf|rm|km|kv|csb|udm|zea|mhr|fur|mt|wuu|lad|lij|ug|pi|sc|or|zh\-classical|bh|nov|ksh|frr|ang|so|kw|stq|nv|hak|ay|frp|ext|szl|pcd|gag|ie|ln|haw|xal|vep|rw|pdc|pfl|eml|gn|krc|crh|ace|to|ce|kl|arc|myv|dsb|as|bjn|pap|tpi|lbe|mdf|wo|jbo|sn|kab|av|cbk\-zam|ty|srn|lez|kbd|lo|ab|tet|mwl|ltg|na|ig|kg|za|kaa|nso|zu|rmy|cu|tn|chy|chr|got|sm|bi|mo|iu|bm|ik|pih|ss|sd|pnt|cdo|ee|ha|ti|bxr|ts|om|ks|ki|ve|sg|rn|cr|lg|dz|ak|ff|tum|fj|st|tw|xh|ny|ch|ng|ii|cho|mh|aa|kj|ho|mus|kr|hz)";
		
		/**
		 * @param string $text Raw Wikipedia syntax (found via a Page's Edit textarea or from the Wikiepdia Data Dump page->revision->text)
		 */
		
		public function __construct($text, $title="", $user_options=false) {
			$this->title = $title;
			
			// remove comments
			$text = preg_replace("#". preg_quote("<!--", "#") ."(.*?)". preg_quote("-->", "#") ."#si", "", $text);
			$this->text = $text;
			
			$this->raw_text = $text;
			
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
			//$this->person_data();
			$this->external_links();
			$this->categories();
			$this->meta_boxes();
			$this->foreign_wikis();
			$this->citations();
			//$this->mentions();
			$this->attachments();
			
			$this->make_logical_guesses_on_content();
			
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
				preg_match("#^\{\{([^\|\:]+)(.*?)\}\}$#si", $text_template, $match);
				
				$template_name = trim($match[1]);   // wikisyntax is very case sensitive, so don't use strtolower or similar
				
				if(!empty($this->options['ignore_template_matches'])) {
					foreach($this->options['ignore_template_matches'] as $ignore_template_match_regex) {
						if(preg_match($ignore_template_match_regex, $template_name)) {
							continue 2;
						}
					}
				}
				
				//$template_attributes = trim(ltrim(trim($match[2]), "|"));   // remove trailing spaces and starting pipe
				$template_attributes = trim(preg_replace("#^(?:[\s|\||\:]*?)#si", "", $match[2]));
				
				$template = array(
					 'template_key'	=> $this->compact_to_slug($template_name)
					,'template'		=> $template_name
				);
				
				if(strlen($template_attributes) > 0) {
					$template_attributes = $this->insert_jungledb_helpers($template_attributes);
					
					//$template_attributes = preg_replace("#\[\[(?:\s*?)([^\|]+?)\|(.+?)\]\]#si", "[[\\1###JNGLDBDEL###\\2]]", $template_attributes);
					
					if((strstr($template_attributes, "=") !== false) || (strstr($template_attributes,"|") !== false)) {
						$template_attributes_array = explode("|", $template_attributes);
						$template_attributes = array();
						
						foreach($template_attributes_array as $taa_attr) {
							$taa_attr = trim($taa_attr);
							
							if(preg_match("#^([A-Za-z0-9\_]+?)\=(.+)$#si", $taa_attr, $taa_attr_match)) {
								$template_attributes[ $this->compact_to_slug($taa_attr_match[1]) ] = trim($taa_attr_match[2]);
							} else {
								$template_attributes[] = trim($taa_attr);
							}
						}
					} else {
						$template_attributes = str_ireplace("###JNGLDBDEL###", "|", $template_attributes);
					}
					
					$template_attributes = $this->clean_jungledb_helpers($template_attributes);
					
					$template['attributes']	= $template_attributes;
				}
				
				//$sub_templates = $this->_find_sub_templates($template['original']);   // eventually...
				
				$templates[] = $template;
			}
			
			if(!empty($templates)) {
				$this->cargo['wikipedia_meta']['templates'] = $templates;
			}
		}
		
			private function _find_sub_templates($string) {
				preg_match_all("/\{{2}((?>[^\{\}]+)|(?R))*\}{2}/x", $string, $matches);
				
				return $matches;
			}
		
		
		/**
		 * Determine page attributes [if available]
		 */
		
		private function page_attributes() {
			$page_attributes = array(
				 'type' => false
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
				$page_attributes['disambiguation_key'] = $this->compact_to_slug($match[1]);
				
				if(strlen(trim($match[2])) > 0) {
					$page_attributes['disambiguation_value'] = trim($match[2]);
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
		
		private function meta_boxes() {
			$meta_boxes = array();
			
			//preg_match_all("#\{\{(?:\s*?)(Infobox|Persondata|Taxobox)(?:\s*?)(.+?)". PHP_EOL ."(.+?)". PHP_EOL ."\}\}". PHP_EOL ."#si", $this->text, $matches);
			
			preg_match_all("/\{{2}((?>[^\{\}]+)|(?R))*\}{2}/x", $this->text, $matches);
			
			//$meta_boxes['_debug'] = $matches;
			
			if(!empty($matches[0])) {
				foreach($matches[0] as $key => $raw_template_syntax) {
					if(!preg_match("#\{{2}(?:\s*?)(Infobox|Persondata|Taxobox|tracklist)(?:\s+?)(.+?)\}{2}$#si", $raw_template_syntax, $template_type_match)) {
						continue;
					}
					
					$infobox_values = array();
					//$infobox_tmp = $matches[1][$key];
					$infobox_tmp = $template_type_match[2];
					
					$infobox_tmp = preg_replace(
						 array("#<ref[^>]*>(.+?)</ref>#si", "#<ref[^>]*>#si")
						,array("", "")
						,$infobox_tmp
					);
					
					$meta_boxes['_debug'][] = $infobox_tmp;
					
					$_meta_box_type = $this->compact_to_slug($template_type_match[1]);
					
					$infobox_tmp = $this->insert_jungledb_helpers($infobox_tmp);
					
					$infobox_tmp = preg_split("#\|(?:\s*?)([A-Za-z0-9\_\s]+?)(?:\s*)=#si", $infobox_tmp, -1, PREG_SPLIT_DELIM_CAPTURE);
					$last_line_key = "";
					
					if((count($infobox_tmp) % 2) == 1) {
						$_subtype = $this->compact_to_slug( array_shift($infobox_tmp) );
						
						if(strlen($_subtype) > 0) {
							$infobox_values['_subtype'] = $_subtype;
						}
					}
					
					//$meta_boxes['_debug_split'][] = $infobox_tmp;
					$i = 0;
					
					foreach($infobox_tmp as $it_key => $line) {
						if((++$i % 2) == 1) {
							$last_line_key = $it_key;
							continue;
						}
						
						
						$row_key = $this->compact_to_slug( $infobox_tmp[ $last_line_key ] );
						
						$row_value = trim( $line );
						
						$row_value = preg_replace("#('{2,5})(.+?)\\1#si", "\\2", $row_value);
						
						if($row_value == "?") {
							continue;
						}
						
						if((strlen($row_key) < 1) || (strlen($row_value) < 1)) {
							continue;
						}
						
						
						
						if(in_array($_meta_box_type, array("tracklist"))) {
							if(preg_match("#^([A-Za-z]+?)([0-9]+?)$#si", $row_key, $rkmatch)) {
								$infobox_values['tracks'][ $rkmatch[2] ][ $rkmatch[1] ] = trim($row_value);
								
								continue;
							}
						}
						
						
						// divide lexiconic lists into physical lists
						$row_value_test = $row_value;
						$row_value_test = preg_replace("#(\[|\{){2}(.+?)(\]|\}){2}#si", " ", $row_value_test);
						$row_value_test = preg_replace("#\s{1,}#si", "", $row_value_test);
						$row_value_test = preg_replace("#(\-|\,|or|and)#si", "-", $row_value_test);
						
						if(preg_match("#^[\-]{1,}$#si", $row_value_test)) {
							$row_value = preg_replace("#(\]|\}){2}(?:\s*?)(?:[\-|\,])(?:\s*?)(\[|\{){2}#si", "\\1\\1<br />\\2\\2", $row_value);
						}
						
						if(preg_match("#<(?:\s*?)br(?:\s*?)(/?)(?:\s*?)>#si", $row_value)) {
							$row_value = preg_split("#<(?:\s*?)br(?:\s*?)(/?)(?:\s*?)>#si", $row_value, -1, PREG_SPLIT_NO_EMPTY);
						}
						
						if(!is_array($row_value)) {
							if($row_value == "?") {
								continue;
							}
							
							if(strlen($row_value) < 1) {
								continue;
							}
						}
						
						$infobox_values[ $row_key ] = $row_value;
					}
					
					if(empty($infobox_values) || (isset($infobox_values['_subtype']) && count($infobox_values) == 1)) {
						continue;
					}
					
					$infobox_values = $this->clean_jungledb_helpers($infobox_values);
					
					$meta_boxes[] = array_merge(
						 array('_type' => $_meta_box_type)
						,$infobox_values
					);
				}
			}
			
			if(!empty($meta_boxes)) {
				$this->cargo['meta_boxes'] = $meta_boxes;
			}
		}
		
		private function sections() {
			$section_titles = array();
			$sections = array();
			
			$sections_splits = preg_split("#(?:\s{1,})==(?:\s*?)([^=]+?)(?:\s*?)==#si", $this->text, -1, PREG_SPLIT_DELIM_CAPTURE);
			
			if(empty($sections_splits)) {
				return;
			}
			
			$intro_section = trim(array_shift($sections_splits));
			//$intro_section = $this->clean_wiki_text($_intro_section);
			
			if(!empty($sections_splits)) {
				foreach($sections_splits as $key => $text) {
					if(($key % 2) !== 0) {
						continue;
					}
					
					$sections[] = trim($text);
				}
			}
			
			if(!empty($intro_section)) {
				$this->cargo['intro'] = $this->clean_wiki_text($intro_section, true);
			}
			
			if(!empty($sections)) {
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
		
		private function external_links() {
			$external_links = array();
			
			preg_match("#". PHP_EOL ."==(?:\s*?)External(?:\s{1,}?)links(?:\s*?)==(.+?)". PHP_EOL . PHP_EOL ."#si", $this->text, $matches);
			
			if(!empty($matches[1])) {
				$lines = explode("\n", trim($matches[1]));   // \n is better than PHP_EOL as the line separators in the wiki syntax might not be the same as the OS-specific PHP_EOL [maybe there's a better way of doing this?]
				
				//$this->cargo['debug_el'] = $lines;   // temporary
				
				if(!empty($lines)) {
					foreach($lines as $line) {
						if(preg_match("#^\*(.+?)$#si", trim($line), $match)) {
							$line_value = trim($match[1]);
							
							switch(true) {
								case preg_match("#\[(?:\s*?)http(s?)\:\/\/([^\s]+?)(?:\s+?)([^\]]+?)(?:\s*?)]#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => $this->compact_to_slug("url")
										,'attributes' => array(
											 'url' => "http". $lmatch[1] ."://". $lmatch[2]
											,'text' => trim($lmatch[3])
										)
									);
									break;
								case preg_match("#\[(?:\s*?)([^\s]+?)(\s+?)http(s?)\:\/\/([^\s]+?)(?:\s*?)]#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => $this->compact_to_slug("url")
										,'attributes' => array(
											 'url' => "http". $lmatch[2] ."://". $lmatch[3]
											,'text' => trim($lmatch[1])
										)
									);
									break;
								case preg_match("#\{\{(?:\s*?)official(?:\s+?)([^\|]+?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
									$el_type = $this->compact_to_slug($lmatch[1]);
									
									$external_links[] = array(
										 'type' => $this->compact_to_slug("official". ((strlen($el_type) > 0)?"_". $el_type:""))
										,'attributes' => trim($lmatch[2])
									);
									break;
								case preg_match("#\{\{(?:\s*?)official(?:\s*?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
									$el_type = "raw";
									
									$el_value = trim($lmatch[1]);
									$el_attr_key = "value";
									
									if(preg_match("#https?\:\/\/#si", $el_value)) {
										$el_type = "website";
										$el_attr_key = "url";
									}
									
									$external_links[] = array(
										 'type' => $this->compact_to_slug("official_". $el_type)
										,'attributes' => array(
											 $el_attr_key => $el_value
										)
									);
									break;
								case preg_match("#\{\{(?:\s*)(iobdb)(((?:\s*)\|([^\|]+?)){1,})\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => $this->compact_to_slug($lmatch[1])
										,'attributes' => preg_split("#(\s*?)\|(\s*?)#si", trim($lmatch[2]), -1, PREG_SPLIT_NO_EMPTY)
									);
									break;
								
								// {{WSJtopic|person/L/spike-lee/5873|Spike Lee}}
								case preg_match("#\{\{(?:\s*?)(WSJtopic|NYTtopic|Guardian(?:\s+?)topic)(?:\s*?)\|([^\|]+?)\|([^\|]+?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => $this->compact_to_slug($lmatch[1])
										,'attributes' => array(
											 'path' => trim($lmatch[2])
											,'name' => trim($lmatch[3])
										)
									);
									break;
								
								case preg_match("#\{\{(?:\s*?)(myspace|facebook|twitter)(?:\s*?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => $this->compact_to_slug($lmatch[1])
										,'attributes' => array(
											 'username' => trim($lmatch[2])
										)
									);
									break;
								case preg_match("#\{\{(?:\s*?)reverbnation(?:\s*?)\|([^\|]+?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => $this->compact_to_slug("reverbnation")
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
										 'type' => $this->compact_to_slug("spotify")
										,'attributes' => $spotify_attrs
									);
									
									unset($bits, $spotify_attrs);
									break;
								
								// {dmoz|aaa|bbb|user}
								case preg_match("#\{\{(?:\s*?)dmoz(?:\s*?)\|([^\|]+?)\|([^\|]+?)\|(?:\s*?)user(?:\s*?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => $this->compact_to_slug("dmoz")
										,'attributes' => array(
											 'username' => trim($lmatch[1])
											,'name' => trim($lmatch[2])
										)
									);
									break;
								
								// {dmoz|xxx|yyy}
								case preg_match("#\{\{(?:\s*?)dmoz(?:\s*?)\|([^\|]+?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => $this->compact_to_slug("dmoz")
										,'attributes' => array(
											 'category' => trim($lmatch[1])
											,'name' => trim($lmatch[2])
										)
									);
									break;
								
								// {dmoz|xxx}
								case preg_match("#\{\{(?:\s*?)dmoz(?:\s*?)\|([^\|]+?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => $this->compact_to_slug("dmoz")
										,'attributes' => array(
											 'id' => trim($lmatch[1])
										)
									);
									break;
								
								case preg_match("#\{\{(?:\s*?)imdb(?:\s*?)([^\|]+?)\|(?:\s*?)([0-9]+?)(?:\s*?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => $this->compact_to_slug("imdb")
										,'attributes' => array(
											 'type' => trim($lmatch[1])
											,'id' => ltrim(trim($lmatch[2]), "0")
										)
									);
									break;
								case preg_match("#\{\{(?:\s*?)rotten\-tomatoes([^\|]*)\|(?:\s*?)([A-Za-z0-9\-\_]+?)(?:\s*?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => $this->compact_to_slug("rotten_tomatoes")
										,'attributes' => array(
											 'id' => ltrim(trim($lmatch[2]), "0")
										)
									);
									break;
								case preg_match("#\{\{(?:\s*?)mtv(?:\s*?)([^\|]+?)\|(?:\s*?)([A-Za-z0-9\-\_]+?)(?:\s*?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => $this->compact_to_slug("mtv")
										,'attributes' => array(
											 'type' => trim($lmatch[1])
											,'id' => trim($lmatch[2])
										)
									);
									break;
								case preg_match("#\{\{(?:\s*?)(amg|imcdb)(?:\s*?)([^\|]+?)\|(?:\s*?)([0-9]+?)(?:\s*?)\}\}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => $this->compact_to_slug($lmatch[1])
										,'attributes' => array(
											 'type' => trim($lmatch[2])
											,'id' => trim($lmatch[3])
										)
									);
									break;
								case preg_match("#\{\{(?:\s*?)allmusic(?:\s*?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
									$tmp = array(
										 'type' => $this->compact_to_slug("allmusic")
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
								case preg_match("#\{{2}(?:\s*?)(imdb|rotten\-tomatoes)(?:\s+?)([A-Za-z0-9\-\_]+?)\|([0-9]+?)\|([^\}]+?)\}{2}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => $this->compact_to_slug($lmatch[1])
										,'attributes' => array(
											 'type' => $this->compact_to_slug($lmatch[2])
											,'id' => trim($lmatch[3])
											,'name' => trim($lmatch[4])
										)
									);
									break;
								case preg_match("#\{\{(?:\s*?)(imdb|discogs|musicbrainz|metacritic|mojo|godtube|fuzzymemories|tv\.com|TV Guide|Ustream|Video|YahooTV|YouTube|Screenonline|Citwf|amg|allmovie|allmusic|bbc|allrovi|bcbd|imcdb|iafd|afdb|bgafd|egafd)(?:\s*?)([^\|]+?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
									$tmp = array(
										 'type' => $this->compact_to_slug($lmatch[1])
										,'attributes' => array(
											'type' => $this->compact_to_slug($lmatch[2])
										)
									);
									
									if(strstr($lmatch[3], "=") === false && strstr($lmatch[3], "|") === false) {
										$tmp['attributes']['id'] = trim($lmatch[3]);
									} elseif(strstr($lmatch[3], "=") === false) {
										$tmp_attrs = explode("|", $lmatch[3]);
										
										// trust that the first entity is usually always the external key
										$tmp['attributes']['id'] = trim( array_shift($tmp_attrs) );
										$tmp['attributes']['name'] = trim( array_shift($tmp_attrs) );
									} else {
										$tmp_attrs = explode("|", $lmatch[3]);
										
										if(!empty($tmp_attrs)) {
											foreach($tmp_attrs as $tmp_value_raw) {
												$tmp_value = explode("=", $tmp_value_raw, 2);
												
												$tmp['attributes'][ trim( @$tmp_value[0] ) ] = trim( @$tmp_value[1] );
											}
											
											unset($tmp_value_raw, $tmp_value);
										}
									}
									
									$external_links[] = $tmp;
									
									unset($tmp, $tmp_attrs);
									break;
								
								// [rotten-tomatoes|the-fast-and-the-furious-tokyo-drift|The Fast and the Furious: Tokyo Drift]
								case preg_match("#\{{2}(?:\s*?)(tv\.com|rotten\-tomatoes)(?:\s*?)\|([^\|]+?)\|([^\}]+?)\}{2}#si", $line_value, $lmatch):
									$external_links[] = array(
										 'type' => $this->compact_to_slug($lmatch[1])
										,'attributes' => array(
											 'id' => trim($lmatch[2])
											,'name' => trim($lmatch[3])
										)
									);
									break;
								default:
									$external_links[] = array(
										 'type' => $this->compact_to_slug("raw")
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
			
			preg_match_all("#\[\[(?:\s*?)Category(?:\s*?)\:([^\]\]]+?)\]\]#si", $this->text, $matches);
			
			if(!empty($matches[0])) {
				foreach($matches[1] as $nil => $mvalue) {
					$categories[] = trim(trim(trim($mvalue), "|"));
				}
			}
			
			if(!empty($categories)) {
				$this->cargo['wikipedia_meta']['categories'] = $categories;
			}
		}
		
		private function foreign_wikis() {
			$foreign_wikis = array();
			
			preg_match_all("#\[\[(?:\s*?)". $this->foreign_wiki_regex ."(?:\s*?)\:([^\]]+?)\]\]#si", $this->text, $matches);
			
			if(!empty($matches[0])) {
				foreach($matches[0] as $mkey => $nil) {
					$foreign_wikis[ $matches[1][$mkey] ] = trim($matches[2][$mkey]);
				}
			}
			
			if(!empty($foreign_wikis)) {
				$this->cargo['wikipedia_meta']['foreign_wiki'] = $foreign_wikis;
			}
		}
		
		private function citations() {
			$citations = array();
			
			
			// required keys for each citation type
			$citation_types = array();
			
			$citation_types['web']			= array("title", "url");
			$citation_types['journal']		= array("journal|periodical");
			$citation_types['conference']	= array("title", "booktitle");
			$citation_types['encyclopedia']	= array("encyclopedia|contribution");
			$citation_types['news_article']	= array("newspaper|magazine");
			$citation_types['patent']		= array("inventor-last");
			
			
			preg_match_all("#<ref([^/>]*?)>(.+?)</ref>#si", $this->text, $matches);
			
			if(!empty($matches[0])) {
				foreach($matches[0] as $cite_text) {
					$cite_text = trim(preg_replace("#<(?:\s*?)/?(?:\s*?)ref(?:[^>]*?)>#si", "", $cite_text));
					
					if(empty($cite_text)) {
						continue;
					}
					
					if(preg_match("#\{\{(?:\s*?)dead link#si", $cite_text)) {
						continue;   // no need to record dead links
					}
					
					if(preg_match("#\{\{(?:\s*?)(cite|Citation)([^\}\}]+?)\}\}#si", $cite_text, $cite_match)) {
						// {{cite *}}
						$cite_match_tag_type = $this->compact_to_slug($cite_match[1]);
						$cite_match_bits = explode("|", $cite_match[2]);
						
						if($cite_match_tag_type == "citation") {
							if(!empty($cite_match_tag_type)) {
								continue;
							}
							
							$cite_match_type = false;
						} else {
							$cite_match_type = $this->compact_to_slug(array_shift($cite_match_bits));
						}
						
						$cite_match_attrs = array();
						
						if(!empty($cite_match_bits)) {
							foreach($cite_match_bits as $cmb_string) {
								$cmb_bits = explode("=", $cmb_string, 2);
								
								if(count($cmb_bits) == 0) {
									continue;
								} elseif(count($cmb_bits) == 1) {
									$cmb_bits[0] = trim($cmb_bits[0]);
									
									if(strlen($cmb_bits[0]) > 0) {
										// singular value
										$cite_match_attrs[] = $cmb_bits[0];
									}
								} else {
									$cmb_bits[0] = trim($cmb_bits[0]);
									$cmb_bits[1] = trim($cmb_bits[1]);
									
									if((strlen($cmb_bits[0]) > 0) && (strlen($cmb_bits[1]) > 0)) {
										$cite_match_attrs[ $this->compact_to_slug($cmb_bits[0]) ] = trim( html_entity_decode($cmb_bits[1]) );
									}
								}
							}
							
							ksort($cite_match_attrs);
						}
						
						if($cite_match_type === false) {
							// {{Citation}} used, match against required attributes to determine citation type, re: http://en.wikipedia.org/wiki/Wikipedia:Citation_templates#Examples
							foreach($citation_types as $citation_type => $citation_match_against) {
								$citation_type_match_status = true;
								
								foreach($citation_match_against as $citation_match_reqd_keys) {
									if(strstr($citation_match_reqd_keys, "|") !== false) {
										// split up
										$citation_match_reqd_keys_bits = explode("|", $citation_match_reqd_keys);
										
										$reqd_keys_bits_match = false;
										
										foreach($citation_match_reqd_keys_bits as $reqd_key_bit) {
											if(array_key_exists($reqd_key_bit, $cite_match_attrs)) {
												$reqd_keys_bits_match = true;
											}
										}
										
										if($reqd_keys_bits_match === false) {
											$citation_type_match_status = false;
										}
									} else {
										if(!array_key_exists($citation_match_reqd_keys, $cite_match_attrs)) {
											$citation_type_match_status = false;
										}
									}
									
									if($citation_type_match_status === false) {
										break;
									}
								}
								
								if($citation_type_match_status === true) {
									$cite_match_type = $citation_type;
									
									break;
								}
							}
							
							if($cite_match_type === false) {
								$cite_match_type = "unknown";
							}
						}
						
						$citations[] = array(
							 '_type' => $cite_match_type   // use '_type' instead of 'type' since future templates might allow attribute 'type' : http://en.wikipedia.org/wiki/Wikipedia:Citation_templates#Examples
							//,'_raw'  => $cite_text
						) + $cite_match_attrs;
						
						continue;
					}
					
					// just record raw text
					$citations[] = array(
						 '_type' => "raw"
						,'_raw'  => $cite_text
					);
				}
			}
			
			if(!empty($citations)) {
				$this->cargo['wikipedia_meta']['citations'] = $citations;
			}
		}
		
		private function mentions() {
			$mentions = array();
			
			preg_match_all("#\[\[([^\]\]]+?)\]\]#si", $this->text, $matches);
			
			if(empty($matches[0])) {
				return false;
			}
			
			foreach($matches[0] as $mention) {
				$mention = trim($mention);
				
				if(preg_match("#^". $this->foreign_wiki_regex ."(?:\s*?)\:#si")) {
					// other language, not really needed
					continue;
				}
				
				// split up actual from display text?
				$mention = $this->clean_jungledb_helpers($mention);
				
				$mention = array_shift(explode("|", $mention, 2));
				
				$mentions[] = $mention;
			}
			
			if(!empty($mentions)) {
				$mentions = array_values(array_unique($mentions));
				
				$this->cargo['wikipedia_meta']['mentions'] = $mentions;
			}
		}
		
		private function attachments() {
			$attachments = array();
			
			//preg_match_all("#\[\[(?:\s*?)(Image|File)\:([^\]\]]+?)\]\]#si", $this->text, $matches);
			preg_match_all("/\[{2}((?>[^\[\]]+)|(?R))*\]{2}/x", $this->text, $matches);
			
			if(empty($matches)) {
				return;
			}
			
			foreach($matches[0] as $mkey => $mraw) {
				if(!preg_match("#\[{2}(?:\s*)(File|Image)\:(.+?)\]{2}$#si", $mraw, $attachment_typer)) {
					continue;
				}
				
				$attachment_typer[2] = $this->insert_jungledb_helpers($attachment_typer[2]);
				
				$attachment_details = explode("|", $attachment_typer[2], 2);
				
				$attachment = array(
					 '_raw' => $mraw
					,'type' => $attachment_typer[1]
					,'filename' => $attachment_details[0]
				);
				
				if(!empty($attachment_details[1])) {
					$attr_details_attrs = explode("|", $attachment_details[1]);
					
					$attachment['attributes'] = array();
					
					foreach($attr_details_attrs as $adas_bit) {
						$adas_bit = trim($adas_bit);
						
						if(strlen($adas_bit) < 1) {
							continue;
						}
						
						if(preg_match("#^[0-9]*?x?[0-9]+px$#si", $adas_bit) || preg_match("#^upright(=(.*)?)?$#si", $adas_bit)) {
							$attachment['attributes']['size'] = $adas_bit;
						} elseif(preg_match("#^(left|right|center|none)$#si", $adas_bit)) {
							$attachment['attributes']['location'] = $adas_bit;
						} elseif(preg_match("#^(thumb|thumbnail|frame|framed|frameless)$#si", $adas_bit)) {
							$attachment['attributes']['type'] = $adas_bit;
						} elseif(preg_match("#^(baseline|middle|sub|super|text\-top|text\-bottom|top|bottom)$#si", $adas_bit)) {
							$attachment['attributes']['alignment'] = $adas_bit;
						} elseif(preg_match("#^alt(?:\s*)=(.+?)$#si", $adas_bit, $aba_match)) {
							$attachment['attributes']['alt'] = $aba_match[1];
						} else {
							$attachment['attributes'][] = $adas_bit;
						}
						
						if(isset($attachment['attributes'][0]) && !isset($attachment['attributes'][1])) {
							$attachment['attributes']['caption'] = $attachment['attributes'][0];
							unset($attachment['attributes'][0]);
						}
					}
				}
				
				$attachments[] = $attachment;
				
				unset($attachment);
			}
			
			if(!empty($attachments)) {
				$this->cargo['wikipedia_meta']['attachments'] = $attachments;
			}
		}
		
		
		// Preparation utilities
		
		private function initial_clean() {
			// strip out the crap we don't need
			
			$this->cargo['title'] = trim($this->title);
			
			$this->text = preg_replace("#<!\-\-(.+?)\-\->#si", "", $this->text);   // get rid of unneeded Editor comments
			$this->text = preg_replace("#<(?:\s*?)(/?)(?:\s*?)(small|ref)([^>]*?)>#si", "", $this->text);   // get rid of specific unneeded tags
			$this->text = preg_replace("#". preg_quote("&nbsp;", "#") ."#si", " ", $this->text);   // get rid of ascii spaces
			$this->text = str_replace("–", "-", $this->text);
			$this->text = preg_replace("#<(?:\s*?)noinclude(?:[^>]*?)>(.+?)<(?:\s*?)/(?:\s*?)noinclude(?:[^>]*?)>#si", "\n\n", $this->text);   // get rid of unneeded Editor comments
			
			// remove wiki link title replacers and sub-article anchors from wiki link elements
			$this->text = preg_replace("#(?:\[{2})([^\:\]]+?)(\||\#)(.*?)(?:\]{2})#i", "[[\\1]]", $this->text);
			
			// remove very specific wiki syntax template calls
			
				// {{nowrap|xxx}} -> xxx
				$this->text = preg_replace("/\{\{(?:(birth\-date and age|nowrap|small|big|huge|resize|smaller|larger|large)(?:\s*?)\|)((\{\{.*?\}\}|.)*?)\}\}/s", "\\2", $this->text);
				
				// {{Birth date and age|xxxx|yy|zz}} -> xxxx-yy-zz
				$this->text = preg_replace_callback("#\{\{(?:\s*)(?:(?:Birth date and age)|(?:Bda)|(?:(?:[A-Za-z0-9\-\_\s]+?)date))(?:\s*?)\|(?:\s*?)([0-9]+?)(?:\s*?)\|(?:\s*?)([0-9]+?)(?:\s*?)\|(?:\s*?)([0-9]+?)(?:\s*)\}\}#si", array($this, "_ic_dater"), $this->text);
			
			
			$this->text = PHP_EOL . PHP_EOL . $this->text . PHP_EOL . PHP_EOL;   // the wrapped PHP_EOL adds for easier regex matches
		}
		
			private function _ic_dater($match) {
				return date("Y-m-d", mktime(0, 0, 0, $match[2], $match[3], $match[1]));
			}
		
		private function clean_wiki_text($wiki_text, $tags_too=false) {
			//$wiki_text = "\n". $wiki_text ."\n";
			
			//$wiki_text = preg_replace("/\n\[\[(?:[^[\]]|(?R))*?\]\]\n/s", "\n\n", $wiki_text);
			
			$wiki_text_replacements = array();
			
			if($tags_too) {
				$wiki_text_replacements["/\{{2}((?>[^\{\}]+)|(?R))*\}{2}/x"] = "";
			}
			
			// references/citations
			$wiki_text_replacements["#<ref[^>]*>(.+?)</ref>#si"] = "";
			$wiki_text_replacements["#<ref[^>]*>#si"] = "";
			
			// text styling
			$wiki_text_replacements["#('{2,5})([^\\1]+?)(?:\\1)#si"] = "\\2";
			
			
			
			foreach($wiki_text_replacements as $wiki_text_replacement_regex => $wiki_text_replacements_replacer) {
				while(preg_match($wiki_text_replacement_regex, $wiki_text)) {
					$wiki_text = PHP_EOL . preg_replace($wiki_text_replacement_regex, $wiki_text_replacements_replacer, $wiki_text) . PHP_EOL;
				}
			}
			
			return trim(strip_tags($wiki_text));
		}
		
		private function pack_cargo() {
			//$this->cargo['raw_text'] = $this->raw_text;
			//$this->cargo['text'] = trim( $this->clean_jungledb_helpers($this->text) );
			
			//$this->cargo = $this->clean_jungledb_helpers($this->cargo);
		}
		
		
		private function make_logical_guesses_on_content() {
			if($this->cargo['page_attributes']['type'] !== "main") {
				return;   // no need to waste time
			}
			
			$guesses = array();
			
			$this->logical_guess_at_content_type($guesses);
			
			if(!empty($guesses)) {
				$this->cargo['page_attributes']['guesses'] = $guesses;
			}
		}
		
			function logical_guess_at_content_type(&$guesses) {
				if(!empty($this->cargo['meta_boxes'])) {
					$meta_box_types = array();
					
					foreach($this->cargo['meta_boxes'] as $mb_meta) {
						if(isset($mb_meta['_type']) && isset($mb_meta['_subtype']) && isset($mb_meta['type'])) {
							$meta_box_types[] = $this->compact_to_slug($mb_meta['_type']) ."|". $this->compact_to_slug($mb_meta['_subtype']) ."|". $this->compact_to_slug($mb_meta['type']);
						} elseif(isset($mb_meta['_type']) && isset($mb_meta['_subtype'])) {
							$meta_box_types[] = $this->compact_to_slug($mb_meta['_type']) ."|". $this->compact_to_slug($mb_meta['_subtype']);
						} elseif(isset($mb_meta['_type']) && isset($mb_meta['type'])) {
							$meta_box_types[] = $this->compact_to_slug($mb_meta['_type']) ."|". $this->compact_to_slug($mb_meta['type']);
						} elseif(isset($mb_meta['_type'])) {
							$meta_box_types[] = $this->compact_to_slug($mb_meta['_type']);
						}
					}
					
					$meta_box_types = array_values(array_unique($meta_box_types));
					
					if(in_array("persondata", $meta_box_types)) {
						$guesses['content_type'] = "person";
						
						return;
					}
					
					if(preg_match("#soundtrack\)$#si", $this->cargo['title']) || in_array("infobox|album|soundtrack", $meta_box_types) || in_array("infobox|soundtrack", $meta_box_types)) {
						$guesses['content_type'] = "entertainment_music_album_soundtrack";
						
						return;
					}
					
					if(preg_match("#(video|console|ps3|ps2|playstation|ps1|snes|nes|n64|xbox|xbox360|xbox 360|gameboy|boy|pc)(\s+)game\)$#si", $this->cargo['title']) || in_array("infobox|vg", $meta_box_types)) {
						$guesses['content_type'] = "entertainment_game_video";
						
						return;
					}
					
					if(preg_match("#film\)$#si", $this->cargo['title']) || in_array("infobox|film", $meta_box_types)) {
						$guesses['content_type'] = "entertainment_movie";
						
						return;
					}
					
					if(in_array("infobox|music_genre", $meta_box_types)) {
						$guesses['content_type'] = "entertainment_music_genre";
						
						return;
					}
				}
			}
		
		
		
		private function compact_to_slug($string, $delim="_") {
			return trim(preg_replace("#(". preg_quote($delim) ."){2,}#si", $delim, preg_replace("#[^A-Za-z0-9]#si", $delim, trim(strtolower(str_replace("'", "", $string))))), $delim);
		}
		
		private function insert_jungledb_helpers($text) {
			if(is_array($text)) {
				foreach($text as $tkey => $tval) {
					$text[$tkey] = $this->insert_jungledb_helpers($tval, $include_curlies);
				}
			}
			
			//if($include_curlies) {
			//	$text = preg_replace_callback("#(?:(?:\{){2})(?:\s*?)([^\|]+?)(.+?)(?:(?:\}){2})#si", array($this, "jungledb_helper_replace_callback"), $text);
			//}
			
			//return preg_replace("#((\[|\{}){2})(?:\s*?)(([^\||\}]{2})+?)\|(.+?)(?:(?:\]|\}){2})#si", "[[\\1###JNGLDBDEL###\\2]]", $text);
			
			return preg_replace_callback("#(?:(?:\{){2})(?:\s*?)([^\|]+?)(.+?)(?:(?:\}){2})#si", array($this, "jungledb_helper_replace_callback"), $text);
		}
			private function jungledb_helper_replace_callback($matches) {
				$cleaned = "{{". $matches[1] . str_replace("|", "###JNGLDBDEL###", $matches[2]) ."}}";
				
				return $cleaned;
			}
		
		private function clean_jungledb_helpers($text) {
			if(is_array($text)) {
				foreach($text as $tkey => $tval) {
					$text[$tkey] = $this->clean_jungledb_helpers($tval);
				}
			}
			
			return str_replace("###JNGLDBDEL###", "|", $text);
		}
	}
	
	
	