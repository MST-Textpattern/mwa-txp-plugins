<?php


// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ('abc' is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'mwa_sitemap';


// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 0;


$plugin['version'] = '0.1';
$plugin['author'] = 'Maximilian Walter';
$plugin['author_uri'] = 'http://max-walter.net/redir/txp_plugins/mwa_sitemap';
$plugin['description'] = 'Sitemap-Generator for Textpattern';


// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = 9;


// Plugin 'type' defines where the plugin is loaded
// 0 = public       : only on the public side of the website (default)
// 1 = public+admin : on both the public and admin side
// 2 = library      : only when include_plugin() or require_plugin() is called
// 3 = admin        : only on the admin side
$plugin['type'] = 3;


// Plugin 'flags' signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use.
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events


# $plugin['flags'] = PLUGIN_HAS_PREFS | PLUGIN_LIFECYCLE_NOTIFY;


// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## aritrary comment
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


if (0) {
?>
# --- BEGIN PLUGIN HELP ---

h1. Sitemap-Generator for Textpattern

Creates an "XML-Sitemap":http://www.sitemaps.org/. The plugin creates the files @sitemap.xml@ and @sitemap.xml.gz@^[1]^ in the document-root. Make sure that the webserver has sufficient rights.

The sitemap will updated automatically if something changes, but you can "click here":?event=mwa_sitemap_manually to update it manually.

Inspired by "rah_sitemap" from "Jukka Svahn":http://rahforum.biz/.

[1] Requires Zlib support in PHP

# --- END PLUGIN HELP ---
<?php
}


# --- BEGIN PLUGIN CODE ---

add_privs('mwa_sitemap_manually', '1,2');
register_callback('mwa_sitemap_manually', 'mwa_sitemap_manually');
register_callback('mwa_sitemap', 'list', 'list_multi_edit');
register_callback('mwa_sitemap', 'article', 'create');
register_callback('mwa_sitemap', 'article', 'edit');
register_callback('mwa_sitemap', 'category', 'cat_article_save');
register_callback('mwa_sitemap', 'category', 'cat_article_create');
register_callback('mwa_sitemap', 'category', 'cat_category_multiedit');
register_callback('mwa_sitemap', 'section', 'section_create');
register_callback('mwa_sitemap', 'section', 'section_delete');
register_callback('mwa_sitemap', 'section', 'section_save');

function mwa_sitemap_manually() {
  require_privs('mwa_sitemap_manually');
  pagetop('');
  echo '<div style="width: 510px; margin: 0 auto;">';
  if (mwa_sitemap()) {
    echo '<p>Sitemap successfully created!</p>';
  }
  else {
    echo "<p>The sitemap couldn't be created. Please check the filesystem-rights.</p>";
  }
  echo '<p><a href="?event=plugin">Return to plugins</a></p>';
  echo '</div>';
}

function mwa_sitemap() {
  # File path
  $file = $_SERVER['DOCUMENT_ROOT'] . '/sitemap.xml';

  # Compress sitemap?
  if (function_exists('gzencode')) {
    $useCompression = true;
    $fileCompressed = $file . '.gz';
  }
  else {
    $useCompression = false;
  }

  # Check file permissions
  if (
    ((!is_file($file) || ($useCompression && !is_file($fileCompressed))) && !is_writable(dirname($file))) ||
    (is_file($file) && !is_writable($file)) ||
    ($useCompression && is_file($fileCompressed) && !is_writable($fileCompressed))
  ) {
    return false;
  }

  if (!function_exists('permlinkurl')) {
    include_once txpath.'/publish/taghandlers.php';
  }

  $out =
    '<?xml version="1.0" encoding="utf-8"?>'.
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.
    '<url><loc>'.hu.'</loc></url>';

  # Get sections
  $rs = safe_rows(
    'name',
    'txp_section',
    "name != 'default' order by name asc"
  );

  foreach($rs as $a) {
    $url = pagelinkurl(array('s' => $a['name']));
    $out .= "<url><loc>{$url}</loc></url>";
  }

  # Get categories
  $rs = safe_rows(
    'name,type,id',
    'txp_category',
    "name != 'root' and type = 'article'"
  );

  foreach($rs as $a) {
    $url = pagelinkurl(array('c' => $a['name']));
    $out .= "<url><loc>{$url}</loc></url>";
  }

  # Get articles
  $rs = safe_rows(
    'ID, Section, Title, url_title, unix_timestamp(Posted) as Posted, unix_timestamp(LastMod) as LastMod',
    'textpattern',
    'Status IN (4, 5) and Posted <= now() and (Expires = \'0000-00-00 00:00:00\' or Expires >= now()) order by Posted desc'
  );

  foreach($rs as $a) {
    $url = permlinkurl($a);
    $lastMod = ($a['LastMod'] < $a['Posted']) ? date('c', $a['Posted']) : date('c', $a['LastMod']);
    $out .= "<url><loc>{$url}</loc><lastmod>{$lastMod}</lastmod></url>";
  }

  $out .= '</urlset>';

  if (!file_put_contents($file, $out)) {
    return false;
  }

  # Compress sitemap
  if ($useCompression) {
    $outCompressed = gzencode($out, 9);

    if (!file_put_contents($fileCompressed, $outCompressed)) {
      return false;
    }
  }

  return true;
}

# --- END PLUGIN CODE ---


?>