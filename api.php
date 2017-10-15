<?php
include_once('config.php');

$debug = false;
$baseSize = 195; // also used in css/cbstar.css (+ 2*12 = 219px)
$regexCover = '/(fc|00fc|cover|cov|cvr|front|\(cover\)|00c|00|01)\.(jpg|jpeg|png)$/i';

if (!file_exists('./cache')) {
  mkdir('./cache');
}

if (isset($_GET['issue'])) {
  $pathInfo = pathinfo($basePath.'/'.$_GET['comic'].'/'.$_GET['issue']);
  $cbType = $pathInfo['extension'];
}

// get all comics
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


// get the cover for a comic
} else if($_GET['get'] == 'cover' && !empty($_GET['comic']) && !isset($_GET['issue'])) {

  $thumb = 'cache/'.md5($_GET['comic']).'.jpg';
  if(!file_exists($thumb)) {
    if(file_exists($basePath.'/'.$_GET['comic'].'/cover.jpg') && !createComicThumb($_GET['comic'])) {
      $thumb = 'img/nocover.jpg';
    }
  }
  header("Content-Type: image/jpeg");
  echo file_get_contents($thumb);
  die;


// get the cover for an issue
} else if($_GET['get'] == 'cover' && !empty($_GET['comic']) && !empty($_GET['issue'])) {

  $thumb = 'cache/'.md5($_GET['comic'].'/'.$_GET['issue']).'.jpg';
  if(!file_exists($thumb)) {
    if(!createIssueThumb($_GET['comic'].'/'.$_GET['issue'])) {
      $thumb = 'img/nocover.jpg';
    }
  }
  header("Content-Type: image/jpeg");
  echo file_get_contents($thumb);
  die;


// get all issues for a comic
} else if($_GET['get'] == 'issues' && !empty($_GET['comic'])) {

  $allFiles = new DirectoryIterator(checkPath($basePath.'/'.$_GET['comic']));
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


// get the list of pages of an issue
} else if($_GET['get'] == 'pages' && !empty($_GET['issue']) && !empty($_GET['comic'])) {

  $pages = getPages($_GET['comic'].'/'.$_GET['issue'], $_GET['cover']);
  header("Content-Type: application/json");
  echo $json;
  die;


// get a page from an issue
} else if(!empty($_GET['page']) && !empty($_GET['issue']) && !empty($_GET['comic']) && $_GET['set'] != 'cover') {

  header("Content-Type: image/jpeg");
  echo getPage($_GET['comic'].'/'.$_GET['issue'], $_GET['page'], $_GET['cover']);
  die;


// set the cover for an issue
} else if(!empty($_GET['page']) && !empty($_GET['issue']) && !empty($_GET['comic']) && $_GET['set'] == 'cover') {

  $thumb = 'cache/'.md5($_GET['comic'].'/'.$_GET['issue']).'.jpg';
  $blob = getPage($_GET['comic'].'/'.$_GET['issue'], $_GET['page']);
  if(renderThumb($thumb, $blob)) {
    $success = true;
  } else {
    $success = false;
  }

  header("Content-Type: application/json");
  echo json_encode(array(
    'success' => $success
  ));
  die;

}


#
# Thumbs
#

function createComicThumb($comic) {
  global $basePath;
  $thumb = 'cache/'.md5($comic).'.jpg';  
  $blob = file_get_contents(checkPath($basePath.'/'.$comic.'/cover.jpg'));
  return renderThumb($thumb, $blob);
}

function createIssueThumb($file) {
  global $cbType;
  $thumb = 'cache/'.md5($file).'.jpg';
  if($cbType == 'cbz') {
    if(!$files = getZipContents($file, true)) {
      $files = getZipContents($file, false);
      usort($files, 'isort');
    }
    $blob = getFileFromZip($file, current($files));
  } else if($cbType == 'cbr') {
    if(!$files = getRarContents($file, true)) {
      $files = getRarContents($file, false);
      usort($files, 'isort');
    }
    $blob = getFileFromRar($file, current($files));
  }  
  return renderThumb($thumb, $blob);
}

