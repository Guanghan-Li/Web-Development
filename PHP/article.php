<?php

$id = $_GET['id'] ?? ($_GET['name'] ?? null);

if ($id == null or $id == '') {
    http_response_code(400);
    echo 'Invalid ID';
    exit;
}

if (!file_exists(__DIR__ . '/articles.json')) {
    http_response_code(500);
    echo 'Server error: articles.json not found';
    exit;
}

$json = file_get_contents(__DIR__ . '/articles.json');
if ($json === false) {
    http_response_code(500);
    echo 'Server error: failed to read articles.json';
    exit;
}

$articles = json_decode($json, true);
if (!is_array($articles)) {
    http_response_code(500);
    echo 'Server error: invalid JSON. ' . json_last_error_msg();
    exit;
}

$match = null;
$matchIndex = -1;

foreach ($articles as $i => $a) {
    if (isset($a['id']) && (string)$a['id'] === (string)$id) {
        $match = $a;
        $matchIndex = $i;   // <-- capture index
        break;
    }
}

if ($match === null) {
    http_response_code(404);
    echo 'Article not found for id: ' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
    exit;
}


$prev = ($matchIndex > 0) ? $articles[$matchIndex - 1] : null;
$next = ($matchIndex >= 0 && $matchIndex < count($articles) - 1) ? $articles[$matchIndex + 1] : null;

$template = __DIR__ . '/article.html';
if (!file_exists($template)) {
    http_response_code(500);
    echo 'Template not found';
    exit;
}

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
if (!$dom->loadHTMLFile($template)) {
    echo 'Failed to load template';
    exit;
}
libxml_clear_errors();

$h1 = $dom->getElementById('article-title');
$ul = $dom->getElementById('tag-list');
$art= $dom->getElementById('article-body');
if (!$h1 || !$ul || !$art) { echo 'Template missing h1/ul/article'; exit; }

$h1->nodeValue = $match['title'];

while ($ul->firstChild) $ul->removeChild($ul->firstChild);
foreach (($match['tags'] ?? []) as $t) {
    $li = $dom->createElement('li');
    $a  = $dom->createElement('a', $t);
    $a->setAttribute('href', 'articles.html?tag=' . rawurlencode($t));
    $li->appendChild($a);
    $ul->appendChild($li);
}

while ($art->firstChild) $art->removeChild($art->firstChild);
$frag = $dom->createDocumentFragment();
if (!$frag->appendXML($match['content'])) {
    $frag = $dom->createDocumentFragment();
    $frag->appendChild($dom->createTextNode('No content.'));
}
$art->appendChild($frag);

libxml_use_internal_errors(true);
$xp = new DOMXPath($dom);
libxml_clear_errors();

$prevA = $xp->query("//footer//nav//a[contains(@class,'secondary')][1]")->item(0);
$nextA = $xp->query("//footer//nav//a[contains(@class,'primary')][1]")->item(0);

if ($prevA) {
    if ($prev) {
        $prevA->setAttribute('href', 'article.php?id=' . rawurlencode($prev['id']));
    } else {
        $prevA->setAttribute('href', '#');
        $prevA->setAttribute('aria-disabled', 'true');
    }
}

if ($nextA) {
    if ($next) {
        $nextA->setAttribute('href', 'article.php?id=' . rawurlencode($next['id']));
    } else {
        $nextA->setAttribute('href', '#');
        $nextA->setAttribute('aria-disabled', 'true');
    }
}

header('Content-Type: text/html; charset=utf-8');

echo $dom->saveHTML();
exit;