<?php
require 'vendor/autoload.php'; // Use composer for AWS SDK and dotenv
use Aws\S3\S3Client;
use Dotenv\Dotenv;

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

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

        if (!array_key_exists(pathinfo($filename, PATHINFO_EXTENSION), $allowed)) {
            die("Error: Invalid file format.");
        }

        if ($filesize > 10 * 1024 * 1024) {
            die("Error: File size exceeds 10MB.");
        }

        // Upload to S3
        $key = 'uploads/' . $filename;
        try {
            $result = $s3Client->putObject([
                'Bucket' => $_ENV['S3_BUCKET_NAME'],
                'Key'    => $key,
                'Body'   => fopen($_FILES["anyfile"]["tmp_name"], 'r'),
                'ACL'    => 'public-read'
            ]);

            $urls3 = $result->get('ObjectURL');
            $cfurl = str_replace("https://{$_ENV['S3_BUCKET_NAME']}.s3.amazonaws.com", $_ENV['CLOUDFRONT_URL'], $urls3);
            echo "Image uploaded successfully. CloudFront URL: " . $cfurl;

            // Store in database
            $conn = mysqli_connect($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $_ENV['DB_NAME']);
            if (!$conn) {
                die("Database connection failed: " . mysqli_connect_error());
            }
            $name = $_POST["name"];
            $sql = "INSERT INTO posts(name, url, cfurl) VALUES('$name', '$urls3', '$cfurl')";
            if (mysqli_query($conn, $sql)) {
                echo "Record saved in database.";
            } else {
                echo "Database error: " . mysqli_error($conn);
            }
            mysqli_close($conn);
        } catch (Aws\S3\Exception\S3Exception $e) {
            die("Error uploading to S3: " . $e->getMessage());
        }
    } else {
        echo "Error: File upload failed.";
    }
}
?>
