## JungleDB PHP Wikipedia Parser

This is an attempt at extracting useful information out of raw Wikipedia page syntax, written as a portable PHP class. Originally written for JungleDB. Released the most recently updated (2015-02-13) version of the wiki_parser.php script, which is a significant improvement over the last copy.

I don't expect to update this repository in the forseeable future.

#### How to use

1. `$wikipedia_syntax_parser = new Jungle_WikiSyntax_Parser($raw_wikipedia_syntax, "George Harrison");`
	
	`$raw_wikipedia_syntax` is the raw Wiki syntax from a database dump or from the Edit textarea of a given page. An example of this syntax is provided in [sample_input.txt](https://github.com/donwilson/PHP-Wikipedia-Syntax-Parser/blob/master/sample_input.txt).
	
	`"Goerge Harrison"` is a string containing the full Wiki page title (e.g.: `George Harrison`, `Template:Wikipedia Syntax`, `File:image.png`) and is *optional* (this helps determine the `page_type` [Main, Template, Special, File, ...])
	
2. `$parsed_wiki_syntax = $wikipedia_syntax_parser->parse();`

	Your `$parsed_wiki_syntax` variable becomes an array with information about the Wiki page itself and useful information extracted from within. An example of this output (using the old_version/wiki_parser.php), after parsing [sample_input.txt](https://github.com/donwilson/PHP-Wikipedia-Syntax-Parser/blob/master/sample_input.txt), can be found in [sample_output.txt](https://github.com/donwilson/PHP-Wikipedia-Syntax-Parser/blob/master/old_version/sample_output.txt).


#### Notes

- When reading Wiki syntax files from disk, make sure they are properly encoded in *UTF-8*. To read these correctly encoded files, please use `implode(file('WIKI_RAW_SYNTAX.TXT'))` as `file_get_contents('WIKI_RAW_SYNTAX.TXT')` seems to mess up language-specific characters.
