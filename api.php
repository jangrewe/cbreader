<?php
include_once('config.php');

$debug = true;
$baseSize = 195; // also used in css/cbstar.css (+ 2*12 = 219px)
$regexCover = '/(fc|00fc|00c|00|01|cover|cvr)\.(jpg|jpeg|png)$/i';

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
}



function createComicThumb($comic, $baseSize) {
  global $basePath;
  $thumb = 'cache/'.md5($comic).'.jpg';
  
  $fp = fopen($basePath.'/'.$comic.'/cover.jpg', 'r');
  if(!$fp) {
    debug("Could not load image.");
    return false;
  }
  
  renderThumb($thumb, $fp, $baseSize);
    
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
  
  renderThumb($thumb, $fp);
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
  
  renderThumb($thumb, $fp);
  $rar->close();
  return true;
}


function renderThumb($thumb, $fp) {
  global $baseSize;
  $img = new Imagick();
  $img->readImageFile($fp);
  
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


function isort($a, $b) {
  return strcasecmp(strtolower($a), strtolower($b));
}

function debug($string) {
  global $debug;
  if ($debug == true) {
    echo $string;
  }
}