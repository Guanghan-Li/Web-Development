<?php

$id = $_GET['id'] ?? ($_GET['name'] ?? null);

if ($id == null or $id == '') {
    http_response_code(400);
    echo 'Invalid ID';
    exit;
}

// Database connection
$databaseFile = 'articles.db';

try {
    $pdo = new PDO('sqlite:' . $databaseFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query to get the article by id
    $query = "SELECT * FROM articles WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        http_response_code(404);
        echo 'Article not found for id: ' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        exit;
    }
    
    $match = $results[0]; // Extract the single article from array
    
    // Get previous and next articles for navigation
    $prevQuery = "SELECT * FROM articles WHERE id < :id ORDER BY id DESC LIMIT 1";
    $prevStmt = $pdo->prepare($prevQuery);
    $prevStmt->bindParam(':id', $id);
    $prevStmt->execute();
    $prevResults = $prevStmt->fetchAll(PDO::FETCH_ASSOC);
    $prev = !empty($prevResults) ? $prevResults[0] : null;
    
    $nextQuery = "SELECT * FROM articles WHERE id > :id ORDER BY id ASC LIMIT 1";
    $nextStmt = $pdo->prepare($nextQuery);
    $nextStmt->bindParam(':id', $id);
    $nextStmt->execute();
    $nextResults = $nextStmt->fetchAll(PDO::FETCH_ASSOC);
    $next = !empty($nextResults) ? $nextResults[0] : null;
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Database error: ' . $e->getMessage();
    exit;
}

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

// Convert tags from comma-separated string back to array
$tagsArray = explode(',', $match['tags']);

while ($ul->firstChild) $ul->removeChild($ul->firstChild);
foreach ($tagsArray as $t) {
    $li = $dom->createElement('li');
    $a  = $dom->createElement('a', $t);
    $a->setAttribute('href', 'articles.php?tag=' . rawurlencode($t));
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
