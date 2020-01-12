<?php

// This is a PLUGIN TEMPLATE for Textpattern CMS.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ("abc" is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'smd_if';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 1;

$plugin['version'] = '1.0.0';
$plugin['author'] = 'Stef Dawson';
$plugin['author_uri'] = 'https://stefdawson.com/';
$plugin['description'] = 'Generic multiple if condition tests';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = '5';

// Plugin 'type' defines where the plugin is loaded
// 0 = public              : only on the public side of the website (default)
// 1 = public+admin        : on both the public and admin side
// 2 = library             : only when include_plugin() or require_plugin() is called
// 3 = admin               : only on the admin side (no Ajax)
// 4 = admin+ajax          : only on the admin side (Ajax supported)
// 5 = public+admin+ajax   : on both the public and admin side (Ajax supported)
$plugin['type'] = '0';

// Plugin "flags" signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '0';

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

/** Uncomment me, if you need a textpack
$plugin['textpack'] = <<< EOT
#@admin
#@language en-gb
abc_sample_string => Sample String
abc_one_more => One more
#@language de-de
abc_sample_string => Beispieltext
abc_one_more => Noch einer
EOT;
**/
// End of textpack

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
/**
 * smd_if
 *
 * A Textpattern CMS plugin for evaluating multiple conditional logic statements
 *  -> Test for (in)equality, less/greater than, divisible by, empty, used, defined, begins/ends, contains, in list, type...
 *  -> Supports and/or logic
 *  -> Data filtration options
 *
 * @author Stef Dawson
 * @link   https://stefdawson.com/
 */

if (class_exists('\Textpattern\Tag\Registry')) {
    Txp::get('\Textpattern\Tag\Registry')
        ->register('smd_if');
}

