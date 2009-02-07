<?php

/*
Plugin Name: bib2html
Plugin URI: http://sergioandreozzi.com/wordpress/bib2html
Description: bib2html enables to add bibtex entries formatted as HTML in wordpress pages and posts. The input data is the bibtex text file and the output is HTML. 
Version: 0.9.3
Author: Sergio Andreozzi
Author URI: http://sergioandreozzi.com
*/


/*  Copyright 2006-2007  Sergio Andreozzi  (email : sergio <DOT> andreozzi <AT> gmail <DOT> com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


/*
This plug-in has been improved thanks to the suggestons and contributions of
- Cristiana Bolchini
-- cleaner bibtex presentation
- Patrick Maué
-- remote bibliographies managed by citeulike.org or bibsonomy.org
- Nemo
-- more characters on key
- Marco Loregian
-- inverting bibtex and html
*/


function sortByYear($a, $b)
{
  $f1 = $a['year']; 
  $f2 = $b['year']; 

  if ($f1 == $f2) return 0;

  return ($f1 < $f2) ? -1 : 1;
}

function bib2htmlProcess($data, $filterType, $filter) {
    $OSBiBPath = dirname(__FILE__) . '/OSBiB/';
    include_once($OSBiBPath.'format/bibtexParse/PARSEENTRIES.php');
    include_once($OSBiBPath.'format/BIBFORMAT.php');
    include_once(dirname(__FILE__) . '/class.TemplatePower.inc.php');

    // parse the content of bib string and generate associative array with valid entries
    $parse = NEW PARSEENTRIES();
    $parse->expandMacro = TRUE;
    $parse->fieldExtract = TRUE;
    $parse->removeDelimit = TRUE;
    $parse->loadBibtexString($data);
    $parse->extractEntries();
    list($preamble, $strings, $entries) = $parse->returnArrays();
	
    /* Format the entries array  for html output */
    $bibformat = NEW BIBFORMAT($OSBiBPath, TRUE); // TRUE implies that the input data is in bibtex format
    $bibformat->cleanEntry=TRUE; // convert BibTeX (and LaTeX) special characters to UTF-8
    list($info, $citation, $styleCommon, $styleTypes) = $bibformat->loadStyle($OSBiBPath."styles/bibliography/", "IEEE");
    $bibformat->getStyle($styleCommon, $styleTypes);

    // currently sorting descending on year by default
    usort($entries, "sortByYear");
    $reverse=true;
    if ($reverse) {
       $entries = array_reverse($entries);
    }
	
    $i=0;
    //   $citations = '<dl>';
    $tpl = new TemplatePower(dirname(__FILE__) . '/bibentry-html.tpl');
    $tpl->prepare();
    foreach ($entries as $entry) {
		// Get the resource type ('book', 'article', 'inbook' etc.)
		$resourceType = $entry['bibtexEntryType'];
		
		//  adds all the resource elements automatically to the BIBFORMAT::item array
		$bibformat->preProcess($resourceType, $entry);

		// apply filters
	        $pos = strpos($filter, $resourceType); 
		$bibkey = $entry['bibtexCitation'];
               
		if ( ( (strcmp($filterType, "allow") === 0) && ($pos === false) ) or
		     ( (strcmp($filterType, "deny")  === 0) && ($pos !== false) ) or
		     ( (strcmp($filterType, "key")   === 0) && (strcmp($filter, $bibkey) != 0) ) ) continue;
 
		$i++;
                $biblogo = '<a href="#' . $bibkey . '" onclick="Effect.toggle(\''. $bibkey . "','appear'); return false\">bibtex</a>";
		 
		// get the formatted resource string ready for printing to the web browser
		// the str_replace is used to remove the { } parentheses possibly present in title 
		// to enforce uppercase, TODO: check if it can be done only on title 
                $tpl->newBlock("bibtex_entry");
                $tpl->assign("year", $entry['year']);
                $tpl->assign("type", $entry['bibtexEntryType']);
                $tpl->assign("pdf", toDownload($entry));
                $tpl->assign("key", strtr($bibkey, ":", "-"));
                $tpl->assign("entry", str_replace(array('{', '}'), '', $bibformat->map()));
                $tpl->assign("bibtex", formatBibtex($entry['bibtexEntry']));
    }        
     
    return $tpl->getOutputContent();          
}
 
 
function toDownload($entry) {
    if(array_key_exists('url',$entry)){
      $string = " <a href='" . $entry['url'] . "' title='Go to document'><img src='" . get_bloginfo('wpurl') . "/wp-content/plugins/bib2html/external.png' width='10' height='10' alt='Go to document' /></a>";
      return $string;
    }
    return '';  
  }

  
