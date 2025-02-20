<?php
require 'vendor/autoload.php'; // Use Composer for AWS SDK and dotenv
use Aws\S3\S3Client;
use Dotenv\Dotenv;

// Load .env file and validate
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$required_envs = ['AWS_REGION', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'S3_BUCKET_NAME', 'CLOUDFRONT_URL', 'DB_HOST', 'DB_USER', 'DB_PASSWORD', 'DB_NAME'];
foreach ($required_envs as $env) {
    if (!isset($_ENV[$env]) || empty($_ENV[$env])) {
        die("Error: Missing required environment variable: $env");
    }
}

// Initialize S3 client
$s3Client = new S3Client([
    'version' => 'latest',
    'region'  => $_ENV['AWS_REGION'],
    'credentials' => [
        'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
        'secret' => $_ENV['AWS_SECRET_ACCESS_KEY']
    ]
]);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_FILES["anyfile"]) && $_FILES["anyfile"]["error"] == 0) {
        $allowed = ["jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png"];
        $filename = $_FILES["anyfile"]["name"];
        $filetype = $_FILES["anyfile"]["type"];
        $filesize = $_FILES["anyfile"]["size"];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Check file extension and MIME type
        if (!array_key_exists($ext, $allowed) || $filetype !== $allowed[$ext]) {
            die("Error: Invalid file format.");
        }

        // File size limit (10MB)
        if ($filesize > 10 * 1024 * 1024) {
            die("Error: File size exceeds 10MB.");
        }

        // Upload to S3
        $key = 'uploads/' . time() . '_' . basename($filename);
        try {
            $fileStream = fopen($_FILES["anyfile"]["tmp_name"], 'r');

            $result = $s3Client->putObject([
                'Bucket' => $_ENV['S3_BUCKET_NAME'],
                'Key'    => $key,
                'Body'   => $fileStream,
                'ACL'    => 'public-read'
            ]);

            fclose($fileStream); // Close the file resource

            $urls3 = $result->get('ObjectURL');
            $cfurl = str_replace("https://{$_ENV['S3_BUCKET_NAME']}.s3.amazonaws.com", $_ENV['CLOUDFRONT_URL'], $urls3);
            echo "Image uploaded successfully. CloudFront URL: " . $cfurl;

            // Store in database (using prepared statements)
            $conn = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $_ENV['DB_NAME']);

            if ($conn->connect_error) {
                die("Database connection failed: " . $conn->connect_error);
            }

            $name = $_POST["name"] ?? 'Untitled'; // Prevent undefined index error
            $stmt = $conn->prepare("INSERT INTO posts (name, url, cfurl) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $urls3, $cfurl);

            if ($stmt->execute()) {
                echo " Record saved in database.";
            } else {
                echo "Database error: " . $stmt->error;
            }

            $stmt->close();
            $conn->close();
        } catch (Aws\S3\Exception\S3Exception $e) {
            die("Error uploading to S3: " . $e->getMessage());
        }
    } else {
        echo "Error: File upload failed.";
    }
}
?>
