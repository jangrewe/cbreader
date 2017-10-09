<?php
include_once('config.php');

$debug = true;
$baseSize = 195; // also used in css/cbstar.css (+ 2*12 = 219px)
$regexCover = '/(fc|00fc|cover|cov|cvr|front|\(cover\)|00c|00|01)\.(jpg|jpeg|png)$/i';

if ($useCaching == true && !file_exists('./cache')) {
  mkdir('./cache');
}

if($_GET['get'] == 'comics') {

  $files = new DirectoryIterator($basePath);
  $arrFiles = array();
  foreach($files as $file) {
    if($file->isDir() && !$file->isDot()) {
      array_push($arrFiles, $file->__toString());
    }
  }
  usort($arrFiles, 'isort');
  if(count($arrFiles) > 0) {
    $json = json_encode(array("count" => count($arrFiles), "comics" => $arrFiles));
  }else{
    $json = json_encode(array("count" => 0));
  }
  header("Content-Type: application/json");
  echo $json;
  die;

  
} else if($_GET['get'] == 'cover' && !empty($_GET['comic']) && !isset($_GET['issue'])) {

  $thumb = 'cache/'.md5($_GET['comic']).'.jpg';

  if(!file_exists($thumb)) {
    if(file_exists($basePath.'/'.$_GET['comic'].'/cover.jpg') && !createComicThumb($_GET['comic'], $baseSize)) {
      $thumb = 'img/nocover.jpg';
    }
  }
  header("Content-Type: image/jpeg");
  echo file_get_contents($thumb);
  die;


} else if($_GET['get'] == 'cover' && !empty($_GET['comic']) && !empty($_GET['issue'])) {

  $thumb = 'cache/'.md5($_GET['comic'].'/'.$_GET['issue']).'.jpg';

  if(!file_exists($thumb)) {
    $pathInfo = pathinfo($basePath.'/'.$_GET['comic'].'/'.$_GET['issue']);
    if($pathInfo['extension'] == 'cbz' && !createCbzThumb($_GET['comic'].'/'.$_GET['issue'], $baseSize)) {
      $thumb = 'img/nocover.jpg';
    } else if ($pathInfo['extension'] == 'cbr' && !createCbrThumb($_GET['comic'].'/'.$_GET['issue'], $baseSize)) {
      $thumb = 'img/nocover.jpg';
    }
  }

  header("Content-Type: image/jpeg");
  echo file_get_contents($thumb);
  die;


} else if($_GET['get'] == 'issues' && !empty($_GET['comic'])) {

  $allFiles = new DirectoryIterator($basePath.'/'.$_GET['comic']);
  $files = new RegexIterator($allFiles, '/\.(cbz|cbr)$/');
  $arrFiles = array();
  foreach($files as $file) {
    array_push($arrFiles, $file->__toString());
  }
  usort($arrFiles, 'isort');
  if(count($arrFiles) > 0) {
    $json = json_encode(array("count" => count($arrFiles), "issues" => $arrFiles));
  }else{
    $json = json_encode(array("count" => 0));
  }
  header("Content-Type: application/json");
  echo $json;
  die;


} else if($_GET['get'] == 'pages' && !empty($_GET['issue']) && !empty($_GET['comic'])) {

  $pathInfo = pathinfo($basePath.'/'.$_GET['comic'].'/'.$_GET['issue']);
  if($pathInfo['extension'] == 'cbz') {
    $pages = getCbzPages($_GET['comic'].'/'.$_GET['issue'], $_GET['cover']);
  } else if ($pathInfo['extension'] == 'cbr') {
    $pages =  getCbrPages($_GET['comic'].'/'.$_GET['issue'], $_GET['cover']);
  }

  header("Content-Type: application/json");
  echo $json;
  die;


} else if(!empty($_GET['page']) && !empty($_GET['issue']) && !empty($_GET['comic']) && $_GET['set'] != 'cover') {

  header("Content-Type: image/jpeg");
  $pathInfo = pathinfo($basePath.'/'.$_GET['comic'].'/'.$_GET['issue']);
  if($pathInfo['extension'] == 'cbz') {
    echo getCbzPage($_GET['comic'].'/'.$_GET['issue'], $_GET['page']);
  } else if ($pathInfo['extension'] == 'cbr') {
    getCbrPage($_GET['comic'].'/'.$_GET['issue'], $_GET['page']);
  }
  die;


} else if(!empty($_GET['page']) && !empty($_GET['issue']) && !empty($_GET['comic']) && $_GET['set'] == 'cover') {

  $thumb = 'cache/'.md5($_GET['comic'].'/'.$_GET['issue']).'.jpg';

  $pathInfo = pathinfo($basePath.'/'.$_GET['comic'].'/'.$_GET['issue']);
  if($pathInfo['extension'] == 'cbz') {
    $page = getCbzPage($_GET['comic'].'/'.$_GET['issue'], $_GET['page']);
  } else if ($pathInfo['extension'] == 'cbr') {
    $page = getCbrPage($_GET['comic'].'/'.$_GET['issue'], $_GET['page']);
  }

  if(renderThumb($thumb, false, $page)) {
    $success = true;
  } else {
    $success = false;
  }
  echo json_encode(array(
    'success' => $success
  ));
  die;

}


