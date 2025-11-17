<?php

// Database connection
$databaseFile = 'articles.db';

try {
    $pdo = new PDO('sqlite:' . $databaseFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query to get the first article (id = 1, which is the latest in the original JSON)
    $query = "SELECT * FROM articles WHERE id = 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        http_response_code(500);
        echo 'Error: No articles found in database';
        exit;
    }
    
    $latest = $results[0];
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Database error: ' . $e->getMessage();
    exit;
}

$id    = $latest['id'];
$title = $latest['title'];
$tagsArray = explode(',', $latest['tags']); // Convert comma-separated string back to array
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

foreach ($tagsArray as $t) {
    $li = $dom->createElement('li');
    $a  = $dom->createElement('a', trim($t));
    $a->setAttribute('href', 'articles.php?tag=' . rawurlencode(trim($t)));
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
