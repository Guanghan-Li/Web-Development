<?php
$tag = $_GET['tag'] ?? null;

// 1) Load JSON
if (!file_exists(__DIR__ . '/articles.json')){
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
if (!is_array($articles)){
    http_response_code(500);
    echo 'Server error: invalid JSON. ' . json_last_error_msg();
    exit;
}

if ($tag !== null && $tag !== '') {
    $articles = array_values(array_filter($articles, function ($a) use ($tag) {
        return isset($a['tags']) && in_array($tag, (array)$a['tags'], true);
    }));
}

$template = __DIR__ . '/articles.html';
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


$xpath = new DOMXPath($dom);
$mainList = $dom->getElementsByTagName('main');
if ($mainList->length === 0) { echo 'Template error: missing <main>'; exit; }
$main = $mainList->item(0);
$sec = $xpath->query(".//*[@id='articles-list']", $main)->item(0);
if ($sec && $sec->parentNode) { $sec->parentNode->removeChild($sec); }
$mainHeader = $xpath->query(".//header[1]", $main)->item(0);
$ref = $mainHeader ? $mainHeader->nextSibling : null;

$previewOf = function (string $html): string {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    $parts = preg_split('/\.\s*/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $firstTwo = array_slice($parts ?: [$text], 0, 2);
    $preview  = implode('. ', array_map('trim', $firstTwo));
    if ($preview !== '' && substr($preview, -1) !== '.') { $preview .= '.'; }
    return $preview;
};

foreach ($articles as $a) {
    $id    = $a['id'];
    $title = $a['title'];
    $tags  = $a['tags'];
    $prev  = $previewOf($a['content']);

    $card = $dom->createElement('a');
    $card->setAttribute('href', 'article.php?id=' . rawurlencode($id));

    $h2 = $dom->createElement('h2', $title);
    $card->appendChild($h2);

    $ul = $dom->createElement('ul');
    $ul->setAttribute('class', 'tags');
    foreach ($tags as $t) {
        $ul->appendChild($dom->createElement('li', $t));
    }
    $card->appendChild($ul);

    $p = $dom->createElement('p', $prev);
    $card->appendChild($p);

    if ($ref) {
        $main->insertBefore($card, $ref);
    } else {
        $main->appendChild($card);
    }
}

header('Content-Type: text/html; charset=utf-8');
echo $dom->saveHTML();
exit;
