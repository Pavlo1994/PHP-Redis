<?php

require_once 'includes/common.inc.php';

$page['css'][] = 'frame';
$page['js'][]  = 'frame';

require 'includes/header.inc.php';



if (!isset($_GET['key'])) {
  ?>
  Invalid key
  <?php

  require 'includes/footer.inc.php';
  die;
}



$type   = $redis->type($_GET['key']);
$exists = $redis->exists($_GET['key']);

$count_elements_page = isset($config['count_elements_page']) ? $config['count_elements_page'] : false;
$page_num_request    = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page_num_request    = $page_num_request === 0 ? 1 : $page_num_request;



?>
<h2><?php echo format_html($_GET['key'])?>
<?php if ($exists) { ?>
  <a href="rename.php?s=<?php echo $server['id']?>&amp;key=<?php echo urlencode($_GET['key'])?>"><img src="images/edit.png" width="16" height="16" title="Rename" alt="[R]"></a>
  <a href="delete.php?s=<?php echo $server['id']?>&amp;key=<?php echo urlencode($_GET['key'])?>" class="delkey"><img src="images/delete.png" width="16" height="16" title="Delete" alt="[X]"></a>
  <a href="export.php?s=<?php echo $server['id']?>&amp;key=<?php echo urlencode($_GET['key'])?>"><img src="images/export.png" width="16" height="16" title="Export" alt="[E]"></a>
<?php } ?>
</h2>
<?php

if (!$exists) {
  ?>
  This key does not exist.
  <?php

  require 'includes/footer.inc.php';
  die;
}



$alt      = false;
$ttl      = $redis->ttl($_GET['key']);

try {
  $encoding = $redis->object('encoding', $_GET['key']);
} catch (Exception $e) {
  $encoding = null;
}


switch ($type) {
  case 'string':
    $value = $redis->get($_GET['key']);
    $size  = strlen($value);
    break;

  case 'hash':
    $values = $redis->hGetAll($_GET['key']);
    $size   = count($values);
    break;

  case 'list':
    $size = $redis->lLen($_GET['key']);
    break;

  case 'set':
    $values = $redis->sCard($_GET['key']);
    $size   = count($values);
    break;

  case 'zset':
    $values = $redis->zCard($_GET['key']);
    $size   = count($values);
    break;
}
  
if (isset($values) && ($count_elements_page !== false)) {
  $values = array_slice($values, $count_elements_page * ($page_num_request - 1), $count_elements_page,true);
}

?>
<table>

<tr><td><div>Type:</div></td><td><div><?php echo format_html($type)?></div></td></tr>

<tr><td><div><abbr title="Time To Live">TTL</abbr>:</div></td><td><div><?php echo ($ttl == -1) ? 'does not expire' : $ttl?> <a href="ttl.php?s=<?php echo $server['id']?>&amp;key=<?php echo urlencode($_GET['key'])?>&amp;ttl=<?php echo $ttl?>"><img src="images/edit.png" width="16" height="16" title="Edit TTL" alt="[E]" class="imgbut"></a></div></td></tr>

<?php if (!is_null($encoding)) { ?>
<tr><td><div>Encoding:</div></td><td><div><?php echo format_html($encoding)?></div></td></tr>
<?php } ?>

<tr><td><div>Size:</div></td><td><div><?php echo $size?> <?php echo ($type == 'string') ? 'characters' : 'items'?></div></td></tr>

</table>

<p>
<?php


// Build pagination div.
if (($count_elements_page !== false) && in_array($type, array('hash', 'list', 'set', 'zset')) && ($size > $count_elements_page)) {
  $pagination = '<div style="width: inherit; word-wrap: break-word;">';
  $url        = preg_replace('/&page=(\d+)/i', '', $_SERVER['REQUEST_URI']);

  for ($i = 0; $i < ceil($size / $count_elements_page); ++$i) {
    $page_num = $i + 1;

    if ($page_num === $page_num_request) {
      $pagination .= $page_num . '&nbsp;';
    } else {
      $pagination .= '<a href="' . $url . '&page=' . $page_num . '">' . $page_num . "</a>&nbsp;";
    }
  }

  $pagination .= '</div>';
}


if (isset($pagination)) {
  echo $pagination;
}


// String
if ($type == 'string') { ?>

<table>
<tr><td><div><?php echo nl2br(format_html($value, $server['charset']))?></div></td><td><div>
  <a href="edit.php?s=<?php echo $server['id']?>&amp;type=string&amp;key=<?php echo urlencode($_GET['key'])?>"><img src="images/edit.png" width="16" height="16" title="Edit" alt="[E]"></a>
</div></td><td><div>
  <a href="delete.php?s=<?php echo $server['id']?>&amp;type=string&amp;key=<?php echo urlencode($_GET['key'])?>" class="delval"><img src="images/delete.png" width="16" height="16" title="Delete" alt="[X]"></a>
</div></td></tr>
</table>

<?php }



