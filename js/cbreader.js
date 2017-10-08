var viewer = false;
var viewerOpts = {
  nav: 'thumbs',
  allowfullscreen: 'native',
  loop: false,
  maxheight: '90%',
  transition: 'crossfade',
  clicktransition: 'crossfade',
  keyboard: {
    'home': true,
    'end': true,
    'up': true,
    'down': true
  }
}
$.fn.spin.presets.spinLarge = {
  lines: 13,
  length: 20,
  width: 10,
  radius: 20,
  hwaccel: true
}
$.fn.spin.presets.spinSmall = {
  lines: 10,
  length: 6,
  width: 4,
  radius: 9,
  hwaccel: true
}

var lastPosition = null;

$(document).ready(function() {

  $("#comics").spin('spinLarge', '#000');
  getComics();
  if(window.location.hash) {
    getIssues(decodeURIComponent(window.location.hash.substring(1)));
  }

});

function getComics() {
  $.getJSON('api.php?get=comics', function(data) {
    $("#comics").spin(false);
    var prevChar = null;
    var curRow = null;
    $.each(data.comics, function(i, comic) {
      var curChar = comic.charAt(0)
      if(curChar != prevChar) {
        var row = $('<div class="row rowHeader alert alert-dark"><h3>'+curChar+'</h3></div><div id="row_'+curChar+'" class="row rowComics"></div>');
        $('#comics').append(row);
      }
      prevChar = curChar;
      var comicCard = $('<div class="card" data-comic="'+comic+'"><img class="card-img-top lazyload" src="data:image/gif;base64,R0lGODdhAQABAPAAAMPDwwAAACwAAAAAAQABAAACAkQBADs=" data-src="api.php?get=cover&comic='+encodeURIComponent(comic)+'" alt="'+comic+'" width="195" height="292"><p class="card-text">'+comic+'</p></div>');
      comicCard.on('click', function() {
        lastPosition = $(window).scrollTop();
        getIssues(comic);
      });
      $('#comics #row_'+curChar).append(comicCard);
    });
    $("#comics img.lazyload").lazyload();
  });
}

function getIssues(comic) {
  var cardComic = $('.card[data-comic="'+comic+'"]');
  $(cardComic).spin('spinSmall', '#000');
  $.getJSON('api.php?get=issues&comic='+encodeURIComponent(comic), function(data) {
    $(cardComic).spin(false);
    $('#comics').fadeOut();
    window.location.hash = comic;
    var issues = $('<div id="issues"></div>');
    var title = $('<div class="row rowHeader alert alert-dark"><button class="btn btn-light btnHome"><span class="oi oi-chevron-left"></span></button> <h3>'+comic+'</h3></div>');
    var issuesList = $('<div class="row rowComics"></div>');
    issues.append(title).append(issuesList);
    $.each(data.issues, function(i, issue) {
      var issueCard = $('<div class="card" data-comic="'+comic+'" data-issue="'+issue+'"><img class="card-img-top" src="api.php?get=cover&comic='+encodeURIComponent(comic)+'&issue='+encodeURIComponent(issue)+'" alt="'+issue+'"><p class="card-text">'+(issue.substr(0, issue.lastIndexOf('.')) || issue)+'</p></div>');
      issuesList.append(issueCard);
      issueCard.on('click', function() {
        showIssue(comic, issue);
      });
    });
    issues.hide().appendTo($('#wrapper')).fadeIn('slow');
      $('html, body').animate({
        scrollTop: $('#wrapper').offset().top - 16
      }, 'slow');
    $('.btnHome').on('click', function() {
      goHome();
    });
  });
}

function showIssue(comic, issue) {
  $.getJSON('api.php?get=pages&issue='+encodeURIComponent(issue)+'&comic='+encodeURIComponent(comic), function(data) {
    viewerData = new Array();
    $.each(data.pages, function (i, page) {
      viewerData.push({
        img: 'api.php?page='+encodeURIComponent(page)+'&issue='+encodeURIComponent(issue)+'&comic='+encodeURIComponent(comic), 
        caption: (i+1)+'/'+data.pages.length
      });
    });
    $('#modalViewer').modal('show');
  });
}

$('#modalViewer').on('shown.bs.modal', function (e) {
  if (viewer == false) {
    var viewerDiv = $('#viewer').fotorama(viewerOpts);
    viewer = viewerDiv.data('fotorama');
  }
  viewer.load(viewerData);
  viewer.show(0);
});

$('#modalViewer').on('hidden.bs.modal', function (e) {
  viewer.destroy();
  viewer = false;
  $(this).removeData();
});

function goHome() {
  $('#issues').fadeOut(function() {
    window.location.hash = '';
    $('#issues').remove();
    $('#comics').fadeIn();
    $('html, body').animate({
      scrollTop: lastPosition
    }, 'slow');
  });
}