#
# Thumbs
#

function createComicThumb($comic, $baseSize) {
  global $basePath;
  $thumb = 'cache/'.md5($comic).'.jpg';
  
  $fp = fopen($basePath.'/'.$comic.'/cover.jpg', 'r');
  if(!$fp) {
    debug("Could not load image.");
    return false;
  }
  
  renderThumb($thumb, $fp, false);
    
  return true;
}

function createCbzThumb($file) {
  global $basePath, $regexCover;
  $thumb = 'cache/'.md5($file).'.jpg';

  $zip = new ZipArchive();
  if ($zip->open($basePath.'/'.$file) !== true) {
    debug("Can't open File.");
    return false;
  }

  $coverFiles = array();
  $zipFiles = array();
  for( $i = 0; $i < $zip->numFiles; $i++ ){
    if(preg_match($regexCover, $zip->statIndex($i)['name'])) {
      array_push($coverFiles, $zip->statIndex($i)['name']);
    }
    if(preg_match('/\.(jpg|jpeg|png)$/i', $zip->statIndex($i)['name'])) {
      array_push($zipFiles, $zip->statIndex($i)['name']);
    }
  }

  if(count($coverFiles) > 0) {
    //usort($coverFiles, 'isort');
    $cover = $coverFiles[0];
  } else {
    usort($zipFiles, 'isort');
    $cover = $zipFiles[0];
  }
  
  $fp = $zip->getStream($cover);
  if(!$fp) {
    debug("Could not load image.");
    return false;
  }
  
  renderThumb($thumb, $fp, false);
  $zip->close();
  return true;
}

function createCbrThumb($file) {
  global $basePath, $regexCover;
  $thumb = 'cache/'.md5($file).'.jpg';

  $rar = RarArchive::open($basePath.'/'.$file);
  if ($rar == false) {
    debug("Can't open File.");
    return false;
  }

  $rarEntries = $rar->getEntries();
  
  $coverFiles = array();
  $rarFiles = array();

  foreach ($rarEntries as $entry) {
    if(preg_match($regexCover, $entry->getName())) {
      array_push($coverFiles, $entry->getName());
    }
    if(preg_match('/\.(jpg|jpeg|png)$/i', $entry->getName())) {
      array_push($rarFiles, $entry->getName());
    }
  }

  if(count($coverFiles) > 0) {
    //usort($coverFiles, 'isort');
    $cover = $coverFiles[0];
  } else {
    usort($rarFiles, 'isort');
    $cover = $rarFiles[0];
  }
  $rarEntry = $rar->getEntry($cover);
  $fp = $rarEntry->getStream($rarEntry);
  if(!$fp) {
    debug("Could not load image.");
    return false;
  }
  
  renderThumb($thumb, $fp, false);
  $rar->close();
  return true;
}

