<?php
	include_once(dirname(__FILE__) . DIRECTORY_SEPARATOR ."utils.php");
	
	
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
	 * @todo cleaning from $this->clean_wiki_text(xxx) isn't cleaning out inline wiki links [see sections->Presidency->Domestic Policy->Economic Policy: http://jungledb.dev/dev/wiki.php?e=3414021]
	 */
	
	class Jungle_WikiSyntax_Parser {
		private static $raw_text;
		private $text = "";
		private $title = "";
		private $cargo = array(
			 'title' => ""
			,'title_md5' => ""
			,'page_attributes' => array()
			,'intro' => ""
			,'sections' => array()
			,'meta_boxes' => array()
			,'tables' => array()
			,'external_links' => array()
			,'categories' => array()
		);
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
		
		public function parse($sections_to_ignore=false) {
			$this->citations();
			
			$this->initial_clean();
			
			// this extracts and removes citations from $this->text
			$this->templates();
			
			// moved to this->sections
			// $this->tables();
			
			$this->page_attributes();
			
			
			$this->date_mentions();
			
			$this->sections();
			//$this->person_data();
			$this->external_links();
			$this->categories();
			$this->meta_boxes();
			$this->foreign_wikis();
			//$this->mentions();
			$this->attachments();
			
			$this->make_logical_guesses_on_content();
			
			$this->pack_cargo();
			
			if(!empty($sections_to_ignore)) {
				foreach($sections_to_ignore as $section_to_ignore) {
					if(isset($this->cargo[ $section_to_ignore ])) {
						unset($this->cargo[ $section_to_ignore ]);
					}
				}
			}
			
			return $this->cargo;
		}
		
		
		// Information extractors
		
		/**
		 * Extract all Wiki templates used
		 */
		
		private function tables($section_header, $section_syntax) {
			
			$section_syntax = PHP_EOL . PHP_EOL . $section_syntax . PHP_EOL . PHP_EOL;
			
			// parse all text at once:
			/*preg_match_all("#(?:(?:=){2,5}\s*([^=]+?)\s*(?:=){2,5})?\s+\{\|(.+?)\|\}\s+#si", $this->text, $matches);*/
			
			// section table extractor
			preg_match_all("#(?:={2,5}\s*([^=]+?)\s*={2,5})?\s+\{\|(.+?)\|\}\s+#si", $section_syntax, $matches);
			
			//$this->cargo['_debug_tables'][] = array(
			//	 'section_header' => $section_header
			////	,'section_syntax' => $section_syntax
			//	,'matches' => $matches
			//);
			
			if(empty($matches[0])) {
				return false;
			}
			
			foreach($matches[0] as $mkey => $raw_table_full_syntax) {
				$table = array();
				
				$raw_table_inner_syntax = $matches[2][$mkey];
				
				$table_lines = explode("\n", $raw_table_inner_syntax);
				
				if(empty($table_lines)) {
					continue;
				}
				
				$c = 0;
				$r = 0;
				$hcl = 0;   // header column line
				$meta = array();
				
				//$row = array();
				$rows = array();
				$headers = array();
				
				foreach($table_lines as $tln => $table_line) {
					$table_line = trim($table_line);
					
					//$meta['_lines'][] = array(
					//	 'c' => $c
					//	,'r' => $r
					//	,'hcl' => $hcl
					//	,'table_line' => $table_line
					//);
					
					if($tln === 0) {
						// table meta
						if(preg_match("#([A-Za-z0-9\-\_]+?)=(?:\"|\')?([A-Za-z0-9\-\_\s]+)(?:\"|\')?#si", $table_line, $match)) {
							// optional header info
							$_meta_key = $this->compact_to_slug($match[1]);
							
							if(!in_array($_meta_key, array("width", "height", "align", "style", "border"))) {
								$meta[ $_meta_key ] = $match[2];
							}
						}
						
						continue;
					}
					
					
					if(preg_match("#^\|\-$#si", $table_line)) {
						if(isset($rows[$r])) {
							ksort($rows[$r]);
							
							$r++;
						}
						
						$c = 0; // reset column iterator
						
						if(isset($headers[$hcl])) {
							ksort($headers[$hcl]);
							
							$hcl++;
						}
						
						continue;
					}
					
					if(preg_match("#^!(.+?)$#si", $table_line, $match)) {
						// table header info
						
						$header_column = $match[1];
						
						$header_column = $this->insert_jungledb_helpers($header_column);
						
						$header_column_bits = explode("|", $header_column);
						
						if(empty($header_column_bits)) {
							continue;
						}
						
						$header_column = array_pop($header_column_bits);
						
						$headers[$hcl][$c] = array(
							 'text' => $this->clean_wiki_text( $this->clean_jungledb_helpers($header_column), false, true)
						);
						
						if(!empty($header_column_bits)) {
							$header_column_meta = trim(array_shift($header_column_bits), " |");
							
							if(strlen($header_column_meta) > 0) {
								preg_match_all("#([A-Za-z0-9\-\_]+?)=(?:\'|\")(.+?)(?:\'|\")#si", $header_column_meta, $hcm_matches);
								
								if(!empty($hcm_matches[0])) {
									$header_column_meta = array();
									//$header_column_meta[':raw'] = $header_column_meta;
									
									foreach($hcm_matches[0] as $hcm_key => $hcm_raw) {
										$_header_meta_key = $this->compact_to_slug($hcm_matches[1][$hcm_key]);
										
										if(!in_array($_header_meta_key, array("width", "height", "align", "style", "border"))) {
											$header_column_meta[ $_header_meta_key ] = $hcm_matches[2][$hcm_key];
										}
									}
									
									if(count($header_column_meta) > 0) {
										$headers[$hcl][$c]['meta'] = $header_column_meta;
									}
								}
							}
						}
						
						$c++;
						
						continue;
					}
					
					
					
					
					$append_last_line = false;
					
					// row info
					if(!preg_match("#^\|(.*?)$#si", $table_line, $match)) {
						$append_last_line = true;
					}
					
					
					$_cols = preg_split("#\|{2}#si", $table_line);
					
					foreach($_cols as $row_column) {
						$row_column = trim($row_column, " |");
						$row_column = $this->clean_wiki_text(trim($row_column, " |"));
						
						// divide column attributes from text
						// -----------------------------------------------------------------------------
						
						
						if(isset($rows[$r][$c])) {
							$c++;
							continue;
						}
						
						if($append_last_line === true) {
							$append_last_line = false;
							
							if(isset($rows[$r][($c - 1)])) {
								$rows[$r][($c-1)] .= PHP_EOL . $row_column;
							}
							
							continue;
						}
						
						
						
						
						
						
						$row_column = $this->insert_jungledb_helpers($row_column);
						
						$row_column_bits = explode("|", $row_column);
						
						if(empty($row_column_bits)) {
							continue;
						}
						
						$row_column = trim(array_pop($row_column_bits), " |");
						
						$row_column = $this->clean_wiki_text($this->clean_jungledb_helpers($row_column), false, true);
						
						if(strlen(trim($row_column, " -|")) > 0) {
							$rows[$r][$c] = $row_column;
							
							if(!empty($row_column_bits)) {
								$row_column_meta = trim(array_shift($row_column_bits), " |");
								
								preg_match_all("#([A-Za-z0-9\-\_]+?)=(?:\'|\")(.+?)(?:\'|\")#si", $row_column_meta, $rcm_matches);
								
								if(!empty($rcm_matches[0])) {
									$row_column_meta = array();
									//$row_column_meta[':raw'] = $row_column_meta;
									
									foreach($rcm_matches[0] as $rcm_key => $rcm_raw) {
										if(!in_array($rcm_key, array("width", "height", "align", "style", "border"))) {
											$row_column_meta[ $this->compact_to_slug($rcm_matches[1][$rcm_key]) ] = $rcm_matches[2][$rcm_key];
										}
									}
									
									//if(count($row_column_meta) > 0) {
									//	$rows[$r][$c]['meta'] = $row_column_meta;
									//}
									
									if(!empty($row_column_meta['rowspan'])) {
										if(is_numeric($row_column_meta['rowspan'])) {
											// fill out spanning rows
											for($i = 1; $i < $row_column_meta['rowspan']; $i++) {
												if(!isset($rows[($r+$i)])) {
													$rows[($r+$i)] = array();
												}
												
												$rows[($r+$i)][$c] = $rows[$r][$c];
											}
										}
									}
									
									
									
									if(!empty($row_column_meta['colspan'])) {
										if(is_numeric($row_column_meta['colspan'])) {
											// fill out spanning columns
											for($i = 1; $i < $row_column_meta['colspan']; $i++) {
												$rows[$r][($c+$i)] = $rows[$r][$c];
											}
										}
									}
									
									
									
								}
							}
						}
						
						
						$c++;
					}
					
				}
				
				
				if(!empty($rows)) {
					//$table[':raw'] = $raw_table_inner_syntax;
					$table['section'] = trim($section_header['title']);
					
					if(!empty($matches[1][$mkey])) {
						$table['subsection'] = trim($matches[1][$mkey]);
					}
					
					if(!empty($meta)) {
						$table['meta'] = $meta;
					}
					
					if(!empty($headers)) {
						$table['headers'] = $headers;
					}
					
					$table['rows'] = $rows;
				}
				
				if(!empty($table)) {
					if(!isset($this->cargo['tables'])) {
						$this->cargo['tables'] = array();
					}
					
					$this->cargo['tables'][] = $table;
				}
				
				
				unset($table, $raw_table_inner_syntax, $table_lines, $rows, $headers, $c, $r, $hcl, $meta, $table_line, $tln, $_meta_key);
			}
			
			//$tables['_debug'] = $matches;
		}
		
		
		
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
					 ':template'	=> $this->compact_to_slug($template_name)
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
					}
					
					$template_attributes = $this->clean_jungledb_helpers($template_attributes);
					
					$template['attributes']	= $this->explodeString_byBreak($template_attributes);
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
			preg_match_all("/\{{2}((?>[^\{\}]+)|(?R))*\}{2}/x", $this->text, $matches);
			
			if(empty($matches[0])) {
				return false;
			}
			
			$meta_boxes = array();
			
			//$meta_boxes['_debug'] = $matches;
			
			$_meta_boxes_multiples = array();
			
			foreach($matches[0] as $key => $raw_template_syntax) {
				if(!preg_match("#\{{2}(?:\s*)(Infobox|Persondata|Taxobox|tracklist)(?:\s+?)(.+?)\}{2}$#si", $raw_template_syntax, $template_type_match)) {
					continue;
				}
				
				$infobox_values = array();
				//$infobox_tmp = $matches[1][$key];
				$infobox_tmp = $template_type_match[2];
				
				//$infobox_tmp = preg_replace(
				//	 array("#<ref[^>]*>(.*?)</ref>#si", "#<ref[^>]*>#si")
				//	,array("", "")
				//	,$infobox_tmp
				//);
				
				
				//$meta_boxes['_debug_before'][] = $infobox_tmp;
				
				// {{url|xxx}} -> [http://[www.]]xxx
				$infobox_tmp = preg_replace_callback("#\{{2}(?:\s*)url(?:\s*)\|(?:\s*)([^\}]+?)(?:\s*)\}{2}#si", function($match) {
					return JungleDB_Utils::prepare_url_string($match[1]);
				}, $infobox_tmp);
				
				// get rid of all template calls
				$infobox_tmp = preg_replace("/\{{2}((?>[^\{\}]+)|(?R))*\}{2}/x", "", $infobox_tmp);
				
				//$meta_boxes['_debug_after'][] = $infobox_tmp;
				
				$_meta_box_type = $this->compact_to_slug($template_type_match[1]);
				
				$infobox_tmp = $this->insert_jungledb_helpers($infobox_tmp);
				
				$infobox_tmp = preg_split("#\|(?:\s*?)([^=]+?)(?:\s*)=#si", $infobox_tmp, -1, PREG_SPLIT_DELIM_CAPTURE);
				$last_line_key = "";
				
				if((count($infobox_tmp) % 2) == 1) {
					$_subtype = $this->compact_to_slug( array_shift($infobox_tmp) );
					
					if(strlen($_subtype) > 0) {
						$infobox_values[':subtype'] = $_subtype;
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
					//if(preg_match("#s$#si", $row_key)) {
						$row_value_test = $row_value;
						$row_value_test = preg_replace("#(\[|\{){2}(.+?)(\]|\}){2}#si", " ", $row_value_test);
						$row_value_test = preg_replace("#\s{1,}#si", "", $row_value_test);
						$row_value_test = preg_replace("#(\-|\,|or|and)#si", "-", $row_value_test);
						
						if(preg_match("#^[\-]{1,}$#si", $row_value_test)) {
							$row_value = preg_replace("#(\]|\}){2}(?:\s*?)(?:[\-|\,])(?:\s*?)(\[|\{){2}#si", "\\1\\1<br />\\2\\2", $row_value);
						}
					//}
					
					// change value based on key
					if(preg_match("#^(accessdate)$#si", $row_key)) {
						$tmp_date = @strtotime($row_value);
						
						if(!empty($tmp_date)) {
							$row_value = date("Y-m-d", $tmp_date);
						}
						
						unset($tmp_date);
					}
					
					
					$row_value = $this->explodeString_byBreak($row_value);
					
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
				
				if(empty($infobox_values) || (isset($infobox_values[':subtype']) && count($infobox_values) == 1)) {
					continue;
				}
				
				$infobox_values = $this->clean_jungledb_helpers($infobox_values);
				
				
				
				
				$_meta_box_key = $_meta_box_type;
				
				if(isset($infobox_values[':subtype'])) {
					$_meta_box_key .= "_". $infobox_values[':subtype'];
					
					unset($infobox_values[':subtype']);
				}
				
				$_meta_box_key = $this->compact_to_slug($_meta_box_key);
				$_meta_box_attributes = $infobox_values;
				
				
				//$meta_boxes[ $_meta_box_key ] = $_meta_box_attributes;
				
				
				if(isset($meta_boxes[ $_meta_box_key ])) {
					if(!in_array($_meta_box_key, $_meta_boxes_multiples)) {
						$_old_me = $meta_boxes[ $_meta_box_key ];
						
						$_meta_boxes_multiples[] = $_meta_box_key;
						
						$meta_boxes[ $_meta_box_key ] = array();
						$meta_boxes[ $_meta_box_key ][] = $_old_me;
					}
					
					$meta_boxes[ $_meta_box_key ][] = $_meta_box_attributes;
				} else {
					$meta_boxes[ $_meta_box_key ] = $_meta_box_attributes;
				}
			}
			
			if(!empty($meta_boxes)) {
				$this->cargo['meta_boxes'] = $meta_boxes;
			}
		}
		
		private function sections($section_text=false, $level=2, $section_title="") {
			if($level === 2) {
				$section_text = $this->text;
			}
			
			if($section_text === false) {
				return array();
			}
			
			$section_text = " ". $section_text ." ";
			
			$s = 0;
			$section = array();
			
			$sections_splits = preg_split("#\s={". $level ."}(?:\s*?)([^=]+)(?:\s*?)={". $level ."}\s#si", $section_text, -1, PREG_SPLIT_DELIM_CAPTURE);
			
			if(empty($sections_splits)) {
				return $section;
			}
			
			//$section['text'] = array_shift($sections_splits);
			$section['text'] = $this->clean_wiki_text(array_shift($sections_splits), true);
			
			if(strlen($section['text']) === 0) {
				unset($section['text']);
			}
			
			if(isset($section['text'])) {
				$this->tables($section_title, $section['text']);
			}
			
			
			if(!empty($sections_splits)) {
				$section['children'] = array();
				
				foreach($sections_splits as $s_key => $s_text) {
					
					if(($s_key % 2) === 0) {
						continue;
					}
					
					// find all child sections
					$section['children'][$s] = array(
						'title' => trim($this->clean_wiki_text($sections_splits[($s_key - 1)]))
					) + $this->sections($s_text, ($level + 1), $sections_splits[($s_key - 1)]);
					
					
					$s++;
				}
			}
			
			
			if($level > 2) {
				return $section;
			}
			
			
			if(strlen($section['text']) > 0) {
				$this->cargo['intro'] = $section['text'];
			}
			
			if(!empty($section['children'])) {
				$this->cargo['sections'] = $section['children'];
			}
			
			
			
			
			
			
			/*
			if(!empty($sections_splits)) {
				foreach($sections_splits as $key => $text) {
					if(($key % 2) === 0) {
						$section['children'][$s++] = array(
							'title' => trim($text)
						) + (array)$this->sections( trim($sections_splits[ ($key + 1) ]), ($level + 1) );
					} else {
						// section-specific extraction!
						
						$this->tables($section[($s - 1)]['text'], $text);
						
						// this area should be used a lot more...
					}
				}
				
				if($level > 2) {
					return $section;
				}
			}
			
			if(!empty($intro_section)) {
				$this->cargo['intro'] = $this->clean_wiki_text($intro_section, true);
			}
			
			if(!empty($sections)) {
				$this->cargo['sections'] = $sections;
			}
			*/
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
			
			preg_match("#". PHP_EOL ."==(?:\s*?)External(?:\s{1,})links(?:\s*?)==(.+?)". PHP_EOL . PHP_EOL ."#si", $this->text, $matches);
			
			if(empty($matches[1])) {
				return false;
			}
			
			
			$_el_multiples = array();
			$lines = explode("\n", trim($matches[1]));   // \n is better than PHP_EOL as the line separators in the wiki syntax might not be the same as the OS-specific PHP_EOL [maybe there's a better way of doing this?]
			
			//$this->cargo['debug_el'] = $lines;   // temporary
			
			if(empty($lines)) {
				return false;
			}
			
			
			foreach($lines as $line) {
				if(!preg_match("#^\*(.+?)$#si", trim($line), $match)) {
					continue;
				}
				
				
				$line_value = trim($match[1]);
				
				switch(true) {
					case preg_match("#\[(?:\s*?)http(s?)\:\/\/([^\s]+?)(?:\s+?)([^\]]+?)(?:\s*?)]#si", $line_value, $lmatch):
						$external_link = array(
							 'type' => $this->compact_to_slug("url")
							,'attributes' => array(
								 'url' => "http". $lmatch[1] ."://". $lmatch[2]
								,'text' => trim($lmatch[3])
							)
						);
						break;
					case preg_match("#\[(?:\s*?)([^\s]+?)(\s+?)http(s?)\:\/\/([^\s]+?)(?:\s*?)]#si", $line_value, $lmatch):
						$external_link = array(
							 'type' => $this->compact_to_slug("url")
							,'attributes' => array(
								 'url' => "http". $lmatch[2] ."://". $lmatch[3]
								,'text' => trim($lmatch[1])
							)
						);
						break;
					case preg_match("#\{\{(?:\s*?)official(?:\s+?)([^\|]+?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
						$el_type = $this->compact_to_slug($lmatch[1]);
						
						$external_link = array(
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
						
						$external_link = array(
							 'type' => $this->compact_to_slug("official_". $el_type)
							,'attributes' => array(
								 $el_attr_key => $el_value
							)
						);
						break;
					case preg_match("#\{\{(?:\s*)(iobdb)(((?:\s*)\|([^\|]+?)){1,})\}\}#si", $line_value, $lmatch):
						$external_link = array(
							 'type' => $this->compact_to_slug($lmatch[1])
							,'attributes' => preg_split("#(\s*?)\|(\s*?)#si", trim($lmatch[2]), -1, PREG_SPLIT_NO_EMPTY)
						);
						break;
					
					// {{WSJtopic|person/L/spike-lee/5873|Spike Lee}}
					case preg_match("#\{\{(?:\s*?)(WSJ|NYT|Guardian(?:\s*))topic(?:\s*?)(\|([^\|]+?)){1,}\}\}#si", $line_value, $lmatch):
						$_attrs = array();
						
						$lmatch2 = preg_split("#(?:\s*)\|(?:\s*)#si", $lmatch[2], -1, PREG_SPLIT_NO_EMPTY);
						
						$_attrs['path'] = trim(ltrim(array_shift($lmatch2), "|"));
						
						if(!empty($lmatch2)) {
							$_attrs['name'] = trim(ltrim(array_shift($lmatch2), "|"));
						}
						
						$external_link = array(
							 'type' => $this->compact_to_slug($lmatch[1])
							,'attributes' => $_attrs
						);
						break;
					
					case preg_match("#\{\{(?:\s*?)(myspace|facebook|twitter)(?:\s*?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
						$external_link = array(
							 'type' => $this->compact_to_slug($lmatch[1])
							,'attributes' => array(
								 'username' => trim($lmatch[2])
							)
						);
						break;
					case preg_match("#\{\{(?:\s*?)reverbnation(?:\s*?)\|([^\|]+?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
						$external_link = array(
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
						
						$external_link = array(
							 'type' => $this->compact_to_slug("spotify")
							,'attributes' => $spotify_attrs
						);
						
						unset($bits, $spotify_attrs);
						break;
					
					// {dmoz|aaa|bbb|user}
					case preg_match("#\{\{(?:\s*?)dmoz(?:\s*?)\|([^\|]+?)\|([^\|]+?)\|(?:\s*?)user(?:\s*?)\}\}#si", $line_value, $lmatch):
						$external_link = array(
							 'type' => $this->compact_to_slug("dmoz")
							,'attributes' => array(
								 'username' => trim($lmatch[1])
								,'name' => trim($lmatch[2])
							)
						);
						break;
					
					// {dmoz|xxx|yyy}
					case preg_match("#\{\{(?:\s*?)dmoz(?:\s*?)\|([^\|]+?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
						$external_link = array(
							 'type' => $this->compact_to_slug("dmoz")
							,'attributes' => array(
								 'category' => trim($lmatch[1])
								,'name' => trim($lmatch[2])
							)
						);
						break;
					
					// {dmoz|xxx}
					case preg_match("#\{\{(?:\s*?)(dmoz|Worldcat id|C\-SPAN)(?:\s*?)\|([^\|]+?)\}\}#si", $line_value, $lmatch):
						$external_link = array(
							 'type' => str_replace("_id", "", $this->compact_to_slug($lmatch[1]))
							,'attributes' => array(
								 'id' => trim($lmatch[2])
							)
						);
						break;
					
					case preg_match("#\{\{(?:\s*?)imdb(?:\s*?)([^\|]+?)\|(?:\s*?)([0-9]+?)(?:\s*?)\}\}#si", $line_value, $lmatch):
						$external_link = array(
							 'type' => $this->compact_to_slug("imdb")
							,'attributes' => array(
								 'type' => trim($lmatch[1])
								,'id' => ltrim(trim($lmatch[2]), "0")
							)
						);
						break;
					case preg_match("#\{\{(?:\s*?)rotten\-tomatoes([^\|]*)\|(?:\s*?)([A-Za-z0-9\-\_]+?)(?:\s*?)\}\}#si", $line_value, $lmatch):
						$external_link = array(
							 'type' => $this->compact_to_slug("rotten_tomatoes")
							,'attributes' => array(
								 'id' => ltrim(trim($lmatch[2]), "0")
							)
						);
						break;
					case preg_match("#\{\{(?:\s*?)mtv(?:\s*?)([^\|]+?)\|(?:\s*?)([A-Za-z0-9\-\_]+?)(?:\s*?)\}\}#si", $line_value, $lmatch):
						$external_link = array(
							 'type' => $this->compact_to_slug("mtv")
							,'attributes' => array(
								 'type' => trim($lmatch[1])
								,'id' => trim($lmatch[2])
							)
						);
						break;
					case preg_match("#\{\{(?:\s*?)(amg|imcdb)(?:\s*?)([^\|]+?)\|(?:\s*?)([0-9]+?)(?:\s*?)\}\}#si", $line_value, $lmatch):
						$external_link = array(
							 'type' => $this->compact_to_slug($lmatch[1])
							,'attributes' => array(
								 'type' => trim($lmatch[2])
								,'id' => trim($lmatch[3])
							)
						);
						break;
					case preg_match("#\{\{(?:\s*?)(allmusic|YouTube)(?:\s*?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
						$tmp = array(
							 'type' => $this->compact_to_slug($lmatch[1])
							,'attributes' => array()
						);
						
						$tmp_attrs = explode("|", $lmatch[2]);
						
						if(!empty($tmp_attrs)) {
							$tmp['attributes'] = array();
							
							foreach($tmp_attrs as $tmp_value_raw) {
								$tmp_value = explode("=", $tmp_value_raw, 2);
								
								$tmp['attributes'][ trim( $tmp_value[0] ) ] = trim( $tmp_value[1] );
							}
							
							unset($tmp_value_raw, $tmp_value);
						}
						
						$external_link = $tmp;
						
						unset($tmp, $tmp_attrs);
						break;
					case preg_match("#\{{2}(?:\s*?)(imdb|rotten\-tomatoes)(?:\s+?)([A-Za-z0-9\-\_]+?)\|([0-9]+?)\|([^\}]+?)\}{2}#si", $line_value, $lmatch):
						$external_link = array(
							 'type' => $this->compact_to_slug($lmatch[1])
							,'attributes' => array(
								 'type' => $this->compact_to_slug($lmatch[2])
								,'id' => trim($lmatch[3])
								,'name' => trim($lmatch[4])
							)
						);
						break;
					case preg_match("#\{\{(?:\s*?)(imdb|ibdb|tcmdb|discogs|musicbrainz|metacritic|mojo|godtube|fuzzymemories|tv\.com|TV Guide|Ustream|Video|YahooTV|YouTube|Screenonline|Citwf|amg|allmovie|allmusic|bbc|allrovi|bcbd|imcdb|iafd|afdb|bgafd|egafd)(?:\s*?)([^\|]+?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
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
						
						$external_link = $tmp;
						
						unset($tmp, $tmp_attrs);
						break;
					
					// [rotten-tomatoes|the-fast-and-the-furious-tokyo-drift|The Fast and the Furious: Tokyo Drift]
					case preg_match("#\{{2}(?:\s*?)(tv\.com|rotten\-tomatoes)(?:\s*?)\|([^\|]+?)\|([^\}]+?)\}{2}#si", $line_value, $lmatch):
						$external_link = array(
							 'type' => $this->compact_to_slug($lmatch[1])
							,'attributes' => array(
								 'id' => trim($lmatch[2])
								,'name' => trim($lmatch[3])
							)
						);
						break;
					default:
						$external_link = array(
							 'type' => $this->compact_to_slug("raw")
							,'attributes' => array(
								 'text' => $line_value
							)
						);
						
						// ............... eventually ignore any malformed text ............... //
				}
				
				
				$_el_type = $external_link['type'];
				$_el_value = $external_link['attributes'];
				
				
				if(isset($external_links[ $_el_type ])) {
					if(!in_array($_el_type, $_el_multiples)) {
						$_old_el = $external_links[ $_el_type ];
						
						$_el_multiples[] = $_el_type;
						
						$external_links[ $_el_type ] = array();
						$external_links[ $_el_type ][] = $_old_el;
					}
					
					$external_links[ $_el_type ][] = $_el_value;
				} else {
					$external_links[ $_el_type ] = $_el_value;
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
				$this->cargo['categories'] = $categories;
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
			//$this->cargo['_debug_citations'] = array();
			
			//$this->text = preg_replace("#<ref(?:[^/>]*?)>(.+?)</ref>#si", "\\1", $this->text);
			$this->text = preg_replace_callback("/\{{2}((?>[^\{\}]+)|(?:(?R)))*\}{2}/x", array($this, "recordAndRemove_Citations"), $this->text);
			
			$this->text = preg_replace(
				 array(
					 "#<\s*?ref[^>]*?>[^<]*?<\s*?/\s*?ref\s*?>#si"
					,"#<\s*?/?\s*?ref[^>]*?\s*?>#si"
				)
				,""
				,$this->text);
		}
		
			private function recordAndRemove_Citations($match) {
				//$this->cargo['_debug_citations'][] = $match;
				
				if(!preg_match("#^\{{2}(?:\s*)(cite|citation)(.+?)\}{2}$#si", $match[0], $cite_match)) {
					//$match[0] = preg_replace("#\{{2}(?:\s*)?(Cite|Citation)(.+?)?\}{2}#si", "", $match[0]);
					
					return $match[0];
				}
				
				if(preg_match("#\{{2}(?:\s*)Citation needed#si", $match[0])) {
					return $match[0];
				}
				
				// required keys for each citation type
				$citation_types = array(
					 'web'			=> array("title", "url")
					,'journal'		=> array("journal|periodical")
					,'conference'	=> array("title", "booktitle")
					,'encyclopedia'	=> array("encyclopedia|contribution")
					,'news_article'	=> array("newspaper|magazine")
					,'patent'		=> array("inventor-last")
				);
				
				
				// {{cite *}}
				$cite_match_tag_type = $this->compact_to_slug($cite_match[1]);
				$cite_match_bits = explode("|", $cite_match[2]);
				
				if($cite_match_tag_type == "citation") {
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
				
				if(!isset($this->cargo['citations'])) {
					$this->cargo['citations'] = array();
				}
				
				$this->cargo['citations'][] = array(
					 ':type' => $cite_match_type   // use ':type' instead of 'type' since future templates might allow attribute 'type' : http://en.wikipedia.org/wiki/Wikipedia:Citation_templates#Examples
					,':raw'  => $match[0]
				) + $cite_match_attrs;
				
				return "";
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
					 ':raw' => $mraw
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
			
			//$this->cargo['title_debug'] = $this->text;
			
			if(preg_match("#\{{2}(?:\s*)?DISPLAYTITLE(?:\s*)?\:(?:\s*)?([^\}]+?)(?:\s*)?\}{2}#si", $this->text, $title_match)) {
				$this->cargo['title'] = trim($title_match[1], "' ");
			}
			
			$this->cargo['title_md5'] = md5($this->cargo['title']);
			
			$this->text = preg_replace("#<!\-\-(.+?)\-\->#si", "", $this->text);   // get rid of unneeded Editor comments
			$this->text = preg_replace("#<(\s*)?(/)?(\s*)?(small|ref)([^>]*)?>#si", "", $this->text);   // get rid of specific unneeded tags
			$this->text = preg_replace("#". preg_quote("&nbsp;", "#") ."#si", " ", $this->text);   // get rid of ascii spaces
			$this->text = str_replace("–", "-", $this->text);
			$this->text = preg_replace("#<(?:\s*?)includeonly(?:[^>]*?)>(.+?)<(?:\s*?)/(?:\s*?)includeonly(?:[^>]*?)>#si", "\\1", $this->text);   // get rid of unneeded Editor comments
			$this->text = preg_replace("#<(?:\s*?)(noinclude|nowiki)(?:[^>]*?)>(.*?)<(?:\s*?)/(?:\s*?)\\1(?:[^>]*?)>#si", "\n\n", $this->text);   // get rid of unneeded Editor comments
			
			// [disabled] remove wiki link title replacers and sub-article anchors from wiki link elements
			//$this->text = preg_replace("#(?:\[{2})([^\:\]]+?)(\||\#)(.*?)(?:\]{2})#i", "[[\\1]]", $this->text);
			
			// remove very specific wiki syntax template calls
			
				// {{nowrap|xxx}} -> xxx
				$this->text = preg_replace("/\{\{(?:(birth\-date and age|nowrap|small|big|huge|resize|smaller|larger|large)(?:\s*?)\|)((\{\{.*?\}\}|.)*?)\}\}/s", "\\2", $this->text);
				
				// {{Birth date|xxxx|yy|zz}} -> xxxx-yy-zz
				$this->text = preg_replace_callback("#\{\{(?:\s*)(?:(?:Birth date)|(?:dob)|(?:(?:[A-Za-z0-9\-\_\s]+?)date))([^\}]+)\}\}#si", array($this, "_ic_dater"), $this->text);
				
				// {{Birth date and age|xxxx|yy|zz}} -> xxxx-yy-zz
				$this->text = preg_replace_callback("#\{\{(?:\s*)(?:(?:Birth date and age)|(?:Bda)|(?:(?:\s*?)\|)?(?:(?:[A-Za-z0-9\-\_\s]+?)date))([^\}]+)\}\}#si", array($this, "_ic_dater"), $this->text);
			
			
			
			//$this->text = PHP_EOL . PHP_EOL . $this->text . PHP_EOL . PHP_EOL;   // the wrapped PHP_EOL adds for easier regex matches
		}
		
			private function _ic_dater($match) {
				if(!isset($match[1])) {
					return $match[0];
				}
				
					if(preg_match("#([0-9]{4})(?:\s*?)\|(?:\s*?)([0-9]{1,2})(?:\s*?)\|(?:\s*?)([0-9]{1,2})#si", $match[1], $icmatch)) {
					return date("Y-m-d", mktime(0, 0, 0, $icmatch[2], $icmatch[3], $icmatch[1]));
				}
				
				if(preg_match("#([0-9]{1,2})(?:\s*?)\|(?:\s*?)([0-9]{1,2})(?:\s*?)\|(?:\s*?)([0-9]{4})#si", $match[1], $icmatch)) {
					return date("Y-m-d", mktime(0, 0, 0, $icmatch[1], $icmatch[2], $icmatch[3]));
				}
				
				// http://regexlib.com/DisplayPatterns.aspx?cattabindex=4&categoryId=5&AspxAutoDetectCookieSupport=1
				if(preg_match("#(0[1-9]|[12][0-9]|3[01])(?:\s*?)(J(anuary|uly)|Ma(rch|y)|August|(Octo|Decem)ber)(?:\s*?)[1-9][0-9]{3}|(0[1-9]|[12][0-9]|30)(?:\s*?)(April|June|(Sept|Nov)ember)(?:\s*?)[1-9][0-9]{3}|(0[1-9]|1[0-9]|2[0-8])(?:\s*?)February(?:\s*?)[1-9][0-9]{3}|29(?:\s*?)February(?:\s*?)((0[48]|[2468][048]|[13579][26])00|[0-9]{2}(0[48]|[2468][048]|[13579][26]))#si", $match[1], $icmatch)) {
					$tmp_icdate = @strtotime($icmatch[0]);
					
					if(!empty($tmp_icdate)) {
						return date("Y-m-d", $tmp_icdate);
					}
				}
				
				return $match[0];
			}
		
		private function clean_wiki_text($wiki_text, $tags_too=false, $br_to_space=false) {
			if(is_array($wiki_text)) {
				foreach($wiki_text as $wtk => $wtv) {
					$wiki_text[$wtk] = $this->clean_wiki_text($wtv, $tags_too);
				}
				
				return $wiki_text;
			}
			
			//$wiki_text = "\n". $wiki_text ."\n";
			
			//$wiki_text = preg_replace("/\n\[\[(?:[^[\]]|(?R))*?\]\]\n/s", "\n\n", $wiki_text);
			
			$wiki_text_replacements = array();
			
			if($tags_too) {
				$wiki_text_replacements["/\{{2}((?>[^\{\}]+)|(?R))*\}{2}/x"] = "";
				$wiki_text_replacements["#\{\|(.*?)\|\}#si"] = "";
			}
			
			// references/citations
			// removed in $this->citations():
			// $wiki_text_replacements["#<ref[^>]*>(.+?)</ref>#si"] = "";
			// $wiki_text_replacements["#<ref[^>]*>#si"] = "";
			
			// text styling
			$wiki_text_replacements["#('{2,5})([^\\1]+?)(?:\\1)#si"] = "\\2";
			
			// links in brackets: [http://xxx( yyy)]
			$wiki_text_replacements["#\[https?([^\]]+?)\]#si"] = "";
			
			$wiki_text_replacements["#\[{2}\s*(". $this->foreign_wiki_regex ."|Template|Wikipedia|Portal|User|File|MediaWiki|Template|Category|Book|Help|Course|Institution)\:\s*(?:([^\]{2}]*?)(?:\[{2}([^\]]+?)\]{2})){1,}(.*?)\s*\]{2}#"] = "[[\\1:\\2\\3]]";
			$wiki_text_replacements["#\[{2}\s*(". $this->foreign_wiki_regex ."|Template|Wikipedia|Portal|User|File|MediaWiki|Template|Category|Book|Help|Course|Institution)\:(.+?)\s*\]{2}#si"] = "";
			
			foreach($wiki_text_replacements as $wiki_text_replacement_regex => $wiki_text_replacements_replacer) {
				while(preg_match($wiki_text_replacement_regex, $wiki_text)) {
					$wiki_text = PHP_EOL . preg_replace($wiki_text_replacement_regex, $wiki_text_replacements_replacer, $wiki_text) . PHP_EOL;
				}
			}
			
			if($br_to_space !== false) {
				$wiki_text = preg_replace("#<\s*?/?\s*?br\s*?/?\s*?>#si", " ", $wiki_text);
			} else {
				$wiki_text = preg_replace("#<\s*?/?\s*?br\s*?/?\s*?>#si", PHP_EOL, $wiki_text);
			}
			
			$wiki_text = explode(PHP_EOL, $wiki_text);
			foreach($wiki_text as $k => $text) { $wiki_text[$k] = trim($text); }
			$wiki_text = implode(PHP_EOL, $wiki_text);
			
			$wiki_text = preg_replace("#(\*|\s)+$#si", "", $wiki_text);
			
			return trim(strip_tags($wiki_text));
		}
		
		private function pack_cargo() {
			//$this->cargo['raw_text'] = $this->raw_text;
			//$this->cargo['text'] = trim( $this->clean_jungledb_helpers($this->text) );
			
			//$this->cargo = $this->clean_jungledb_helpers($this->cargo);
			
			
			//unset($this->cargo['wikipedia_meta']);
			
			
			// wrap up by cleaning
			$this->cargo['intro'] = preg_replace("#\(\s*?;\s+#si", "(", $this->cargo['intro']);
			$this->cargo['intro'] = preg_replace("#\s*?\(\s*?\)\s*?#si", " ", $this->cargo['intro']);
			
			
			JungleDB_Utils::trim_it($this->cargo);
		}
		
		private function make_logical_guesses_on_content() {
			if($this->cargo['page_attributes']['type'] !== "main") {
				return;   // no need to waste time
			}
			
			$this->cargo['page_attributes']['content_type'] = $this->logical_guess_at_content_type();
		}
		
			function logical_guess_at_content_type() {
				if(!empty($this->cargo['meta_boxes'])) {
					$meta_box_types = array();
					
					foreach($this->cargo['meta_boxes'] as $mb_type => $mb_meta) {
						if(isset($mb_meta['type'])) {
							$meta_box_types[] = str_replace("_", "|", $mb_type) ."|". $this->compact_to_slug($mb_meta['type']);
						} else {
							$meta_box_types[] = str_replace("_", "|", $mb_type);
						}
					}
					
					$meta_box_types = array_values(array_unique($meta_box_types));
					
					if(in_array("persondata", $meta_box_types)) {
						return "person";
					}
					
					if(preg_match("#soundtrack\)$#si", $this->cargo['title']) || in_array("infobox|album|soundtrack", $meta_box_types) || in_array("infobox|soundtrack", $meta_box_types)) {
						return "entertainment_music_album_soundtrack";
					}
					
					if(in_array("infobox|vg", $meta_box_types)) {
						return "entertainment_game_video";
					}
					
					if(in_array("infobox|film", $meta_box_types)) {
						return "entertainment_movie";
					}
					
					if(in_array("infobox|music|genre", $meta_box_types)) {
						return "entertainment_music_genre";
					}
					
					if(in_array("infobox|artist|discography", $meta_box_types)) {
						return "entertainment_music_discography";
					}
				}
				
				
				// by header
				
				if(preg_match("#film\)?$#si", $this->cargo['title'])) {
					return "entertainment_movie";
				}
				
				if(preg_match("#(video|console|ps3|ps2|playstation|ps1|snes|nes|n64|xbox|xbox360|xbox 360|gameboy|boy|pc)(\s+)game\)?$#si", $this->cargo['title'])) {
					return "entertainment_game_video";
				}
				
				if(preg_match("#discography\)?$#si", $this->cargo['title'])) {
					return "entertainment_music_discography";
				}
				
				
				
				
				// not sure :(
				return ":unknown";
			}
		
		
		
		private function date_mentions() {
			
			/*
			$months = array(
				"January","February","March","April","May","June","July","August","September","October","November","December",
				"Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Sept","Oct","Nov","Dec",
			);
			
			//preg_match_all("#\s(in|on|after|since) (". implode("|", $months) .")(\-|\s)([0-3]{,1}[0-9]{1})(,? (((\')?([0-2]{1}[0-9]{1})?([0-9]{,2}))|([0-9]{,4}))?), (.+?)\.#si", $this->text, $matches);
			preg_match_all("#\s(?:in|on|after|since|before) (". implode("|", $months) .")([^\.]+)\.#si", $this->text, $matches);
			
			$this->cargo['_debug_datementions'] = $matches;
			*/
			
			
			
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
			
			$text = preg_replace_callback("#((?:\{|\[){2})(?:\s*?)([^\|]+?)(.+?)((?:\]|\}){2})#si", function($matches) {
				return $matches[1] . $matches[2] . str_replace("|", "###JNGLDBDEL###", $matches[3]) . $matches[4];
			}, $text);
			
			return $text;
		}
		
		private function clean_jungledb_helpers($text) {
			if(is_array($text)) {
				foreach($text as $tkey => $tval) {
					$text[$tkey] = $this->clean_jungledb_helpers($tval);
				}
			}
			
			return str_replace("###JNGLDBDEL###", "|", $text);
		}
		
		private function explodeString_byBreak($string) {
			if(is_array($string)) {
				foreach($string as $tkey => $tval) {
					$string[$tkey] = $this->explodeString_byBreak($tval);
				}
			} elseif(preg_match("#<\s*?/?\s*?br\s*?/?\s*?>#si", $string)) {
				$string = preg_split("#<\s*?/?\s*?br\s*?/?\s*?>#si", $string, -1, PREG_SPLIT_NO_EMPTY);
			}
			
			return $string;
		}
	}
	
	
	