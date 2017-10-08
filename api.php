<?php
include_once('config.php');

$debug = false;
$baseSize = 195; // also used in css/cbstar.css (+ 2*12 = 219px)

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

  $thumb = 'cache/'.md5($_GET['comic'].'/cover.jpg').'.jpg';

  if(!file_exists($thumb)) {
    if(file_exists($basePath.'/'.$_GET['comic'].'/cover.jpg') && !createComicThumb($_GET['comic'].'/cover.jpg', $baseSize)) {
      $thumb = 'img/nocover.jpg';
    }
  }
  header("Content-Type: image/jpeg");
  echo file_get_contents($thumb);
  die;


} else if($_GET['get'] == 'cover' && !empty($_GET['comic']) && !empty($_GET['issue'])) {

  $thumb = 'cache/'.md5($_GET['comic'].'/'.$_GET['issue']).'.jpg';

  if(!file_exists($thumb)) {
    if(!createIssueThumb($_GET['comic'].'/'.$_GET['issue'], $baseSize)) {
      $thumb = 'img/nocover.jpg';
    }
  }

  header("Content-Type: image/jpeg");
  echo file_get_contents($thumb);
  die;


} else if($_GET['get'] == 'issues' && !empty($_GET['comic'])) {

  $allFiles = new DirectoryIterator($basePath.'/'.$_GET['comic']);
  $files = new RegexIterator($allFiles, '/\.(cbz)$/');
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



function createComicThumb($file, $baseSize) {
  global $basePath;
  $thumb = 'cache/'.md5($file).'.jpg';
  
  $fp = fopen($basePath.'/'.$file, 'r');
  if(!$fp) {
    debug("Could not load image.");
    return false;
  }
  
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


function createIssueThumb($file, $baseSize) {
  global $basePath;
  $thumb = 'cache/'.md5($file).'.jpg';

  $zip = new ZipArchive();
  if ($zip->open($basePath.'/'.$file) !== true) {
    debug("Can't open File.");
    return false;
  }

  $coverFiles = array();
  $zipFiles = array();
  for( $i = 0; $i < $zip->numFiles; $i++ ){
    if(preg_match('/(fc|0fc|0c|00|01|cover|cvr)\.(jpg|jpeg|png)$/i', $zip->statIndex($i)['name'])) {
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

  $zip->close();

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