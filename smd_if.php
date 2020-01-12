//<?php
/**
 * smd_if
 *
 * A Textpattern CMS plugin for evaluating multiple conditional logic statements
 *  -> Test for (in)equality, less/greater than, divisible by, empty, used, defined, begins/ends, contains, in list, type...
 *  -> Supports and/or logic
 *  -> Data filtration options
 *
 * @author Stef Dawson
 * @link   http://stefdawson.com/
 */

function smd_if($atts,$thing) {
	global $thisarticle, $pretext, $thisfile, $thislink, $thisimage, $thissection, $thiscategory, $thispage, $thiscomment, $variable, $prefs;

	extract(lAtts(array(
		'field'          => '',
		'operator'       => '',
		'value'          => '',
		'logic'          => 'and',
		'case_sensitive' => '0',
		'filter'         => '',
		'replace'        => '',
		'filter_type'    => 'all',
		'filter_on'      => 'field',
		'param_delim'    => ',',
		'mod_delim'      => ':',
		'list_delim'     => '/',
		'var_prefix'     => 'smd_if_',
		'debug'          => '0',
	), $atts));

	// Special field names that refer to $pretext or elsewhere - everything else is assumed to
	// exist in $thisarticle so custom fields can be used
	$allPtxt = array(
		"id"            => '$pretext["id"]',
		"s"             => '$pretext["s"]',
		"c"             => '$pretext["c"]',
		"query"         => '$pretext["q"]',
		"pg"            => '$pretext["pg"]',
		"month"         => '$pretext["month"]',
		"author"        => '$pretext["author"]',
		"status"        => '$pretext["status"]',
		"page"          => '$pretext["page"]',
		"next_id"       => '$pretext["next_id"]',
		"next_title"    => '$pretext["next_title"]',
		"next_utitle"   => '$pretext["next_utitle"]',
		"prev_id"       => '$pretext["prev_id"]',
		"prev_title"    => '$pretext["prev_title"]',
		"prev_utitle"   => '$pretext["prev_utitle"]',
		"permlink_mode" => '$pretext["permlink_mode"]',
	);

	// Each entry has the operation to be eval()d later and a list of disallowed fields
	$allOps = array(
		'eq'        => array('isset(VARNAME) && CAST FIELD === CAST VALUE', ''),
		'not'       => array('isset(VARNAME) && CAST FIELD !== CAST VALUE', ''),
		'gt'        => array('isset(VARNAME) && CAST FIELD > CAST VALUE', ''),
		'ge'        => array('isset(VARNAME) && CAST FIELD >= CAST VALUE', ''),
		'lt'        => array('isset(VARNAME) && CAST FIELD < CAST VALUE', ''),
		'le'        => array('isset(VARNAME) && CAST FIELD <= CAST VALUE', ''),
		'between'   => array('isset(VARNAME) && ($tween=explode("'.$list_delim.'", VALUE)) && CAST FIELD > $tween[0] && CAST FIELD < $tween[1]', ''),
		'range'     => array('isset(VARNAME) && ($rng=explode("'.$list_delim.'", VALUE)) && CAST FIELD >= $rng[0] && CAST FIELD <= $rng[1]', ''),
		'divisible' => array('isset(VARNAME) && CAST FIELD % CAST VALUE === 0', ''),
		'in'        => array('isset(VARNAME) && in_array(FIELD, explode("'.$list_delim.'", VALUE)) !== false', ''),
		'notin'     => array('isset(VARNAME) && in_array(FIELD, explode("'.$list_delim.'", VALUE)) === false', ''),
		'begins'    => array('isset(VARNAME) && strpos(FIELD, VALUE) === 0', ''),
		'contains'  => array('isset(VARNAME) && strpos(FIELD, VALUE) !== false', ''),
		'ends'      => array('isset(VARNAME) && substr(FIELD, strlen(FIELD) - strlen(VALUE)) === VALUE', ''),
		'defined'   => array('isset(VARNAME)', 'parent'),
		'undefined' => array('!isset(VARNAME)', 'parent'),
		'isempty'   => array('isset(VARNAME) && FIELD == ""', ''),
		'isused'    => array('isset(VARNAME) && FIELD != ""', ''),
		'isnum'     => array('isset(VARNAME) && ctype_digit((string)FIELD)', ''),
		'isalpha'   => array('isset(VARNAME) && ctype_alpha((string)FIELD)', ''),
		'isalnum'   => array('isset(VARNAME) && ctype_alnum((string)FIELD)', ''),
		'islower'   => array('isset(VARNAME) && ctype_lower((string)FIELD)', ''),
		'isupper'   => array('isset(VARNAME) && ctype_upper((string)FIELD)', ''),
		'ispunct'   => array('isset(VARNAME) && ctype_punct((string)FIELD)', ''),
		'isspace'   => array('isset(VARNAME) && ctype_space((string)FIELD)', ''),
	);

	$numericOps = "gt, ge, lt, le, eq, not, divisible, range, between";
	$caseOps = "islower, isupper";
	$spaceOps = "isnum, isalpha, isalnum, islower, isupper, ispunct, begins, contains, ends";
	$fields = do_list($field, $param_delim);
	$numFlds = count($fields);
	$ops = do_list($operator, $param_delim);
	$numOps = count($ops);
	$vals = do_list($value, $param_delim);
	$numVals = count($vals);
	$parentCats = ''; // Placeholder for the concatenated list of category leaf nodes
	$replacements = array();
	$type = ($thisfile) ? "file" : (($thislink) ? "link" : (($thisimage) ? "image" : "article"));
	$out = array();
	$iterations = ($numFlds > $numOps) ? $numFlds : $numOps;
	$filter = (!empty($filter)) ? do_list($filter, $param_delim) : array();
	$replace = (!empty($replace)) ? do_list($replace, $param_delim) : array();
	$numFilters = count($filter);
	$numReplace = count($replace);
	$filter_on = (!empty($filter_on)) ? do_list($filter_on, $param_delim) : array();
	$filter_type = (!empty($filter_type)) ? do_list($filter_type, $param_delim) : array();
	if ($debug > 1 && ($filter || $replace)) {
		echo "++ FILTERS / REPLACEMENTS ++";
		dmp($filter, $replace);
	}

	for ($idx = 0; $idx < $iterations; $idx++) {
		$fld = ($idx < $numFlds) ? $fields[$idx] : $fields[0]; // Allow short-circuit
		$fldParts = explode($mod_delim, $fld);
		$val = ($idx < $numVals) ? $vals[$idx] : '';
		$valList = explode($list_delim, $val);
		$valRep = array();
		foreach ($valList as $kdx => $theval) {
			$valRep[$kdx] = explode($mod_delim, $theval);
		}

		$op = ($idx < $numOps && $ops[$idx] != '') ? $ops[$idx] : (($fldParts[0]=="parent") ? "contains" : "eq");
		$opParts = explode($mod_delim, $op);
		$op = (array_key_exists($opParts[0], $allOps)) ? $opParts[0] : "eq";
		$cast = ((count($opParts) == 2) && ($opParts[1] === "NUM") && (in_list($op, $numericOps))) ? '(int)' : '';
		$length = ((count($opParts) == 2) && ($opParts[1] === "LEN") && (in_list($op, $numericOps))) ? 'strlen(FIELD)' : '';
		// The cast to string is necessary to counteract the === in the eq operator.
		// It doesn't impact anything else because string comparisons work fairly intuitively in PHP
		// (e.g. 19 < 2 = false even though in terms of string order they'd go 19, 2, 20, 21,...)
		$count = ((count($opParts) == 2) && ($opParts[1] === "COUNT") && (in_list($op, $numericOps))) ? '(string)count(explode("'.$list_delim.'", FIELD))' : '';
		$killSpaces = ((count($opParts) == 2) && ($opParts[1] === "NOSPACE") && (in_list($op, $spaceOps))) ? true : false;
		$stripFld = ((count($fldParts) > 1) && (in_array("NOTAGS", $fldParts))) ? true : false;
		$trimFld = ((count($fldParts) > 1) && (in_array("TRIM", $fldParts))) ? true : false;
		$escapeFld = ((count($fldParts) > 1) && (in_array("ESC", $fldParts))) ? true : false;
		$escapeAllFld = ((count($fldParts) > 1) && (in_array("ESCALL", $fldParts))) ? true : false;
		$case_sensitive = (in_list($op, $caseOps)) ? 1 : $case_sensitive;
		$pat = ($idx < $numFilters) ? $filter[$idx] : (($filter) ? $filter[0] : '');
		$rep = ($idx < $numReplace) ? $replace[$idx] : (($replace) ? $replace[0] : '');
		if ($debug) {
			echo 'TEST '.($idx+1).n;
			dmp($fldParts, $opParts, $valRep);
		}
		// Get the operator replacement code
		$exclude = do_list($allOps[$op][1]);
		$op = $allOps[$op][0];

		// As long as the current operator allows this field...
		if (!in_array($fldParts[0], $exclude)) {
			// Make up the test field variable
			if ($fldParts[0] == 'file') {
				$rfld = $fldParts[1];
				$fld = '$thisfile["'.$rfld.'"]';
			} else if (isset($thisfile[$fldParts[0]])) {
				$rfld = $fldParts[0];
				$fld = '$thisfile["'.$rfld.'"]';
			} else if ($fldParts[0] == 'link') {
				$rfld = $fldParts[1];
				$fld = '$thislink["'.$rfld.'"]';
			} else if (isset($thislink[$fldParts[0]])) {
				$rfld = $fldParts[0];
				$fld = '$thislink["'.$rfld.'"]';
			} else if ($fldParts[0] == 'image') {
				$rfld = $fldParts[1];
				$fld = '$thisimage["'.$rfld.'"]';
			} else if (isset($thisimage[$fldParts[0]])) {
				$rfld = $fldParts[0];
				$fld = '$thisimage["'.$rfld.'"]';
			} else if ($fldParts[0] == 'category') {
				$rfld = $fldParts[1];
				$fld = '$thiscategory["'.$rfld.'"]';
			} else if (isset($thiscategory[$fldParts[0]])) {
				$rfld = $fldParts[0];
				$fld = '$thiscategory["'.$rfld.'"]';
			} else if ($fldParts[0] == 'section') {
				$rfld = $fldParts[1];
				$fld = '$thissection["'.$rfld.'"]';
			} else if (isset($thissection[$fldParts[0]])) {
				$rfld = $fldParts[0];
				$fld = '$thissection["'.$rfld.'"]';
			} else if ($fldParts[0] == 'page') {
				$rfld = $fldParts[1];
				$fld = '$thispage["'.$rfld.'"]';
			} else if (isset($thispage[$fldParts[0]])) {
				$rfld = $fldParts[0];
				$fld = '$thispage["'.$rfld.'"]';
			} else if ($fldParts[0] == 'comment') {
				$rfld = $fldParts[1];
				$fld = '$thiscomment["'.$rfld.'"]';
			} else if (isset($thiscomment[$fldParts[0]])) {
				$rfld = $fldParts[0];
				$fld = '$thiscomment["'.$rfld.'"]';
			} else if ($fldParts[0] == 'pretext') {
				$rfld = $fldParts[1];
				$fld = '$pretext["'.$rfld.'"]';
			} else if (array_key_exists($fldParts[0], $allPtxt)) {
				$rfld = $fldParts[0];
				$fld = $allPtxt[$rfld];
			} else if ($fldParts[0] == 'pref') {
				$rfld = $fldParts[1];
				$fld = '$prefs["'.$rfld.'"]';
			} else if ($fldParts[0] == "parent") {
				$treeField = 'name';
				$level = '';
				foreach ($fldParts as $part) {
					if ($part == "parent") {
						$theCat = ($thisfile) ? $thisfile['category'] : (($thislink) ? $thislink['category'] : (($thisimage) ? $thisimage['category'] : (($thiscategory['name']) ? $thiscategory['name'] : $pretext['c'])));
					} else if (strpos($part, "CAT") === 0) {
						$theCat = $thisarticle["category".substr($part, 3)];
					} else if (strpos($part, "LVL") === 0) {
						$level = substr($part, 3);
					} else if (strpos($part, "TTL") === 0) {
						$treeField = 'title';
					} else if (strpos($part, "KIDS") === 0) {
						$treeField = 'children';
					}
				}

				$tree = getTreePath(doSlash($theCat), $type);
				if ($debug && $tree) {
					echo "CATEGORY TREE:";
					dmp($tree);
				}
				$items = array();
				foreach ($tree as $leaf) {
					if ($leaf['name'] == "root" || $leaf['name'] == $theCat) {
						continue;
					} else if ($level == '' || $level == $leaf['level']) {
						$items[] = $leaf[$treeField];
					}
				}
				$parentCats = implode(" ", $items);
				$rfld = sanitizeForUrl($parentCats);
				if ($debug && $parentCats) {
					echo "++ PARENT INFO ++";
					dmp($parentCats);
				}
				$fld = '$parentCats';
			} else if ($fldParts[0] == "txpvar") {
				if (count($fldParts) > 1) {
					$rfld = $fldParts[1];
					$fld = '$variable["'.$rfld.'"]';
				}
			} else if ($fldParts[0] == "urlvar") {
				if (count($fldParts) > 1) {
					$rfld = $fldParts[1];
					$fld = '$_GET["'.$rfld.'"]';
				}
			} else if ($fldParts[0] == "postvar") {
				if (count($fldParts) > 1) {
					$rfld = $fldParts[1];
					$fld = '$_POST["'.$rfld.'"]';
				}
			} else if ($fldParts[0] == "svrvar") {
				if (count($fldParts) > 1) {
					$rfld = $fldParts[1];
					$fld = '$_SERVER["'.$rfld.'"]';
				}
			} else if ($fldParts[0] == "phpvar") {
				if (count($fldParts) > 1) {
					$rfld = $fldParts[1];
					$fld = '$GLOBALS["'.$rfld.'"]';
				}
			} else if ($fldParts[0] == 'article') {
				$rfld = strtolower($fldParts[1]);
				$fld = '$thisarticle["'.$rfld.'"]';
			} else if (isset($thisarticle[$fldParts[0]])) {
				$rfld = strtolower($fldParts[0]);
				$fld = '$thisarticle["'.$rfld.'"]';
			} else if ($fldParts[0] == "NULL") {
				$smd_if_var = '';
				$fld = '$smd_if_var';
				$rfld = "NULL";
			} else {
				$smd_if_var = $fldParts[0];
				$fld = '$smd_if_var';
				$rfld = "field".($idx*1+1);
			}
			$rlfld = $var_prefix."len_".$rfld;
			$rcfld = $var_prefix."count_".$rfld;
			$rfld = $var_prefix.$rfld;

			// Take a copy of $fld to use in any isset() requests
			$fldClean = $fld;

			// Apply user-defined field filters
			if ($killSpaces) {
				$fld = 'preg_replace("/\s+/","",'.$fld.')';
			}
			if ($stripFld) {
				$fld = 'trim(strip_tags('.$fld.'))';
			}
			if ($trimFld) {
				$fld = 'trim('.$fld.')';
			}
			if ($escapeFld) {
				$fld = 'htmlentities('.$fld.')';
			}
			if ($escapeAllFld) {
				$fld = 'htmlentities('.$fld.', ENT_QUOTES)';
			}
			$do_ffilt = ($pat && in_array('field', $filter_on) && (in_array($fldParts[0], $filter_type) || in_array('all', $filter_type)) ) ? true : false;

			// Find the real value to compare against (may be another field)
			$valcnt = 1;
			$vflds = array();
			$core_vfld = "val".(($idx*1)+1);

			foreach ($valRep as $jdx => $valParts) {
				$stripVal = ((count($valParts) > 1) && (in_array("NOTAGS", $valParts))) ? true : false;
				$trimVal = ((count($valParts) > 1) && (in_array("TRIM", $valParts))) ? true : false;
				$escapeVal = ((count($valParts) > 1) && (in_array("ESC", $valParts))) ? true : false;
				$escapeAllVal = ((count($valParts) > 1) && (in_array("ESCALL", $valParts))) ? true : false;
				$numValParts = count($valParts);
				if ($valParts[0] == "urlvar") {
					if ($numValParts > 1) {
						$vfld = $valParts[1];
						$val = (isset($_GET[$vfld]) && $_GET[$vfld] != "") ? '$_GET["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
					}
				} else if ($valParts[0] == "postvar") {
					if ($numValParts > 1) {
						$vfld = $valParts[1];
						$val = (isset($_POST[$vfld]) && $_POST[$vfld] != "") ? '$_POST["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
					}
				} else if ($valParts[0] == "svrvar") {
					if ($numValParts > 1) {
						$vfld = $valParts[1];
						$val = (isset($_SERVER[$vfld]) && $_SERVER[$vfld] != "") ? '$_SERVER["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
					}
				} else if ($valParts[0] == "txpvar") {
					if ($numValParts > 1) {
						$vfld = $valParts[1];
						$val = (isset($variable[$vfld]) && $variable[$vfld] != "") ? '$variable["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
					}
				} else if ($valParts[0] == "phpvar") {
					if ($numValParts > 1) {
						$vfld = $valParts[1];
						$val = (isset($GLOBALS[$vfld]) && $GLOBALS[$vfld] != "") ? '$GLOBALS["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
					}
				} else if ($valParts[0] == "pref") {
					if ($numValParts > 1) {
						$vfld = $valParts[1];
						$val = (isset($prefs[$vfld]) && $prefs[$vfld] != "") ? '$prefs["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
					}
				} else if ($valParts[0] == "file") {
					if ($numValParts > 1) {
						$vfld = $valParts[1];
						$val = (isset($thisfile[$vfld]) && $thisfile[$vfld] != "") ? '$thisfile["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
					}
				} else if ($valParts[0] == "link") {
					if ($numValParts > 1) {
						$vfld = $valParts[1];
						$val = (isset($thislink[$vfld]) && $thislink[$vfld] != "") ? '$thislink["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
					}
				} else if ($valParts[0] == "image") {
					if ($numValParts > 1) {
						$vfld = $valParts[1];
						$val = (isset($thisimage[$vfld]) && $thisimage[$vfld] != "") ? '$thisimage["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
					}
				} else if ($valParts[0] == "category") {
					if ($numValParts > 1) {
						$vfld = $valParts[1];
						$val = (isset($thiscategory[$vfld]) && $thiscategory[$vfld] != "") ? '$thiscategory["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
					}
				} else if ($valParts[0] == "section") {
					if ($numValParts > 1) {
						$vfld = $valParts[1];
						$val = (isset($thissection[$vfld]) && $thissection[$vfld] != "") ? '$thissection["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
					}
				} else if ($valParts[0] == "page") {
					if ($numValParts > 1) {
						$vfld = $valParts[1];
						$val = (isset($thispage[$vfld]) && $thispage[$vfld] != "") ? '$thispage["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
					}
				} else if ($valParts[0] == "comment") {
					if ($numValParts > 1) {
						$vfld = $valParts[1];
						$val = (isset($thiscomment[$vfld]) && $thiscomment[$vfld] != "") ? '$thiscomment["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
					}
				} else if ($valParts[0] == "pretext") {
					if ($numValParts > 1) {
						$vfld = $valParts[1];
						$val = (isset($pretext[$vfld]) && $pretext[$vfld] != "") ? '$pretext["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
					}
				} else if ($valParts[0] == "article") {
					if ($numValParts > 1) {
						$vfld = $valParts[1];
						$val = (isset($thisarticle[$vfld]) && $thisarticle[$vfld] != "") ? '$thisarticle["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
					}
				} else if (strpos($valParts[0], "?") === 0) {
					$valParts[0] = substr(strtolower($valParts[0]), 1);
					$vfld = $valParts[0];
					if (isset($thisfile[$vfld]) && $thisfile[$vfld] != "") {
						$val = '$thisfile["'.$vfld.'"]';
					} else if (isset($thislink[$vfld]) && $thislink[$vfld] != "") {
						$val = '$thislink["'.$vfld.'"]';
					} else if (isset($thisimage[$vfld]) && $thisimage[$vfld] != "") {
						$val = '$thisimage["'.$vfld.'"]';
					} else if (isset($thiscategory[$vfld]) && $thiscategory[$vfld] != "") {
						$val = '$thiscategory["'.$vfld.'"]';
					} else if (isset($thissection[$vfld]) && $thissection[$vfld] != "") {
						$val = '$thissection["'.$vfld.'"]';
					} else if (isset($thispage[$vfld]) && $thispage[$vfld] != "") {
						$val = '$thispage["'.$vfld.'"]';
					} else if (isset($thiscomment[$vfld]) && $thiscomment[$vfld] != "") {
						$val = '$thiscomment["'.$vfld.'"]';
					} else if (array_key_exists($vfld, $allPtxt) && $allPtxt[$vfld] != "") {
						$val = $allPtxt[$vfld];
					} else if (isset($thisarticle[$vfld]) && $thisarticle[$vfld] != "") {
						$val = '$thisarticle["'.$vfld.'"]';
					} else {
						$val = doQuote(str_replace('"', '\"', $vfld));
					}
				} else {
					$vfld = $core_vfld.'_'.$valcnt;
					$val = doQuote(str_replace('"', '\"', $valParts[0]));
				}

				// Apply user-defined value filters
				if ($stripVal) {
					$val = 'trim(strip_tags('.$val.'))';
				}
				if ($trimVal) {
					$val = 'trim('.$val.')';
				}
				if ($escapeVal) {
					$val = 'htmlentities('.$val.')';
				}
				if ($escapeAllVal) {
					$val = 'htmlentities('.$val.', ENT_QUOTES)';
				}
				$do_vfilt = ($pat && in_array('value', $filter_on) && (in_array($valParts[0], $filter_type) || in_array('all', $filter_type)) ) ? true : false;

				// Replace the string parts by evaluating any variables...
				$filt_fld = ($do_ffilt) ? "preg_replace('$pat', '$rep', $fld)" : $fld;
				$filt_val = ($do_vfilt) ? "preg_replace('$pat', '$rep', $val)" : $val;
				eval ("\$valRep[$jdx] = ".$filt_val.";");

				// Only add sub-values to the replacements array if there's more than one sub-value
				if (count($valRep) > 1) {
					$vflds[$var_prefix.$vfld] = $valRep[$jdx];
					$vflds[$var_prefix."len_".$vfld] = strlen($valRep[$jdx]);
				}

				$valcnt++;
			}

			$joinedVals = join($list_delim, $valRep);
			$smd_prefilter = doQuote($joinedVals);

			// Add the combined operator for backwards compatibility with plugin v0.8x
			$vflds[$var_prefix.$core_vfld] = $joinedVals;
			$vflds[$var_prefix."len_".$core_vfld] = strlen($joinedVals);

			$cmd = str_replace("CAST", $cast, $op);
			$cmd = ($length) ? str_replace("FIELD", $length, $cmd) : $cmd;
			$cmd = ($count) ? str_replace("FIELD", $count, $cmd) : $cmd;
			$cmd = str_replace("FIELD", (($case_sensitive) ? $filt_fld : 'strtolower('.$filt_fld.')'), $cmd);
			$cmd = str_replace("VARNAME", $fldClean, $cmd);
			$cmd = str_replace("VALUE", (($case_sensitive) ? 'VALUE' : 'strtolower(VALUE)'), $cmd);

			// Value replacements have already been run through evil() so they can be assigned directly
			foreach ($vflds as $valit => $valval) {
				$replacements['{'.$valit.'}'] = $valval;
			}

			// Field replacements need some eval() action...
			$cmd = "@\$replacements['{".$rfld."}'] = ".$filt_fld."; \n@\$replacements['{".$rlfld."}'] = strlen(".$filt_fld."); \n@\$replacements['{".$rcfld."}'] = count(explode('".$list_delim."', ".$filt_fld.")); \n\$out[".$idx."] = (".str_replace("VALUE", (($smd_prefilter==="''" && strpos($op, "strpos") !== false) ? "' '" : $smd_prefilter), $cmd).") ? 'true' : 'false';";
			if ($debug) {
				dmp($cmd);
			}
			// ... and evaluate the expression
			eval($cmd);
		}
	}
	if ($debug) {
		echo "RESULT:";
		dmp($out);
		echo "REPLACEMENTS:";
		dmp($replacements);
	}
	if ($debug > 2) {
		if ($pretext) {
			echo "PRETEXT:";
			dmp($pretext);
		}
		if ($thisarticle) {
			echo "THIS ARTICLE:";
			dmp($thisarticle);
		}
		if ($thisfile) {
			echo "THIS FILE:";
			dmp($thisfile);
		}
		if ($thislink) {
			echo "THIS LINK:";
			dmp($thislink);
		}
		if ($thisimage) {
			echo "THIS IMAGE:";
			dmp($thisimage);
		}
		if ($thiscategory) {
			echo "THIS CATEGORY:";
			dmp($thiscategory);
		}
		if ($thissection) {
			echo "THIS SECTION:";
			dmp($thissection);
		}
		if ($thispage) {
			echo "THIS PAGE:";
			dmp($thispage);
		}
		if ($thiscomment) {
			echo "THIS COMMENT:";
			dmp($thiscomment);
		}
		if ($prefs) {
			echo "PREFS:";
			dmp($prefs);
		}
	}

	// Check logic
	$result = ($out) ? true : false;
	if (strtolower($logic) == "and" && in_array("false", $out)) {
		$result = false;
	}
	if (strtolower($logic) == "or" && !in_array("true", $out)) {
		$result = false;
	}

	return parse(EvalElse(strtr($thing, $replacements), $result));
}