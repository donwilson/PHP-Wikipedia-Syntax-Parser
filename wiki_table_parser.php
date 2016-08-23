<?php
	$contents = file_get_contents("./wikisyntax_sample_table.txt");
	
	if(empty($contents)) {
		die("Nothing to do");
	}
	
	function print_pre($array, $html=false) {
		print "<pre>". (!empty($html)?htmlentities(print_r($array,1), ENT_COMPAT, 'UTF-8'):print_r($array,1)) ."</pre>\n";
	}
	
	$table_data = array(
		'rows'		=> array(),
	);
	
	$table = trim($contents);
	$table = str_replace("\r", "", $table);
	$table = preg_replace(array("#^\s*\{\s*\|\s*(?:\|\-\s*)?#si", "#(?:\s*\|\-)?\s*\|\s*\}\s*$#si"), "", $table);
	$table = preg_replace("#\s*(?:width|height|border|style|class)=\"(?:[^\"]*?)\"#si", "", $table);
	$table = preg_replace(array("#^\s*\|\-\s*#si", "#\s*\|\-\s*$#si"), "", $table);
	
	$table = trim($table);
	
	print "<h2>Raw table</h2>\n";
	print_pre($table, true);
	print "<hr />\n";
	
	$rows = preg_split("#\s*". preg_quote("|-", "#") ."\s*#", $table, null, PREG_SPLIT_NO_EMPTY);
	
	print "<h2>Individual rows (raw)</h2>\n";
	print_pre($rows, true);
	
	print "<hr />\n";
	
	
	if(empty($rows)) {
		die("No table rows found");
	}
	
	
	for($i = 1; $i <= count($rows); $i++) {
		$table_data[ $i ] = array(
			'type'		=> "row",
			'columns'	=> array(),
		);
	}
	
	
	$num_cols = 0;
	$num_header_rows = 0;
	$row_i = 1;
	
	foreach($rows as $row) {
		$row_type = false;
		
		if($row_type === false && preg_match("#scope=\"\s*(row|col)\s*\"#si", $row, $match)) {
			if(strtolower(trim($match[1])) == "row") {
				$row_type = "row";
			} elseif(strtolower(trim($match[1])) == "col") {
				$row_type = "header";
			}
		}
		
		$row = preg_replace("#\s*scope=\"([^\"]*?)\"#", "", $row);
		
		$row = preg_replace("#^\s*(\!\s*)?\|\s*#si", "", $row);
		//$row_lines = preg_split("#". preg_quote("\n", "#") ."(!\s*?)\|\s*#si", $row);
		$row = str_replace(array("!!", "||"), "\n", $row);
		
		$columns = explode("\n", $row);
		
		if(empty($columns)) {
			continue;
		}
		
		$num_cols = max($num_cols, count($columns));
		
		
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
			$col_x = 1;   // number of columns to fill
			$col_y = 1;   // number of rows to fill
			
			if(preg_match_all("#\s*(row|col)span=\"([0-9]+?)\"#si", $column, $matches)) {
				foreach(array_keys($matches['0']) as $mkey) {
					$span_type = strtolower(trim($matches['1'][$mkey]));
					$span_length = trim($matches['2'][$mkey]);
					
					if($span_type == "row") {
						$col_y = $span_length;
					} elseif($span_type == "col") {
						$col_x = $span_length;
					}
				}
				
				$column = preg_replace("#\s*(row|col)span=\"([^\"]*)\"#si", "", $column);
			}
			
			$column = preg_replace("#^\s*(\!\s*)?\|\s*#si", "", $column);
			$column = trim($column);
			
			$column_data = array(
				'text' => $column,
			);
			
			
			// process and save basic row data columns
			if($row_type === "data") {
				for($y = $row_i; $y <= ($row_i + ($col_y - 1)); $y++) {
					$table_data[ $y ]['type'] = "data";
					
					for($i = $col_i; $i <= ($col_i + ($col_x - 1)); $i++) {
						if($i <= $num_cols && !isset($table_data[ $y ]['columns'][ $i ])) {
							$table_data[ $y ]['columns'][ $i ] = $column_data;
						}
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
		
		$row_i++;
	}
	
	print "<h2>Variables</h2>\n";
	print_pre(array(
		'row_i' => $row_i,
		'col_i' => $col_i,
		'num_header_rows' => $num_header_rows,
		'num_cols' => $num_cols,
	), true);
	print "<hr />\n";
	
	
	print "<h2>Table_Data</h2>\n";
	print_pre($table_data, true);
	