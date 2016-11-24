window.addEventListener && window.addEventListener('load', function() {
  'use strict';
  var xhr = new XMLHttpRequest();
  xhr.responseType = 'json';
  xhr.onload = function(e) {
    console.log('status: ' + e);
    console.log(e);
    var searchResult = xhr.response;
    console.log(searchResult);
    addResultArea();
    addSearchResults(searchResult.found_pages);
  };
  console.log(xhr);
  console.log('aaa');
  xhr.open('POST', 'http://localhost:8070/pwd/b2390/', true);
  console.log('bbb');
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.send('q=bug&cmd=search&action=list&start=0');

  function addSearchResults(resultList) {
    var ul = document.querySelector('#search_result_ul');
    resultList.forEach(function(value, index) {
      addSearchResult(ul, value);
    });

  }
  function getLinedText(data) {
    var lines = [];
    data.lines.forEach(function(item) {
      lines.push('' + (item.index + 1) + ':' + item.line);
    });
    return lines.join('');
  }
  function addSearchResult(ul, data) {
    // <li>
    var a = document.createElement('a');
    var aLabel = document.createTextNode(data.page);
    a.href = data.link;
    a.appendChild(aLabel);
    var li = document.createElement('li');
    li.appendChild(a);
    ul.appendChild(li);
    // <pre>
    var pre = document.createElement('pre');
    //var preText = document.createTextNode(getLinedText(data));
    pre.innerHTML = getLinedText(data);
    //pre.appendChild(preText);
    ul.appendChild(pre);
  }
  function addResultArea() {
    var bodyDiv = document.querySelector('div#body');
    // <hr>
    var hr = document.createElement('hr');
    hr.className = 'full_hr';
    //var hr
    bodyDiv.insertBefore(hr, bodyDiv.childNodes[0]);

    // <ul>
    var ul = document.createElement('ul');
    ul.id = 'search_result_ul';
    bodyDiv.insertBefore(ul, hr);
  }
});
