<?php

/**
 * Convert Apache access log into a CSV file
 *
 * @author Matthew Boynes
 */

# Read out the input and output files from the command line arguments
$input_file = getcwd() . '/' . $argv[1];
$output_file = getcwd() . '/' . $argv[2];

# Let's make sure these files are accessible
if (!$wh = fopen($output_file, 'a'))
	die("\n\nFATAL ERROR!\nCannot open file `$output_file` for writing\n");

if (!$rh = fopen($input_file, 'r'))
	close_and_exit("\n\nFATAL ERROR!\nCannot open file `$input_file` for reading\n");

# Try to write out the header in the output file
if (fwrite($wh, '"IP","Time","Request_Type","Path","Response","Referral_Domain","Referral_Path","User_Agent"'."\n") === FALSE)
	close_and_exit("\n\nFATAL ERROR!\Cannot write to $output_file");


/**
 * Replace 3-letter months with 2-digit months; to be used with preg_replace_callback
 *
 * @param array $matches The regex search matches
 * @return String The replacement pattern
 * @author Matthew Boynes
 */
function replace_month($matches) {
	switch ($matches[0]) {
		case '-Jan-':
			return '-01-';
			break;
		case '-Feb-':
			return '-02-';
			break;
		case '-Mar-':
			return '-03-';
			break;
		case '-Apr-':
			return '-04-';
			break;
		case '-May-':
			return '-05-';
			break;
		case '-Jun-':
			return '-06-';
			break;
		case '-Jul-':
			return '-07-';
			break;
		case '-Aug-':
			return '-08-';
			break;
		case '-Sep-':
			return '-09-';
			break;
		case '-Oct-':
			return '-10-';
			break;
		case '-Nov-':
			return '-11-';
			break;
		case '-Dec-':
			return '-12-';
			break;
	}
}

/**
 * Helper function to close our two open files and output a message. For the sake of keeping DRY. Note that this function exits the script
 *
 * @param string $message Optional. If exiting with a message, pass it here
 * @return void
 * @author Matthew Boynes
 */
function close_and_exit( $message='' ) {
	global $rh, $wh;
	fclose($rh);
	fclose($wh);
	if ($message) echo $message;
	exit;
}


# Counter variables
$i = $good = $bad = 0;


/**
 * The main loop. If everything checks out, this loops through the input file line-by-line
 * and runs a series of search and replace regular expressions to CSV-ify it. If it doesn't
 * end with the correct number of columns, it skips the line and reports it. It will also
 * output a dot "." for every 5,000 lines it reads, as a sort of progress meter.
 *
 * @author Matthew Boynes
 */
if ($rh && $wh) {
	echo "\nProcessing `$output_file`\n";
	while (($line = fgets($rh)) !== false) {
		$i++;
		$search = array(
			'/^([\d\.]+|localhost)\s+[-"]+\s+[-_"\w]+\s+\[(.*?)\]\s+"(?:([A-Z]+) )?(.+?)(?: HTTP\/1.\d)?"\s+(\d{3})\s+[-\d]+\s+(".*?")\s+(".*?")$/',
			'/("\d{3}",")((?:http:\/\/)?[^"]+?\/)(.*?)"/',
			'/("\d{3}","(?:[^"]*?[^\/])?")/',
			'/"(\d\d)\/(\w{3})\/(\d{4}):(\d\d:\d\d:\d\d).*?"/'
		);
		$replace = array(
			'"$1","$2","$3","$4","$5",$6,$7',
			'$1$2","/$3"',
			'$1,"-"',
			'"\3-\2-\1 \4"'
		);
		$new_line = preg_replace($search, $replace, $line);
		$new_line = preg_replace_callback('/-(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)-/', 'replace_month', $new_line);

		# Verify: We should have 7 comma-quote pairs
		if ( substr_count($new_line, '","') != 7 ) {
			echo "\nSkipping line $i due to line incompatibility:\n\t$line\n\t$new_line";
			$bad++;
			continue;
		}

		# Write this line to our output file
		if (fwrite($wh, $new_line) === FALSE)
			close_and_exit("\n\nFATAL ERROR!\Cannot write to $output_file");

		$good++;
		if ( $i % 5000 == 0 ) echo ".";
	}
	if (!feof($rh)) {
		echo "Error: unexpected fgets() fail\n";
	}
}

# We're done! Report on how we did.
close_and_exit( "\n
*******************************
*      Process Complete!      *
*******************************

RESULTS
-------------------------------
Total lines read: $i
Skipped: $bad
Imported: $good
-------------------------------

" );


?>