function renderThumb($thumb, $fp = false, $blob = false) {
  global $baseSize;
  $img = new Imagick();
  if($fp != false) {
    $img->readImageFile($fp);
  } else if ($blob != false) {
    $img->readImageBlob($blob);
  }
  
  $width = $img->getImageWidth();
  $height = $img->getImageHeight();
  $thumbWidth = $baseSize;
  if($height > $width) {
    $thumbHeight = $baseSize/2*3;
  }else{
    $thumbHeight = $baseSize/3*2;    
  }

  $img->cropThumbnailImage($thumbWidth, $thumbHeight);  
  $img->writeImage('jpg:'.$thumb);

  return true;
}

#
# Pages
#


function getCbzPages($file, $cover = false) {
  global $basePath, $regexCover;

  $zip = new ZipArchive();
  if ($zip->open($basePath.'/'.$file) !== true) {
    debug("Can't open File.");
    return false;
  }

  $zipFiles = array();
  for( $i = 0; $i < $zip->numFiles; $i++ ){
    if($cover == false) {
      if(preg_match('/\.(jpg|jpeg|png)$/i', $zip->statIndex($i)['name'])) {
        array_push($zipFiles, $zip->statIndex($i)['name']);
      }
    } else {
      if(preg_match($regexCover, $zip->statIndex($i)['name'])) {
        array_push($zipFiles, $zip->statIndex($i)['name']);
      }
    }
  }
  $zip->close();
  usort($zipFiles, 'isort');

  if(count($zipFiles) > 0) {
    if(limit != false) {
      $zipFiles = array_slice($zipFiles, 0, $limit);
    }
    $json = json_encode(array("count" => count($zipFiles), "pages" => $zipFiles));
  }else{
    $json = json_encode(array("count" => 0));
  }
  header("Content-Type: application/json");
  echo $json;
  die;
}

function getCbrPages($file, $cover = false) {
  global $basePath, $regexCover;

  $rar = RarArchive::open($basePath.'/'.$file);
  if ($rar == false) {
    debug("Can't open File.");
    return false;
  }

  $rarEntries = $rar->getEntries();
  $rarFiles = array();
  foreach ($rarEntries as $entry) {
    if($cover == false) {
      if(preg_match('/\.(jpg|jpeg|png)$/i', $entry->getName())) {
        array_push($rarFiles, $entry->getName());
      }
    } else {
      if(preg_match($regexCover, $entry->getName())) {
        array_push($rarFiles, $entry->getName());
      }
    }
  }

  usort($rarFiles, 'isort');

  if(count($rarFiles) > 0) {
    if(limit != false) {
      $rarFiles = array_slice($rarFiles, 0, $limit);
    }
    $json = json_encode(array("count" => count($rarFiles), "pages" => $rarFiles));
  }else{
    $json = json_encode(array("count" => 0));
  }
  header("Content-Type: application/json");
  echo $json;
  die;
}

function getCbzPage($file, $page) {
  global $basePath;
  $zip = new ZipArchive;
  if ($zip->open($basePath.'/'.$file) === TRUE) {
    $output = $zip->getFromName($page);
    $zip->close();
  }
  return $output;
}

function getCbrPage($file, $page) {
  global $basePath;
  $rar = RarArchive::open($basePath.'/'.$file);
  $rarEntries = $rar->getEntries();
  $rarEntry = $rar->getEntry($page);
  $stream = $rarEntry->getStream($rarEntry);
  $output = stream_get_contents($stream);
  fclose($stream);
  return $output;
}


#
# Util
#

function isort($a, $b) {
  return strcasecmp(strtolower($a), strtolower($b));
}

function debug($string) {
  global $debug;
  if ($debug == true) {
    echo $string;
  }
}