function smd_if($atts, $thing)
{
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

    // Special field names that refer to $pretext or elsewhere - everything else is
    // assumed to exist in $thisarticle so custom fields can be used.
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

    // Each entry has the operation to be eval()d later and a list of disallowed fields.
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
    $parentCats = ''; // Placeholder for the concatenated list of category leaf nodes.

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
        $fld = ($idx < $numFlds) ? $fields[$idx] : $fields[0]; // Allow short-circuit.
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

        // Get the operator replacement code.
        $exclude = do_list($allOps[$op][1]);
        $op = $allOps[$op][0];

        // As long as the current operator allows this field...
        if (!in_array($fldParts[0], $exclude)) {
            // Make up the test field variable.
            if ($fldParts[0] == 'file') {
                $rfld = $fldParts[1];
                $fld = '$thisfile["'.$rfld.'"]';
            } elseif (isset($thisfile[$fldParts[0]])) {
                $rfld = $fldParts[0];
                $fld = '$thisfile["'.$rfld.'"]';
            } elseif ($fldParts[0] == 'link') {
                $rfld = $fldParts[1];
                $fld = '$thislink["'.$rfld.'"]';
            } elseif (isset($thislink[$fldParts[0]])) {
                $rfld = $fldParts[0];
                $fld = '$thislink["'.$rfld.'"]';
            } elseif ($fldParts[0] == 'image') {
                $rfld = $fldParts[1];
                $fld = '$thisimage["'.$rfld.'"]';
            } elseif (isset($thisimage[$fldParts[0]])) {
                $rfld = $fldParts[0];
                $fld = '$thisimage["'.$rfld.'"]';
            } elseif ($fldParts[0] == 'category') {
                $rfld = $fldParts[1];
                $fld = '$thiscategory["'.$rfld.'"]';
            } elseif (isset($thiscategory[$fldParts[0]])) {
                $rfld = $fldParts[0];
                $fld = '$thiscategory["'.$rfld.'"]';
            } elseif ($fldParts[0] == 'section') {
                $rfld = $fldParts[1];
                $fld = '$thissection["'.$rfld.'"]';
            } elseif (isset($thissection[$fldParts[0]])) {
                $rfld = $fldParts[0];
                $fld = '$thissection["'.$rfld.'"]';
            } elseif ($fldParts[0] == 'page') {
                $rfld = $fldParts[1];
                $fld = '$thispage["'.$rfld.'"]';
            } elseif (isset($thispage[$fldParts[0]])) {
                $rfld = $fldParts[0];
                $fld = '$thispage["'.$rfld.'"]';
            } elseif ($fldParts[0] == 'comment') {
                $rfld = $fldParts[1];
                $fld = '$thiscomment["'.$rfld.'"]';
            } elseif (isset($thiscomment[$fldParts[0]])) {
                $rfld = $fldParts[0];
                $fld = '$thiscomment["'.$rfld.'"]';
            } elseif ($fldParts[0] == 'pretext') {
                $rfld = $fldParts[1];
                $fld = '$pretext["'.$rfld.'"]';
            } elseif (array_key_exists($fldParts[0], $allPtxt)) {
                $rfld = $fldParts[0];
                $fld = $allPtxt[$rfld];
            } elseif ($fldParts[0] == 'pref') {
                $rfld = $fldParts[1];
                $fld = '$prefs["'.$rfld.'"]';
            } elseif ($fldParts[0] == "parent") {
                $treeField = 'name';
                $level = '';

                foreach ($fldParts as $part) {
                    if ($part == "parent") {
                        $theCat = ($thisfile) ? $thisfile['category'] : (($thislink) ? $thislink['category'] : (($thisimage) ? $thisimage['category'] : (($thiscategory['name']) ? $thiscategory['name'] : $pretext['c'])));
                    } elseif (strpos($part, "CAT") === 0) {
                        $theCat = $thisarticle["category".substr($part, 3)];
                    } elseif (strpos($part, "LVL") === 0) {
                        $level = substr($part, 3);
                    } elseif (strpos($part, "TTL") === 0) {
                        $treeField = 'title';
                    } elseif (strpos($part, "KIDS") === 0) {
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
                    } elseif ($level == '' || $level == $leaf['level']) {
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
            } elseif ($fldParts[0] == "txpvar") {
                if (count($fldParts) > 1) {
                    $rfld = $fldParts[1];
                    $fld = '$variable["'.$rfld.'"]';
                }
            } elseif ($fldParts[0] == "urlvar") {
                if (count($fldParts) > 1) {
                    $rfld = $fldParts[1];
                    $fld = '$_GET["'.$rfld.'"]';
                }
            } elseif ($fldParts[0] == "postvar") {
                if (count($fldParts) > 1) {
                    $rfld = $fldParts[1];
                    $fld = '$_POST["'.$rfld.'"]';
                }
            } elseif ($fldParts[0] == "svrvar") {
                if (count($fldParts) > 1) {
                    $rfld = $fldParts[1];
                    $fld = '$_SERVER["'.$rfld.'"]';
                }
            } elseif ($fldParts[0] == "phpvar") {
                if (count($fldParts) > 1) {
                    $rfld = $fldParts[1];
                    $fld = '$GLOBALS["'.$rfld.'"]';
                }
            } elseif ($fldParts[0] == 'article') {
                $rfld = strtolower($fldParts[1]);
                $fld = '$thisarticle["'.$rfld.'"]';
            } elseif (isset($thisarticle[$fldParts[0]])) {
                $rfld = strtolower($fldParts[0]);
                $fld = '$thisarticle["'.$rfld.'"]';
            } elseif ($fldParts[0] == "NULL") {
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

            // Take a copy of $fld to use in any isset() requests.
            $fldClean = $fld;

            // Apply user-defined field filters.
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

            // Find the real value to compare against (may be another field).
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
                } elseif ($valParts[0] == "postvar") {
                    if ($numValParts > 1) {
                        $vfld = $valParts[1];
                        $val = (isset($_POST[$vfld]) && $_POST[$vfld] != "") ? '$_POST["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
                    }
                } elseif ($valParts[0] == "svrvar") {
                    if ($numValParts > 1) {
                        $vfld = $valParts[1];
                        $val = (isset($_SERVER[$vfld]) && $_SERVER[$vfld] != "") ? '$_SERVER["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
                    }
                } elseif ($valParts[0] == "txpvar") {
                    if ($numValParts > 1) {
                        $vfld = $valParts[1];
                        $val = (isset($variable[$vfld]) && $variable[$vfld] != "") ? '$variable["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
                    }
                } elseif ($valParts[0] == "phpvar") {
                    if ($numValParts > 1) {
                        $vfld = $valParts[1];
                        $val = (isset($GLOBALS[$vfld]) && $GLOBALS[$vfld] != "") ? '$GLOBALS["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
                    }
                } elseif ($valParts[0] == "pref") {
                    if ($numValParts > 1) {
                        $vfld = $valParts[1];
                        $val = (isset($prefs[$vfld]) && $prefs[$vfld] != "") ? '$prefs["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
                    }
                } elseif ($valParts[0] == "file") {
                    if ($numValParts > 1) {
                        $vfld = $valParts[1];
                        $val = (isset($thisfile[$vfld]) && $thisfile[$vfld] != "") ? '$thisfile["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
                    }
                } elseif ($valParts[0] == "link") {
                    if ($numValParts > 1) {
                        $vfld = $valParts[1];
                        $val = (isset($thislink[$vfld]) && $thislink[$vfld] != "") ? '$thislink["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
                    }
                } elseif ($valParts[0] == "image") {
                    if ($numValParts > 1) {
                        $vfld = $valParts[1];
                        $val = (isset($thisimage[$vfld]) && $thisimage[$vfld] != "") ? '$thisimage["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
                    }
                } elseif ($valParts[0] == "category") {
                    if ($numValParts > 1) {
                        $vfld = $valParts[1];
                        $val = (isset($thiscategory[$vfld]) && $thiscategory[$vfld] != "") ? '$thiscategory["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
                    }
                } elseif ($valParts[0] == "section") {
                    if ($numValParts > 1) {
                        $vfld = $valParts[1];
                        $val = (isset($thissection[$vfld]) && $thissection[$vfld] != "") ? '$thissection["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
                    }
                } elseif ($valParts[0] == "page") {
                    if ($numValParts > 1) {
                        $vfld = $valParts[1];
                        $val = (isset($thispage[$vfld]) && $thispage[$vfld] != "") ? '$thispage["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
                    }
                } elseif ($valParts[0] == "comment") {
                    if ($numValParts > 1) {
                        $vfld = $valParts[1];
                        $val = (isset($thiscomment[$vfld]) && $thiscomment[$vfld] != "") ? '$thiscomment["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
                    }
                } elseif ($valParts[0] == "pretext") {
                    if ($numValParts > 1) {
                        $vfld = $valParts[1];
                        $val = (isset($pretext[$vfld]) && $pretext[$vfld] != "") ? '$pretext["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
                    }
                } elseif ($valParts[0] == "article") {
                    if ($numValParts > 1) {
                        $vfld = $valParts[1];
                        $val = (isset($thisarticle[$vfld]) && $thisarticle[$vfld] != "") ? '$thisarticle["'.$vfld.'"]' : doQuote(str_replace('"', '\"', $vfld));
                    }
                } elseif (strpos($valParts[0], "?") === 0) {
                    $valParts[0] = substr(strtolower($valParts[0]), 1);
                    $vfld = $valParts[0];

                    if (isset($thisfile[$vfld]) && $thisfile[$vfld] != "") {
                        $val = '$thisfile["'.$vfld.'"]';
                    } elseif (isset($thislink[$vfld]) && $thislink[$vfld] != "") {
                        $val = '$thislink["'.$vfld.'"]';
                    } elseif (isset($thisimage[$vfld]) && $thisimage[$vfld] != "") {
                        $val = '$thisimage["'.$vfld.'"]';
                    } elseif (isset($thiscategory[$vfld]) && $thiscategory[$vfld] != "") {
                        $val = '$thiscategory["'.$vfld.'"]';
                    } elseif (isset($thissection[$vfld]) && $thissection[$vfld] != "") {
                        $val = '$thissection["'.$vfld.'"]';
                    } elseif (isset($thispage[$vfld]) && $thispage[$vfld] != "") {
                        $val = '$thispage["'.$vfld.'"]';
                    } elseif (isset($thiscomment[$vfld]) && $thiscomment[$vfld] != "") {
                        $val = '$thiscomment["'.$vfld.'"]';
                    } elseif (array_key_exists($vfld, $allPtxt) && $allPtxt[$vfld] != "") {
                        $val = $allPtxt[$vfld];
                    } elseif (isset($thisarticle[$vfld]) && $thisarticle[$vfld] != "") {
                        $val = '$thisarticle["'.$vfld.'"]';
                    } else {
                        $val = doQuote(str_replace('"', '\"', $vfld));
                    }
                } else {
                    $vfld = $core_vfld.'_'.$valcnt;
                    $val = doQuote(str_replace('"', '\"', $valParts[0]));
                }

                // Apply user-defined value filters.
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

                // Replace the string parts by evaluating any variables.
                $filt_fld = ($do_ffilt) ? "preg_replace('$pat', '$rep', $fld)" : $fld;
                $filt_val = ($do_vfilt) ? "preg_replace('$pat', '$rep', $val)" : $val;
                eval("\$valRep[$jdx] = ".$filt_val.";");

                // Only add sub-values to the replacements array if there's more than one sub-value.
                if (count($valRep) > 1) {
                    $vflds[$var_prefix.$vfld] = $valRep[$jdx];
                    $vflds[$var_prefix."len_".$vfld] = strlen($valRep[$jdx]);
                }

                $valcnt++;
            }

            $joinedVals = join($list_delim, $valRep);
            $smd_prefilter = doQuote($joinedVals);

            // Add the combined operator for backwards compatibility with plugin v0.8.x.
            $vflds[$var_prefix.$core_vfld] = $joinedVals;
            $vflds[$var_prefix."len_".$core_vfld] = strlen($joinedVals);

            $cmd = str_replace("CAST", $cast, $op);
            $cmd = ($length) ? str_replace("FIELD", $length, $cmd) : $cmd;
            $cmd = ($count) ? str_replace("FIELD", $count, $cmd) : $cmd;
            $cmd = str_replace("FIELD", (($case_sensitive) ? $filt_fld : 'strtolower('.$filt_fld.')'), $cmd);
            $cmd = str_replace("VARNAME", $fldClean, $cmd);
            $cmd = str_replace("VALUE", (($case_sensitive) ? 'VALUE' : 'strtolower(VALUE)'), $cmd);

            // Value replacements have already been run through evil() so
            // they can be assigned directly.
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

    // Check logic.
    $result = ($out) ? true : false;

    if (strtolower($logic) == "and" && in_array("false", $out)) {
        $result = false;
    }

    if (strtolower($logic) == "or" && !in_array("true", $out)) {
        $result = false;
    }

    return parse(EvalElse(strtr($thing, $replacements), $result));
}
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
h1. smd_if

A generic 'if condition' tester. Can test any field or variable in the current article, file, image, link, URL var, @<txp:variable />@ or PHP context for a variety of attributes and take action if TRUE or FALSE.

h2. Features

* Supports most major article, file, image and link variables such as section, category, custom fields, id, query string, author, body, excerpt, yahde yahde, plus url vars, server vars, txp vars, php vars, and sub-category/parent checking
* Tests include equality, inequality, less than, greater than, divisible by, empty, used, defined, begins, ends, contains, is numeric / alpha / alphanumeric / lowercase / uppercase, among others
* Tests for more than one condition at once and applies either AND logic (all tests must pass) or OR logic (any test must pass)
* All tested fields and values are available to the container so you can display them
* Custom regular-expression filters are available to help weed out bad data
* Ugly and very dirty. Uses PHP's @eval()@ command which most programmers concur should be renamed @evil()@

h2. Installation / Uninstallation

Download the plugin from either "textpattern.org":https://textpattern.org/plugins/930/smd_if, or the "software page":https://stefdawson.com/sw, paste the code into the Txp _Admin->Plugins_ pane, install and enable the plugin. Visit the "forum thread":https://forum.textpattern.com/viewtopic.php?id=25357 for more info or to report on the success or otherwise of the plugin.

To uninstall, simply delete from the _Admin->Plugins_ page.

h2. Usage

Use the tag in any page, form or article context. Can also be used inside file, image, or link lists to take action depending on attributes of the current item.

h3(tag). smd_if

p(tag-summary). At the place you wish to compare a field with another value, put this tag with at least the @field@ attribute. Specify each field you wish to test as a series of comma-separated items -- though the comma is overridable with @param_delim@. If the result of all the comparison tests is TRUE the contained block will be executed. If the result is FALSE, any @<txp:else />@ will be executed instead. Without any @<txp:else />@ block, nothing is output if the result is FALSE.

*Attributes*

; %(atnm mand)field%
: Comma-separated list of "fields":#field to test.
; %(atnm)operator%
: Comma-separated list of "operations":#operator for comparison with the corresponding field (e.g. @eq@, @not@, @begins@, etc).
: Default: @eq@ for most tests, @contains@ for parent tests
; %(atnm)value%
: Comma-separated list of "values":#value with which to compare the corresponding fields.
; %(atnm)logic%
: How multiple tests are joined: Choose from:
:: @and@: all conditions must be met for a TRUE result.
:: @or@: any of the conditions that match will give a TRUE result.
: Default: @and@
; %(atnm)case_sensitive%
: Whether to perform case-sensitive comparisons. Note that if using @islower@ or @isupper@ in a comparison, case sensitivity will automatically be switched on while the test is taking place. Values:
:: 1 = yes
:: 0 = no
: Default: 0
; %(atnm)filter%
: List of regular expressions with which to filter one or more fields/values. See "filtering":#filtering.
; %(atnm)replace%
: List of items with which to replace each of the matching filters.
; %(atnm)filter_type%
: Limit the filter to certain types of test. Choose from any of:
:: @all@
:: @urlvar@
:: @postvar@
:: @svrvar@
:: @txpvar@
:: @phpvar@
:: or any field name
: Default: @all@
; %(atnm)filter_on%
: Apply the filter to either the @field@, the @value@ or both. Please see the "filtering caveat":#fcaveat below for important information about this attribute.
: Default: @field@
; %(atnm)param_delim%
: Delimiter used between each field, operator or value in a multi-test. You normally only need to change this if you have used that character in the name of a custom field, for example.
: Default:  comma (,)
; %(atnm)mod_delim%
: Delimiter used when specifying field or operator modifiers for:
:: urlvar, postvar, svrvar, txpvar
:: parent from @LVL@ and @CAT@
:: separating an operator from the NUM or NOSPACE modifiers
: Default: colon (:)
; %(atnm)list_delim%
: Delimiter used when specifying a list of values to check via the @in@, @notin@, @between@ and @range@ operators.
: Default: forward-slash (/)
; %(atnm)var_prefix%
: Prefix any replacement variable names with this string. If nesting smd_if tags, you will probably need to specify this on any inner smd_if tags to prevent the {replacement} variables clashing.
: Default: @smd_if_@

The lists are processed in order, i.e. the 1st field uses the 1st operator in the list and compares it to the 1st value; the 2nd field uses the 2nd operator and compares it to the 2nd value, and so on. Values should usually be specified with placeholders to maintain order: e.g. @value=", 4,, top"@

Note that, although the first three attributes are usually mandatory, if you happen to require the default operator for all your tests, you can safely omit @operator@. Similarly with values: if you are entirely testing the existence or type of variables, you can omit the @value@ parameter if you wish. And if you are testing the same @field@ again and again for differing conditions, you can list it just once as a convenient shortcut.

h3(atnm #field). field

List of field locations to look at. A non-exhaustive list of some useful values are:

* @s@ (global section) or @section@ (article section)
* @c@ (global category) or @category1@, or @category2@ if on an article page
* @authorid@ or @author@
* @id@ (file/link/image/article ID). In an individual article context, use @thisid@ instead
* @query@ (the query string from the search form)
* @pg@ (the current page number in lists)
* @month@ (current month field from the address bar)
* @status@ (document status: 200, 404, 403, etc)
* @page@ (the Txp Page template used by this section)
* @next_id@ / @prev_id@ (ID of next/prev document if on article page)
* @next_title@ / @prev_title@(Title of next/prev document if on article page)
* @next_utitle@ / @prev_utitle@ (url-title of next/prev document if on article page)
* @permlink_mode@ (take action based on one of the messy/clean URL schemes)
* @other article/file/link field@ (e.g. body, excerpt, article_image, keywords, linkanme, filename, downloads, ...)
* @my_custom_field_name@ (any custom fields you have defined)
* @urlvar:var_name@ (any variable in the address bar after the @?@)
* @postvar:var_name@ (any variable posted from an HTML form)
* @svrvar:var_name@ (any standard server variable, e.g. HTTP_USER_AGENT)
* @txpvar:my_var@ (any Textpattern variable set with @<txp:variable name="my_var" />@)
* @phpvar:my_var@ (any PHP variable _in the global scope_)
* @parent:optional_modifiers@ (whether the given category is a descendent of another category)
* @NULL@ (useful when comparing arbitrary values for emptiness)

If you specify a field name that does not exist, the text you use will be taken verbatim in most cases.

To avoid ambiguity you can prefix the field name with one of @pretext@, @article@, @image@, @link@ or @file@, separating it from the field by @mod_delim@.

If you wish to compare a field that might contain HTML (e.g @body@), add the modifier @:NOTAGS@ to the end of the field. It will have its HTML and PHP tags stripped from it and will also be trimmed to remove leading and trailing spaces. You may choose to solely remove spaces from both the start and end of any field by adding @:TRIM@ to the end of the field.

If you suspect a field might contain HTMLish input like @<@, @>@, @&@ or quotes/apostrophes you can elect to convert them to entities like @&lt;@, @&quot;@, etc. Just specify the @:ESC@ modifier to replace everything except apostrophes with its entity equivalent, or use @:ESCALL@ to include apostrophes.

The special field @parent@ checks the parent category for a match. Unlike the other field types, the default "operator":#operator for parent is 'contains'. This is because the entire tree is checked for a match, starting from the top of the tree down to the current category. Internally, the plugin makes up a "breadcrumb trail" of categories in the current branch, each separated by a space, so testing for equality would require putting them all in the "value":#value parameter.

You are of course free to choose an alternative operator; @begins@ is very useful for testing if the top level category matches the one given in the @value@ field.

If you use @parent:LVLn@, the comparison will be restricted to that "level" of sub-category; LVL1 is the "top" level, LVL2 is the next sub-category level, and so on. When using these modifiers, the 'eq' operator becomes more useful because you are comparing a single parent category.

If you wish to compare against the category's title instead of its name, add the @:TTL@ modifier. To test the number of children the given category has, specify @:KIDS@. Note that TTL and KIDS are mutually exclusive and if they are both employed, the last one used takes priority.

When using articles, you can further modify the behaviour of the parent using the @CATn@ syntax (where 'n' is 1 or 2). Specifying "parent" without @CATn@ will use the global category (@?c=@). If you add @:CATn@ it will instead compare the article's category1 or category2 respectively.

You can use CAT, LVL and TTL/KIDS in combination, independently or not at all. This allows comparisons such as "if the 2nd sub-category of category1 equals blahblah" or "if category2 is a child of blahblah". See "Example 4":#eg4.

One other special field is @NULL@. This is exactly what it says it is: empty. The reason for its inclusion is that sometimes you wish to test something that isn't a true variable -- e.g. a replacement variable from smd_vars or smd_each -- to see if it's empty or not.

If you were to put this:

@<txp:smd_if field="{result}" operator="isempty">@

you would not get the result you expect (it's pretty esoteric but it revolves around the fact that @""@ (as a variable name) is not empty, it's invalid). To get round this you may use NULL as a placeholder and move the thing you want to check into the @value@ instead, e.g:

@<txp:smd_if field="NULL" operator="eq" value="{result}">@

will test the NULL object (i.e. 'emptiness') to see if it's equal to the @{result}@ replacement variable. You can use similar logic to test for optional variables by swapping the field and value, like this:

@<txp:smd_if field="7" operator="gt" value="{result}">@

That would see if the replacement variable @{result}@ was less than or equal to 7 (that's not a typo, the logic is reversed in this case: it is interpreted as: "is 7 greater than {result}", which is the same as "is {result} less than or equal to 7"!)

h3(atnm #operator). operator

List of operators to apply, in order, to each field. Choose from:

* @eq@ Equal (the default for all except 'parent')
* @not@ Not equal
* @lt@ Less than
* @gt@ Greater than
* @le@ Less than or equal to
* @ge@ Greater than or equal to
* @in@ Field is one of a list of values (use @list_delim@ to separate values)
* @notin@ Field is not one of the given list of values (use @list_delim@ to separate values)
* @divisible@ Field is exactly divisible by the value
* @between@ Field lies between the given values, exclusive (use @list_delim@ to separate values)
* @range@ Field is within the range of given values, inclusive (use @list_delim@ to separate values)
* @begins@ Field begins with a sequence of characters
* @contains@ Field contains a sequence of characters (default for 'parent')
* @ends@ Field ends with a sequence of characters
* @isempty@ Field is empty (contains nothing)
* @isused@ Field has some value
* @defined@ Field is set (useful with urlvar variables)
* @undefined@ Field is not set, or missing from the URL line
* @isnum@ Field is a number
* @isalpha@ Field contains characters only
* @isalnum@ Field contains alphanumeric characters only
* @islower@ Every character in the field is lower case
* @isupper@ Every character in the field is upper case
* @ispunct@ Every character in the field is some punctuation mark
* @isspace@ Every character in the field is a whitespace character (or tab, newline, etc)

With the comparison operators (primarily gt, lt, ge, le) you may find odd behaviour when comparing numbers. For example, @urlvar:pic gt 6@ will return TRUE if @pic@ is set to "fred". This is because the word "fred" (or at least the "character f") is greater in the ASCII table than the "character 6".

To circumvent this problem, you may append @:NUM@ to the end of any of these operators to force the plugin to check that the values are integers.

If you wish to compare the length of a field, append @:LEN@ to any of the numerical comparison operators. It doesn't make sense to use both @:NUM@ and @:LEN@ together, so they are mutually exclusive.

If you wish to compare the quantity (count) of a list of items in a field, append @:COUNT@ to any of the numerical comparison operators. Again, this option is mutually exclusive with all other modifiers.

There is a subtle difference between the operators @between@ and @range@; the former does not include the endpoint values you specify whereas the latter does. For example, the value @10@ is in the range @10/20@ but it is _not_ between @10/20@. The value @11@, however, satisfies both. You can use the @:NUM@ modifier for these two operators, though most tests will work fine without it, and you may check if list @:COUNT@ values lie within two endpoints. You can also compare non-integer values with these operators.

The @begins@, @ends@ and @contains@ operators, along with any of the @is@ operators (except @isspace@), can take an extra parameter as well. Since they compare every character against the given behaviour, space characters can mess things up a bit. For example @field="custom1" operator="islower"@ will fail if custom1 contains "this is a test". Or comparing something to @body@ can fail because the body often starts with a number of space characters. To circumvent this, add @:NOSPACE@ to the operator which will remove all spaces from the string before testing it.

Note also that while @defined@ and @undefined@ differ semantically from @isused@ and @isempty@ (respectively), the way Txp assigns variables means that, for the most part, the terms are interchangeable. When dealing with urlvars, postvars and svrvars, the two sets of operators behave independently, as you would expect. See "Example 5":#eg5 for more. Neither @defined@ nor @undefined@ make sense with @parent@, so they are forbidden.

h3(atnm #value). value

List of values to compare each field in turn to. Can be static values/text or the name of any Txp field, like those given in "field":#field (except "parent").

To distinguish a Txp field from static text, prefix the field name with @?@. For example: @value="title"@ will compare your chosen field against the word "title", whereas @value="?title"@ will compare your field against the current article's title.

If you wish to compare a value that might contain HTML (e.g @?body@), add the modifier @:NOTAGS@ to the end of the value. It will have any HTML and PHP tags stripped from it and will also be trimmed to remove leading and trailing spaces. You may choose to solely remove spaces from both the start and end of any value by adding @:TRIM@ to the end of the value.

If you suspect a value might contain HTMLish input like @<@, @>@, @&@ or quotes/apostrophes you can elect to convert them to entities like @&gt;@, @&quot;@, etc. Just specify the @:ESC@ modifier to replace everything except apostrophes with its entity equivalent, or use @:ESCALL@ to include apostrophes.

Note that you may find using double-quotes in fields gives unexpected results. They are best avoided, or worked around by using @contains@ instead of @eq@.

h3. Replacement tags

Every field or value that you refer to in your smd_if tag becomes available within the containing block so you can display its contents if you wish. Most of the time this is not much use but it can be very useful with the @in@ operator or the @:LEN@ modifier. For instance, if you have asked smd_if to test the URL variable named 'level' and told it to compare it to the custom field labelled 'allowable_levels', two tags become available which you can use within the containing block:

* @{smd_if_level}@ would display the value of the 'level' URL variable.
* @{smd_if_allowable_levels}@ would display the contents of the current article's custom field.

By default the replacement tags are prefixed with @smd_if_@ so they don't clash with the ones in smd_gallery (for example, when using smd_if inside an smd_gallery tag). You can change this prefix with the @var_prefix@ attribute.

If you are comparing a fixed-value field (such as @field="NULL"@ or @value="12"@ or smd_gallery's @value="{category}"@) the name of the replacement tags are @{smd_if_fieldN}@ for fields and @{smd_if_valN}@ for values, where N is the test number starting from 1.

If you use the multiple value options such as @in@, @between@, @range@, etc you will also see replacement tags of the following format: @{smd_if_valN_X}@ where N is the value counter (as above) and X is an incrementing number; with one for each value in your list. For example, @value="10/20/30"@ sets:

* @{ smd_if_val1_1 }@ = 10
* @{ smd_if_val1_2 }@ = 20
* @{ smd_if_val1_3 }@ = 30

There are also 'length' replacement tags. Following a similar convention to above, these are prefixed with @smd_if_len_@. If you get stuck, temporarily switch @debug="1"@ on to see the replacements available and their associated names/values.

See "Example 8":#eg8 and "9":#eg9 for more.

h2(#filtering). Filtering

All user input is tainted by default.

Any time you rely on someone to enter something, at least one person will invariably catch you out; either accidentally or maliciously. For this reason, smd_if supports powerful filtering rules so you can trap and remove suspect input. It already does some of this for you with the NOTAGS modifier, but filtering gives another level of control above that.

Let's say you are asking the user to enter a value and you need to compare it to a range. What if, instead of a number, they entered @fr3d@ or @;rm -rf 1*;@. The plugin might just fall over (probably with an error, which is not very pleasant for users), or it might cause harm to the filesystem if a person trying to hack your site was skilled enough (it's unlikely, but possible).

In both cases, filtering comes to the rescue. You can specify that you _expect_ only digits from 0 to 9 in your fields and can tell the plugin to chop out anything that is not numeric. So in the first case above, all you would see if the user entered 'fr3d' would be '3'.

Fortunately -- and unfortunately -- it's built around "regular expressions":https://www.regular-expressions.info/ which are fiendishly powerful but can also be fiendishly tricky to learn if you are not familiar. They are worth learning.

Let's dive in at the deep end and tell the plugin that, under no circumstances, must we allow any input that is non-numeric:

bc(block). <txp:variable name="highest" value="100" />
<txp:smd_if field="urlvar:low" operator="ge, le"
     value="1, txpvar:highest" filter="/[^0-9]+/">
   <p>{smd_if_low} is valid and is between
   {smd_if_val1} and {smd_if_highest}.</p>
<txp:else />
   <p>Sorry, the value {smd_if_low} is not within the
     range {smd_if_val1}-{smd_if_highest}.</p>
</txp:smd_if>

[ Eagle-eyed people may notice that something similar can be achieved with the @:NUM@ modifier. The difference here is that the replacement variables are also filtered, whereas with @:NUM@ they contain the original (possibly invalid) input ]

Although out of the scope of this documentation, it's worth just taking a moment to see what the filter is doing:

bc. /[^0-9]+/

* The forward slashes are start and end delimiters and should always be present (unless you know what you're doing!)
* The square brackets @[]@ denote a character class, or group of characters. In this case they contain the range of digits 0 to 9
* The circumflex @^@ negates the class (i.e. non-digits)
* The plus @+@ means 'one or more of the things I've just seen'

So putting it all together, it reads "Find every occurrence of one or more non-digits". Thus it looks at every field and finds anything non-numeric. Then it replaces whatever it finds with @''@, i.e. nothing, nada, zip. Effectively, it deletes whatever non-digits it finds and leaves the good stuff (the numbers) behind.

h3(#freplacing). Replacing bad data

For each matching filter there's an equivalent @replace@ string. By default this is set to @replace=""@ which means "replace whatever you find with nothing"; or in other words "delete everything that matches the filter". You may elect to replace your filtered data with something else, say, @replace="txp"@. So if someone entered "fr3d" you would see the replacement variable has a value "txp3txp" (the reason you only get one 'txp' before the number is due to the expression being _greedy_ and gobbling up as many characters as it can in a group before replacing them. See any regex tutorial for more on this topic).

Under normal circumstances you won't want to mess with @replace@ as it'll do what you want with the default 'delete' operation.

h3(#foptions). Filtering options

By default, the plugin only looks at @field@ data. If you wish to change that, use the @filter_in@ attribute.

The plugin also applies the filter to all fields (@filter_type="all"@). You may wish to only target a filter at url and server vars, in which case you would specify @filter_type="urlvar, svrvar"@. Or maybe you wish to validate the article image field in case someone entered some rogue data there: @filter_type="article_image"@.

h3(#fcaveat). Filtering caveat

If you specify @filter_on="field, value"@ it is important to note that the _same filter_ will be applied to each corresponding field and value. If your filter is too strict there's a chance it may filter every character out of both @field@ and @value@, thus if your test was for equality the test would return 'true'. Here's an example:

bc(block). <txp:smd_if field="urlvar:comp1" operator="eq"
     value="urlvar:comp2" filter="/[^a-zA-Z]+/"
     filter_on="field, value">
   // I'm NOT necessarily valid
</txp:smd_if>

If your user typed in the URL @site.com/article?comp1=12345&comp2=67890@ the plugin would do the following:

# Filter comp1 and remove all non-letter characters
# Filter comp2 and remove all non-letter characters
# Look at comp1 (which is now empty) and comp2 (which is also empty), then compare them

You can see what's going to happen: the test result is going to be 'true' because "nothing" does indeed equal "nothing". So the act of the user entering two nonsensical, completely numeric strings of data has broken your logic.

For this reason, if you are filtering on both field and value, you should perform _additional_ tests to see if either field / value is set at all. This is better:

bc(block). <txp:smd_if field="urlvar:comp1, urlvar:comp1, urlvar:comp2"
     operator="eq, isused, isused" value="urlvar:comp2"
     filter="/[^a-zA-Z]+/"
     filter_on="field, value">
   // I'm now actually valid
</txp:smd_if>

So now, if your filter removes everything from both URL vars, it still fails the 'has the user entered anything at all' tests because as far as the plugin is concerned, the visitor has submitted rubbish.

h3(#ffurther). Going further

The above examples all use a single filter. You can specify more than one filter and replacement if you wish, just comma-delimit them (unless you've overridden the @param_delim@ of course).

When you specify more than one filter, they ignore the @filter_type@ attribute because the filters are applied in order; one per test. If you wish to skip a particular field and not apply a filter, simply leave an empty comma as a placeholder, e.g. @filter=", /[^a-zA-Z0-9\-]+/, , /[^0-9]+/"@ would apply the respective filters to the 2nd and 4th tests only.

h2(#eg1). Example 1: standard comparison

bc(block). <txp:smd_if field="section, id"
     operator="begins, gt"
     value="lion, 12">
 <p>The lion sleeps tonight</p>
<txp:else />
 <p>Roooooarrrr! *CHOMP*</p>
</txp:smd_if>

Checks if the current section name begins with the word "lion" and the article ID is greater than 12. Displays "The lion sleeps tonight" if both conditions are met or displays the text "Roooooarrrr! *CHOMP*" if not.

h2(#eg2). Example 2: other types of field

bc(block). <txp:smd_if field="summary, category1, urlvar:pic"
     operator="isused, eq, isnum"
     value=", animal ," >
  <p>All matched</p>
<txp:else />
 <p>Match failed</p>
</txp:smd_if>

Checks if the custom field labelled "summary" has some data in it, checks if category1 equals "animal" and tests if the urlvar @pic@ is numeric (e.g. @?pic=5@).

If all these conditions are met the "All matched" message is displayed, else the "Match failed" message is shown. Note that @isused@ and @isnum@ don't take arguments for @value@ and their positions are held by empty commas (technically the last comma isn't needed but it helps keep everything neat if you add further tests later on).

h2(#eg3). Example 3: using 'or' logic

bc(block). <txp:smd_if field="article_image, svrvar:HTTP_USER_AGENT"
     operator="eq, contains"
     value="urlvar:pic, Safari"
     logic="or">
 <p>Come into my parlour</p>
<txp:else />
 <p>Not today, thanks</p>
</txp:smd_if>

Compares (for equality) the current article image id with the value of the url variable @pic@ and checks if the value of the HTTP_USER_AGENT string contains "Safari". This example uses the 'or' logic, hence if _either_ condition is met the 'come into my parlour' message is shown, otherwise the 'not today' message is displayed.

h2(#eg4). Example 4: sub-category testing

bc(block). <txp:smd_if field="parent:LVL2"
     operator="eq"
     value="mammal">
 <txp:article />
<txp:else />
 <p>Not today, thanks</p>
</txp:smd_if>

On a category list page, this checks the 2nd sub-category of the tree to see if it equals "mammal". If it does, the article is displayed; if not, the message is shown instead. Removing the @:LVL2@ -- which means you can also remove the operator parameter to force the comparison to be the default "contains" -- checks if the current (global) category is a child of 'mammal' at any nesting level.

Move the example into an article or article Form and change the field to @parent:CAT1@ to see if the article's category1 matches 'mammal' at any level, or use @field="parent:CAT1:LVL2"@ to combine the checks.

h2(#eg5). Example 5: defined/undefined/isused/isempty

bc(block). <txp:smd_if field="urlvar:pic, urlvar:page"
     operator="gt:NUM, undefined"
     value="?article_image,">
 <p>Yes please</p>
<txp:else />
 <p>Not today, thanks</p>
</txp:smd_if>

Tests if the url variable @pic@ is strictly numerically greater than the value in the current article's @article_image@ field and that the url variable @page@ is missing from the URL address. Compare the outcome of this test with the other operators using the following table when testing the @page@ urlvar:

|_. URL |_. defined |_. undefined |_. isused |_. isempty |
| index.php?pag | FALSE | TRUE | FALSE | FALSE |
| index.php?page= | TRUE | FALSE | FALSE | TRUE |
| index.php?page=4 | TRUE | FALSE | TRUE | FALSE |

h2(#eg6). Example 6: short circuiting the @field@

Put this inside your @plainlinks@ form and execute a @<txp:linklist />@ from an article page/form:

bc(block). <txp:smd_if field="id"
     operator="ge:NUM, le:NUM"
     value="urlvar:min, urlvar:max">
  <txp:linkdesctitle /><br />
</txp:smd_if>

That will list only the links that have IDs between the @min@ and @max@ variables specified on the address bar. Notice that the id field is only listed once and each operator is applied to it in turn.

h2(#eg7). Example 7: alphanumeric testing

bc(block). <txp:smd_if field="urlvar:product_code"
     operator="isalnum">
  <txp:output_form form="show_product" />
<txp:else />
 <p>Invalid product code</p>
</txp:smd_if>

Tests to see if the product_code URL variable is alphanumeric and displays a form if so.

h2(#eg8). Example 8: displaying used values

bc(block). <txp:smd_if field="urlvar:sort_order"
     operator="in"
     value="id/price/size/colour">
  <p>Sorting values by {smd_if_sort_order}</p>
  // Do some stuff
<txp:else />
  // Use a default sort, or show an error here
</txp:smd_if>

By using the replacement tag {smd_if_sort_order} you have plucked the value from the URL bar and inserted it into the article. Useful when using the @in@ or @notin@ operators because, although you know that the field matched one of the values in your list, you would otherwise not know which one has been given on the address bar. If you specify the @debug@ attribute in tags like these you can more easily see what replacements are available.

h2(#eg9). Example 9: using phpvar

bc(block). <txp:php>
global $bodyex;
$bodyex = excerpt(array()).body(array());
</txp:php>
<txp:smd_if field="phpvar:bodyex"
     operator="gt:LEN"
     value="300">
  <p>You are a big boy at {smd_if_len_bodyex}
     characters long!</p>
</txp:smd_if>

If put in an article Form (NOT directly in an article or you'll get an out of memory error!), this checks the excerpt and body and shows the message if the combined total length is more than 300 characters.

h2(#eg10). Example 10: @between@ and @range@

Check that the given URL params @day@ and @month@ are within reasonable tolerance. Note that it's still possible to specify the 31st February so you may require extra checks in the container.

bc(block). <txp:smd_if field="urlvar:day, urlvar:month"
     operator="between, between" value="0/32, 0/13">
   // Likely a valid date supplied
</txp:smd_if>

This is functionally equivalent, and probably more obvious to anyone else reading the code:

bc(block). <txp:smd_if field="urlvar:day, urlvar:month"
     operator="range, range" value="1/31, 1/12">
   // Likely a valid date supplied
</txp:smd_if>

If you wanted to factor the year in and make sure that nobody used a year less than 1900 or greater than the current year, try this:

bc(block). <txp:php>
global $thisyear;
$thisyear = date("Y");
</txp:php>
<txp:smd_if field="urlvar:d, urlvar:m, urlvar:y"
     operator="range, range, range"
     value="1/31, 1/12, 1900/phpvar:thisyear">
   // Likely a valid date supplied
</txp:smd_if>

h2(#eg11). Example 11: reading multiple values from different places

bc(block). <txp:variable name="ltuae">42</txp:variable>
<txp:smd_if field="urlvar:trigger"
     operator="in" value="3/15/36/txpvar:ltuae/180/?secret">
   <p>You found one of the magic numbers</p>
</txp:smd_if>

First of all we set up the Txp variable, then test the URL variable @trigger@ and see if it is one of the numbers listed in the @value@ attribute. Note that we have specified the Txp variable as one of the numbers, and also the contents of the custom field called @secret@. Essentially, this builds up the value attribute from all the sources and tests the final result. So if secret held the number 94, this smd_if tag checks if trigger is one of 3, 15, 36, 42, 180 or 94 and displays the message if so. If @secret@ instead contained @94/101/248@ these three values would also be tested as part of the @in@ operator.

h2(#eg12). Example 12: item counts

This counts the number of items in @my_list@ and tests them against the @value@. Note that @list_delim@ is used between list items.

bc. <txp:variable name="my_list">8 / 42 / 11 / 75 / 14</txp:variable>
<txp:smd_if field="txpvar:my_list" operator="gt:COUNT" value="3">
   Yes, there are {smd_if_val1} or more values
   (actually: {smd_if_count_my_list})
<txp:else />
  There are fewer than {smd_if_val1} values in the list.
</txp:smd_if>

h2. Author

"Stef Dawson":https://stefdawson.com/contact. Based on an idea brewing in the back of my mind while hacking chs_if_urlvar.

# --- END PLUGIN HELP ---
-->
<?php
}
?>