<?php

$databaseFile = 'articles.db';

try {
    // Create PDO connection
    $pdo = new PDO('sqlite:' . $databaseFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create articles table
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS articles (
            id INTEGER PRIMARY KEY,
            title TEXT NOT NULL,
            tags TEXT NOT NULL,
            content TEXT NOT NULL
        )
    ";
    
    $pdo->exec($createTableQuery);
    echo "Database created successfully.\n";
    
    // Load articles from JSON
    if (!file_exists(__DIR__ . '/articles.json')) {
        throw new Exception('articles.json not found');
    }
    
    $json = file_get_contents(__DIR__ . '/articles.json');
    $articles = json_decode($json, true);
    
    if (!is_array($articles)) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    // Insert articles into database
    $insertQuery = "INSERT INTO articles (id, title, tags, content) VALUES (:id, :title, :tags, :content)";
    $stmt = $pdo->prepare($insertQuery);
    
    foreach ($articles as $article) {
        $id = $article['id'];
        $title = $article['title'];
        $tags = implode(',', $article['tags']); // Convert array to comma-separated string
        $content = $article['content'];
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':tags', $tags);
        $stmt->bindParam(':content', $content);
        
        $stmt->execute();
        echo "Article with title '$title' was inserted.\n";
    }
    
    // Test query to verify data
    $testQuery = "SELECT * FROM articles";
    $stmt = $pdo->prepare($testQuery);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n=== Test Query Results ===\n";
    var_dump($results);
    echo "\nTotal articles in database: " . count($results) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
