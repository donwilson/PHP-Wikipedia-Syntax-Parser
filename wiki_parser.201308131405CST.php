<?php
	require_once(__DIR__ ."/utils.php");
	
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
	 * @todo articles without only 1 section: http://jungledb.dev/dev/wiki.php?e=2307763
	 */
	
	class Jungle_WikiSyntax_Parser {
		private $raw_text;
		private $text = "";
		private $title = "";
		private $cargo = array(
			'title'				=> "",
			'title_hash'		=> "",
			'page_attributes'	=> array(),
			'sections'			=> array(),
			'tables'			=> array(),
			'quotes'			=> array(),
			'meta_boxes'		=> array(),
			'external_links'	=> array(),
			'categories'		=> array(),
			'portals'			=> array(),
		);
		
		private $options = array(
			'page_attributes'			=> array(),
			'wikipedia_meta'			=> array(),
			
			// regex rules to ignore/remove specific wiki templates
			'ignore_template_matches'	=> array(
				"#^(?:\'|\-|Wikiquote|Use dmy dates|Use mdy dates|nowrap|clear)#si",   // functions on words
				"#^(?:Expand section|Sister project links|Wikipedia books link|Wikiatlas|Link (?:FL|GL|FA|GA)|good article|anchor|commons|reflist|refimprove|refbegin|refend|Unreferenced|Citation needed|Primary sources|Citation style|medref|no footnotes|more footnotes|cleanup\-|Sources|Verification|Verify)#si",
				"#^(?:\-|X mark|Check mark|Tick|hmmm|n\.b\.|bang|(N|Y)\&|Ya|Y|aye|Check mark\-n|X mark\-n|X mark big|Cross((?:\s*)\|(?:[0-9]+?)(?:\s*?)))$#si",
				"#^(?:Empty section|Wikinews|link recovered via|expandsect|sortname|see also|sfn|also|Subscription required|Fact|Full|Page needed|Season needed|Volume needed|Clarify|Examples|List fact|Nonspecific)#si",
				"#^(?:Featured article|lead too short|inadequate lead|Lead too long|Lead rewrite|Lead missing|distinguish|redirect|fact|pp\-|permanently protected|temporarily protected)#si",
				"#^(?:infobox|taxobox|navbox|cite|citation|awards|won|nom|end|Persondata)#si",
				"#^(?:Nihongo|Details|Essay|double image|Multiple image|Hatnote|Please check|Inline citations|Indrefs|Citations|No citations|In\-text citations|Nofootnote|Nocitations|Inline refs needed|Inline\-citations|Inline|Nofootnotes|Needs footnotes|Nofn|No inline citations|Noinline|Inlinerefs|Inline\-sources|In line citation|In\-line citations|Inline|Citations|uw\-biog1|uw\-biog2|uw\-biog3|uw\-biog4)#si",
				"#^(?:s\-|Col\-begin|Col\-start|Col\-begin\-small|Col\-break|Col\-2|Col\-1\-of\-2|Col\-2\-of\-2|Col\-3|Col\-1\-of\-3|Col\-2\-of\-3|Col\-3\-of\-3|Col\-4|Col\-1\-of\-4|Col\-2\-of\-4|Col\-3\-of\-4|Col\-4\-of\-4|Col\-5|Col\-1\-of\-5|Col\-2\-of\-5|Col\-3\-of\-5|Col\-4\-of\-5|Col\-5\-of\-5|Col\-end|End|Top|Mid|Bottom|Columns\-start|start box|end box|Column|Columns\-end|Multicol|Multicol\-break|Multicol\-end|Div col|Div col end|col\-float|col\-float\-break|col\-float\-end)#si",
				
				// http://en.wikipedia.org/wiki/Category:Inline_dispute_templates
				"#^(?:Chronology citation needed|Contradict\-inline|Copyvio link|Discuss|Irrelevant citation|Neologism inline|POV\-statement|Slang|Spam link|Speculation\-inline|Talkfact|Tone\-inline|Under discussion\-inline|Undue\-inline)#si",
				
				// specific regex rules
				"#^(?:cn|Oc|Hin|Inc\-video|TOCright|TOCleft|Rp|bgcolor\-(?:[A-Za-z0-9\-\_]+))$#si",
				"#^(?:BLP|Copy to )#si",
				"#^(?:[A-Za-z0-9\-\_\s]+?)(BLP)#si",
				
				// {{xxx|...}} -- cutoff with | for exact template finding
				"#^((?:[A-Za-z0-9\-]+)\-stub|dn|dts|Needdab|hidden begin|hidden end|Italic title|unref|Gallery|confusing|Wiktionary|Wikify|Wikt|Wiktionary pipe|Wiktionary category|Wiktionary\-inline|Wiktionary redirect|Copy to Wiktionary|Italic title prefixed|flag|r|Other uses|Otheruses|Multiple issues|as of|Not a typo|Wikisource|es|es icon|legend|\#tag\:(?:.+?)|fb|Advert|lang\-(?:[A-Za-z]{2,4})|pt icon|explain|Context|Bk|rp|nr|notability|cleanup|Tone|Video game cleanup|Plot|link\-interwiki|Issue|Update|For|CI|Cat improve|Improve categories|Catimprove|Cleanup\-cat|Cleanup cat|Few categories|Few cats|Fewcategories|Fewcats|Improve\-categories|Improve\-cats|Improve categories|Improve cats|Improvecategories|Improvecats|More categories|More category|Morecat|Morecategories|Morecats|Cat\-improve|Category\-improve|Categories\-improve|Category improve|Categories improve)\s*\|?#si",
				
				// article messages/wiki cleanup
				"#^(?:\.\.\.|Abbreviations|Advert|All plot|Almanac|Alternative text missing|Anachronism|Autobiography|Bad summary|Biblio|Booster|Broken|Buzzword|Category unsourced|Check category|CIA|Clarify timeframe|Clarify\-section|Cleanup|Cleanup AfD|Cleanup Congress Bio|Cleanup red links|Cleanup\-articletitle|Cleanup\-astrology|Cleanup\-biography|Cleanup\-book|Cleanup\-colors|Cleanup\-combine|Cleanup\-comics|Cleanup\-gallery|Cleanup\-GM|Cleanup\-HTML|Cleanup\-ICHD|Cleanup\-images|Cleanup\-infobox|Cleanup\-IPA|Cleanup\-lang|Cleanup\-link rot|Cleanup\-remainder|Cleanup\-reorganize|Cleanup\-rewrite|Cleanup\-school|Cleanup\-spam|Cleanup\-statistics|Cleanup\-tense|Cleanup\-translation|Cleanup\-university|Cleanup\-weighted|Close paraphrasing|Colloquial|Condense|Confusing|Confusing section|Context needed|Contradict|Contradict section|Contradict\-other|Contradict\-other\-multiple|Convert template|Coord missing|Copied howto|Copied section to Wikisource|Copied to Wikibooks|Copied to Wikibooks Cookbook|Copy edit|Copy edit\-section|Criticism section|Criticism title|Dablinks|Dead end|Dead link header|Debate|Dicdef|Directory|Duplication|Editorial|Empty section|Essay\-like|Example farm|Expand article|Expand section|Expert\-maths|Expert\-subject|Expert\-subject\-multiple|External links|Famous|Fanpov|Fiction|Fiction trivia|Format footnotes|Further reading cleanup|Game guide|Generalize|Howto|Ibid|Icon\-issues|Improve categories|In popular culture|In\-universe|Inappropriate person|Incomplete)\s*\|?#si",
				"#^(?:Incomplete table|ISBN|Jagged 85 shortened|Like resume|Local|Manual|Misleading|Missing fields|Missing information|More plot|More\-specific\-links|MOS|MOSLOW|NCBI taxonomy|New infobox|News release|News release section|NFimageoveruse|No plot|Nonfiction|NPOV language|Obituary|Off\-topic|ORList|Out of date|Outdated as of|Over\-quotation|Overcolored|Overcoloured|Overlinked|Overly detailed|Peacock|Plot|Prune|Puffery|Recategorize|Refactor|Religion primary|Repair coord|Repetition|Repetition section|Repetition\-inline|Review|Rewrite section|RJL|Schedule|Section\-diffuse|Sections|Too many see alsos|Specific|Story|Strawman|Sub\-sections|Summarize|Summarize section|Summary style|Sync|Tagged|Technical|Term paper|Textbook|ToLCleanup|Tone|Too abstract|Too many photos|Too\-many\-boxes|Travel guide|Trivia|Uncategorized|Unclear date|Underlinked|Undue|Undue precision|Update|Update after|Update section|Very long|Very long section|Video game cleanup|Wikify|Wikify section|Ambox|1911 POV|Abbreviations|Advert|Aero\-table|Afd\-merge required|Afd\-merge to|Afdnotice2|All plot|Almanac|Alphabetize|Alumni|Anachronism|Animals cleanup|Arabic script needed|Armenian script needed|Article for deletion|Autobiography|Bad summary|Being translated)\s*\|?#si",
				"#^(?:Video game cleanup|Gamecleanup|Bengali script needed|Berber script needed|Biblio|BLP IMDb refimprove|BLP IMDb\-only refimprove|BLP primary sources|BLP selfpublished|BLP sources|BLP sources section|BLP unsourced|BLP unsourced section|Booster|Broken|Burmese script needed|Buzzword|Category unsourced|Catholic\-cleanup|Caution|Check category|Chemical\-importance|Cherokee script needed|Cherry picked|Chinese script needed|CIA|CIA\-Sect|Citation style|Citations broken|Cite check|Cite plot points|Clarify\-section|Cleanup AfD|Cleanup Congress Bio|Cleanup FJ biography|Cleanup red links|Cleanup split|Cleanup\-articletitle|Cleanup\-astrology|Cleanup\-biography|Cleanup\-book|Cleanup\-colors|Cleanup\-combine|Cleanup\-comics|Cleanup\-gallery|Cleanup\-GM|Cleanup\-HTML|Cleanup\-ICHD|Cleanup\-images|Cleanup\-IPA|Cleanup\-lang|Cleanup\-link rot|Cleanup\-list|Cleanup\-list\-sort|Cleanup\-reorganize|Cleanup\-rewrite|Cleanup\-school|Cleanup\-spam|Cleanup\-statistics|Cleanup\-tense|Cleanup\-translation|Cleanup\-university|Cleanup\-weighted|Close paraphrasing|Coat rack|COI|Colloquial|Comparison|Condense|Confusing|Content|Context|Contradict|Contradict\-other|Contradict\-other\-multiple|Converted|Copied section to Wikisource|Copied to Wikibooks|Copied to Wikibooks Cookbook|Copy edit\-section|Copy section to Wikisource|Copy to gaming wiki|Copy to Wikibooks|Copy to Wikibooks Cookbook|Copy to Wikimedia Commons|Copy to Wikiquote|Copy to Wikisource|Copy to Wikiversity|Copy to Wikivoyage|Copy to Wiktionary|Copypaste|Copyvio\-revdel|Copyvioel|Corrupt (organization)|Coverage|Create\-list|Criticism section|Criticism title|Crystal|Csb\-pageincluded|Csb\-pageincludes|Csb\-wikipage|Current)\s*\|?#si",
				"#^(?:Current Australian COTF|Current disaster|Current person|Current related|Current severe outbreak|Current spaceflight|Current sport|Current sport\-related|Current sports transaction|Current tornado outbreak|Current\-anytext|Dabconcept|Dablinks|Db\-revise|Dead end|Dead link header|Debate|Devanagari script needed|Dicdef|Directory|Dispute about|Disputed|Disputed title|Disputed\-category|Disputed\-list|Duplication|DYKissues|Editorial|Egyptian hieroglyphic script needed|Election results missing|Empty section|End of season|Essay\-like|Example farm|Exit list|Expand article|Expand section|Expand Spanish|Expert\-maths|Expert\-subject|Expert\-subject\-multiple|External links|Famous|Famous players|Famous players generic|Fanpov|Few references exist|Fiction|Fiction trivia|Film IMDb refimprove|Footballer\-unknown\-status|Format footnotes|Formula missing descriptions|Fringe theories|Further reading cleanup|Game guide|Gameplay|Generalize|Geographic refimprove|Geographical imbalance|Georgian script needed|Globalize|GOCEinuse|GOCEuc|Greek script needed|Hasty|Hebrew script needed|Historical congressional article|Historical information needed|Historicize|Hoax|Howto|Hypothesis|Ibid|Icon\-issues|Importance\-section|Improve categories|In popular culture|In translation|In\-universe|Inadequate lead|Inappropriate person|Inappropriate title|Inclusion|Incoherent|Incoming links|Incomplete|Incomplete disambiguation|Indian cinema under construction|Indian pop cat|IndicL|Infobox problem|Integrate\-section|InterTransWiki|Invalid references|ISBN|ISSN\-disclaimer|Jagged 85 cleanup|Japanese script needed|Khmer script needed|Kmposts)\s*\|?#si",
				"#^(?:Korean script needed|Lacking overview|Lao script needed|Launching|Lead missing|Lead rewrite|Lead too long|Lead too short|Like resume|Link up|Linkage|List dispute|List missing criteria|List to table|List years|Listcopy|Listmaybe|Local|Manual|ManualTranswiki|Math2english|ME\-importance|Mediated|Medref|Memorial|Merge FJC|Merge school|Merging from|Metricate|Meyers|Mileposts|Wikicite|Misleading|Missing|Missing information|Missing information non\-contentious|Missing\-taxobox|Mission|More footnotes|More plot|More\-specific\-links|MOS|MOSLOW|MRfD|Multiple issues|Music\-examples|Nationalhistory|NCBI taxonomy|Need consensus|Needhanja|Needhiragana|Needkanji|Needs table|Neologism|New unreviewed article|New user article|New user article LSU|News release|News release section|NFaudiooveruse|NFimageoveruse|No Copy to Wikibooks|No footnotes|No plot|No prose|Noleak|Noleander shortened|Non\-free|Non\-free\-lists|Non\-free\-overuse|Non\-free\-vio|Nonfiction|Nonnotable content|Not English|Notability|Nothaweng|Notice|Notvio|NovelsWikiProject Collaboration|Now project|NPOV language|Obituary|ODM\-List\-Country|Off\-topic|One source|Only\-two\-dabs|Original research|ORList|Orphan|Out of date|Over coverage|Over\-quotation|Overcoloured|Overlinked|Overly detailed|Page numbers improve|Page numbers needed|Pageinprogress|More information|Partisan sources|Patronymic|Peacock|Persian script needed|Please check ISSN)\s*\|?#si",
				"#^(?:Plot hook|Plot notice|POV|POV\-check|POV\-title|POV\-title\-section|POV\:KS|Pp\-create|Pp\-dispute|Pp\-meta|Primary sources|Pro and con list|Proofreader needed|Proposed deletion endorsed|Prose|Prune|Pruned|Pseudo|Puffery|R mentioned in hatnote|Rank order|Recategorize|Recent death|Recent death presumed|Recent unreviewed edits|Recentism|Recently revised|Ref expand|Refimprove|Refimprove section|Regional units|Religion primary|Religious text primary|Repair coord|Repetition|Repetition section|Review|Review wikification|Rewrite section|Rewriting|RJL|Rough translation|Samoan script needed|Schedule|Science review|Section images needed|Section\-diffuse|Section\-sort|Sections|Self\-published|Self\-reference|Spacing|Spam\-request|Specific|Speculation|Split|Split dab|Split2|Splitsectionto|Sports venue to be demolished|Spotlight working|Story|Strawman|Substituted template|Summarize|Summarize section|Summary style|Sync|Synthesis|Syriac script needed|Systemic bias|Tamil script needed|Tech Issue|Technical|Term paper|Textbook|Thai script needed|Third\-party|Tibetan script needed|Time references needed|Time\-context|Tok Pisin script needed|ToLCleanup|Tone|Too abstract|Too few opinions|Too many photos|Too Many Revisions|Too many see alsos|Too\-many\-boxes|TrainingPage|TranslatePassage|Translation WIP|Travel guide|Trivia|TWCleanup|TWCleanup2|Unbalanced|Unbalanced section|Uncategorized stub|Unchronological|Unclear date)\s*\|?#si",
				"#^(?:Underlinked|Undue|Undue\-section|Units attention|Unlinked references|Unreferenced|Unreferenced Kent|Unreferenced section|Unreferenced small|Unreferenced top icon|Unreferenced\-Fs|Unreferenced\-law|UnreferencedMED|Unreliable sources|Unsorted|Upcoming contests|Update|Update\-EB|USgovtPOV|USRD\-wrongdir|Verifiability|Verify sources|Very long|Very long section|Video game cleanup|Vietnamese script needed|Vital construction|WA botmark|Weasel|WH\-transwiki\-from|WH\-transwiki\-to|Wikify|Wikify section|WPJournalsCotW\-Article|WWFinuse|Yiddish script needed)\s*\|?#si",
				"#^(?:BLP IMDb refimprove|BLP IMDb\-only refimprove|BLP primary sources|BLP selfpublished|BLP sources|BLP sources section|BLP unsourced|BLP unsourced section|Broken ref|Chronology citation needed|Circular|Citation style|Citations broken|Cite check|Cite plot points|Cleanup\-link rot|Disputed|Disputed\-section|Expert\-talk|Geodata\-check|Hoax|Ibid|Include\-eb|Irrelevant citation|ISSN\-needed|Issue|Medref|More footnotes|Neologism|Neologism inline|No footnotes|Nonspecific|One source|Original research|Page numbers improve|Page numbers needed|Peacock term|Primary sources|Refimprove|Refimprove section|Reflist\-talk|Religious text primary|Science review|Section OR|Self\-published|Self\-reference|Specific time|Specify|Speculation|Speculation\-inline|Synthesis|Synthesis\-inline|Tertiary|Time references needed|Tone\-inline|Unlinked references|Unreferenced|Unreferenced section|Unreferenced\-law|Unreferenced\-law section|Unreferenced2|Unreliable sources|Verify sources|Volume needed|Weasel\-inline)\s*\|?#si",
				"#^(?:Language|Aa|Ab|Ace|Ae|Af|Ak|Am|An|Ar|Arn|Arz|As|Ast|Av|Ay|Az|Ba|Bi|Be|Ber|Bg|Bla|Bm|Bn|Bo|Br|Bs|Bxr|Byq|Ca|Cbv|Cdo|Ce|Ceb|Ch|Chm|Ckb|Ckv|Ssf|Co|Cr|Crh|Cz|Cu|Cv|Cy|Da|De|Dsb|Dv|Dz|Ee|El|En|Eo|Es|Et|Eu|Ext|Fa|Ff|Fi|Fj|Fo|Fr|Frp|Fur|Fy|Ga|Gd|Gl|Gn|Grc|Gsw|Gu|Gv|Ha|He|Hi|Hif|Ho|Hr|Hsb|Ht|Hu|Hy|Hz|Ia|Id|Ie|Ig|Ii|Ik|Ilo|Io|Is|It|Iu|Ja|Jac|Jv|Ka|Kab|Kar|Kg|Ki|Kj|Kk|Kl|Km|Kn|Knn|Ko|Kr|Ks|Ksh|Ku|Kv|Kw|Ky|Kz|La|Lb|Lg|Li|Lij|Liv|Ln|Lo|Lou|Lt|Lu|Lv|Mam|Me|Mh|Mi|Mk|Ml|Mn|Mo|Mr|Ms|Mt|Mwl|My|Myn|Na|Nah|Nan|Nap|Nd|Nds|Ne|New|Ng|Nl|Nn|No|Non|Nr|Nso|Nv|Ny|Oc|Oe|Oj|Om|Or|Os|Oto|Pa|Ph|Pi|Pl|Pms|Ps|Pt|Qu|Quc|Rm|Rmy|Rn|Ro|Roa\-rup|Ru|Rw|Sa|Sah|Sc|Scn|Sco|Sd|Sdc|Sdn) icon\s*$#si",
				"#^(?:DABcheck|DGA\-icon|DYK user topicon|FA number|FA pass|FA user topicon|FAC|FAC welcome|FAC withdrawn|FAC2|FACClosed|FACfailed|FAClink|FAR|FAR\-icon|FARClosed|FARMessage|FARpass|FARpassed|FARpasswith|FC withdrawn|Featured animation|Featured article|Featured article candidates|Featured article review|Featured article tools|Featured list|Featured list candidates|Featured list removal candidates|Featured picture|Featured picture set|Featured portal|Featured sound|FFA number|FFL number|FL number|FL pass|FL user topicon|FLC|FLC withdrawn|FLCClosed|FLCfailed|FLRC|Former featured picture|Former featured sound|FormerFA2|FP number|FP user topicon|FPcandidates|FPCold|FPCresult|FPlowres|FPO number|FPO user topicon|FPOC|FPOCClosed|FPR|FPRMessage|FS number|FSC|FT number|FT pass|ITN user topicon|Link FA|Link FL|OFAN|PI featured article|QOTD|QuoteText|TFA|Today's featured article request|WP FAC|WP FC|WP FL|WP FLC)\s*\|?#si",
				"#^(?:Alphabetize|Complete|Dynamic list|Expand list|Expand list section|Inc\-film|Inc\-lit|Inc\-musong|Inc\-personnel|Inc\-results|Inc\-sport|Inc\-transport|Inc\-tv|Inc\-up|Inc\-vg|Inc\-video|List dispute|List missing criteria|Main list|Dyoh|LastMonth|Lmonth|NextMonth|Nmonth|RD Archive header|RD Archive header monthly|RD medadvice|RD medremoval|RD removal|RD\-deleted|RefDesk|Refdesk|Refdeskchatty|Searchanswer|TodayRD|Tomor|Tomorr|WPRDAC attention|Yest|Yester|AFCHD|Help desk templates|Help me IRC|Help me\-na|Help me\-nq|Help me\-ns|Helpme\-unblock|Helpme\-unblock2|Sofixit|Solookitup|Dyoh|Cleanup\-spam|No more links|NoMoreCruft|NoMoreLinksTrackback|Prone to spam|Spam link|Spam note|Welcomespam|Dispute templates|3O|3Odec|3OR|3ORshort|Accessibility dispute|BLPVio|Cleanup\-articletitle|Content|Controversial|Dispute about|Dispute progress|Dispute\-resolution|Disputed|Disputed chem|Disputed tag|Disputed title|Disputed\-category|Disputed\-list|Disputed\-section|Gdansk\-Vote\-Notice|Hidden content dispute|Jew\-MJ dispute|List dispute|List missing criteria|Out of date|Pbneutral|Rfc|RFCheader|Speculation|Third opinion|Under discussion|DR case status|DRN|DRN archive bottom|DRN archive top|DRN case status|Drn filing editor|DRN status|DRN\-notice)\s*\|?#si",
				"#^(?:User DRN|Chronology citation needed|Contradict\-inline|Copyvio link|Discuss|Irrelevant citation|Neologism inline|POV\-statement|Slang|Spam link|Speculation\-inline|Talkfact|Tone\-inline|Under discussion\-inline|Undue\-inline|Dispute templates|1911 POV|According to whom|Advert|Autobiography|By whom|Catholic\-cleanup|Cherry picked|Circular\-ref|Coat rack|COI|Compared to?|Fringe theories|Like resume|Lopsided|Manual|Memorial|NPOV disputes progress|NPOV language|Obituary|Partisan sources|Pbneutral|Peacock|POV|POV\-check|POV\-lead|POV\-map|POV\-section|POV\-statement|POV\-title|Pp\-reset|Pseudo|Puffery|Recentism|Self\-published|Self\-published inline|Self\-published source|Story|Systemic bias|Tone|Too few opinions|Unbalanced|Unbalanced section|Undue|Undue\-inline|USgovtPOV|Weasel|Which|Who|Refdeskchatty|R template index|Redirect template|This is a redirect|R from code|Redirect for convenience|Deprecated stub|Deprecated template|R from title without diacritics|R from incomplete disambiguation|R from incorrect disambiguation|R from incorrect name|R from misspelling|Redirect from modification|R from name and country|No redirect|R from other disambiguation|Redirect from plural|R from postal abbreviation|R from alternative punctuation|R from ambiguous page|R from merge|R from other capitalisation|R from printworthy plural|R from tzid|R restricted|R to disambiguation page|R from railroad name with ampersand|Redirect from case citation|Redirect to circumvent Special\:RELC|Redirect to related topic|R from related word|R from shortcut|Redirect to plural|R from alternative spacing|R from UK postcode|R from unnecessary disambiguation|R from US postal abbreviation|R from year)\s*\|?#si",
				
				// http://en.wikipedia.org/wiki/Template:Citation_needed/doc#Inline_templates
				"#^(?:Attribution needed|Which|Citation needed|Primary source\-inline|Retracted|Third\-party\-inline|Author missing|Author incomplete|Date missing|ISBN missing|Publisher missing|Title incomplete|Year missing|Contradict\-inline|Contradiction\-inline|Examples|Inconsistent|List fact|Lopsided|Clarify timeframe|Update\-small|Where|Year needed|Disambiguation needed|Pronunciation needed|Ambiguous|Awkward|Buzz|Elucidate|Expand acronym|Why|Cite quote|Clarify|Examples|List fact|Nonspecific|Page needed|Citation needed span|Cn\-span|Fact span|Reference necessary|Full|Season needed|Volume needed|Better source|Dead link|Failed verification|Request quotation|Self\-published inline|Source need translation|Verify credibility|Verify source|Definition|Dubious|Technical\-statement|Or|Peacock term|POV\-statement|Quantify|Time fact|Chronology citation needed|Undue\-inline|Vague|Weasel\-inline|When|Who|Whom|By whom|Update after|Cite check|Refimprove|Unreferenced|Citation style|No footnotes)#si",
				
				// http://en.wikipedia.org/wiki/Template:Use_Australian_English
				"#^(?:Use Australian English|Use American English|Use British English|Use British \(Oxford\) English|Use Canadian English|Use Indian English|Use New Zealand English|Use Pakistani English|Use South African English)#si",
				
				// other wikis
				"#^(?:en|de|fr|nl|it|pl|es|ru|ja|pt|zh|sv|vi|uk|ca|no|fi|cs|hu|fa|ko|ro|id|tr|ar|sk|eo|da|sr|lt|kk|ms|he|eu|bg|sl|vo|hr|war|hi|et|az|gl|nn|simple|la|el|th|new|sh|roa\-rup|oc|mk|ka|tl|ht|pms|te|ta|be\-x\-old|be|br|lv|ceb|sq|jv|mg|cy|mr|lb|is|bs|my|uz|yo|an|lmo|hy|ml|fy|bpy|pnb|sw|bn|io|af|gu|zh\-yue|ne|nds|ur|ku|ast|scn|su|qu|diq|ba|tt|ga|cv|ie|nap|bat\-smg|map\-bms|wa|als|am|kn|gd|bug|tg|zh\-min\-nan|sco|mzn|yi|yec|hif|roa\-tara|ky|arz|os|nah|sah|mn|ckb|sa|pam|hsb|li|mi|si|co|gan|glk|bar|bo|fo|bcl|ilo|mrj|se|nds\-nl|fiu\-vro|tk|vls|ps|gv|rue|dv|nrm|pag|pa|koi|xmf|rm|km|kv|csb|udm|zea|mhr|fur|mt|wuu|lad|lij|ug|pi|sc|or|zh\-classical|bh|nov|ksh|frr|ang|so|kw|stq|nv|hak|ay|frp|ext|szl|pcd|gag|ie|ln|haw|xal|vep|rw|pdc|pfl|eml|gn|krc|crh|ace|to|ce|kl|arc|myv|dsb|as|bjn|pap|tpi|lbe|mdf|wo|jbo|sn|kab|av|cbk\-zam|ty|srn|lez|kbd|lo|ab|tet|mwl|ltg|na|ig|kg|za|kaa|nso|zu|rmy|cu|tn|chy|chr|got|sm|bi|mo|iu|bm|ik|pih|ss|sd|pnt|cdo|ee|ha|ti|bxr|ts|om|ks|ki|ve|sg|rn|cr|lg|dz|ak|ff|tum|fj|st|tw|xh|ny|ch|ng|ii|cho|mh|aa|kj|ho|mus|kr|hz)(?:\s*?)\:#si",
			),
		);
		
		// list of subdomains that WikiPedia uses for foreign wikis
		private $foreign_wiki_regex = "(en|de|fr|nl|it|pl|es|ru|ja|pt|zh|sv|vi|uk|ca|no|fi|cs|hu|fa|ko|ro|id|tr|ar|sk|eo|da|sr|lt|kk|ms|he|eu|bg|sl|vo|hr|war|hi|et|az|gl|nn|simple|la|el|th|new|sh|roa\-rup|oc|mk|ka|tl|ht|pms|te|ta|be\-x\-old|be|br|lv|ceb|sq|jv|mg|cy|mr|lb|is|bs|my|uz|yo|an|lmo|hy|ml|fy|bpy|pnb|sw|bn|io|af|gu|zh\-yue|ne|nds|ur|ku|ast|scn|su|qu|diq|ba|tt|ga|cv|ie|nap|bat\-smg|map\-bms|wa|als|am|kn|gd|bug|tg|zh\-min\-nan|sco|mzn|yi|yec|hif|roa\-tara|ky|arz|os|nah|sah|mn|ckb|sa|pam|hsb|li|mi|si|co|gan|glk|bar|bo|fo|bcl|ilo|mrj|se|nds\-nl|fiu\-vro|tk|vls|ps|gv|rue|dv|nrm|pag|pa|koi|xmf|rm|km|kv|csb|udm|zea|mhr|fur|mt|wuu|lad|lij|ug|pi|sc|or|zh\-classical|bh|nov|ksh|frr|ang|so|kw|stq|nv|hak|ay|frp|ext|szl|pcd|gag|ie|ln|haw|xal|vep|rw|pdc|pfl|eml|gn|krc|crh|ace|to|ce|kl|arc|myv|dsb|as|bjn|pap|tpi|lbe|mdf|wo|jbo|sn|kab|av|cbk\-zam|ty|srn|lez|kbd|lo|ab|tet|mwl|ltg|na|ig|kg|za|kaa|nso|zu|rmy|cu|tn|chy|chr|got|sm|bi|mo|iu|bm|ik|pih|ss|sd|pnt|cdo|ee|ha|ti|bxr|ts|om|ks|ki|ve|sg|rn|cr|lg|dz|ak|ff|tum|fj|st|tw|xh|ny|ch|ng|ii|cho|mh|aa|kj|ho|mus|kr|hz)";
		
		private $inner_bypass;   // rigged to pass messages between two private classes
		
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
		
		public function __destruct() {
			$this->text = "";
			$this->title = "";
			$this->cargo = "";
		}
		
		public function parse($sections_to_ignore=array()) {
			$this->citations();
			
			$this->initial_clean();
			
			$this->templates();
			
			$this->page_attributes();
			
			if(!in_array("sections", $sections_to_ignore)) {
				$this->sections();
			}
			
			if(!in_array("external_links", $sections_to_ignore)) {
				$this->external_links();
			}
			
			if(!in_array("categories", $sections_to_ignore)) {
				$this->categories();
			}
			
			if(!in_array("portals", $sections_to_ignore)) {
				$this->portals();
			}
			
			if(!in_array("meta_boxes", $sections_to_ignore)) {
				$this->meta_boxes();
			}
			
			if(!in_array("foreign_wikis", $sections_to_ignore)) {
				$this->foreign_wikis();
			}
			
			if(!in_array("attachments", $sections_to_ignore)) {
				$this->attachments();
			}
			
			//if(!in_array("guess", $sections_to_ignore)) {
			//	$this->make_logical_guesses_on_content();
			//}
			
			$this->pack_cargo();
			
			$this->cargo = $this->finalize_internal_links($this->cargo);
			
			
			if(!empty($sections_to_ignore)) {
				foreach($sections_to_ignore as $section_to_ignore) {
					if(isset($this->cargo[ $section_to_ignore ])) {
						unset($this->cargo[ $section_to_ignore ]);
					}
				}
			}
			
			// move citations down
			if(isset($this->cargo['citations'])) {
				$citations = $this->cargo['citations'];
				
				unset($this->cargo['citations']);
				
				$this->cargo['citations'] = $citations;
				
				unset($citations);
			}
			
			foreach(array_keys($this->cargo) as $cargo_key) {
				if(empty($this->cargo[$cargo_key])) {
					unset($this->cargo[$cargo_key]);
				}
			}
			
			$this->cargo['debug']['peak_memory_usage'] = memory_get_peak_usage();
			
			return $this->cargo;
		}
		
		private function tables($section_header_key, $section_syntax) {
			$section_syntax_offset_cursor_position = 0;
			
			if(preg_match_all("#(\{{2}col\-begin\}{2})(.+)(\{{2}col\-end\}{2})#si", $section_syntax, $col_matches, PREG_OFFSET_CAPTURE)) {
				foreach(array_keys($col_matches[0]) as $col_key) {
					$cols = preg_split("#\s*\{{2}\-\}{2}\s*#s", $col_matches[2][ $col_key ][0], -1, PREG_SPLIT_NO_EMPTY);
					
					if(!empty($cols)) {
						foreach($cols as $col) {
							// column cleanup
							$col = preg_replace("#^\|([^\|]+)\|#si", "", $col);
							$col = preg_replace("#(\s*)\|([^\|]+)\|\s*$#si", "\\1", $col);
							$col = trim($col, " |");
							
							// process any inner tables
							$section_syntax = $this->tables($section_header_key, $col);
						}
					}
				}
			}
			
			while(preg_match("#\{\|(?:.+?)\|\}#s", $section_syntax, $matches, PREG_OFFSET_CAPTURE)) {
				$bits = preg_split("#\|\}\s*#s", $matches[0][0], -1, PREG_SPLIT_DELIM_CAPTURE);
				
				$new = array_shift($bits) ."|}";
				$new .= array_shift($bits);
				
				$offset = $matches[0][1];
				
				if(strtoupper(mb_detect_encoding($section_syntax)) == "UTF-8") {
					$offset = mb_strlen(mb_strcut($section_syntax, 0, $matches[0][1]));
				}
				
				
				$section_syntax = $this->table_process($new, $offset, $section_syntax, $section_header_key);
			}
			
			return $section_syntax;
		}
		
		private function table_process($table, $raw_table_full_offset, $section_syntax, $section_header_key) {
			// Generate unique table key
			$table_key = substr(md5(microtime(true) ."_". rand(0, 9999) ."_". (__LINE__ * rand(1, 100))), 0, 8);
			
			$raw_table_full_length = mb_strlen($table);
			
			$wiki_table_shortcode = "[wiki_table=". $table_key ."]";
			
			$section_syntax_before = $section_syntax;
			$section_syntax = mb_substr($section_syntax, 0, $raw_table_full_offset) . $wiki_table_shortcode . mb_substr($section_syntax, ($raw_table_full_offset + $raw_table_full_length));
			
			$table_before = $table;
			
			$table = preg_replace("#\|\-([^\n]*)\n#si", "|-\n", $table);
			$table = preg_replace("#^\{\|([^\n]*?)\n\|\-#si", "{|", $table);
			
			$table_data = array();
			
			$table = trim(str_replace("\r", "", $table));
			$table = preg_replace(array("#^\s*\{\s*\|\s*(?:\|\-\s*)?#si", "#(?:\s*\|\-)?\s*\|\s*\}\s*$#si"), "", $table);
			$table = preg_replace("#\s*(?:width|height|border|style|class|cellpadding|cellspacing|align|id)=(\"|\')(?:[^\\1]*?)\\1#si", "", $table);
			$table = preg_replace("#\s*(?:width|height|border|style|class|cellpadding|cellspacing|align|id)=(?:[^\s]*?)#si", "", $table);
			$table = preg_replace("#". preg_quote(PHP_EOL, "#") ."\|{2,}#si", "\n|", $table);
			$table = preg_replace("#". preg_quote(PHP_EOL, "#") ."\!{2,}#si", "\n!", $table);
			$table = preg_replace(array("#^\s*\|\-\s*#si", "#\s*\|\-\s*$#si"), "", $table);
			
			$table = trim($table);
			
			//new dBug(array(
			//	'table_key'	=> $table_key,
			//	'raw_table_full_length'	=> $raw_table_full_length,
			//	'raw_table_full_offset'	=> $raw_table_full_offset,
			//	'wiki_table_shortcode'	=> $wiki_table_shortcode,
			//	'wiki_table_shortcode_length'	=> mb_strlen($wiki_table_shortcode),
			//	'table'	=> $table_before,
			//	'table_len'	=> strlen($table_before),
			//	'table_len_mb'	=> mb_strlen($table_before),
			//	'section_syntax_before'	=> $section_syntax_before,
			//	'section_syntax'	=> $section_syntax,
			//));
			
			//$rows = preg_split("#\s*". preg_quote("|-", "#") ."\s*#", $table, -1, PREG_SPLIT_NO_EMPTY);
			$rows = preg_split("#\s". preg_quote("|-", "#") ."\s#", $table);
			
			if(empty($rows)) {
				return $section_syntax;
			}
			
			$num_header_rows = 0;
			$row_i = 1;
			
			$rowspan_bucket = array();   // store rowspan="" data while going through rows
			
			foreach($rows as $row) {
				if($num_header_rows === 0 && preg_match("#\s*\|\+\s*#si", $row)) {
					//print "continue... ". __LINE__ ."<br />\n";
					continue;
				}
				
				$row = $this->insert_jungledb_helpers($row);
				
				$row = str_replace("!!", "\n!", $row);
				$row = str_replace("||", "\n|", $row);
				
				$row = preg_replace("#^\s*\|". preg_quote(PHP_EOL, "#") ."#si", "", $row);
				
				$row_type = false;
				
				if($row_type === false && preg_match("#scope=\"\s*(row|col)\s*\"#si", $row, $match)) {
					if(strtolower(trim($match[1])) == "row") {
						$row_type = "data";
					} elseif(strtolower(trim($match[1])) == "col") {
						$row_type = "header";
					}
				}
				
				if($row_type === false && preg_match("#\n\s*!#si", $row)) {
					$row_type = "header";
				}
				
				//$columns = preg_split("#\s*(\![^\|]*)?\|\s*#si", $row, -1, PREG_SPLIT_DELIM_CAPTURE ^ PREG_SPLIT_NO_EMPTY);
				//$columns = explode("\n", $row);
				$columns = preg_split("#\s*". preg_quote(PHP_EOL, "#") ."(\||\!)#si", $row);
				
				//array_shift($columns);
				
				
				if(empty($columns)) {
					//print "continue... ". __LINE__ ."<br />\n";
					//print htmlentities($row) ."<br />\n";
					//print "<hr />\n";
					continue;
				}
				
				if(empty($num_header_rows)) {
					$num_header_rows = 1;
					$_num_header_rows = 1;
				}
				
				if($row_type === false && ($row_i <= $num_header_rows)) {
					$row_type = "header";
				}
				
				// default to row_type = row
				if($row_type === false) {
					$row_type = "data";
				}
				
				$table_data[ $row_i ]['type'] = $row_type;
				
				
				// save column data
				$max_rowspan = 1;
				$col_i = 1;   // column id
				
				foreach($columns as $column) {
					$column_before = $column;
					
					if($row_type === "header") {
						while(!empty($rowspan_bucket[ $col_i ])) {
							$rowspan_bucket[ $col_i ]--;
							
							$col_i++;
						}
					}
					
					$col_x = 1;   // number of columns to fill
					$col_y = 1;   // number of rows to fill
					
					if(preg_match_all("#\s*(row|col)span=(\"|\'|)([0-9]+)\\2#si", $column, $spanmatches)) {
						foreach(array_keys($spanmatches['0']) as $mkey) {
							$span_type = strtolower(trim($spanmatches['1'][$mkey]));
							$span_length = trim($spanmatches['3'][$mkey]);
							
							//if($span_length <= 1) {
							//	continue;
							//}
							
							if($span_type == "row") {
								$col_y = max(1, $span_length);
								
								if($row_type === "header") {
									$rowspan_bucket[ $col_i ] = ($span_length - 1);
								}
							} elseif($span_type == "col") {
								$col_x = max(1, $span_length);
							}
						}
					}
					
					// leading spaces + ! + spaces
					$column = preg_replace("#^\s*\!\s*#si", "", $column);
					
					
					// leading spaces + | + spaces
					$column = preg_replace("#^\s*\|\s*#si", "", $column);
					
					
					// leading "xxx="""
					$column = preg_replace("#^\s*([A-Za-z0-9\-\_]+)=\"(.+?)\"#si", "", $column);
					$column = preg_replace("#^\s*([A-Za-z0-9\-\_]+)=(.*)\s*(?:\||\!)#si", "", $column);
					
					// leading spaces + | + spaces
					$column = preg_replace("#^\s*\|{1,}\s*#si", "", $column);
					$column = trim($column);
					
					
					$column = $this->clean_jungledb_helpers($column);
					$column = $this->clean_wiki_text($column, true);
					
					
					$column = trim($column, " |");
					
					if($row_type === "header") {
						$column = ltrim($column, " |!");
					}
					
					$column_data = array(
						'text' => $column,
					);
					
					// process and save basic row data columns
					if($row_type === "data") {
						for($y = $row_i; $y <= ($row_i + ($col_y - 1)); $y++) {
							$table_data[ $y ]['type'] = "data";
							
							for($i = $col_i; $i <= ($col_i + ($col_x - 1)); $i++) {
								if(isset($table_data[ $y ]['columns'][ $i ])) {
									$col_i++;
									
									continue;
								}
								
								$table_data[ $y ]['columns'][ $i ] = $column_data;
							}
						}
					}
					
					// process header data
					if($row_type === "header") {
						if($col_x > 1) {
							$column_data['colspan'] = $col_x;
						}
						
						if($col_y > 1) {
							$column_data['rowspan'] = $col_y;
							
							$max_rowspan = max($max_rowspan, $col_y);
						}
						
						
						$table_data[ $row_i ]['columns'][ $col_i ] = $column_data;
					}
					
					if($col_x > 1) {
						$col_i = ($col_i + ($col_x - 1));
					}
					
					$col_i++;
				}
				
				$num_header_rows = ($num_header_rows + ($max_rowspan - 1));
				
				
				// remove completely empty rows -- stop on first non-strlen=0 column, else remove and go on
				$check_continue = true;
				
				foreach($table_data[ $row_i ]['columns'] as $column) {
					if(strlen( trim($column['text']) ) > 0) {
						$check_continue = false;
						
						break;
					}
				}
				
				if($check_continue === true) {
					// didn't pass check, remove this data and go on without updating increaser
					
					unset($table_data[ $row_i ]);
					
					continue;
				}
				
				$row_i++;
			}
			
			
			if(!empty($table_data)) {
				
				// clean columns
				$num_cols = 0;
				
				foreach($table_data as $table_row_id => $table_row) {
					if(empty($num_cols) && $table_row['type'] == "header") {
						foreach($table_row['columns'] as $column) {
							if(!empty($column['colspan'])) {
								$num_cols += $column['colspan'];
							} else {
								$num_cols++;
							}
						}
						
						continue;
					}
					
					if(!empty($num_cols) && $table_row['type'] === "data") {
						ksort($table_data[$table_row_id]['columns']);
						
						foreach(array_keys($table_row['columns']) as $column_id) {
							if($column_id > $num_cols) {
								unset($table_data[$table_row_id]['columns'][$column_id]);
							}
						}
					}
				}
				
				
				if(!isset($this->cargo['tables'])) {
					$this->cargo['tables'] = array();
				}
				
				$this->cargo['tables'][ $table_key ] = array(
					'key'			=> $table_key,
					'section'		=> $section_header_key,
					'num_columns'	=> $num_cols,
					'num_rows'		=> ($row_i - 1 - $num_header_rows),
					'data'			=> $table_data,
				);
			}
			
			return $section_syntax;
		}
		
		private function quotes($section_header_key, $section_syntax) {
			// http://en.wikipedia.org/wiki/Category:Quotation_templates
			// specific: http://en.wikipedia.org/wiki/Template:Cquote
			// TODO
			
			$this->inner_bypass = $section_header_key;
			$section_syntax = preg_replace_callback("#\{{2}\s*(bq|Block quote|Bquote|cquote|cquote2|quote|blockquote|quote box|rquote|Quotation)\s*\|(.+?)\}{2}#si", array($this, "quote_parser"), $section_syntax);
			
			return $section_syntax;
		}
		
		private function quote_parser($match) {
			$section_header_key = $this->inner_bypass;
			
			$template = strtolower(trim($match[1]));
			$contents = explode("|", trim($this->insert_jungledb_helpers($match[2]), " |"));
			
			// stylized text only
			if(in_array($template, array("bq"))) {
				return trim($match[2], " |");
			}
			
			
			$quote = array(
				'key'		=> substr(md5($section_header_key ."_". rand(1, 9999) ."_". rand(300, 5099)), 0, 8),
				'section'	=> $section_header_key,
				'text'		=> false,
			);
			
			
			// complex template
			if(in_array($template, array("quote box"))) {
				foreach($contents as $content) {
					if(preg_match("#^\s*title\s*=\s*(.+?)$#si", $content, $content_match)) {
						$quote['title'] = trim($content_match[1]);
						
						continue;
					}
					
					if(preg_match("#^\s*quote\s*=\s*(.+?)$#si", $content, $content_match)) {
						$quote['text'] = trim($content_match[1]);
						
						continue;
					}
					
					if(preg_match("#^\s*source\s*=\s*(.+?)$#si", $content, $content_match)) {
						$quote['source'] = trim($content_match[1]);
						
						continue;
					}
				}
			} else {
				// general template
				
				$quote['text'] = array_shift($contents);
				
				if(!empty($contents) && in_array($template, array("block quote", "bquote", "quotation"))) {
					array_shift($contents);
					array_shift($contents);
				}
				
				if(!empty($contents)) {
					$quote['author'] = array_shift($contents);
				}
				
				if(!empty($contents)) {
					$quote['source'] = array_shift($contents);
				}
				
				if(!empty($contents)) {
					$quote['publication'] = array_shift($contents);
				}
			}
			
			
			// prep text
			
			if($quote['text'] === false) {
				return "";
			}
			
			$quote['text'] = trim(preg_replace("#^(?:quotetext|\d|text)\s*=\s*#si", "", trim($quote['text'])));
			
			if(strlen($quote['text']) < 1) {
				return "";
			}
			
			
			
			if(isset($quote['author'])) {
				$quote['author'] = trim(preg_replace("#^(?:personquoted|\d|sign)\s*=\s*#si", "", trim($quote['author'])));
				
				if(strlen($quote['author']) < 1) {
					unset($quote['author']);
				}
			}
			
			if(isset($quote['source'])) {
				$quote['source'] = trim(preg_replace("#^(?:quotesource|\d|source)\s*=\s*#si", "", trim($quote['source'])));
				
				if(strlen($quote['source']) < 1) {
					unset($quote['source']);
				}
			}
			
			$quote = array_map(array($this, "clean_jungledb_helpers"), $quote);
			
			$this->cargo['quotes'][ $quote['key'] ] = $quote;
			
			return "[wiki_quote=". $quote['key'] ."]";
		}
		
		private function templates() {
			
			$templates = array();
			
			//preg_match_all("#(". preg_quote("{{", "#") .")(.*?)(". preg_quote("}}", "#") .")#si", $this->text, $matches);
			$text_templates = $this->_find_sub_templates($this->text);
			
			foreach($text_templates[0] as $text_template) {
				if(!preg_match("#^\{\{([^\|\:]+)(.*?)\}\}$#si", $text_template, $match)) {
					continue;
				}
				
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
					
					//$template_attributes = preg_replace("#\[\[\s*([^\|]+?)\|(.+?)\]\]#si", "[[\\1###JNGLDBDEL###\\2]]", $template_attributes);
					
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
		
		private function page_attributes() {
			$page_attributes = array(
				 'type' => false
			);
			
			if($page_attributes['type'] === false && (preg_match("#^(Template|Wikipedia|Portal|User|File|MediaWiki|Template|Category|Book|Help|Course|Institution)\:#si", $this->title, $match))) {
				// special wikipedia pages
				
				$page_attributes['type'] = strtolower($match['1']);
			}
			
			if($page_attributes['type'] === false && (preg_match("#\#REDIRECT\s*\[\[([^\]]+?)\]\]#si", $this->text, $match))) {
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
			
			if(preg_match("#\{\{\s*Use\s+([ymdhs]{3,5})\s+date#si", $this->text, $match)) {
				$page_attributes['date_format'] = $match[1];
			}
			
			if(!empty($page_attributes)) {
				$this->cargo['page_attributes'] = $page_attributes;
			}
		}
		
		private function meta_boxes() {
			
			// Todo: Some pages use {{MLB infobox ... }} instead of {{Infobox MLB ... }} [ex: http://en.wikipedia.org/wiki/Texas_Rangers_(baseball)]. I think {{MLB ...}} is an actual Wikipedia template and not distinctly an Infobox template
			
			//////// TODO /////////////////
			$meta_box_templates = array(
				"USCensusPop",
				"Automatic taxobox",
				"(?:[A-Za-z0-9\s\-\_]+?) (?:infobox|taxobox)",
				"Football kit",
				"Cladogram",
				"tracklist",
				"Track listing",
				"Historical populations",
				"Demography",
				//"MLB infobox",
				"Coord\s*\|",
				"Location map",
				"Weather box",
				"Album ratings",
				"Film ratings",
				"Song ratings",
				"Video game reviews",
				"Chembox",
				"Convention list",
				"Pedigree",
				"DYS",
				"NCAA Division I baseball rankings",
				"NCAA Division I FBS football ranking movements",
				"CFB Conference Schedule Entry",
				"CFB passer rating",
				"CFB Schedule End",
				"CFB Schedule Entry",
				"CFB Standings End",
				"CFB Standings Entry",
				"CFB Standings Start",
				"CFB Team Depth Chart",
				"CFB Yearly Record End",
				"CFB Yearly Record Entry",
				"CFB Yearly Record Start",
				"CFB Yearly Record Subhead",
				"CFB Yearly Record Subtotal",
				"College athlete recruit end",
				"College athlete recruit entry",
				"Tomatobox",
				"Sourcetext",
				"Singlechart",
				"Singles discography",
				"USFL team",
				"V8 supercar race",
				"Video game multiple console reviews",
				"Video game titles",
				"rocketspecs",
				"Rfam box",
				"VG Reviews",
				"Episode list",
				"Independent baseball team",
				"Infobox independent baseball team",
				
				// Generic ones at the bottom
				"Infobox",
				"Geobox",
				"Persondata",
				"Succession box",
				"Taxobox",
			);
			
			preg_match_all("/\{{2}((?>[^\{\}]+)|(?R))*\}{2}/x", $this->text, $matches);
			
			if(empty($matches[0])) {
				return false;
			}
			
			$skipped_templates = array("main", "main list", "cat main", "mainarticle", "see also2", "see also", "see", "further", "about", "rellink", "portal", "portalbox", "portal box", "portal bar", "portal-inline", "bq", "block quote", "bquote", "cquote", "cquote2", "quote", "blockquote", "quote box", "rquote", "quotation");
			
			$meta_boxes = array();
			
			//$meta_boxes['_debug'] = $matches;
			
			$_meta_boxes_multiples = array();
			
			foreach($matches[0] as $key => $raw_template_syntax) {
				$raw_template_syntax = trim($raw_template_syntax);
				
				// skip over those that we already remove/process
				if(!preg_match("#\{{2}\s*(". implode("|", $meta_box_templates) .")(.+?)\}{2}$#si", $raw_template_syntax, $template_type_match)) {
					if(!preg_match("#\{{2}\s*([^\|]+)(?:\|(.*?))?\}{2}$#si", $raw_template_syntax, $skip_match)) {
						continue;
					}
					
					$skip_match[1] = strtolower(trim($skip_match[1], " |"));
					
					// show it if we don't manually process it
					if(in_array($skip_match[1], $skipped_templates)) {
						continue;
					}
					
					if(!empty($this->options['ignore_template_matches'])) {
						foreach($this->options['ignore_template_matches'] as $ignore_template_match_regex) {
							if(preg_match($ignore_template_match_regex, $skip_match[1])) {
								continue 2;
							}
						}
					}
					
					//print "raw_template_syntax = <pre>". preg_replace("#\{{2}\s*([^\|\}\:]+)(\|)?#si", "{{<a href=\"http://en.wikipedia.org/wiki/Template:\\1\">\\1</a>\\2", $raw_template_syntax, 1) ."</pre>\n\n";
					
					continue;
				}
				
				$infobox_values = array();
				
				// pull actual data out
				
				if(!preg_match("#^\{{2}\s*([^\|]+)\|(.*)\s*\}{2}$#si", $raw_template_syntax, $template_type_match)) {
					// something went wrong
					continue;
				}
				
				
				//$infobox_tmp = $matches[1][$key];
				$infobox_tmp = $template_type_match[2];
				
				//$meta_boxes['_debug_before'][] = $infobox_tmp;
				
				// {{url|xxx}} -> [http://[www.]]xxx
				$infobox_tmp = preg_replace_callback("#\{{2}\s*url\s*\|\s*([^\}]+?)\s*\}{2}#si", function($match) {
					return JungleDB_Utils::prepare_url_string($match[1]);
				}, $infobox_tmp);
				
				// get rid of all template calls
				$infobox_tmp = preg_replace("/\{{2}((?>[^\{\}]+)|(?R))*\}{2}/x", "", $infobox_tmp);
				
				//$meta_boxes['_debug_after'][] = $infobox_tmp;
				
				$_meta_box_type = $this->compact_to_slug($template_type_match[1]);
				
				//$meta_boxes['_debug_types'][] = $_meta_box_type;
				
				$infobox_tmp = trim($infobox_tmp, " \r\t\n|");
				
				if("coord" === $_meta_box_type) {
					$_infobox_values = preg_split("#\s*\|\s*#s", $infobox_tmp);
					
					if(!empty($_infobox_values)) {
						foreach(array_keys($_infobox_values) as $iv_key) {
							if(!preg_match("#^\s*(.+)\s*(?:\:|=)\s*(.+)\s*$#si", $_infobox_values[$iv_key], $line_type)) {
								continue;
							}
							
							if(preg_match("#^\s*([A-Za-z0-9\-\_]+)\s*\:#si", $_infobox_values[$iv_key])) {
								unset($_infobox_values[$iv_key]);
								
								continue;
							}
							
							$infobox_values[ $this->compact_to_slug($line_type[1]) ] = trim($line_type[2], " =|");
							
							unset($_infobox_values[$iv_key]);
						}
						
						// wiki styling, unneeded
						if(isset($infobox_values['display'])) {
							unset($infobox_values['display']);
						}
						
						if(isset($infobox_values['format'])) {
							unset($infobox_values['format']);
						}
						
						if(isset($infobox_values['notes'])) {
							unset($infobox_values['notes']);
						}
						
						if(isset($infobox_values['dim'])) {
							$infobox_values['diameter'] = $infobox_values['dim'];
							
							unset($infobox_values['dim']);
						}
						
						$infobox_values['coords'] = implode("|", $_infobox_values);
						$coords = array_map('trim', array_values($_infobox_values));   // reset keys
						
						if(count($coords) == 2) {
							// decimal [without |N|W]
							
							// North or South?
							$coord  = ltrim($coords[0], " -") . (substr($coords[0], 0, 1) == "-"?"S":"N");
							$coord .= " ";
							$coord .= ltrim($coords[1], " -") . (substr($coords[1], 0, 1) == "-"?"W":"E");
							
							$infobox_values['coord'] = $coord;
							
							unset($coord);
							unset($infobox_values['coords']);
						} elseif(count($coords) == 4) {
							// decimal
							$infobox_values['coord'] = $coords[0] . $coords[1] ." ". $coords[2] . $coords[3];
							
							unset($coord);
							unset($infobox_values['coords']);
						} elseif(count($coords) == 6) {
							// degrees
							$coord  = $coords[0] ."°". $coords[1] ."'". strtoupper($coords[2]);
							$coord .= " ";
							$coord .= $coords[3] ."°". $coords[4] ."'". strtoupper($coords[5]);
							
							$infobox_values['coord'] = $coord;
							
							unset($coord);
							unset($infobox_values['coords']);
						} elseif(count($coords) == 8) {
							// degrees
							$coord  = $coords[0] ."°". $coords[1] ."'". $coords[2] ."\"". strtoupper($coords[3]);
							$coord .= " ";
							$coord .= $coords[4] ."°". $coords[5] ."'". $coords[6] ."\"". strtoupper($coords[7]);
							
							$infobox_values['coord'] = $coord;
							
							unset($coord);
							unset($infobox_values['coords']);
						}
						
						unset($coords);
						
						/*
						if(preg_match("##si", $infobox_values['coords'])) {
							
							
							unset($infobox_values['coords']);
						} elseif(preg_match("#(\d+)#si", $infobox_values['coords'])) {
							
							
							unset($infobox_values['coords']);
						}
						*/
						
						
					}
					
					//$infobox_values = array_map(array($this, ), $infobox_values);
				} elseif("historical_populations" === $_meta_box_type) {
					$infobox_values['dates'] = array();
					
					$_infobox_values = preg_split("#\s*\|\s*#s", trim($infobox_tmp, " \n\t\r|"));
					
					$dated = false;
					
					foreach($_infobox_values as $_infobox_value) {
						if(preg_match("#^\s*([A-Za-z]+)\s*=\s*(.+?)\s*$#si", $_infobox_value, $tmp__match)) {
							$slug = $this->compact_to_slug($tmp__match[1]);
							
							if(in_array($slug, array("align"))) {
								continue;
							}
							
							$infobox_values[ $slug ] = $this->clean_wiki_text( trim($tmp__match[2]) );
							
							continue;
						}
						
						if(false !== $dated) {
							$infobox_values['dates'][ $dated ] = $this->clean_wiki_text( trim($_infobox_value) );
							
							$dated = false;
						} else {
							$dated = $this->clean_wiki_text( trim($_infobox_value) );
						}
					}
				} else {
					$infobox_tmp = $this->insert_jungledb_helpers($infobox_tmp);
					
					//if(preg_match("#\s*([infobox|taxobox])\s*$#si", $_meta_box_type)) {
					//	$infobox_tmp = "|". trim($infobox_tmp, " |\n\t");
					//}
					
					$infobox_tmp = ltrim($infobox_tmp, " |\r\t\n");
					
					//$infobox_values[':debug'] = $infobox_tmp;
					
					$infobox_tmp = preg_split("#(?:^|\|)\s*([^=|\|]+?)\s*=#si", $infobox_tmp, -1, PREG_SPLIT_DELIM_CAPTURE);
					//preg_match_all("#\s*(^|\|)\s*([^=]+)\s*=#si", $infobox_tmp, -1, PREG_SPLIT_DELIM_CAPTURE);
					
					if("" === $infobox_tmp[0]) {
						array_shift($infobox_tmp);
					}
					
					if(("automatic_taxobox" === $_meta_box_type) && (false === strpos("=", $infobox_tmp[0]))) {
						$_meta_box_type = $this->compact_to_slug($_meta_box_type ." ". array_shift($infobox_tmp));
					}
					
					//$infobox_values[':debug_tmp'] = $infobox_tmp;
					
					$last_line_key = "";
					
					//if(((count($infobox_tmp) % 2) === 1)) {
					//	$_subtype = array_shift($infobox_tmp);
					//	$_subtype = $this->compact_to_slug($_subtype);
					//	
					//	if(!empty($_subtype) && $_subtype !== ":nil") {
					//		$infobox_values[':subtype'] = $_subtype;
					//		//$meta_boxes['_debug_subtypes'][] = $_subtype;
					//	}
					//}
					
					//$meta_boxes['_debug_split'][] = $infobox_tmp;
					$i = 0;
					
					foreach($infobox_tmp as $it_key => $line) {
						if((++$i % 2) == 1) {
							$last_line_key = $it_key;
							continue;
						}
						
						$row_key = $this->compact_to_slug( $infobox_tmp[ $last_line_key ] );
						
						$row_value = trim($line, " |");
						
						$row_value = preg_replace("#('{2,5})(.+?)\\1#si", "\\2", $row_value);
						
						if((strlen($row_key) < 1) || (strlen($row_value) < 1)) {
							continue;
						}
						
						
						if($row_value == "?") {
							continue;
						}
						
						
						if(in_array($_meta_box_type, array("tracklist"))) {
							if(preg_match("#^([A-Za-z]+?)([0-9]+?)$#si", $row_key, $rkmatch)) {
								$infobox_values['tracks'][ $rkmatch[2] ][ $rkmatch[1] ] = trim($row_value, " |");
								
								continue;
							}
						}
						
						// divide lexiconic lists into physical lists
						$row_value_test = $row_value;
						$row_value_test = preg_replace("#(\[|\{){2}(.+?)(\]|\}){2}#si", " ", $row_value_test);
						$row_value_test = preg_replace("#\s{1,}#si", "", $row_value_test);
						$row_value_test = preg_replace("#(\-|\,|or|and)#si", "-", $row_value_test);
						
						if(preg_match("#^[\-]{1,}$#si", $row_value_test)) {
							$row_value = preg_replace("#(\]|\}){2}\s*(?:[\-|\,](?:\s*(?:and|&))?)\s*(\[|\{){2}#si", "\\1\\1<br />\\2\\2", $row_value);
						}
						
						
						// Some values are numbers (25) or (25/100), remove parenthesis
						$row_value = preg_replace("#^\(([0-9/]+)\)$#si", "\\1", $row_value);
						
						
						
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
							$row_value = trim($row_value, " \t\r\n");
						}
						
						
						if(is_array($row_value)) {
							if(count($row_value) == 1) {
								$row_value = current($row_value);
							}
						}
						
						if(!is_array($row_value)) {
							if($row_value == "?") {
								continue;
							}
							
							if(strlen($row_value) < 1) {
								continue;
							}
						}
						
						if(is_array($row_value)) {
							$row_value = array_map(function($item) {
								return trim($item, " |");
							}, $row_value);
						} else {
							$row_value = trim($row_value, " |");
						}
						
						$infobox_values[ $row_key ] = $row_value;
					}
				}
				
				if(empty($infobox_values) || (isset($infobox_values[':subtype']) && count($infobox_values) == 1)) {
					continue;
				}
				
				$infobox_values = $this->clean_jungledb_helpers($infobox_values);
				
				
				
				
				$_meta_box_key = $_meta_box_type;
				
				//$meta_boxes['_debug_types_during'][] = $_meta_box_key;
				
				//if(isset($infobox_values[':subtype'])) {
				//	$_meta_box_key .= "_". $infobox_values[':subtype'];
				//	
				//	unset($infobox_values[':subtype']);
				//}
				
				//$meta_boxes['_debug_types_after'][] = $_meta_box_key;
				
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
		
		private function sections($section_text=false, $level=2, $section_key="") {
			if($level === 2) {
				$section_text = $this->text;
			}
			
			if($section_text === false) {
				return array();
			}
			
			$section_text = " ". $section_text ." ";
			
			$s = 0;
			$section = array();
			
			$sections_splits = preg_split("#\s(?:={". $level ."})([^=]+?)(?:={". $level ."})\s#si", $section_text, -1, PREG_SPLIT_DELIM_CAPTURE);
			
			if(empty($sections_splits)) {
				return $section;
			}
			
			$section['text'] = array_shift($sections_splits);
			
			// {{main}}
			if(preg_match("#\{{2}\s*(Main|Main list|Cat main|Mainarticle|See also2|See also|See|Further|About|Rellink)\s*\|(.+?)\}{2}\s+#si", $section['text'], $main_match)) {
				$bleh = explode("|", $main_match[2]);
				$main_title = array_shift($bleh);
				$main_title = trim($main_title, " |}");
				$main_title_hash = md5(ucfirst($main_title));
				$main_title_key = substr($main_title_hash, 0, 8);
				
				$section['main'][] = array(
					':wiki_type'	=> $this->compact_to_slug( $main_match[1] ),
					'title'			=> $main_title,
					'title_hash'	=> $main_title_hash,
					'title_key'		=> $main_title_key,
				);
			}
			
			// extract tables
			$section['text'] = $this->tables($section_key, $section['text']);
			
			// extract quotes
			$section['text'] = $this->quotes($section_key, $section['text']);
			
			// clean out any unwanted wiki syntax
			$section['text'] = $this->clean_wiki_text($section['text'], true);
			
			if(strlen($section['text']) > 0) {
				//$section['text'] = preg_replace("#\n{1}?#si", "\n\n", $section['text']);
				//$section['text'] = preg_replace("#\n{3,}?#si", "\n", $section['text']);
				$section['text'] = JungleDB_Utils::remove_emptied_sentence_bits($section['text']);
			} else {
				unset($section['text']);
			}
			
			if(!empty($sections_splits)) {
				$section['children'] = array();
				
				foreach($sections_splits as $s_key => $s_text) {
					if(($s_key % 2) === 0) {
						continue;
					}
					
					$_section_title = trim($this->clean_wiki_text($sections_splits[($s_key - 1)]), " =");
					$_section_title = JungleDB_Utils::remove_emptied_sentence_bits($_section_title);
					
					if(in_array(strtolower($_section_title), array("references", "see also", "external links"))) {
						continue;
					}
					
					$_section_key = substr(md5($_section_title), 0, 8);
					
					// find all child sections
					$section['children'][$s++] = array(
						'key'	=> $_section_key,
						'title' => $_section_title,
					) + $this->sections($s_text, ($level + 1), $_section_title, $_section_key);
				}
			}
			
			
			if($level > 2) {
				return $section;
			}
			
			
			$this->cargo['sections'] = array();
			
			if(!empty($section['children'])) {
				$this->cargo['sections'] = array_merge($this->cargo['sections'], $section['children']);
			}
			
			if(!empty($section['text'])) {
				array_unshift($this->cargo['sections'], array('key'	=> substr(md5("JungleDB:Wikipedia Introduction"), 0, 8), 'text' => $section['text']));
			}
		}
		
		private function external_links() {
			$external_links = array();
			$_external_links = array();
			
			if(!preg_match("#\s+={2}\s*External\s+links\s*={2}\s+#si", $this->text, $matches)) {
				return false;
			}
			
			$bits = preg_split("#\s+={2}\s*External\s+links\s*={2}\s+#si", $this->text);
			
			$lines = explode("\n", array_pop($bits));
			
			$_el_multiples = array();
			
			if(empty($lines)) {
				return false;
			}
			
			
			foreach($lines as $line) {
				if(!preg_match("#^\*(.+?)$#si", trim($line), $match)) {
					continue;
				}
				
				$external_link = array();
				$line_value = trim($match[1]);
				
				switch(true) {
					// [http://... ...]
					case preg_match("#\[\s*http(s?)\:\/\/([^\s]+?)\s+([^\]]+?)\s*]#si", $line_value, $lmatch):
						$external_link = array(
							'type' => ":". $this->compact_to_slug("url"),
							'attributes' => array(
								'url' => "http". $lmatch[1] ."://". $lmatch[2],
								'text' => trim($lmatch[3], " -"),
							),
						);
					break;
					
					// [... http://...]
					case preg_match("#\[\s*([^\s]+?)(\s+?)http(s?)\:\/\/([^\s]+?)\s*]#si", $line_value, $lmatch):
						$external_link = array(
							 'type' => ":". $this->compact_to_slug("url")
							,'attributes' => array(
								 'url' => "http". $lmatch[2] ."://". $lmatch[3]
								,'text' => trim($lmatch[1], " -")
							)
						);
					break;
					
					// {official [...]|...}
					case preg_match("#\{\{\s*official\s+([^\|]+?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
						$el_type = $this->compact_to_slug($lmatch[1]);
						
						$bleh = explode("|", $lmatch[2]);
						
						$external_link = array(
							'type' => $this->compact_to_slug("official". ((strlen($el_type) > 0)?"_". $el_type:"")),
							'attributes' => trim(preg_replace("#^\s*([0-9]+?)\s*=\s*#si", "", array_shift($bleh))),
						);
					break;
					
					// {official|...}
					case preg_match("#\{\{\s*official\s*\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
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
					
					// {iobdb|...}
					case preg_match("#\{\{\s*(iobdb)\s*(?:(name|show|venue|title)\s*\|\s*)?\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
						$attributes = "";
						$subtype = (!empty($match[2])?trim(strtolower($match[2]), " |"):"name");   // name for default
						$_bits = explode("|", trim($lmatch[3]));
						
						if($subtype == "name") {
							$attributes = trim(array_shift($_bits)) ."||". trim(array_shift($_bits));
							
							try {
								$possible_middle = trim(array_shift($_bits));
								
								if(!empty($possible_middle) && strstr($possible_middle, " ") === false) {
									$attributes = str_replace("||", "|". $possible_middle ."|", $attributes);
								}
							} catch(Exception $e) {}
						} elseif($subtype == "show") {
							$attributes = trim(array_shift($_bits), " |");
						} elseif(($subtype == "title") || ($type == "venue")) {
							$attributes = trim(array_shift($_bits), " |");
						} else {
							break;
						}
						
						$external_link = array(
							'type' => $this->compact_to_slug($lmatch[1]),
							'attributes' => (in_array($subtype, array("name", "venue", "title", "show"))?$subtype:"name") .":". $attributes,
						);
					break;
					
					// {{[WSJtopic|NYTtopic|Guardian topic]|...}}
					case preg_match("#\{\{\s*(WSJ|NYT|Guardian\s*)topic\s*\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
						$bleh = explode("|", $lmatch[2]);
						
						$external_link = array(
							'type'			=> $this->compact_to_slug($lmatch[1]),
							'attributes'	=> trim(array_shift($bleh)),
						);
					break;
					
					
					// {{Find a grave|xxx[|yyy]}}
					case preg_match("#\{\{\s*(Find a grave)\s*\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
						$bleh = explode("|", $lmatch[2]);
						
						$external_link = array(
							'type' => $this->compact_to_slug($lmatch[1]),
							'attributes' => trim(array_shift($bleh)),
						);
					break;
					
					// {[myspace|facebook|twitter]|...}
					case preg_match("#\{\{\s*(myspace|facebook|twitter)\s*\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
						$bleh = explode("|", $lmatch[2]);
						
						$external_link = array(
							'type' => $this->compact_to_slug($lmatch[1]),
							'attributes' => trim(array_shift($bleh)),
						);
					break;
					
					// {reverbnation|...}
					case preg_match("#\{\{\s*reverbnation\s*\|([^\|]+?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
						$bleh = explode("|", $lmatch[1]);
						
						$external_link = array(
							'type' => $this->compact_to_slug("reverbnation"),
							'attributes' => trim(array_shift($bleh)),
						);
					break;
					
					// {spotify|...}
					case preg_match("#\{\{\s*spotify\s*\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
						$bleh = explode("|", $lmatch[1]);
						
						$spotify_type = trim(strtolower(array_pop($bleh)));
						
						$bleh = explode("|", $lmatch[1]);
						
						$external_link = array(
							'type'			=> $this->compact_to_slug("spotify"),
							'attributes'	=> (in_array($spotify_type, array("artist", "track", "search", "album"))?$spotify_type:"artist") .":". trim(array_shift($bleh)),
						);
					break;
					
					// {dmoz|aaa|bbb[|user]}
					case preg_match("#\{\{\s*dmoz\s*\|([^\}]+?)\}\}#si", $line_value, $lmatch):
						$bits = explode("|", $lmatch[1]);
						
						$external_link = array(
							'type'			=> $this->compact_to_slug("dmoz"),
							'attributes'	=> ((count($bits) > 1) && (trim(strtolower(array_pop($bits))) === "user")?"user:":"") . trim(array_shift($bits)),
						);
					break;
					
					// {yahoo directory|xxx|yyy}
					case preg_match("#\{\{\s*yahoo directory\s*\|([^\|]+?)\|([^\}\}]+?)\s*\}\}#si", $line_value, $lmatch):
						$external_link = array(
							'type'			=> $this->compact_to_slug( "yahoo directory" ),
							'attributes'	=> trim($lmatch[2]),
						);
					break;
					
					// {dmoz|xxx}
					case preg_match("#\{\{\s*(dmoz|Worldcat\s+?id|C\-SPAN|ning|nndb|tvtropes)\s*\|\s*([^\}]+?)\s*\}\}#si", $line_value, $lmatch):
						$bleh = explode("|", $lmatch[2]);
						
						$external_link = array(
							'type'			=> str_replace("_id", "", $this->compact_to_slug($lmatch[1])),
							'attributes'	=> trim(preg_replace("#(id|key)\s*=\s*#si", "", array_shift($bleh))),
						);
					break;
					
					// [[IMDbXXXX:YYYY]] -- http://en.wikipedia.org/wiki/Wikipedia:IMDb
					// more: http://meta.wikimedia.org/wiki/Interwiki_map
					case preg_match("#\[{2}\s*IMDb(Name|Title|Company|Character|Event)\s*\:\s*([0-9]+)\s*\]{2}#si", $line_value, $lmatch):
						$type_to_typekey = array("name" => "nm", "person" => "nm", "movie" => "tt", "title" => "tt", "episode" => "tt", "episodes" => "tt", "company" => "co", "character" => "ch", "event" => "ev", "awards" => "ev", "award" => "ev");
						
						$external_link = array(
							'type'			=> $this->compact_to_slug("imdb"),
							'attributes'	=> $type_to_typekey[ strtolower(trim($lmatch[1])) ] . str_pad(ltrim($lmatch[2], " -=0"), 7, "0", STR_PAD_LEFT),
						);
					break;
					
					// {imdb xxx|yyy}
					case preg_match("#\{\{\s*imdb\s*([^\|]+?)\|\s*([^\}]+?)\}\}#si", $line_value, $lmatch):
						$type_to_typekey = array("name" => "nm", "person" => "nm", "bio" => "nm", "movie" => "tt", "title" => "tt", "episode" => "tt", "episodes" => "tt", "company" => "co", "character" => "ch", "event" => "ev", "awards" => "ev", "award" => "ev");
						
						$id = false;
						
						if(preg_match("#id\s*=\s*([0-9]{1,7})\s*#si", $lmatch[0], $id_match)) {
							$id = trim($id_match[1], " |=-");
						} elseif(preg_match("#\|\s*([0-9]{1,7})\s*#si", $lmatch[0], $id_match)) {
							$id = trim($id_match[1], " |=-");
						} else {
							$bleh = explode("|", $lmatch[2]);
							$id = trim(preg_replace("#\s*id\s*=\s*#si", "", array_shift($bleh)), " |=-");
						}
						
						$typekey = strtolower(trim($lmatch[1], " |_-="));
						
						if(!array_key_exists($typekey, $type_to_typekey)) {
							break;
						}
						
						$external_link = array(
							'type'			=> $this->compact_to_slug("imdb"),
							'attributes'	=> $type_to_typekey[ $typekey ] . str_pad(ltrim($id, "0"), 7, "0", STR_PAD_LEFT),
						);
					break;
					
					// {rotten tomatoes person|xxx}
					case preg_match("#\{\{\s*rotten(?:\-|\s)tomatoes person\s*\|\s*([^\}]+?)\s*\}\}#si", $line_value, $lmatch):
						$bleh = explode("|", $lmatch[1]);
						
						$external_link = array(
							'type' => $this->compact_to_slug("rotten_tomatoes"),
							'attributes' => ltrim(trim(preg_replace("#id\s*=\s*#si", "", array_shift($bleh))), " 0"),
						);
					break;
					
					// {rotten tomatoes|xxx}
					case preg_match("#\{\{\s*rotten(?:\-| )tomatoes\s*\|\s*([^\}]+?)\s*\}\}#si", $line_value, $lmatch):
						$bleh = explode("|", $lmatch[1]);
						
						$external_link = array(
							'type' => $this->compact_to_slug("rotten_tomatoes"),
							'attributes' => ltrim(trim(preg_replace("#id\s*=\s*#si", "", array_shift($bleh))), " 0"),
						);
					break;
					
					// {OL [book|author|work]|xxx[|yyy]}
					case preg_match("#\{\{\s*OL(?:[^\|]*)?\|\s*([^\}]+?)\}\}#si", $line_value, $lmatch):
						$bleh = explode("|", $lmatch[1]);
						
						$external_link = array(
							'type' => $this->compact_to_slug( "open library" ),
							'attributes' => trim(strtolower(preg_replace("#\s*id\s*=\s*#si", "", array_shift($bleh)))),
						);
					break;
					
					// {mtv ...}
					case preg_match("#\{\{\s*mtv\s*([^\|]+?)\|\s*([A-Za-z0-9\-\_]+?)(?:\s*?\|(?:[^\}]+?))?\s*\}\}#si", $line_value, $lmatch):
						$external_link = array(
							'type' => $this->compact_to_slug("mtv"),
							'attributes' => trim(strtolower($lmatch[1])) .":". trim(preg_replace("#\s*id\s*=\s*#si", "", $lmatch[2])),
						);
					break;
					
					// {amg|[game|movie]|xxx[|yyy]}
					case preg_match("#\{\{\s*AMG\s*\|\s*(game|movie)\s*\|\s*([^\}]+?)\}\}#si", $line_value, $lmatch):
						$external_link = array(
							'type' => $this->compact_to_slug( "amg" ),
							'attributes' => trim($lmatch[1], " |") .":". trim(preg_replace("#\s*id\s*=\s*#si", "", $lmatch[2])),
						);
					break;
					
					// {amg [name|game|movie]|xxx[|yyy]}
					case preg_match("#\{\{\s*(?:AMG|AllRovi)(?:\/|\s)*([A-Za-z]+?)\s*\|\s*([^\}]+?)\}\}#si", $line_value, $lmatch):
						$bleh = explode("|", $lmatch[2]);
						
						$external_link = array(
							'type' => $this->compact_to_slug( "amg" ),
							'attributes' => trim(strtolower($lmatch[1])) .":". trim(preg_replace("#\s*id\s*=\s*#si", "", array_shift($bleh))),
						);
					break;
					
					// {allmovie xxx|yyy[|...]}
					case preg_match("#\{{2}\s*allmovie\s+([A-Za-z0-9]+)\s*\|\s*([0-9]+)(?:.*)\}{2}#si", $line_value, $lmatch):
						$kind = trim(strtolower($lmatch[1]), " |");
						$id = trim($lmatch[2], " |}");
						
						if($kind === "title") {
							$kind = "movie";
						}
						
						if(empty($kind)) {
							break;
						}
						
						$external_link = array(
							'type' => $this->compact_to_slug( "amg" ),
							'attributes' => ltrim($kind .":". $id, ":"),
						);
					break;
					
					// {allmusic[|class_name]|xxx[|...]}
					case preg_match("#\{\{\s*allmusic\s*\|\s*([^\}]+?)\}\}#si", $line_value, $lmatch):
						$bits = preg_split("#\s*\|(\s*[A-Za-z0-9\_]+\s*=)?\s*#si", $lmatch[1]);
						$allmusic_id = strtolower(trim(preg_replace("#\s*(id|class)\s*=\s*#si", "", array_shift($bits))));
						
						if(in_array($allmusic_id, array("artist", "album", "song", "work", "explore"))) {
							$bleh = explode("/", array_shift($bits));
							$allmusic_id .= ":". strtolower(trim(preg_replace("#\s*(id|class)\s*=\s*#si", "", array_shift($bleh))));
						}
						
						$allmusic_id = preg_replace("#([A-Za-z0-9\-\_]+?)\-([a-z]{1,3}[0-9]{1,})$#si", "\\2", $allmusic_id);
						
						$external_link = array(
							'type' => $this->compact_to_slug( "amg" ),
							'attributes' => "music:". trim($allmusic_id),
						);
					break;
					
					
					// {imcdb|xxx}
					case preg_match("#\{\{\s*(imcdb|bcdb)\s*([^\|]+?)\|\s*(?:id\s*=\s*)?([0-9]+?)\s*\}\}#si", $line_value, $lmatch):
						$external_link = array(
							'type' => $this->compact_to_slug($lmatch[1]),
							'attributes' => (!empty($lmatch[2])?trim($lmatch[2]) .":":"" ) . trim($lmatch[3]),
						);
					break;
					
					// {tv.com|the-fast-and-the-furious-tokyo-drift|The Fast and the Furious: Tokyo Drift}
					case preg_match("#\{{2}\s*(tv\.com)\s*\|\s*([^\}]+?)\}{2}#si", $line_value, $lmatch):
						$bleh = explode("|", $lmatch[2]);
						
						$external_link = array(
							'type' => $this->compact_to_slug($lmatch[1]),
							'attributes' => trim(preg_replace("#\s*id\s*=\s*#si", "", array_shift($bleh))),
						);
					break;
					
					// {people.com}
					case preg_match("#\{{2}\s*people\.com\s*\}{2}#si", $line_value, $lmatch):
						$external_link = array(
							'type' => "people",
							'attributes' => str_replace(" ", "_", strtolower($this->title)),
						);
					break;
					
					// {google+|...}
					case preg_match("#\{{2}\s*(google\+)\s*\|([0-9]+?)(?:\s*?\|(?:[^\}]+?))?\s*\}{2}#si", $line_value, $lmatch):
						$external_link = array(
							'type' => $this->compact_to_slug( str_ireplace("google+", "google_plus", $lmatch[1]) ),
							'attributes' => trim($lmatch[2], " |"),
						);
					break;
					
					// {youtube|...}
					case preg_match("#\{\{\s*YouTube\s*\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
						if(empty($lmatch[1])) {
							break;
						}
						
						$tmp = array(
							'type' => $this->compact_to_slug( "youtube" ),
							'attributes' => "",
						);
						
						if(preg_match("#(user|u|channel|showid|sid|show|s|id)\s*=\s*([^\|]+)\s*\|?#si", $lmatch[1], $idmatch)) {
							switch(strtolower(trim($idmatch[1]))) {
								case 'user':
								case 'u':
								case 'channel':
									$tmp['attributes'] = "user:";
								break;
								
								case 'showid':
								case 'sid':
									$tmp['attributes'] = "show_id:";
								break;
								
								case 'show':
								case 's':
									$tmp['attributes'] = "show:";
								break;
								
								case 'id':
									$tmp['attributes'] = "video:";
								break;
							}
							
							$tmp['attributes'] .= trim($idmatch[2], " }");
						}
						
						if(empty($tmp['attributes'])) {
							break;
						}
						
						$external_link = $tmp;
						
						unset($tmp, $tmp_attrs);
					break;
					
					
					case preg_match("#\{\{\s*(ibdb|isfdb|tcmdb|discogs|ymovies|musicbrainz|metacritic|mojo|godtube|fuzzymemories|tv\.com|TV Guide|Ustream|Video|YahooTV|YouTube|Screenonline|Citwf|bbc|bcbd|imcdb|iafd|afdb|bgafd|egafd)\s*([^\|]+?)\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
						$bleh = explode("|", $lmatch[3]);
						
						$external_link = array(
							'type' => $this->compact_to_slug($lmatch[1]),
							'attributes' => (!empty($lmatch[2])?$this->compact_to_slug($lmatch[2]) .":":"") . trim(preg_replace("#\s*(movie|artist|id)\s*=\s*#si", "", array_shift($bleh))),
						);
					break;
					
					// {[A-Za-z0-9]|...}
					case preg_match("#\{\{\s*([A-Za-z0-9\-\_]+?)\s*\|([^\}\}]+?)\}\}#si", $line_value, $lmatch):
						$bleh = explode("|", $lmatch[2]);
						
						$external_link = array(
							'type' => $this->compact_to_slug($lmatch[1]),
							'attributes' => trim(preg_replace("#\s*(movie|artist|id)\s*=\s*#si", "", array_shift($bleh))),
						);
					break;
					
					default:
						$external_link = array(
							'type'			=> ":nil",
							'attributes'	=> $line_value,
						);
						
						// ............... eventually ignore any malformed text ............... //
				}
				
				if(!empty($external_link['type'])) {
					$_el_type = $external_link['type'];
					$_el_value = $external_link['attributes'];
					
					$_external_links[] = array(
						'type' => $_el_type,
						'value' => $_el_value,
					);
				}
				
			}
			
			if(empty($_external_links)) {
				return false;
			}
			
			
			
			$group__external_link_keys = array(":url", ":nil");
			$ignore__external_link_keys = array("waybackdate", "dead link");
			
			foreach($_external_links as $_el) {
				if(in_array($_el['type'], $ignore__external_link_keys)) {
					continue;
				}
				
				if(in_array($_el['type'], $group__external_link_keys)) {
					$external_links[ $_el['type'] ][] = $_el['value'];
				} else {
					$external_links[ $_el['type'] ] = $_el['value'];
				}
			}
			
			
			// parse specific urls
			if(!empty($external_links[':url'])) {
				foreach($external_links[':url'] as $url_key => $url_value) {
					switch(true) {
						// Official website
						case preg_match("#\s?official (?:web)?site$#si", $url_value['text']):
							if(!isset($external_links[ $this->compact_to_slug("official website") ])) {
								$external_links[ $this->compact_to_slug("official website") ] = trim($url_value['url'], " |)(");
								unset($external_links[':url'][$url_key]);
							}
						break;
						
						// Find a grave
						case preg_match("#https?\:\/\/(?:www\.)?findagrave\.com\/(?:.+?)id=([0-9]+)&?#si", $url_value['url'], $umatch):
							if(!isset($external_links[ $this->compact_to_slug("find a grave") ])) {
								$external_links[ $this->compact_to_slug("find a grave") ] = $umatch[1];
							}
							
							unset($external_links[':url'][$url_key]);
						break;
						
						case preg_match("#https?\:\/\/(?:www\.)?timesonline\.co\.uk\/tol\/comment\/obituaries\/article([0-9]+)\.ece#si", $url_value['url'], $umatch):
							if(!isset($external_links[ $this->compact_to_slug("times obituary") ])) {
								$external_links[ $this->compact_to_slug("times obituary") ] = $umatch[1];
							}
							
							unset($external_links[':url'][$url_key]);
						break;
						
						// NYTimes Movies
						case preg_match("#https?\:\/\/movies\.nytimes\.com\/(person|movie)\/([0-9]+)\/#si", $url_value['url'], $umatch):
							if(!isset($external_links[ $this->compact_to_slug("nytimes movies") ])) {
								$external_links[ $this->compact_to_slug("nytimes movies") ] = $umatch[1] .":". $umatch[2];
							}
							
							unset($external_links[':url'][$url_key]);
						break;
						
						// Easy social profile urls
						case preg_match("#^https?\:\/\/(?:www\.)?(soundcloud\.com|myspace\.com|facebook\.com|twitter\.com|youtube\.com|vault\.fbi\.gov)\/([A-Za-z0-9\-\_]+)\/?$#si", trim($url_value['url']), $umatch):
							if(!isset($external_links[ $this->compact_to_slug( $umatch[1] ) ])) {
								$external_links[ $this->compact_to_slug( $umatch[1] ) ] = $umatch[2];
							}
							
							unset($external_links[':url'][$url_key]);
						break;
						
						// Forbes
						case preg_match("#https?\:\/\/(?:www\.)?forbes.com\/(profile|companies)\/([^\/]+)\/?#si", $url_value['url'], $umatch):
							if(!isset($external_links[ $this->compact_to_slug("discogs") ])) {
								$external_links[ $this->compact_to_slug("discogs") ] = str_replace("companies", "company", trim(strtolower($umatch[1]), " /]|")) .":". trim($umatch[2], " /|");
							}
							
							unset($external_links[':url'][$url_key]);
						break;
						
						// Discogs
						case preg_match("#https?\:\/\/(?:www\.)?discogs.com\/(user|artist|label)\/([^\/]+)\/?#si", $url_value['url'], $umatch):
							if(!isset($external_links[ $this->compact_to_slug("discogs") ])) {
								$external_links[ $this->compact_to_slug("discogs") ] = trim(strtolower($umatch[1]), " /]|") .":". trim($umatch[2], " /|");
							}
							
							unset($external_links[':url'][$url_key]);
						break;
						
						// Discogs - Master/Release
						case preg_match("#https?\:\/\/(?:www\.)?discogs.com\/([^\/]+)\/(master|release)\/?#si", $url_value['url'], $umatch):
							if(!isset($external_links[ $this->compact_to_slug("discogs") ])) {
								$external_links[ $this->compact_to_slug("discogs") ] = trim(strtolower($umatch[2]), " /]|") .":". trim($umatch[1], " /|");
							}
							
							unset($external_links[':url'][$url_key]);
						break;
					}
				}
				
				if(empty($external_links[':url'])) {
					unset($external_links[':url']);
				}
			}
			
			
			ksort($external_links);
			
			
			if(!empty($external_links)) {
				$this->cargo['external_links'] = $external_links;
			}
		}
		
		private function categories() {
			$categories = array();
			
			if(preg_match_all("#\[\[\s*Category\s*\:([^\]\]]+?)\]\]#si", $this->text, $matches)) {
				foreach($matches[1] as $nil => $mvalue) {
					$bleh = explode("|", $mvalue);
					$category = trim(array_shift($bleh));
					$bleh = null;
					
					$categories[ md5($category) ] = $category;
				}
			}
			
			if(!empty($categories)) {
				$this->cargo['categories'] = $categories;
			}
		}
		
		private function portals() {
			$portals = array();
			
			if(preg_match_all("#\{{2}\s*Portal(|\s*box|\s*bar|\-inline)\s*\|(.+?)\}{2}#si", $this->text, $matches)) {
				foreach(array_keys($matches[0]) as $key) {
					$_portals = explode("|", $matches[2][$key]);
					
					if(empty($_portals)) {
						continue;
					}
					
					if($matches[1][$key] == "-inline") {
						// inline portal template
						if(count($_portals) > 1) {
							// size= must be here
							foreach($_portals as $_portal) {
								if(!preg_match("#size\s*=#si", $_portal)) {
									$_portal = trim(str_replace(" ", "_", $_portal), " _|");
									
									$portals[ md5(strtolower($_portal)) ] = $_portal;
									
									break;
								}
							}
						}
					} else {
						// General portal template
						foreach($_portals as $_portal) {
							$_portal = trim(str_replace("_", " ", $_portal), " _|");
							
							$portals[ md5(strtolower($_portal)) ] = $_portal;
						}
					}
				}
			}
			
			if(!empty($portals)) {
				$this->cargo['portals'] = $portals;
			}
		}
		
		private function foreign_wikis() {
			$foreign_wikis = array();
			
			preg_match_all("#\[\[\s*". $this->foreign_wiki_regex ."\s*\:([^\]]+?)\]\]#si", $this->text, $matches);
			
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
			
			if(!preg_match("#^\{{2}\s*(cite|citation|ref\s*\||ref label|reflist|note\s*\||note label)(.+?)\}{2}$#si", $match[0], $cite_match)) {
				//$match[0] = preg_replace("#\{{2}\s*?(Cite|Citation)(.+?)?\}{2}#si", "", $match[0]);
				
				return $match[0];
			}
			
			if(preg_match("#\{{2}\s*Citation needed#si", $match[0])) {
				return "";
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
			} elseif(substr($cite_match_tag_type, 0, 4) == "cite") {
				$cite_match_type = $this->compact_to_slug(array_shift($cite_match_bits));
			} else {
				return "";
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
		
		private function attachments() {
			$attachments = array();
			
			// [[File:]] [[Image:]]
			if(preg_match_all("/\[{2}((?>[^\[\]]+)|(?R))*\]{2}/x", $this->text, $matches)) {
				foreach($matches[0] as $mkey => $mraw) {
					if(!preg_match("#\[{2}\s*(File|Image)\:(.+?)\]{2}$#si", $mraw, $attachment_typer)) {
						continue;
					}
					
					$attachment_typer[2] = $this->insert_jungledb_helpers($attachment_typer[2]);
					
					$attachment_details = explode("|", $attachment_typer[2], 2);
					
					$attachment = array(
						':raw' => $mraw,
						'type' => strtolower(trim($attachment_typer[1], " |")),
						'filename' => trim($attachment_details[0], " |"),
					);
					
					//if(!empty($attachment_details[1])) {
					//	$attr_details_attrs = explode("|", $attachment_details[1]);
					//	
					//	$attachment['attributes'] = array();
					//	
					//	foreach($attr_details_attrs as $adas_bit) {
					//		$adas_bit = trim($adas_bit);
					//		
					//		if(strlen($adas_bit) < 1) {
					//			continue;
					//		}
					//		
					//		if(preg_match("#^[0-9]*?x?[0-9]+px$#si", $adas_bit) || preg_match("#^upright(=(.*)?)?$#si", $adas_bit)) {
					//			$attachment['attributes']['size'] = $adas_bit;
					//		} elseif(preg_match("#^(left|right|center|none)$#si", $adas_bit)) {
					//			$attachment['attributes']['location'] = $adas_bit;
					//		} elseif(preg_match("#^(thumb|thumbnail|frame|framed|frameless)$#si", $adas_bit)) {
					//			$attachment['attributes']['type'] = $adas_bit;
					//		} elseif(preg_match("#^(baseline|middle|sub|super|text\-top|text\-bottom|top|bottom)$#si", $adas_bit)) {
					//			$attachment['attributes']['alignment'] = $adas_bit;
					//		} elseif(preg_match("#^alt\s*=(.+?)$#si", $adas_bit, $aba_match)) {
					//			$attachment['attributes']['alt'] = $aba_match[1];
					//		} else {
					//			$attachment['attributes'][] = $adas_bit;
					//		}
					//		
					//		if(isset($attachment['attributes'][0]) && !isset($attachment['attributes'][1])) {
					//			$attachment['attributes']['caption'] = $attachment['attributes'][0];
					//			unset($attachment['attributes'][0]);
					//		}
					//	}
					//}
					
					$attachments[] = $attachment;
					
					unset($attachment);
				}
			}
			
			//// {{listen}}
			//if(preg_match_all("#\{{2}\s*listen\s*\|(.+)\}{2}#si", $this->text, $matches)) {
			//	foreach(array_keys($matches[0]) as $key) {
			//		$listen_attrs = trim($matches[1][ $key ], " |");
			//		$listen_attrs = $this->insert_jungledb_helpers($listen_attrs);
			//		
			//		$attrs = explode("|", $listen_attrs);
			//		
			//		if(!empty($attrs)) {
			//			$lattachment = array(
			//				'type'		=> "sound",
			//			);
			//			
			//			foreach($attrs as $attr) {
			//				
			//			}
			//		}
			//	}
			//}
			
			if(!empty($attachments)) {
				$this->cargo['wikipedia_meta']['attachments'] = $attachments;
			}
		}
		
		private function pack_cargo() {
			//$this->cargo['raw_text'] = $this->raw_text;
			//$this->cargo['text'] = trim( $this->clean_jungledb_helpers($this->text) );
			
			//$this->cargo = $this->clean_jungledb_helpers($this->cargo);
			
			//unset($this->cargo['wikipedia_meta']);
			
			JungleDB_Utils::trim_it($this->cargo);
		}
		
		private function initial_clean() {
			// strip out the crap we don't need
			
			$this->cargo['title'] = trim($this->title);
			
			if(preg_match("#\{{2}\s*?DISPLAYTITLE\s*?\:\s*?([^\}]+?)\s*?\}{2}#si", $this->text, $title_match)) {
				$this->cargo['title'] = trim($title_match[1], "' ");
			}
			
			$this->cargo['title'] = trim($this->clean_wiki_text($this->cargo['title']));
			
			$this->cargo['title_hash'] = md5($this->cargo['title']);
			
			//$this->text = html_entity_decode($this->text);
			
			
			// ASCII special language characters
			$special_chars = array(
				'&Ccedil;' => "Ç", '&ccedil;' => "ç", '&Euml;' => "Ë", '&euml;' => "ë", '&#262;' => "Ć", '&#263;' => "ć", '&#268;' => "Č", '&#269;' => "č", '&#272;' => "Đ", '&#273;' => "đ", '&#352;' => "Š", '&#353;' => "š", '&#381;' => "Ž", '&#382;' => "ž", '&Agrave;' => "À", '&agrave;' => "à", '&Ccedil;' => "Ç", '&ccedil;' => "ç", '&Egrave;' => "È", '&egrave;' => "è", '&Eacute;' => "É", '&eacute;' => "é", '&Iacute;' => "Í", '&iacute;' => "í", '&Iuml;' => "Ï", '&iuml;' => "ï", '&Ograve;' => "Ò", '&ograve;' => "ò", '&Oacute;' => "Ó", '&oacute;' => "ó", '&Uacute;' => "Ú", '&uacute;' => "ú", '&Uuml;' => "Ü", '&uuml;' => "ü", '&middot;' => "·", '&#262;' => "Ć", '&#263;' => "ć", '&#268;' => "Č", '&#269;' => "č", '&#272;' => "Đ", '&#273;' => "đ", '&#352;' => "Š", '&#353;' => "š", '&#381;' => "Ž", '&#382;' => "ž", '&Aacute;' => "Á", '&aacute;' => "á", '&#268;' => "Č", '&#269;' => "č", '&#270;' => "Ď", '&#271;' => "ď", '&Eacute;' => "É", '&eacute;' => "é", '&#282;' => "Ě", '&#283;' => "ě", '&Iacute;' => "Í", '&iacute;' => "í", '&#327;' => "Ň", '&#328;' => "ň",
				'&Oacute;' => "Ó", '&oacute;' => "ó", '&#344;' => "Ř", '&#345;' => "ř", '&#352;' => "Š", '&#353;' => "š", '&#356;' => "Ť", '&#357;' => "ť", '&Uacute;' => "Ú", '&uacute;' => "ú", '&#366;' => "Ů", '&#367;' => "ů", '&Yacute;' => "Ý", '&yacute;' => "ý", '&#381;' => "Ž", '&#382;' => "ž", '&AElig;' => "Æ", '&aelig;' => "æ", '&Oslash;' => "Ø", '&oslash;' => "ø", '&Aring;' => "Å", '&aring;' => "å", '&Eacute;' => "É", '&eacute;' => "é", '&Euml;' => "Ë", '&euml;' => "ë", '&Oacute;' => "Ó", '&oacute;' => "ó", '&#264;' => "Ĉ", '&#265;' => "ĉ", '&#284;' => "Ĝ", '&#285;' => "ĝ", '&#292;' => "Ĥ", '&#293;' => "ĥ", '&#308;' => "Ĵ", '&#309;' => "ĵ", '&#348;' => "Ŝ", '&#349;' => "ŝ", '&#364;' => "Ŭ", '&#365;' => "ŭ", '&Auml;' => "Ä", '&auml;' => "ä", '&Ouml;' => "Ö", '&ouml;' => "ö", '&Otilde;' => "Õ", '&otilde;' => "õ", '&Uuml;' => "Ü", '&uuml;' => "ü", '&Aacute;' => "Á", '&aacute;' => "á", '&ETH;' => "Ð", '&eth;' => "ð", '&Iacute;' => "Í", '&iacute;' => "í", '&Oacute;' => "Ó", '&oacute;' => "ó", '&Uacute;' => "Ú", '&uacute;' => "ú", '&Yacute;' => "Ý", '&yacute;' => "ý", '&AElig;' => "Æ", '&aelig;' => "æ", '&Oslash;' => "Ø", '&oslash;' => "ø", '&Auml;' => "Ä", '&auml;' => "ä",
				'&Ouml;' => "Ö", '&ouml;' => "ö", '&Agrave;' => "À", '&agrave;' => "à", '&Acirc;' => "Â", '&acirc;' => "â", '&Ccedil;' => "Ç", '&ccedil;' => "ç", '&Egrave;' => "È", '&egrave;' => "è", '&Eacute;' => "É", '&eacute;' => "é", '&Ecirc;' => "Ê", '&ecirc;' => "ê", '&Euml;' => "Ë", '&euml;' => "ë", '&Icirc;' => "Î", '&icirc;' => "î", '&Iuml;' => "Ï", '&iuml;' => "ï", '&Ocirc;' => "Ô", '&ocirc;' => "ô", '&OElig;' => "Œ", '&oelig;' => "œ", '&Ugrave;' => "Ù", '&ugrave;' => "ù", '&Ucirc;' => "Û", '&ucirc;' => "û", '&Uuml;' => "Ü", '&uuml;' => "ü", '&#376;' => "Ÿ", '&yuml;' => "ÿ", '&Auml;' => "Ä", '&auml;' => "ä", '&Ouml;' => "Ö", '&ouml;' => "ö", '&Uuml;' => "Ü", '&uuml;' => "ü", '&szlig;' => "ß", '&Aacute;' => "Á", '&aacute;' => "á", '&Eacute;' => "É", '&eacute;' => "é", '&Iacute;' => "Í", '&iacute;' => "í", '&Oacute;' => "Ó", '&oacute;' => "ó", '&#336;' => "Ő", '&#337;' => "ő", '&Uacute;' => "Ú", '&uacute;' => "ú", '&Uuml;' => "Ü", '&uuml;' => "ü", '&#368;' => "Ű", '&#369;' => "ű", '&Aacute;' => "Á", '&aacute;' => "á", '&ETH;' => "Ð",
				'&eth;' => "ð", '&Eacute;' => "É", '&eacute;' => "é", '&Iacute;' => "Í", '&iacute;' => "í", '&Oacute;' => "Ó", '&oacute;' => "ó", '&Uacute;' => "Ú", '&uacute;' => "ú", '&Yacute;' => "Ý", '&yacute;' => "ý", '&THORN;' => "Þ", '&thorn;' => "þ", '&AElig;' => "Æ", '&aelig;' => "æ", '&Ouml;' => "Ö", '&uml;' => "ö", '&Aacute;' => "Á", '&aacute;' => "á", '&Eacute;' => "É", '&eacute;' => "é", '&Iacute;' => "Í", '&iacute;' => "í", '&Oacute;' => "Ó", '&oacute;' => "ó", '&Uacute;' => "Ú", '&uacute;' => "ú", '&Agrave;' => "À", '&agrave;' => "à", '&Acirc;' => "Â", '&acirc;' => "â", '&Egrave;' => "È", '&egrave;' => "è", '&Eacute;' => "É", '&eacute;' => "é", '&Ecirc;' => "Ê", '&ecirc;' => "ê", '&Igrave;' => "Ì", '&igrave;' => "ì", '&Iacute;' => "Í", '&iacute;' => "í", '&Icirc;' => "Î", '&icirc;' => "î", '&Iuml;' => "Ï", '&iuml;' => "ï", '&Ograve;' => "Ò", '&ograve;' => "ò", '&Ocirc;' => "Ô", '&ocirc;' => "ô", '&Ugrave;' => "Ù", '&ugrave;' => "ù", '&Ucirc;' => "Û", '&ucirc;' => "û", '&#256;' => "Ā", '&#257;' => "ā", '&#268;' => "Č", '&#269;' => "č", '&#274' => "Ē", '&#275;' => "ē", '&#290;' => "Ģ", '&#291;' => "ģ", '&#298;' => "Ī", '&#299;' => "ī", '&#310;' => "Ķ", '&#311;' => "ķ", '&#315;' => "Ļ", '&#316;' => "ļ", '&#325;' => "Ņ", '&#326;' => "ņ", '&#342;' => "Ŗ",
				'&#343;' => "ŗ", '&#352;' => "Š", '&#353;' => "š", '&#362;' => "Ū", '&#363;' => "ū", '&#381;' => "Ž", '&#382;' => "ž", '&AElig;' => "Æ", '&aelig;' => "æ", '&Oslash;' => "Ø", '&oslash;' => "ø", '&Aring;' => "Å", '&aring;' => "å", '&#260;' => "Ą", '&#261;' => "ą", '&#262;' => "Ć", '&#263;' => "ć", '&#280;' => "Ę", '&#281;' => "ę", '&#321;' => "Ł", '&#322;' => "ł", '&#323;' => "Ń", '&#324;' => "ń", '&Oacute;' => "Ó", '&oacute;' => "ó", '&#346;' => "Ś", '&#347;' => "ś", '&#377;' => "Ź", '&#378;' => "ź", '&#379;' => "Ż", '&#380;' => "ż", '&Agrave;' => "À", '&agrave;' => "à", '&Aacute;' => "Á", '&aacute;' => "á", '&Acirc;' => "Â", '&acirc;' => "â", '&Atilde;' => "Ã", '&atilde;' => "ã", '&Ccedil;' => "Ç", '&ccedil;' => "ç", '&Egrave;' => "È", '&egrave;' => "è", '&Eacute;' => "É", '&eacute;' => "é", '&Ecirc;' => "Ê", '&ecirc;' => "ê", '&Igrave;' => "Ì", '&igrave;' => "ì", '&Iacute;' => "Í", '&iacute;' => "í", '&Iuml;' => "Ï", '&iuml;' => "ï", '&Ograve;' => "Ò", '&ograve;' => "ò", '&Oacute;' => "Ó", '&oacute;' => "ó", '&Otilde;' => "Õ", '&otilde;' => "õ", '&Ugrave;' => "Ù", '&ugrave;' => "ù", '&Uacute;' => "Ú", '&uacute;' => "ú", '&Uuml;' => "Ü", '&uuml;' => "ü", '&ordf;' => "ª", '&ordm;' => "º", '&#258;' => "Ă", '&#259;' => "ă", '&Acirc;' => "Â", '&acirc;' => "â", '&Icirc;' => "Î", '&icirc;' => "î", '&#350;' => "Ş", '&#351;' => "ş", '&#354;' => "Ţ", '&#355;' => "ţ", '&Agrave;' => "À", '&agrave;' => "à",
				'&Egrave;' => "È", '&egrave;' => "è", '&Eacute;' => "É", '&eacute;' => "é", '&Igrave;' => "Ì", '&igrave;' => "ì", '&Ograve;' => "Ò", '&ograve;' => "ò", '&Oacute;' => "Ó", '&oacute;' => "ó", '&Ugrave;' => "Ù", '&ugrave;' => "ù", '&Aacute;' => "Á", '&aacute;' => "á", '&Auml;' => "Ä", '&auml;' => "ä", '&#268;' => "Č", '&#269;' => "č", '&#270;' => "Ď", '&#271;' => "ď", '&Eacute;' => "É", '&eacute;' => "é", '&#313;' => "Ĺ", '&#314;' => "ĺ", '&#317;' => "Ľ", '&#318;' => "ľ", '&#327;' => "Ň", '&#328;' => "ň", '&Oacute;' => "Ó", '&oacute;' => "ó", '&Ocirc;' => "Ô", '&ocirc;' => "ô", '&#340;' => "Ŕ", '&#341;' => "ŕ", '&#352;' => "Š", '&#353;' => "š", '&#356;' => "Ť", '&#357;' => "ť", '&Uacute;' => "Ú", '&uacute;' => "ú", '&Yacute;' => "Ý", '&yacute;' => "ý", '&#381;' => "Ž", '&#382;' => "ž", '&#268;' => "Č", '&#269;' => "č", '&#352;' => "Š", '&#353;' => "š", '&#381;' => "Ž", '&#382;' => "ž", '&Aacute;' => "Á", '&aacute;' => "á", '&Eacute;' => "É", '&eacute;' => "é", '&Iacute;' => "Í", '&iacute;' => "í", '&Oacute;' => "Ó", '&oacute;' => "ó", '&Ntilde;' => "Ñ", '&ntilde;' => "ñ", '&Uacute;' => "Ú", '&uacute;' => "ú", '&Uuml;' => "Ü", '&uuml;' => "ü", '&iexcl;' => "¡", '&ordf;' => "ª", '&iquest;' => "¿", '&ordm;' => "º", '&Aring;' => "Å", '&aring;' => "å", '&Auml;' => "Ä", '&auml;' => "ä", '&Ouml;' => "Ö", '&ouml;' => "ö", '&Ccedil;' => "Ç", '&ccedil;' => "ç", '&#286;' => "Ğ", '&#287;' => "ğ", '&#304;' => "İ", '&#305;' => "ı", '&Ouml;' => "Ö", '&ouml;' => "ö", '&#350;' => "Ş", '&#351;' => "ş", '&Uuml;' => "Ü", '&uuml;' => "ü", '&euro;' => "€", '&pound;' => "£", '&laquo;' => "«", '&raquo;' => "»", '&bull;' => "•", '&dagger;' => "†", '&copy;' => "©", '&reg;' => "®", '&deg;' => "°", '&micro;' => "µ", '&middot;' => "·", '&#8470;' => "№",
			);
			
			$special_chars = array_flip($special_chars);
			$special_chars = array_unique($special_chars);
			$special_chars = array_flip($special_chars);
			
			$this->text = strtr($this->text, $special_chars);
			
			//$this->text = str_replace(array("’", "‘"), "'", $this->text);
			//$this->text = iconv(mb_detect_encoding($this->text), 'US-ASCII//TRANSLIT', $this->text);
			$this->text = str_replace("\r", "", $this->text);
			
			// specific template replacements
			$template_names_from_to = array(
				'Supercbbox'	=> "Infobox comic book title",
				'Superherobox'	=> "Infobox comics character",
			);
			
			foreach($template_names_from_to as $tpl_from => $tpl_to) {
				$this->text = preg_replace("#\{{2}\s*". preg_quote($tpl_from, "#") ."#si", "{{". $tpl_to, $this->text);
			}
			
			$this->text = preg_replace("#\{{2}\s*(?:nts|du|birth\-date and age|nowrap|small|big|huge|resize|smaller|larger|large|Unicode|ISBNT|Bk|sup|sub|subsub|ssub|su|sup sub|e)\s*\|\s*([^\}]+)\}{2}#si", "\\1", $this->text);
			
			$this->text = preg_replace_callback("#\{{2}\s*(?:voiced by2|voiced by|anime voices)\s*\|([^\}]+?)\}{2}#si", function($match) {
				$bits = explode("|", $match[1]);
				
				if(!empty($bits)) {
					$return = "Voiced by ";
					
					foreach(array_keys($bits) as $bit_key) {
						if(preg_match("#^\s*type\s*\=#si", $bits[$bit_key])) {
							unset($bits[$bit_key]);
						}
					}
					
					return $return . implode(" and ", $bits);
				}
			}, $this->text);
			
			$this->text = preg_replace_callback("#\{{2}\s*(?:Nihongo|ISBNT)\s*\|(.+?)\}{2}#si", function($match) {
				$split = explode("|", $match[1]);
				
				if(count($split) > 1) {
					return array_shift($split) ." (". trim(array_shift($split)) .")";
				}
				
				return array_shift($split);
			}, $this->text);
			
			$this->text = preg_replace("#<!\-\-(?:.+?)\-\->#si", "", $this->text);   // get rid of unneeded Editor comments
			$this->text = preg_replace("#<span(?:[^>]*)>(.+?)<\/span>#si", "\\1", $this->text);   // get rid of unneeded Editor comments
			$this->text = preg_replace("#". preg_quote("&", "#") ."(\#x2012|\#x8210|\#x2013|\#x8211|\#x2014|\#x8212|\#x2015|\#x8213|\#x2053|\#x8275|ndash|mdash)". preg_quote(";", "#") ."#si", "-", $this->text);
			$this->text = preg_replace("#<\s*?(/)?\s*?(u|b|ins|del|s|i|small|ref)(\s[^>]*)?>#si", "", $this->text);   // get rid of specific unneeded tags
			//$this->text = preg_replace("#". preg_quote("&nbsp;", "#") ."#si", " ", $this->text);   // get rid of ascii spaces
			$this->text = str_replace(array("–", "—", "‒", "―"), "-", $this->text);   // dashes
			$this->text = preg_replace("#<\s*(noinclude|onlyinclude|includeonly)(?:[^>]*?)>(.+?)<\s*/\s*\\1(?:[^>]*?)>#si", "\\2", $this->text);   // get rid of transclusion tags (keep internal contents)
			$this->text = preg_replace("#<\s*(nowiki)(?:[^>]*?)>(.*?)<\s*/\s*\\1(?:[^>]*?)>#si", "\n\n", $this->text);   // get rid of unneeded Editor comments
			
			// [disabled] remove wiki link title replacers and sub-article anchors from wiki link elements
			//$this->text = preg_replace("#(?:\[{2})([^\:\]]+?)(\||\#)(.*?)(?:\]{2})#i", "[[\\1]]", $this->text);
			
			// annoying in-link links
			$this->text = preg_replace_callback("/\[{2}((?>[^\[\]]+)|(?R))*\]{2}/x", function($match) {
				return "[[". preg_replace("#(\[|\]){2}#si", "", $match[0]) ."]]";
			}, $this->text);
			
			// remove very specific wiki syntax template calls
			
			
			// magic word templates
			// http://en.wikipedia.org/wiki/Category:Magic_word_templates
			$this->text = preg_replace("#\{{2}\s*(?:BASEPAGENAME|Currentmonthname|Currentyear|DEFAULTSORT|DISPLAYTITLE|EDITSPERUSER|FULLPAGENAME|Namespace|New section|Newsection|No gallery|PAGENAME|PAGESINCATEGORY|PAGESINCAT|NUMBERINGROUP|NUMINGROUP|PAGESINNS|PAGESINNAMESPACE|PAGESIZE|PROTECTIONLEVEL|DISPLAYTITLE|DEFAULTSORT|DEFAULTSORTKEY|DEFAULTCATEGORYSORT)(?:(\:|\|)[^\}]+)?\s*\}{2}#si", "", $this->text);
			$this->text = preg_replace("#(?:__NOTOC__|__FORCETOC__|__TOC__|__NOEDITSECTION__|__NEWSECTIONLINK__|__NONEWSECTIONLINK__|__NOGALLERY__|__HIDDENCAT__|__NOCONTENTCONVERT__|__NOCC__|__NOTITLECONVERT__|__NOTC__|__START__|__END__|__INDEX__|__NOINDEX__|__STATICREDIRECT__)#si", "", $this->text);
			$this->text = preg_replace("#\{{2}\s*(?:NAMESPACEE|SUBJECTSPACEE|TALKSPACEE|SUBJECTSPACE|TALKSPACE|FULLPAGENAMEE|PAGENAMEE|BASEPAGENAMEE|SUBPAGENAMEE|SUBJECTPAGENAMEE|ARTICLEPAGENAMEE|TALKPAGENAMEE|TALKPAGENAME|FULLPAGENAME|PAGENAME|BASEPAGENAME|SUBPAGENAME|SUBJECTPAGENAME|ARTICLEPAGENAME|NUMBEROFPAGES|NUMBEROFARTICLES|NUMBEROFFILES|NUMBEROFEDITS|NUMBEROFVIEWS|NUMBEROFUSERS|NUMBEROFADMINS|NUMBEROFACTIVEUSERS|CURRENTWEEK|CURRENTTIMESTAMP|LOCALYEAR|LOCALMONTH|LOCALMONTHNAME|LOCALMONTHNAMEGEN|LOCALMONTHABBREV|LOCALDAY|LOCALDAY2|LOCALDOW|LOCALDAYNAME|LOCALTIME|LOCALHOUR|LOCALWEEK|LOCALTIMESTAMP|SITENAME|SERVER|SERVERNAME|DIRMARK|DIRECTIONMARK|SCRIPTPATH|STYLEPATH|CURRENTVERSION|CONTENTLANGUAGE|CONTENTLANG|PAGEID|REVISIONID|REVISIONDAY|REVISIONDAY2|REVISIONMONTH|REVISIONMONTH1|REVISIONYEAR|REVISIONTIMESTAMP|REVISIONUSER|CURRENTYEAR|CURRENTMONTH|CURRENTMONTHNAME|CURRENTMONTHNAMEGEN|CURRENTMONTHABBREV|CURRENTDAY|CURRENTDAY2|CURRENTDOW|CURRENTDAYNAME|CURRENTTIME|CURRENTHOUR)\s*\}{2}#si", "", $this->text);
			
			// todo: {{canonicalurl:}} {{fullurl:}} {{localurl:}} {{filepath:}} {{urlencode:}}
			// todo: {{padleft:}} & {{padright:}}
			// todo: {{#dateformat:}} & {{#formatdate:}}
			// todo: {{grammar:}} {{plural:}} {{gender:}} {{int:}} {{int:editsectionhint[|...]}}
			// todo: {{#language:}} {{#special(e):}} {{#tag:}}
			// todo: {{Roman|N}} -> MXXIV (where N=1024)
			
			// {{formatnum:xxx}} -> xxx
			$this->text = preg_replace_callback("#\{{2}\s*(?:formatnum|lc|lcfirst|uc|ucfirst)\s*\:(.+?)\}{2}#si", function ($match) {
				return trim($match[1], " |");
			}, $this->text);
			
			// {{convert|value|input[|...]}} -> value input
			$this->text = preg_replace_callback("#\{{2}\s*convert\s*\|([^\|]+)\|([^\|]+)\|(?:[^\}]+)\}{2}#si", function ($match) {
				$match[1] = trim($match[1], " |");
				$match[2] = trim($match[2], " |");
				
				if(strtolower(substr($match[2], 0, 2)) == "sq") {
					$match[2] = substr($match[2], 2) ."^2";
				}
				
				if(is_numeric($match[1])) {
					return number_format($match[1]) ." ". $match[2];
				}
				
				return $match[1] ." ". $match[2];
			}, $this->text);
			
			// {{awards table[|sortable=yes]}} -> {|...
			$this->text = preg_replace_callback("#\{{2}\s*awards table(?:\s*\|\s*sortable)?\s*\}{2}#si", function($match) {
				return "{| class=\" wikitable\" style=\"table-layout: fixed; margin-right:0\"\n|-\n! scope=\"col\" width=\"6%\"  | Year\n! scope=\"col\" width=\"35%\" | Nominated work\n! scope=\"col\" width=\"48%\" | Award\n! scope=\"col\" width=\"11%\" | Result";
			}, $this->text);
			
			// {{end}} -> |}
			$this->text = preg_replace("#\{{2}\s*end\s*\}{2}#si", "|}", $this->text);
			
			
			// {{frac}} -> /
			$this->text = preg_replace_callback("#\{{2}\s*(?:frac|fraction)\s*([^\}]*?)\}{2}#si", function($match) {
				$parts = explode("|", trim($match[1], " |"));
				
				if(count($parts) == 3) {
					return array_shift($parts) ." ". array_shift($parts) ."/". array_shift($parts);
				} elseif(count($parts) == 2) {
					return array_shift($parts) ."/". array_shift($parts);
				} elseif(count($parts) == 1) {
					return "1/". array_shift($parts);
				} else {
					return "/";
				}
			}, $this->text);
			
			// {{ns:x}} -> $namespace[x]
			$this->text = preg_replace_callback("#\{{2}\s*ns\s*\|\s*([0-9\-]+?)\s*\}{2}#si", function($match) {
				switch($match[1]) {
					case "-2": return "Media"; break;
					case "-1": return "Special"; break;
					case "0": return ""; break;
					case "1": return "Talk"; break;
					case "2": return "User"; break;
					case "3": return "User talk"; break;
					case "4": return "Wikipedia"; break;
					case "5": return "Wikipedia talk"; break;
					case "6": return "File"; break;
					case "7": return "File talk"; break;
					case "8": return "MediaWiki"; break;
					case "9": return "MediaWiki talk"; break;
					case "10": return "Template"; break;
					case "11": return "Template talk"; break;
					case "12": return "Help"; break;
					case "13": return "Help talk"; break;
					case "14": return "Category"; break;
					case "15": return "Category talk"; break;
					case "100": return "Portal"; break;
					case "101": return "Portal talk"; break;
					case "108": return "Book"; break;
					case "109": return "Book talk"; break;
					default: return $match[0];
				}
			}, $this->text);
			
			// {{rp[|...]}} -> 
			$this->text = preg_replace("#\{{2}\s*rp\s*(?:\|(.+?))?\}{2}#si", "", $this->text);
			
			// {{color|xxx|yyy}} -> yyy
			$this->text = preg_replace("#\{{2}\s*color\s*\|\s*(?:[^\|]+?)\s*\|\s*([^\}]+?)\s*\}{2}#si", "\\1", $this->text);
			
			// {{nts|xxx}} -> xxx
			$this->text = preg_replace("#\{{2}\s*nts\s*\|\s*([^\|]+?)\s*\}{2}#si", "\\1", $this->text);
			
			// {{nts|xxx|prefix=yyy}} -> yyyxxx
			$this->text = preg_replace("#\{{2}\s*nts\s*\|\s*(?:[^\|]+?)\s*\|\s*prefix\s*=\s*([^\}]+?)\s*\}{2}#si", "\\2\\1", $this->text);
			
			// {{nts|xxx|yyy}} -> xxx
			$this->text = preg_replace("#\{{2}\s*nts\s*\|\s*(?:[^\|]+?)\s*\|\s*([^\}]+?)\s*\}{2}#si", "\\1", $this->text);
			
			
			// {{sort|xxx|yyy}} -> yyy
			$this->text = preg_replace("#\{{2}\s*sort\s*\|\s*(?:[^\|]+?)\s*\|\s*([^\}]+?)\s*\}{2}#si", "\\1", $this->text);
			
			
			// {{sortname|The|Game|nolink=1}} -> The Game
			$this->text = preg_replace("#\{{2}\s*sortname\s*\|\s*([^\|]+?)\s*\|\s*([^\|]+?)\s*\|\s*nolink\s*=\s*1\s*\}{2}#si", "\\1 \\2", $this->text);
			
			// {{sortname|The|Game|dab=singer}} -> [[The Game (singer)|The Game]]
			$this->text = preg_replace("#\{{2}\s*sortname\s*\|\s*([^\|]+?)\s*\|\s*([^\|]+?)\s*\|\s*dab\s*=\s*([^\}]+?)\s*\}{2}#si", "[[\\1 \\2 (\\3)|\\1 \\2]]", $this->text);
			
			// {{sortname|The|Game|The Game (singer)}} -> [[The Game (singer)|The Game]]
			//$this->text = preg_replace("#\{{2}\s*sortname\s*\|\s*([^\|]+?)\s*\|\s*([^\|]+?)\s*\|\s*([^\}]+?)\s*\}{2}#si", "[[\\1 \\2 (\\3)|\\1 \\2]]", $this->text);
			
			// {{sortname|The|Game}} -> The Game
			$this->text = preg_replace("#\{{2}\s*sortname\s*\|\s*([^\|]+?)\s*\|\s*([^\}]+?)\s*\}{2}#si", "[[\\1 \\2]]", $this->text);
			
			
			// {{nowrap|xxx}} -> xxx
			//$this->text = preg_replace("/\{\{(?:(nts|du|birth\-date and age|nowrap|small|big|huge|resize|smaller|larger|large)\s*\|)((\{\{.*?\}\}|.)*?)\}\}/s", "\\2", $this->text);
			
			
			// {{lang-xx|yyy}} -> yyy
			$this->text = preg_replace("#\{{2}\s*lang\-(?:[A-Za-z0-9\-]{2,7})\s*\|\s*(.+?)\s*\}{2}#si", "\\1", $this->text);
			
			// {{Birth date|xxxx|yy|zz}} -> xxxx-yy-zz
			$this->text = preg_replace_callback("#\{\{\s*(?:(?:Birth date)|(?:dob)|(?:(?:[A-Za-z0-9\-\_\s]+?)date))([^\}]+)\}\}#si", array($this, "_ic_dater"), $this->text);
			
			// {{Birth date and age|xxxx|yy|zz}} -> xxxx-yy-zz
			$this->text = preg_replace_callback("#\{\{\s*(?:(?:Birth date and age)|(?:Bda)|(?:\s*\|)?(?:(?:[A-Za-z0-9\-\_\s]+?)date))([^\}]+)\}\}#si", array($this, "_ic_dater"), $this->text);
			
			
			// http://en.wikipedia.org/wiki/Template:No2
			$replace_templates = array(
				'yes' => "Yes",
				'no' => "No",
				'coming soon' => "Coming Soon",
				'bad\s*\|\s*([^\}]*)?\s*' => "\\1",
				'bad' => "Bad",
				'Site active' => "Active",
				'Site inactive' => "Inactive",
				'(won|good|nom|okay|depends|free|nonfree|proprietary|needs|no result|release\-candidate|nightly|pending)\s*\|\s*([A-Za-z0-9\-\_]+)\s*=\s*([0-9]+?)\s*' => "\\3 <\\1:\\2>",
				'won' => "Won",
				'good' => "Good",
				'nom' => "Nominated",
				'sho' => "Shortlisted",
				'partial' => "Partial",
				'yes\-No' => "Yes/No",
				'okay' => "Okay",
				'depends' => "Depends",
				'some' => "Some",
				'any' => "Any",
				'n\/a(\s*\|[^\}]*)?' => "N/A",
				'BLACK' => "N/A",
				'dunno' => "?",
				'Unknown' => "Unknown",
				'Included' => "Included",
				'Dropped' => "Dropped",
				'beta' => "Beta",
				'table\-experimental\s*\|\s*([^\}]*)?\s*' => "\\2 <experimental>",
				'table\-experimental' => "",
				'free' => "Free",
				'nonfree' => "Non-Free",
				'proprietary' => "Proprietary",
				'release\-candidate' => "Release Candidate",
				'needs' => "Needs",
				'\?' => "?",
				'unofficial' => "Unofficial",
				'nightly' => "nightly",
				'usually' => "Usually",
				'rarely' => "Rarely",
				'sometimes' => "Sometimes",
				'draw' => "",
				'pending' => "Pending",
				
				'Check mark' => "['jngl:bool': YES]",
				'(?:X mark|Tick|y|aye|ya|y\&)(?:\s*\|[^\}]*)?' => "%TRUE%",
				'(?:X mark big|Cross|n|nay|na|x mark\-n|n\&)(?:\s*\|[^\}]*)?' => "%FALSE%",
				'(?:n\.b\.|bang)' => "!",
				'hmmm' => "?",
			);
			
			$replacements = array();
			
			foreach($replace_templates as $regex => $with) {
				$regex = "#\{{2}". $regex ."\}{2}#si";
				$replacements[ $regex ] = $with;
			}
			
			$this->text = preg_replace(array_keys($replacements), array_values($replacements), $this->text);
			
			
			//$this->text = PHP_EOL . PHP_EOL . $this->text . PHP_EOL . PHP_EOL;   // the wrapped PHP_EOL adds for easier regex matches
		}
		
		private function _find_sub_templates($string) {
			preg_match_all("/\{{2}((?>[^\{\}]+)|(?R))*\}{2}/x", $string, $matches);
			
			return $matches;
		}
		
		private function _find_sub_links($string) {
			preg_match_all("/\[{2}((?>[^\[\]+)|(?R))*\]{2}/x", $string, $matches);
			
			return $matches;
		}
		
		private function _ic_dater($match) {
			if(!isset($match[1])) {
				return $match[0];
			}
			
				if(preg_match("#([0-9]{4})\s*\|\s*([0-9]{1,2})\s*\|\s*([0-9]{1,2})#si", $match[1], $icmatch)) {
				return date("Y-m-d", mktime(0, 0, 0, $icmatch[2], $icmatch[3], $icmatch[1]));
			}
			
			if(preg_match("#([0-9]{1,2})\s*\|\s*([0-9]{1,2})\s*\|\s*([0-9]{4})#si", $match[1], $icmatch)) {
				return date("Y-m-d", mktime(0, 0, 0, $icmatch[1], $icmatch[2], $icmatch[3]));
			}
			
			// http://regexlib.com/DisplayPatterns.aspx?cattabindex=4&categoryId=5&AspxAutoDetectCookieSupport=1
			if(preg_match("#(0[1-9]|[12][0-9]|3[01])\s*(J(anuary|uly)|Ma(rch|y)|August|(Octo|Decem)ber)\s*[1-9][0-9]{3}|(0[1-9]|[12][0-9]|30)\s*(April|June|(Sept|Nov)ember)\s*[1-9][0-9]{3}|(0[1-9]|1[0-9]|2[0-8])\s*February\s*[1-9][0-9]{3}|29\s*February\s*((0[48]|[2468][048]|[13579][26])00|[0-9]{2}(0[48]|[2468][048]|[13579][26]))#si", $match[1], $icmatch)) {
				$tmp_icdate = @strtotime($icmatch[0]);
				
				if(!empty($tmp_icdate)) {
					return date("Y-m-d", $tmp_icdate);
				}
			}
			
			return $match[0];
		}
		
		public function clean_wiki_text($wiki_text, $tags_too=false, $br_to_space=false) {
			if(is_array($wiki_text)) {
				foreach($wiki_text as $wtk => $wtv) {
					$wiki_text[$wtk] = $this->clean_wiki_text($wtv, $tags_too);
				}
				
				return $wiki_text;
			}
			
			//$wiki_text = "\n". $wiki_text ."\n";
			
			//$wiki_text = preg_replace("/\n\[\[(?:[^[\]]|(?R))*?\]\]\n/s", "\n\n", $wiki_text);
			
			// Change hard wikipedia links into normal [[xxx|yyy]] links
			$wiki_text = preg_replace_callback("#\[http\:\/\/en\.wikipedia\.org\/wiki\/([^\s]+?)(?:\s+)([^\]]+?)\]#si", function($match) {
				$bleh = explode("#", str_replace("_", " ", trim($match[1])));
				$url = array_shift($bleh);
				$text = trim($match[2]);
				
				return "[[". $url . (!empty($text)?"|". $text:"") ."]]";
			}, $wiki_text);
			
			$wiki_text = preg_replace_callback("#\[([^\]]+?)(?:\s+)http\:\/\/en\.wikipedia\.org\/wiki\/([^\]]+?)\]#si", function($match) {
				$bleh = explode("#", str_replace("_", " ", trim($match[2])));
				$url = array_shift($bleh);
				$text = trim($match[1]);
				
				return "[[". $url . (!empty($text)?"|". $text:"") ."]]";
			}, $wiki_text);
			
			$wiki_text_replacements = array();
			
			if($tags_too) {
				$wiki_text_replacements["#\{{2}s\-start\}{2}(.+?)\{{2}s\-end\}{2}#si"] = "";
				
				$wiki_text_replacements["/\{{2}((?>[^\{\}]+)|(?R))*\}{2}/x"] = "";
				$wiki_text_replacements["#\{\|(.*?)\|\}#si"] = "";
			}
			
			// references/citations
			// removed in $this->citations():
			// $wiki_text_replacements["#<ref[^>]*>(.+?)</ref>#si"] = "";
			// $wiki_text_replacements["#<ref[^>]*>#si"] = "";
			
			// text styling
			$wiki_text_replacements["#('{2,5})([^\\1]+?)(?:\\1)#si"] = "\\2";
			
			$wiki_text_replacements["#('{2,})#si"] = "";
			
			$wiki_text_replacements["#". preg_quote("\n", "#") ."\;#si"] = "\n";
			
			// links in brackets: [http://xxx( yyy)] -> [url=xxx]yyy[/url]
			$wiki_text_replacements["#\[(https?)([^\s]+) ([^]]+)\]#si"] = "[url=\\1\\2]\\3[/url]";
			$wiki_text_replacements["#\[(.+) https?([^\]]+?)\]#si"] = "[url=\\2\\3]\\1[/url]";
			
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
			
			
			$wiki_text = preg_replace_callback("/\[{2}((?>[^\]]+)|(?R))*\]{2}/x", function($match) {
				if(preg_match("#^\[{2}\s*\:?(File|Media|Image)\s*\:#si", $match[0])) {
					return "";
				}
				
				return $match[0];
			}, $wiki_text);
			
			
			$wiki_text = trim($wiki_text, " *\r\t\n");
			//$wiki_text = preg_replace("#[\*|\s]+$#si", "", $wiki_text);
			//$wiki_text = preg_replace("#(\*|\s)+$#si", "", $wiki_text);
			
			$wiki_text = preg_replace("#\((\s*\,){1,}#si", "(", $wiki_text);
			$wiki_text = preg_replace("#(\,\s*){1,}\)#si", ")", $wiki_text);
			$wiki_text = preg_replace("#(\s*\,){2,}#si", ",", $wiki_text);
			$wiki_text = preg_replace("#\(\s*\)#si", "", $wiki_text);
			
			$wiki_text = preg_replace("#(\n){3,}#si", "\n\n", $wiki_text);
			$wiki_text = preg_replace("#([[:blank:]]{2,})#si", " ", $wiki_text);
			
			$wiki_text = preg_replace("#</?([^>]+)>#si", "", $wiki_text);
			
			$wiki_text = trim($wiki_text);
			
			return trim(strip_tags($wiki_text));
		}
		
		private function make_logical_guesses_on_content() {
			if($this->cargo['page_attributes']['type'] !== "main") {
				return;   // no need to waste time
			}
			
			$this->cargo['page_attributes']['content_type'] = $this->logical_guess_at_content_type();
		}
		
		private function logical_guess_at_content_type() {
			if(!empty($this->cargo['meta_boxes'])) {
				
				$meta_box_types = array();
				
				foreach($this->cargo['meta_boxes'] as $mb_type => $mb_meta) {
					$meta_box_types[] = $mb_type;
					
					if(!empty($mb_meta[':subtype'])) {
						$meta_box_types[] = $mb_type ."|". $this->compact_to_slug($mb_meta[':subtype']);
					}
				}
				
				$meta_box_types = array_values(array_unique($meta_box_types));
				
				
				
				// if infobox exists in one of these keys, then return key
				$contentTypeToMetaBoxes = array(
					// movies
					'entertainment_movie' => array("infobox_film"),
					
					// video games
					'entertainment_game_video' => array("infobox_video_game"),
					'entertainment_game_video_series' => array("infobox_video_game_series"),
					
					// theater
					'entertainment_theater_musical' => array("infobox_musical"),
					
					// music
					'entertainment_music_album_soundtrack' => array("infobox_album|soundtrack", "infobox_album|film", "infobox_album|cast", "infobox_soundtrack"),
					'entertainment_music_song' => array("infobox_anthem", "infobox_hymn", "infobox_musical_composition", "infobox_song", "infobox_single", "infobox_standard"),
					'entertainment_music_video' => array("infobox_album|video"),
					'entertainment_music_album' => array("infobox_album"),
					'entertainment_music_artist' => array("infobox_dancer", "infobox_choir", "infobox_college_marching_band", "infobox_school_marching_band", "infobox_musical_artist"),
					'entertainment_music_conference' => array("infobox_music_conference"),
					'entertainment_music_concert' => array("infobox_concert", "infobox_orchestra_concert", "infobox_music_festival"),
					'entertainment_music_competition' => array("infobox_reality_talent_competition"),
					'entertainment_music_genre' => array("infobox_music_genre"),
					'entertainment_music_genre_culture' => array("infobox_music_of"),
					'entertainment_music_discography_producer' => array("infobox_producer_discography"),
					'entertainment_music_discography_artist' => array("infobox_artist_discography"),
					'entertainment_music_instrument' => array("infobox_instrument"),
					'entertainment_music_label' => array("infobox_record_label"),
					'entertainment_music_chord' => array("infobox_chord"),
					
					// locations
					'location_venue' => array("infobox_venue"),
					'location_settlement' => array("infobox_settlement"),
					
					
					// life
					
					'person' => array("persondata"),
					
				);
				
				// cycle through
				foreach($contentTypeToMetaBoxes as $content_type => $meta_boxes) {
					foreach($meta_boxes as $meta_box) {
						if(in_array($meta_box, $meta_box_types)) {
							return $content_type;
						}
					}
				}
				
				
				// smarter
				if(preg_match("#soundtrack\)$#si", $this->cargo['title'])) {
					return "entertainment_music_album_soundtrack";
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
		
		private function compact_to_slug($string, $separator="_") {
			$string = str_replace(array("'", "’", "‘"), "", $string);   // non word separator characters
			
			$string = preg_replace('~[^\\pL\d]+~u', $separator, $string);
			
			// normalize
			$normalize = array(
				'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Å' => 'A', 'Ä' => 'A', 'Æ' => 'AE', 'Ç' => 'C', 
				'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ð' => 'Eth', 
				'Ñ' => 'N', 'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 
				'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 
				'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'ä' => 'a', 'æ' => 'ae', 'ç' => 'c', 
				'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'eth', 
				'ñ' => 'n', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø'=>'o', 
				'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 
				'ß' => 'sz', 'þ' => 'thorn', 'ÿ' => 'y',
			);
			
			//$string = strtr($string, $normalize);
			$new_string = "";
			
			if(is_array($string)) {
				print "<h1>Error on usage of compact_to_slug( [ARRAY GIVEN] )</h1>\n";
				print $this->title;
				print "<pre>". print_r($string, true) ."</pre>\n";
			}
			
			$string_clear = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
			
			for ($i = 0; $i < strlen($string_clear); $i++) {
				$ch1 = $string_clear[$i];
				$ch2 = mb_substr($string, $i, 1);
				
				$new_string .= $ch1=='?'?$ch2:$ch1;
			}
			
			$string = $new_string;
			
			// lowercase
			$string = mb_strtolower($string);
			
			// remove unwanted characters
			//$string = preg_replace('~[^-\w]+~', '', $string);
			
			$string = preg_replace("#". preg_quote($separator, "#") ."{2,}#si", $separator, $string);
			$string = strtolower(trim($string, $separator));
			
			if(empty($string)) {
				return ':nil';
			}
			
			return $string;
		}
		
		private function insert_jungledb_helpers($text) {
			if(is_array($text)) {
				foreach($text as $tkey => $tval) {
					$text[$tkey] = $this->insert_jungledb_helpers($tval, $include_curlies);
				}
			}
			
			//if($include_curlies) {
			//	$text = preg_replace_callback("#(?:(?:\{){2})\s*([^\|]+?)(.+?)(?:(?:\}){2})#si", array($this, "jungledb_helper_replace_callback"), $text);
			//}
			
			//return preg_replace("#((\[|\{}){2})\s*(([^\||\}]{2})+?)\|(.+?)(?:(?:\]|\}){2})#si", "[[\\1###JNGLDBDEL###\\2]]", $text);
			
			$text = preg_replace_callback("#((?:\{|\[){2})\s*([^\|]+?)(.+?)((?:\]|\}){2})#si", function($matches) {
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
				return preg_split("#(\s*(,|;|•))?\s*<\s*?/?\s*?br\s*?/?\s*?>\s*#si", $string, -1, PREG_SPLIT_NO_EMPTY);
			}
			
			return $string;
		}
		
		private function finalize_internal_links($value) {
			if(is_array($value)) {
				foreach($value as $vkey => $vval) {
					$value[$vkey] = $this->finalize_internal_links($vval);
				}
				
				return $value;
			}
			
			//return preg_replace_callback("#\[{2}\s*([^\]]+?)\s*\]{2}#si", function($match)
			return preg_replace_callback("/\[{2}((?>[^\[\]]+)|(?R))*\]{2}/x", function($match) {
				//if(preg_match("#^([A-Za-z0-9\-\_]+?)\:#si", $match[1])) {
				//	return $match[0];
				//}
				
				if(preg_match("#^\[{2}\s*(File|Image|Media|Template|Wikipedia|Help|Special|User|Book|Portal|MediaWiki|Category|WP|Project|WT|Project talk|Image talk)\s*\:#si", $match[0])) {
					return "[[". preg_replace("#(\[|\]){2}#si", "", $match[0]) ."]]";
				}
				
				if(!isset($match[1])) {
					return $match[0];
				}
				
				if(strlen($match[1]) > 0 && preg_match("#^([^\#]+)\#([^\|]+)\|(.+)$#si", $match[1], $submatch)) {
					return "[wiki=". md5(ucfirst(trim($submatch[1]))) ."#". trim($submatch[2]) ."]". trim(preg_replace("#('{2,})(.+?)\\1#si", "\\2", $submatch[3])) ."[/wiki]";
				}
				
				if(strlen($match[1]) > 0 && preg_match("#^([^\#]+)\|(.+)$#si", $match[1], $submatch)) {
					return "[wiki=". md5(ucfirst(trim($submatch[1]))) ."]". trim(preg_replace("#('{2,})(.+?)\\1#si", "\\2", $submatch[2])) ."[/wiki]";
				}
				
				if(strlen($match[1]) > 0 && preg_match("#^([^\#]+)\#(.+)$#si", $match[1], $submatch)) {
					return "[wiki=". md5(ucfirst(trim($submatch[1]))) ."]". trim($submatch[1]) ." (". trim(preg_replace("#('{2,})(.+?)\\1#si", "\\2", $submatch[2])) .")[/wiki]";
				}
				
				return "[wiki=". md5(ucfirst(trim($match[1]))) ."]". trim(preg_replace("#('{2,})(.+?)\\1#si", "\\2", $match[1])) ."[/wiki]";
			}, $value);
		}
	}
	
	
	