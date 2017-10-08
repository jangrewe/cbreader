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
    $.each(data.comics, function(i, title) {
      var curChar = title.charAt(0)
      if(curChar != prevChar) {
        var row = $('<div class="row rowHeader alert alert-dark"><h3>'+curChar+'</h3></div><div id="row_'+curChar+'" class="row rowComics"></div>');
        $('#comics').append(row);
      }
      prevChar = curChar;
      var comic = $('<div class="card" data-comic="'+title+'"><img class="card-img-top lazyload" src="data:image/gif;base64,R0lGODdhAQABAPAAAMPDwwAAACwAAAAAAQABAAACAkQBADs=" data-src="api.php?get=cover&comic='+encodeURIComponent(title)+'" alt="'+title+'" width="195" height="292"><p class="card-text">'+title+'</p></div>');
      comic.on('click', function() {
        getIssues(title);
      });
      $('#comics #row_'+curChar).append(comic);
    });
    $("#comics img.lazyload").lazyload();
  });
}

function getIssues(comic) {
  var cardComic = $('.card[data-comic="'+comic+'"]');
  $(cardComic).spin('spinSmall', '#000');
  //var comic = $(cardComic).data('comic');
  $.getJSON('api.php?get=issues&comic='+encodeURIComponent(comic), function(data) {
    $(cardComic).spin(false);
    $('#comics').fadeOut();
    window.location.hash = comic;
    var issues = $('<div id="issues"></div>');
    var title = $('<div class="row rowHeader alert alert-dark"><button class="btn btn-light btnHome"><span class="oi oi-chevron-left"></span></button> <h3>'+comic+'</h3></div>');
    var issuesList = $('<div class="row rowComics"></div>');
    issues.append(title).append(issuesList);
    $.each(data.issues, function(i, e) {
      var issue = $('<div class="card" data-comic="'+comic+'" data-issue="'+e+'"><img class="card-img-top" src="api.php?get=cover&comic='+encodeURIComponent(comic)+'&issue='+encodeURIComponent(e)+'" alt="'+e+'"><p class="card-text">'+(e.substr(0, e.lastIndexOf('.')) || e)+'</p></div>');
      issuesList.append(issue);
    });
    issues.hide().appendTo($('#wrapper')).fadeIn('slow');
     $('html, body').animate({
        scrollTop: $('#wrapper').offset().top - 16
      }, 'slow');
    $('.btnHome').on('click', function() {
      $('#issues').fadeOut(function() {
        $('#issues').remove();
        $('#comics').fadeIn();
      });
    });
  });
}
