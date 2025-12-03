/* jQuery call plug-ins */
$(document).ready(function() {
    /* Fancybox */
    $('.screens').fancybox();
    $('.site-link').fancybox();
    /* Table sorter */
    $('.sl_table_list_sort').tablesorter();
});

/* jQuery image replace */
$(function() {
    $("#img_replace").change(function() {
        var str = $(this).val();
        $("#picture").attr("src", str);
    });
});

/* jQuery close or open elements and save cookies */
$(function() {
    // $dataPhp = $('#data').attr('data-attr');
    // alert($dataPhp);
    var all = $('.data').data('all');
    //alert(all.id);

    //for (i = 0; i < 10; i++) {
        if (all) {
        obj = all.id;
        id = '#' + obj;
        if ($.cookie(obj) == '0') {
            $(id).css({'display' : 'none'});
        } else {
            $(id).css({'display' : 'block'});
        }
    //}
}
});
/*
$(function() {
    for (i = 0; i < 100; i++) {
        obj = 'sl-open-' + i;
        id = '#' + obj;
        if ($.cookie(obj) == '1') {
            $(id).css({'display' : 'block'});
        } else {
            $(id).css({'display' : 'none'});
        }
    }
});
*/
function CloseOpen(obj, path) {
    var cname = obj;
    var cvalue = '';
    var cexpires = 30;
    var cpath = (path) ? location.pathname + location.search : '';
    var cdomain = location.host;
    var obj = '#' + obj;
    if ($(obj).css('display') == 'none') {
        $(obj).animate({height: 'show'}, 400);
        cvalue = 1;
    } else {
        $(obj).animate({height: 'hide'}, 200);
        cvalue = 0;
    }
    $.cookie(cname, cvalue, {expires: cexpires, path: cpath, domain: cdomain});
}

/* jQuery display or hide elements */
function HideShow(obj, eff, opt, dur) {
    /* Set the effect type (type: blind, bounce, clip, drop, explode, fold, highlight, puff, pulsate, scale, shake, size, slide) */
    var effect = (eff) ? eff : 'blind';
    /* Set the options for the effect type chosen (type: right, left, up, down) */
    var options = (opt) ? opt : 'left';
    /* Set the duration (default: 400 milliseconds) */
    var duration = (dur) ? dur : 400;
    $('#' + obj).toggle(effect, {direction: options}, duration);
}

/* jQuery scroll top */
function Upper(obj, dur) {
    var duration = (dur) ? dur : 200;
    $(obj).animate({scrollTop: 0}, duration);
    return false;
}

/* jQuery UI tabs and cookies */
$(function() {
    var obj = '';
    var cname = 'sl_tabs';
    var cvalue = '';
    var cexpires = 30;
    var cpath = location.pathname + location.search;
    var cdomain = location.host;
    for (i = 0; i < 10; i++) {
        obj = '#sl_tabs_' + i;
        $(obj).tabs({
            active: ($.cookie(cname) || 0),
            activate: function (event, ui) {
                cvalue = ui.newTab.parent().children().index(ui.newTab);
                $.cookie(cname, cvalue, {expires: cexpires, path: cpath, domain: cdomain});
            }
        });
    }
});

/* jQuery UI tabs and cookies */
/*
$(function() {
    var cname = 'sl_tabs';
    var cvalue = '';
    var cexpires = 30;
    var cpath = location.pathname + location.search;
    var cdomain = location.host;
    $('#sl_tabs').tabs({
        active: ($.cookie(cname) || 0),
        activate: function (event, ui) {
            var cvalue = ui.newTab.parent().children().index(ui.newTab);
            $.cookie(cname, cvalue, {expires: cexpires, path: cpath, domain: cdomain});
        }
    });
});
*/

/* jQuery checkbox */
function CheckBox(id, clas) {
    if ($(id).prop('checked')) {
        $(clas).prop('checked', true);
    } else {
        $(clas).prop('checked', false);
    }
}

/* jQuery AJAX loading */
function AjaxLoad(typ, ld, obj, adata, acheck) {
    if (typ == 'POST') {
        var form = $('#form' + obj)[0];
        var fdata = $(form).serialize();
        if (acheck != '') {
            var info = '';
            var nfound = 0;
            var elements = fdata.split('&');
            for (i = 0; i < elements.length; i++) {
                var svars = elements[i].split('=');
                for (var x in acheck) {
                    if (svars[0] == x && svars[1] != '') {
                        info = '';
                        nfound = 1;
                        break;
                    } else {
                        info = acheck[x];
                    }
                }
            }
            if (info != '' && nfound != 1) {
                alert (info);
                return;
            }
        }
        var adata = (adata) ? adata + '&' + fdata : fdata;
    } else if (typ == 'GET') {
        var adata = adata;
    }
    if (ld == '1') {
        $('#rep' + obj).html('<span class="sl_loading"></span>');
    }
    if (typ == 'POST' || typ == 'GET') {
        $.ajax({
            type: typ,
            url: 'index.php',
            data: adata,
            cache: false,
            success: function(data) {
                $('#rep' + obj).fadeOut(250, function() {
                    $(this).html(data);
                    $(this).fadeIn(250);
                });
            }
        });
    }
}

/* Universal Translator (Plain-Text + HTML Safe) */
function TranslateLang(input, output, lang, info, key) {
    var txt = $('input.' + input).val();
    if (txt) {
        txt = txt.trim();
    }
    if (!txt) {
        alert(info);
        return;
    }

    // Normalize language format: ru|de, ru-de, ru_de, RU-DE, ruDe…
    lang = lang.toLowerCase().trim();
    var parts = lang.split(/[-_|]/);

    if (parts.length !== 2) {
        alert('Wrong language format: ' + lang);
        return;
    }

    var from = parts[0].substring(0, 2);
    var to   = parts[1].substring(0, 2);

    // Detect HTML
    var hasHTML = /<[^>]+>/.test(txt);

    if (!hasHTML) {
        $.getJSON('https://api.mymemory.translated.net/get', {
            q: txt,
            langpair: from + '|' + to
        }, function(res) {
            var translated = (res?.responseData?.translatedText || txt)
                .replace(/&nbsp;/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
            $('input.' + output).val(translated);
        }).fail(function() {
            alert('Translation request failed');
        });

    } else {
        var div = document.createElement('div');
        div.innerHTML = txt;

        var nodes = [];
        (function scan(node) {
            if (node.nodeType === 3 && node.nodeValue.trim() !== '') {
                nodes.push(node);
            } else {
                for (var i = 0; i < node.childNodes.length; i++) {
                    scan(node.childNodes[i]);
                }
            }
        })(div);

        var index = 0;

        function processNext() {
            if (index >= nodes.length) {
                $('input.' + output).val(div.innerHTML);
                return;
            }
            var original = nodes[index].nodeValue.trim();
            $.getJSON('https://api.mymemory.translated.net/get', {
                q: original,
                langpair: from + '|' + to
            }, function(res) {
                if (res?.responseData?.translatedText) {
                    nodes[index].nodeValue = res.responseData.translatedText;
                }
                index++;
                setTimeout(processNext, 100);
            }).fail(function() {
                index++;
                setTimeout(processNext, 100);
            });
        }
        processNext();
    }
}