// Hash
else if ($type == 'hash') { ?>

<table>
<tr><th><div>Key</div></th><th><div>Value</div></th><th><div>&nbsp;</div></th><th><div>&nbsp;</div></th></tr>

<?php foreach ($values as $hkey => $value) { ?>
  <tr <?php echo $alt ? 'class="alt"' : ''?>><td><div><?php echo format_html($hkey, $server['charset'])?></div></td><td><div><?php echo nl2br(format_html($value, $server['charset']))?></div></td><td><div>
    <a href="edit.php?s=<?php echo $server['id']?>&amp;type=hash&amp;key=<?php echo urlencode($_GET['key'])?>&amp;hkey=<?php echo urlencode($hkey)?>"><img src="images/edit.png" width="16" height="16" title="Edit" alt="[E]"></a>
  </div></td><td><div>
    <a href="delete.php?s=<?php echo $server['id']?>&amp;type=hash&amp;key=<?php echo urlencode($_GET['key'])?>&amp;hkey=<?php echo urlencode($hkey)?>" class="delval"><img src="images/delete.png" width="16" height="16" title="Delete" alt="[X]"></a>
  </div></td></tr>
<?php $alt = !$alt; } ?>

<?php }


// List
else if ($type == 'list') { ?>

<table>
<tr><th><div>Index</div></th><th><div>Value</div></th><th><div>&nbsp;</div></th><th><div>&nbsp;</div></th></tr>

<?php 
  if (($count_elements_page === false) && ($size > $count_elements_page)) {
    $start = 0;
    $end   = $size;
  } else {
    $start = $count_elements_page * ($page_num_request - 1);
    $end   = min($start + $count_elements_page, $size);
  }

  for ($i = $start; $i < $end; ++$i) {
    $value = $redis->lIndex($_GET['key'], $i);
?>
  <tr <?php echo $alt ? 'class="alt"' : ''?>><td><div><?php echo $i?></div></td><td><div><?php echo nl2br(format_html($value, $server['charset']))?></div></td><td><div>
    <a href="edit.php?s=<?php echo $server['id']?>&amp;type=list&amp;key=<?php echo urlencode($_GET['key'])?>&amp;index=<?php echo $i?>"><img src="images/edit.png" width="16" height="16" title="Edit" alt="[E]"></a>
  </div></td><td><div>
    <a href="delete.php?s=<?php echo $server['id']?>&amp;type=list&amp;key=<?php echo urlencode($_GET['key'])?>&amp;index=<?php echo $i?>" class="delval"><img src="images/delete.png" width="16" height="16" title="Delete" alt="[X]"></a>
  </div></td></tr>
<?php $alt = !$alt; } ?>

<?php }



// Set
else if ($type == 'set') {

?>
<table>
<tr><th><div>Value</div></th><th><div>&nbsp;</div></th><th><div>&nbsp;</div></th></tr>

<?php foreach ($values as $value) {
  $display_value = $redis->exists($value) ? '<a href="view.php?s='.$server['id'].'&key='.urlencode($value).'">'.nl2br(format_html($value, $server['charset'])).'</a>' : nl2br(format_html($value, $server['charset']));
?>
  <tr <?php echo $alt ? 'class="alt"' : ''?>><td><div><?php echo $display_value ?></div></td><td><div>
    <a href="edit.php?s=<?php echo $server['id']?>&amp;type=set&amp;key=<?php echo urlencode($_GET['key'])?>&amp;value=<?php echo urlencode($value)?>"><img src="images/edit.png" width="16" height="16" title="Edit" alt="[E]"></a>
  </div></td><td><div>
    <a href="delete.php?s=<?php echo $server['id']?>&amp;type=set&amp;key=<?php echo urlencode($_GET['key'])?>&amp;value=<?php echo urlencode($value)?>" class="delval"><img src="images/delete.png" width="16" height="16" title="Delete" alt="[X]"></a>
  </div></td></tr>
<?php $alt = !$alt; } ?>

<?php }



// ZSet
else if ($type == 'zset') { ?>

<table>
<tr><th><div>Score</div></th><th><div>Value</div></th><th><div>&nbsp;</div></th><th><div>&nbsp;</div></th></tr>

<?php foreach ($values as $value) {
  $score         = $redis->zScore($_GET['key'], $value);
  $display_value = $redis->exists($value) ? '<a href="view.php?s='.$server['id'].'&key='.urlencode($value).'">'.nl2br(format_html($value, $server['charset'])).'</a>' : nl2br(format_html($value, $server['charset']));
?>
  <tr <?php echo $alt ? 'class="alt"' : ''?>><td><div><?php echo $score?></div></td><td><div><?php echo $display_value ?></div></td><td><div>
    <a href="edit.php?s=<?php echo $server['id']?>&amp;type=zset&amp;key=<?php echo urlencode($_GET['key'])?>&amp;score=<?php echo $score?>&amp;value=<?php echo urlencode($value)?>"><img src="images/edit.png" width="16" height="16" title="Edit" alt="[E]"></a>
    <a href="delete.php?s=<?php echo $server['id']?>&amp;type=zset&amp;key=<?php echo urlencode($_GET['key'])?>&amp;value=<?php echo urlencode($value)?>" class="delval"><img src="images/delete.png" width="16" height="16" title="Delete" alt="[X]"></a>
  </div></td></tr>
<?php $alt = !$alt; } ?>

<?php }

if ($type != 'string') { ?>
  </table>

  <p>
  <a href="edit.php?s=<?php echo $server['id']?>&amp;type=<?php echo $type?>&amp;key=<?php echo urlencode($_GET['key'])?>" class="add">Add another value</a>
  </p>
<?php }

if (isset($pagination)) {
  echo $pagination;
}

require 'includes/footer.inc.php';

