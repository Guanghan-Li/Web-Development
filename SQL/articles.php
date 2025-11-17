<?php
$tag = $_GET['tag'] ?? null;

// Database connection
$databaseFile = 'articles.db';

try {
    $pdo = new PDO('sqlite:' . $databaseFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query to get all articles or filtered by tag
    if ($tag !== null && $tag !== '') {
        // Filter articles by tag - using LIKE since tags are comma-separated
        $query = "SELECT * FROM articles WHERE tags LIKE :tag";
        $stmt = $pdo->prepare($query);
        $searchTag = '%' . $tag . '%';
        $stmt->bindParam(':tag', $searchTag);
    } else {
        // Get all articles
        $query = "SELECT * FROM articles";
        $stmt = $pdo->prepare($query);
    }
    
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Database error: ' . $e->getMessage();
    exit;
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
    $tagsArray = explode(',', $a['tags']); // Convert comma-separated string back to array
    $prev  = $previewOf($a['content']);

    $card = $dom->createElement('a');
    $card->setAttribute('href', 'article.php?id=' . rawurlencode($id));

    $h2 = $dom->createElement('h2', $title);
    $card->appendChild($h2);

    $ul = $dom->createElement('ul');
    $ul->setAttribute('class', 'tags');
    foreach ($tagsArray as $t) {
        $ul->appendChild($dom->createElement('li', trim($t)));
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
