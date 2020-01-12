# Changelog

## 1.0.0 - 2020-01-12

* Registered plugin tag.

## 0.9.2 - 2016-04-20

* Add support for 'thiscategory', 'thissection', 'thispage' and 'thiscomment'.
* Add support for prefs matching.

## 0.9.1 - 2012-02-22

* Fix pretext checks for section/category (thanks, saccade).
* Enable explicit checks for pretext, file, link, image, article, category, section, page and comment.
* Add 'var_prefix' to allow nesting of smd_if tags.
* Add COUNT modifier (thanks, the_ghost).
* Add ESC and ESCALL modifiers.
* Fix checks for defined/undefined.

## 0.9.0 - 2010-03-02

* Internal code refactorisation.
* Allow multiple values to be read from multiple sources (thanks, speeke).
* Enhance replacement tags.

## 0.8.2 - 2010-03-02

* Add 'between' and 'range' (thanks, speeke).

## 0.8.1 - 2009-09-26

* Add parent TTL and KIDS modifiers (thanks, photonomad).
* Improve parent debug output.

## 0.8.0 - 2009-04-05

* Add filtering capability.

## 0.7.7 - 2009-03-22

* Add TRIM modifier (thanks, gomedia).

## 0.7.6 - 2009-03-20

* Add 'postvar' field type (thanks, kostas45).

## 0.7.5 - 2008-12-02

* Add 'divisible' operator (thanks, gomedia).
* Allow short-circuit of fields (thanks, redbot).

## 0.7.4 - 2008-10-13

* Bug fix the smd_if_ names of vals and fields to avoid clashes. Now numerically indexed.

## 0.7.3 - 2008-10-13

* Add NOSPACE support to 'begins', 'ends' and 'contains' (thanks, mapu).
* Add phpvar support, LEN modifier and length replacement tags (all thanks, the_ghost).

## 0.7.2 - 2008-10-01

* Add NOTAGS modifier (thanks, mapu).

## 0.7.1 - 2008-10-01

* Fix the fix for empty custom fields implemented in 0.7.0 (thanks, mapu/visualpeople).

## 0.7.0 - 2008-09-10

* Fix warning if empty custom field in value (thanks, visualpeople).
* Add txpvar support (thanks, the_ghost).
* Add thisimage support.
* Add operators 'in', 'notin' and the 'list_delim' attribute.
* Enable replacement tags for matched variables.

## 0.6.2 - 2008-06-11

* Fix incorrect result if `eval()` is empty.
* Add NULL field object.

## 0.6.1 - 2008-05-26

* Fix stupid oversight in field name generation to allow arbitrary names instead of forcing `$thisarticle` (thanks to Joana Carvalho for leading me to this).

## 0.6.0 - 2008-05-25

* Fix 'undefined index' errors (thanks, redbot and the_ghost).
* Add more pretext variables.
* Add more 'is' checks (and the NOSPACE modifier).
* Allow file and link tests (including parent categories).

## 0.5.1 - 2008-01-15

* Fix defined/undefined syntax error.
* Tighten isused/isempty to distinguish them from defined/undefined.

## 0.5.0 - 2008-01-14

* Add case_sensitive option (thanks, the_ghost).
* Make 'contains' the default for 'parent' tests (thanks, the_ghost).
* Improve help (thanks, the_ghost).
* Add 'delim' options.

## 0.4.1 - 2008-01-06

* Fix lower case field names and undefined index error (thanks, peterj).

## 0.4.0 - 2008-01-06

* Add `?` notation to allow the value to read Txp fields (thanks, NeilA).
* Better quote support (thanks, NeilA).

## 0.3.0 - 2008-01-02

* Add defined/undefined and strict numeric comparisons.

## 0.2.0 - 2007-12-30

* Add parent category checking (thanks, the_ghost).

## 0.1.0 - 2007-12-30

* Initial release