function bib2html($myContent) {

   // search for all [bibtex filename] tags and extract the filename
   preg_match_all("/\[\s*bibtex\s+file=(.+)(\s+(allow|deny|key)=(.+))*]/U", $myContent, $bibItemsSets, PREG_SET_ORDER);

   if ($bibItemsSets) {
		
		foreach ($bibItemsSets as $bibItems) {
                        // check if bib file is URL
                        $isUrl = strpos($bibItems[1], "http://");
			if ($isUrl !== false) $bibItems[1] = getCached($bibItems[1]);
			$bibFile = dirname(__FILE__)."/data/".$bibItems[1];

 			if (file_exists($bibFile)) {
				$bib = file_get_contents($bibFile);
				if (!empty($bib)) {
				        // if bibtex file identified and opened, then convert to html
					$htmlbib = bib2htmlProcess($bib, $bibItems[3], $bibItems[4]);
					$myContent = str_replace($bibItems[0], $htmlbib, $myContent);			
				} else { 
					$myContent = str_replace( $bibItems[0], $bibItems[1] . ' bibtex file empty', $myContent);	
				}          
			} else {
				$myContent = str_replace( $bibItems[0], $bibItems[1] . ' bibtex file not found', $myContent);	
			}
		}     
	}
		
	return $myContent;
}

// this function formats a bibtex code in order to be readable
// when appearing in the modal window
function formatBibtex($entry){
     $order = array("},");
     $replace = "}, <br />\n &nbsp;";

     $entry = preg_replace('/\s\s+/', ' ', trim($entry));
     $new_entry = str_replace($order, $replace, $entry);
     $new_entry = str_replace(", author", ", <br />\n &nbsp;&nbsp;author", $new_entry);
     $new_entry = str_replace(", Author", ", <br />\n &nbsp;&nbsp;author", $new_entry);
     $new_entry = str_replace(", AUTHOR", ", <br />\n &nbsp;&nbsp;author", $new_entry);
     $new_entry = preg_replace('/\},?\s*\}$/', "}\n}", $new_entry); 
    return $new_entry;
}

/* Returns filename of cached version of given url  */
function getCached($url) {
    // check if cached file exists
    $name = substr($url, strrpos($url, "/")+1);
    $file = dirname(__FILE__) . "/data/" . $name . ".cached.bib";
    
    // check if file date exceeds 60 minutes   
    if (! (file_exists($file) && (filemtime($file) + 3600 > time())))  {
        // not returned yet, grab new version
        $f=fopen($file,"wb");
        if ($f) {
                  fwrite($f,file_get_contents($url));
                  fclose($f);
        } else echo "Failed to write file" . $file . " - check directory permission according to your Web server privileges.";
    }
   
    return $name.".cached.bib";
}


function bib2html_head()
{
  if (!function_exists('wp_enqueue_script')) {
 	echo "\n" . '<script src="'.  get_bloginfo('wpurl') . '/wp-content/plugins/bib2html/js/jquery.js"  type="text/javascript"></script>' . "\n";
  	echo '<script src="'.  get_bloginfo('wpurl') . '/wp-content/plugins/bib2html/js/bib2html.js"  type="text/javascript"></script>' . "\n";
//  echo '<script src="'.  get_bloginfo('wpurl') . '/wp-content/plugins/bib2html/js/jqModal.js"  type="text/javascript"></script>' . "\n";
 // echo '<link type="text/css" rel="stylesheet" media="all" href="' . get_bloginfo('wpurl') . '/wp-content/plugins/bib2html/css/jqModal.css" />' . "\n";
}
echo "<style type=\"text/css\">

div.bibtex {
    display: none;
}</style>";

}

function bib2html_init() {
	if (function_exists('wp_enqueue_script')) {
              // wp_deregister_script('jquery');
              // wp_register_script('jquery', get_bloginfo('wpurl') . '/wp-content/plugins/bib2html/js/jquery.js', false, '1.2.1');
              // wp_enqueue_script('jquery');
               wp_register_script('bib2html', get_bloginfo('wpurl') . '/wp-content/plugins/bib2html/js/bib2html.js', array('jquery'), '0.7');
               wp_enqueue_script('bib2html');
	} 
}

add_action('init', 'bib2html_init');	
add_action('wp_head', 'bib2html_head');
add_filter('the_content', 'bib2html',1);


?>
