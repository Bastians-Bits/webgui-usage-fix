<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Helpers.php";
extract(parse_plugin_cfg('dynamix',true));

// add translations
$_SERVER['REQUEST_URI'] = '';
require_once "$docroot/webGui/include/Translations.php";

$var = parse_ini_file("/var/local/emhttp/var.ini");
?>
<!DOCTYPE HTML>
<html <?=$display['rtl']?>lang="<?=strtok($locale,'_')?:'en'?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta http-equiv="Content-Security-Policy" content="block-all-mixed-content">
<meta name="format-detection" content="telephone=no">
<meta name="viewport" content="width=1300">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="same-origin">
<link type="image/png" rel="shortcut icon" href="/webGui/images/<?=_var($var,'mdColor','red-on')?>.png">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-fonts.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-{$display['theme']}.css")?>">

<style>
.notice{background-image:none;color:#e68a00;font-size:6rem;text-transform:uppercase;text-align:center;padding:4rem 0}
#system,#array{margin:6rem 0;text-align:center;font-size:3rem}
</style>

<script src="<?autov('/webGui/javascript/dynamix.js')?>"></script>
<script src="<?autov('/webGui/javascript/translate.'.($locale?:'en_US').'.js')?>"></script>
<script>
/*
 * If we have a sessionStorage item for hiding the UPC's 'lets unleash your hardware' overlay for ENOKEYFILE state users
 * this will remove the item so that if the user reboots their server the overlay will display again once the server comes back up.
*/
const serverName = '<?=_var($var,'NAME')?>';
const guid = '<?=_var($var,'flashGUID')?>';
sessionStorage.removeItem(`${serverName}_${guid ? guid.slice(-12) : 'NO_GUID'}`);

var start = new Date();

var boot = new NchanSubscriber('/sub/var',{subscriber:'websocket'});
boot.on('message', function(msg) {
  var ini = parseINI(msg);
  switch (ini['fsState']) {
    case 'Stopped'   : var status = "<span class='red'><?=_('Array Stopped')?></span>"; break;
    case 'Started'   : var status = "<span class='green'><?=_('Array Started')?></span>"; break;
    case 'Formatting': var status = "<span class='green'><?=_('Array Started')?></span>&bullet;<span class='orange tour'><?=_('Formatting device(s)')?></span>"; break;
    default          : var status = "<span class='orange'>"+_('Array '+ini['fsState'])+"</span>";
  }
  if (ini['fsProgress']) status += "&bullet;<span class='blue tour'>"+_(ini['fsProgress'])+"</span>";
  $('#array').html(status);
});

function parseINI(msg) {
  var regex = {
    section: /^\s*\[\s*\"*([^\]]*)\s*\"*\]\s*$/,
    param: /^\s*([^=]+?)\s*=\s*\"*(.*?)\s*\"*$/,
    comment: /^\s*;.*$/
  };
  var value = {};
  var lines = msg.split(/[\r\n]+/);
  var section = null;
  lines.forEach(function(line) {
    if (regex.comment.test(line)) {
      return;
    } else if (regex.param.test(line)) {
      var match = line.match(regex.param);
      if (section) {
        value[section][match[1]] = match[2];
      } else {
        value[match[1]] = match[2];
      }
    } else if (regex.section.test(line)) {
      var match = line.match(regex.section);
      value[match[1]] = {};
      section = match[1];
    } else if (line.length==0 && section) {
      section = null;
    };
  });
  return value;
}
function timer() {
  var now = new Date();
  return Math.round((now.getTime()-start.getTime())/1000);
}
function reboot_now() {
  $('.notice').html("<?=_("Reboot")?>");
  boot.start();
  reboot_online();
}
function shutdown_now() {
  $('.notice').html("<?=_("Shutdown")?>");
  boot.start();
  shutdown_online();
}
function reboot_online() {
  $.ajax({url:'/webGui/include/ProcessStatus.php',type:'POST',data:{name:'emhttpd',update:true},timeout:5000})
  .done(function(){
    $('#system').html("<?=_("System is going down")?>... "+timer());
    setTimeout(reboot_online,5000);
  })
  .fail(function(){start=new Date(); setTimeout(reboot_offline,5000);});
}
function reboot_offline() {
  $.ajax({url:'/webGui/include/ProcessStatus.php',type:'POST',data:{name:'emhttpd',update:true},timeout:5000})
  .done(function(){location = '/Main';})
  .fail(function(){
    $('#system').html("<?=_("System is rebooting")?>... "+timer());
    setTimeout(reboot_offline,1000);
  });
}
function shutdown_online() {
  $.ajax({url:'/webGui/include/ProcessStatus.php',type:'POST',data:{name:'emhttpd',update:true},timeout:5000})
  .done(function(){
    $('#system').html("<?=_("System is going down")?>... "+timer());
    setTimeout(shutdown_online,5000);
  })
  .fail(function(){start=new Date(); setTimeout(shutdown_offline,5000);});
}
function shutdown_offline() {
  var time = timer();
  if (time < 30) {
    $('#system').html("<?=_("System is offline")?>... "+time);
    setTimeout(shutdown_offline,5000);
  } else {
    $('#system').html("<?=_("System is powered off")?>... "+time);
    setTimeout(power_on,1000);
  }
}
function power_on() {
  $.ajax({url:'/webGui/include/ProcessStatus.php',type:'POST',data:{name:'emhttpd',update:true},timeout:5000})
  .done(function(){location = '/Main';})
  .fail(function(){setTimeout(power_on,1000);});
}
$(document).ajaxSend(function(elm, xhr, s){
  if (s.type == "POST") {
    s.data += s.data?"&":"";
    s.data += "csrf_token=<?=_var($var,'csrf_token')?>";
  }
});
</script>
</head>
<?
$safemode = '/boot/unraidsafemode';
$progress = (_var($var,'fsProgress')!='')? "&bullet;<span class='blue tour'>{$var['fsProgress']}</span>" : '';

switch (_var($_POST,'cmd','shutdown')) {
case 'reboot':
  if (isset($_POST['safemode'])) touch($safemode); else @unlink($safemode);
  exec('/sbin/reboot -n');
  echo '<body onload="reboot_now()">';
  break;
case 'shutdown':
  if (isset($_POST['safemode'])) touch($safemode); else @unlink($safemode);
  exec('/sbin/poweroff -n');
  echo '<body onload="shutdown_now()">';
  break;
}
echo '<div class="notice"></div>';
echo '<div id="array">';
switch (_var($var,'fsState')) {
case 'Stopped':
  echo "<span class='red'>",_('Array Stopped'),"</span>$progress"; break;
case 'Starting':
  echo "<span class='orange'>",_('Array Starting'),"</span>$progress"; break;
case 'Stopping':
  echo "<span class='orange'>",_('Array Stopping'),"</span>$progress"; break;
default:
  echo "<span class='green'>",_('Array Started'),"</span>$progress"; break;
}
echo '</div>';
echo '<div id="system"></div>';
echo '</body>';
?>
</html>