function renderThumb($thumb, $blob) {
  global $baseSize;
  $img = new Imagick();
  $img->readImageBlob($blob);
    
  $width = $img->getImageWidth();
  $height = $img->getImageHeight();
  $thumbWidth = $baseSize;
  if($height > $width) {
    $thumbHeight = $baseSize/2*3;
  }else{
    $thumbHeight = $baseSize/3*2;    
  }

  $img->cropThumbnailImage($thumbWidth, $thumbHeight);
  if($thumb == false) {
    return $img->getImageBlob();
  } else {
    $img->writeImage('jpg:'.$thumb);
  }
  return true;
}


#
# Pages
#

function getPages($file, $cover = false) {
  global $cbType;
  if ($cbType == 'cbz') {
    $files = getZipContents($file, $cover);  
  } else if ($cbType == 'cbr') {
    $files = getRarContents($file, $cover);  
  }  
  usort($files, 'isort');
  if(count($files) > 0) {
    $json = json_encode(array("count" => count($files), "pages" => $files));
  }else{
    $json = json_encode(array("count" => 0));
  }
  header("Content-Type: application/json");
  echo $json;
  die;
}

function getPage($file, $page, $cover = false) {
  global $cbType;
  if ($cbType == 'cbz') {
    $blob = getFileFromZip($file, $page);
  } else if ($cbType == 'cbr') {
    $blob = getFileFromRar($file, $page);
  }
  if($cover == true) {
    $blob = renderThumb(false, $blob);
  }
  return $blob;
}


#
# Zip Handling
#

function getZipContents($zipFile, $cover) {
  global $basePath, $regexCover;
  $zip = new ZipArchive();
  if ($zip->open(checkPath($basePath.'/'.$zipFile)) !== true) {
    debug("Can't open File.");
    return false;
  }
  $files = array();
  for($i=0; $i<$zip->numFiles; $i++){
    if($cover == false) {
      if(preg_match('/\.(jpg|jpeg|png)$/i', $zip->statIndex($i)['name'])) {
        array_push($files, $zip->statIndex($i)['name']);
      }
    } else {
      if(preg_match($regexCover, $zip->statIndex($i)['name'])) {
        array_push($files, $zip->statIndex($i)['name']);
      }
    }
  }
  $zip->close();
  return $files;
}

function getFileFromZip($zipFile, $file) {
  global $basePath;
  $zip = new ZipArchive();
  if ($zip->open(checkPath($basePath.'/'.$zipFile)) !== true) {
    debug("Can't open File.");
    return false;
  }
  $stream = $zip->getStream($file);
  $blob = stream_get_contents($stream);
  fclose($stream);
  $zip->close();
  return $blob;
}


#
# Rar Handling
#

function getRarContents($rarFile, $cover) {
  global $basePath, $regexCover;
  $rar = RarArchive::open(checkPath($basePath.'/'.$rarFile));
  if ($rar == false) {
    debug("Can't open File.");
    return false;
  }
  $rarEntries = $rar->getEntries();
  $files = array();
  foreach ($rarEntries as $entry) {
    if($cover == false) {
      if(preg_match('/\.(jpg|jpeg|png)$/i', $entry->getName())) {
        array_push($files, $entry->getName());
      }
    } else {
      if(preg_match($regexCover, $entry->getName())) {
        array_push($files, $entry->getName());
      }
    }
  }
  $rar->close();
  return $files;
}

function getFileFromRar($rarFile, $file) {
  global $basePath;
  $rar = RarArchive::open(checkPath($basePath.'/'.$rarFile));
  if ($rar == false) {
    debug("Can't open File.");
    return false;
  }
  $rarEntry = $rar->getEntry($file);
  $stream = $rarEntry->getStream($rarEntry);
  $blob = @stream_get_contents($stream);
  fclose($stream);
  $rar->close();
  return $blob;
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

function checkPath($file) {
  global $basePath;
  if(strpos(realpath($file), $basePath) === 0) {
    return realpath($file);
  } else {
    echo "nope.";
    die;
  }
}