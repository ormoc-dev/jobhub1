<?php
// Test page to verify images are loading correctly
echo "<h2>Image Test - WORKLINK Slider Images</h2>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 40px; }
.image-test { margin: 20px 0; padding: 20px; border: 1px solid #ddd; }
.image-test img { max-width: 300px; height: auto; margin: 10px; }
</style>";

$images = ['1.jpg', '2.jpg', '3.jpg', '4.jpg', '5.jpg'];

foreach ($images as $index => $image) {
    $imagePath = "images/" . $image;
    echo "<div class='image-test'>";
    echo "<h3>Slide " . ($index + 1) . ": " . $image . "</h3>";
    
    if (file_exists($imagePath)) {
        echo "<p style='color: green;'>✅ Image exists</p>";
        echo "<img src='$imagePath' alt='Slide " . ($index + 1) . "'>";
    } else {
        echo "<p style='color: red;'>❌ Image not found: $imagePath</p>";
    }
    echo "</div>";
}

echo "<p><a href='index.php'>← Back to Homepage</a></p>";
?>
