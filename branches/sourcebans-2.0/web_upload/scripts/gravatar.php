<?php
header('Content-Type: text/javascript; charset=UTF-8');

if(!isset($_GET['id']))
  exit;
?>
window.addEvent('domready', function(e) {
  var parent = $('head-userbox');
  var size   = parent.getComputedSize();
  
  parent.set('styles', {
    'position': 'relative',
  });
  
  new Element('img', {
    'alt': 'Gravatar',
    'src': 'http://www.gravatar.com/avatar/<?php echo $_GET['id']; ?>?s=' + size['height'],
    'styles': {
      'top': size['padding-top'] + 'px',
      'right': size['padding-right'] + 'px',
      'position': 'absolute',
    },
    'title': 'Gravatar',
  }).inject(parent);
});