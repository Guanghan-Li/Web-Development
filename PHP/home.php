<?php
if (!file_exists(__DIR__ . '/articles.json')) { 
    http_response_code(500); 
    echo 'Error: articles.json not found'; 
    exit; 
}

$json = file_get_contents(__DIR__ . '/articles.json');
$articles = json_decode($json, true);

if (!is_array($articles) || empty($articles)) { 
    http_response_code(500);
    echo 'Error: invalid or empty JSON'; 
    exit; 
}

$latest = $articles[0];
$id    = $latest['id'];
$title = $latest['title'];
$tags  = $latest['tags'];
$contentHtml = $latest['content'];

$previewOf = function (string $html): string {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    $parts = preg_split('/\.\s*/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $firstTwo = array_slice($parts ?: [$text], 0, 2);
    $preview  = implode('. ', array_map('trim', $firstTwo));
    if ($preview !== '' && substr($preview, -1) !== '.') { $preview .= '.'; }
    return $preview;
};

$previewText = $previewOf($contentHtml);
$previewHtml = '<p>' . htmlspecialchars($previewText, ENT_QUOTES, 'UTF-8') . '</p>';

$template = __DIR__ . '/index.html';
if (!file_exists($template)) { 
    http_response_code(500); 
    echo 'Error: index.html template not found'; 
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

$xpath = new DOMXPath($dom);
$get = fn($id) => $xpath->query("//*[@id='$id']")->item(0);

$h1   = $get('latest-title');
$ul   = $get('latest-tags');
$body = $get('latest-body');
$cont = $get('continue-link');
$all  = $get('view-all-link');

if (!$h1 || !$ul || !$body) { 
    echo 'Template missing required nodes'; 
    exit; 
}

$h1->nodeValue = $title;

while ($ul->firstChild) $ul->removeChild($ul->firstChild);

foreach ($tags as $t) {
    $li = $dom->createElement('li');
    $a  = $dom->createElement('a', $t);
    $a->setAttribute('href', 'articles.php?tag=' . rawurlencode($t));
    $li->appendChild($a);
    $ul->appendChild($li);
}

while ($body->firstChild) $body->removeChild($body->firstChild);

$frag = $dom->createDocumentFragment();
if (!$frag->appendXML($previewHtml)) {
    $frag = $dom->createDocumentFragment();
    $frag->appendChild($dom->createTextNode(strip_tags($previewHtml)));
}
$body->appendChild($frag);

if ($cont) $cont->setAttribute('href', 'article.php?id=' . rawurlencode($id));
if ($all)  $all->setAttribute('href', 'articles.php');

header('Content-Type: text/html; charset=utf-8');
echo $dom->saveHTML();
exit;
