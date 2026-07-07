<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Return the CSS season class based on the current date
function setTemplateSeason(): string {
    $zdate = date('z');
    if ($zdate > 355 || $zdate < 5) return 'sl-newyear';
    $seas = [0 => 'sl-winter', 1 => 'sl-spring', 2 => 'sl-summer', 3 => 'sl-autumn'];
    return $seas[floor(date('n') / 3) % 4];
}

# Query recent forum topics and render via forum-teaser partial
function setTemplateForum(): string {
    global $db, $tpl;
    $items = '';
    $result = getForumTopics('id, title, ltime, luid, lname, lpost, status', '97, 98, 99, 100, 101', 3);
    while ([$id, $title, $time, $luid, $lname, $lpost, $status] = $db->getSqlRow($result)) {
        $items .= $tpl->getHtmlFrag('forum-teaser-item', [
            'hidden' => $status <= 1 || $time > date('Y-m-d H:i:s'),
            'href' => 'index.php?name=forum&amp;op=view&amp;id='.$id.'&amp;last#'.$lpost,
            'title' => getDecodedText($title),
            'by' => _POSTEDBY,
            'poster' => $luid ? user_info($lname) : htmlspecialchars($lname, ENT_QUOTES, 'UTF-8'),
            'when_label' => _DATE,
            'when' => format_time($time, _TIMESTRING),
        ]);
    }
    return $tpl->getHtmlPart('forum-teaser', ['items' => $items, 'label' => _FORUM]);
}

# Provide head-time variables for the lite theme layout
function getThemeHeadVars(): array {
    global $db, $conf, $tpl, $sitevars;
    $mname = $conf['name'] ? getModuleName($conf['name']) : '';
    $fcat = (int)getVar('get', 'cat', 'num', 0);
    $ctitle = '';
    if (!$fcat) {
        $fcat = (int)($sitevars['head_cid'] ?? 0);
        $ctitle = (string)($sitevars['head_item'] ?? '');
    }
    $sep = $conf[$conf['name']]['defis'] ?? ($conf['files']['defis'] ?? $conf['defis']);
    $cname = ($fcat && $conf['name']) ? getTplCategoryTrail($conf['name'], $fcat, $sep, $mname, false) : '';
    if ($cname !== '' && $ctitle !== '') {
        $cname .= ' '.urldecode($sep).' '.htmlspecialchars(getDecodedText($ctitle), ENT_QUOTES, 'UTF-8');
    }
    [$count] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB."_faq WHERE time <= now() AND status != '0'"));
    $random = mt_rand(0, max(0, (int)$count - 1));
    [$fid, $title] = $db->getSqlRow($db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_faq ORDER BY id DESC LIMIT '.$random.', 1'));
    $ftitle = getDecodedText((string)$title);
    $faq = $tpl->getHtmlFrag('link', [
        'href' => 'index.php?name=faq&amp;op=view&amp;id='.$fid,
        'title' => $ftitle,
        'icon_name' => 'stars',
        'label' => $ftitle,
    ]);
    $head = '';
    if ($mname !== '' || $cname !== '') {
        $head = $tpl->getHtmlFrag('lite-head-banner', ['module' => $mname, 'category' => $cname]);
    }
    $season = setTemplateSeason();
    return [
        'season' => $season,
        'modul' => $conf['name'] ?? '',
        'faqtitle' => $faq,
        'head_html' => $head,
        'preload' => getTemplateLcpPreload($season, $head !== ''),
    ];
}

# Build a single LCP preload link for the hero actually rendered on this page:
# the main-module slider uses <season>.jpg, head-content pages use <season>-cat.jpg
function getTemplateLcpPreload(string $season, bool $hasHead): string {
    global $conf;
    if (($conf['name'] ?? '') === 'main') {
        $img = preg_replace('/^sl-/', '', $season).'.jpg';
    } elseif ($hasHead) {
        $img = preg_replace('/^sl-/', '', $season).'-cat.jpg';
    } else {
        return '';
    }
    $href = 'templates/'.getTheme().'/images/seasons/'.$img;
    if (!is_file(BASE_DIR.'/'.$href)) return '';
    return '<link rel="preload" as="image" href="'.$href.'" fetchpriority="high">'."\n";
}

# Provide foot-time variables for the lite theme layout
function getThemeFootVars(): array {
    return [
        'forumblock' => setTemplateForum(),
    ];
}
