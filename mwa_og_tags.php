<?php


// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ('abc' is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'mwa_og_tags';


// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 0;


$plugin['version'] = '0.1';
$plugin['author'] = 'Maximilian Walter';
$plugin['author_uri'] = 'http://max-walter.net/redir/txp_plugins/mwa_og_tags';
$plugin['description'] = 'OpenGraph-Tags for Textpattern';


// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = 5;


// Plugin 'type' defines where the plugin is loaded
// 0 = public       : only on the public side of the website (default)
// 1 = public+admin : on both the public and admin side
// 2 = library      : only when include_plugin() or require_plugin() is called
// 3 = admin        : only on the admin side
$plugin['type'] = 0;


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

h1. OpenGraph-Tags for Textpattern

Integrates easily the meta-tags for the "Open Graph protocol":https://developers.facebook.com/docs/opengraph/.

h2. Usage

pre. <txp:mwa_og_tags tag="<property>" [value="<Value>"] [default="<Default value>"] />

If the value is empty, the script attempts to retrieve the correct values. This works only for the following properties:

* title (Value of <txp:page_title />)
* image (Value of <txp:article_image />)
* url (Value of the requested URL)
* description (Value of <txp:excerpt />, if empty the first 200 charakters of <txp:body />)
* site_name (Value of <txp:site_name />)

If nothing was found, the value of "default" is used.

h2. Examples

pre. <txp:mwa_og_tags tag="title" />
<txp:mwa_og_tags tag="image" />
<txp:mwa_og_tags tag="url" />
<txp:mwa_og_tags tag="description" default="Default text for non-articles" />
<txp:mwa_og_tags tag="type" value="blog" />
<txp:mwa_og_tags tag="site_name" />
<txp:mwa_og_tags tag="language" value="de-DE" />

# --- END PLUGIN HELP ---
<?php
}


# --- BEGIN PLUGIN CODE ---

function mwa_og_tags($atts) {
  extract($atts);

  if (empty($tag)) {
    return '';
  }

  if (empty($value)) {
    global $thisarticle,$conf;

    switch ($tag) {
      case 'title':
        $value = page_title(array());
        break;
      case 'image':
        $value = mwa_article_image_url();
        break;
      case 'url':
        $value = hu.substr($_SERVER['REQUEST_URI'], 1);
        break;
      case 'description':
        $value = mwa_excerpt();
        break;
      case 'site_name':
        $value = site_name();
        break;
      default:
        $value = '';
        break;
    }
  }
  
  if (empty($value) && !empty($default)) {
    $value = $default;
  }

  return sprintf('<meta property="og:%s" content="%s" />', $tag, htmlspecialchars($value));
}

function mwa_article_image_url() {
  global $thisarticle;
  
  if (!empty($thisarticle['article_image']) && intval($thisarticle['article_image'])) {
    $rs = safe_row('id, ext, thumbnail', 'txp_image', 'id = '.intval($thisarticle['article_image']));

    if ($rs) {
      if ($rs['thumbnail']) {
        return imagesrcurl($rs['id'], $rs['ext'], true);
      }
      else {
        return imagesrcurl($rs['id'], $rs['ext']);
      }
    }
  }
  
  return '';
}

function mwa_excerpt() {
  global $thisarticle;
  $retval = $thisarticle['excerpt'];
  if (empty($retval)) {
    $retval = trim(strip_tags(parse($thisarticle['body'])));
    if (200 < strlen($retval)) {
      $retval = substr($retval, 0, 197) . '...';
    }
  }
  $retval = str_replace("\n", " ", $retval);
  return $retval;
}

# --- END PLUGIN CODE ---


?>