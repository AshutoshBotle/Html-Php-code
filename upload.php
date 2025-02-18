<?php
require 'vendor/autoload.php'; // Make sure autoload is included
use Aws\S3\S3Client;

// Retrieve AWS credentials from environment variables
$s3Client = new S3Client([
    'version' => 'latest',
    'region'  => getenv('AWS_REGION'), // Set your region in the environment
    'credentials' => [
        'key'    => getenv('AWS_ACCESS_KEY_ID'),
        'secret' => getenv('AWS_SECRET_ACCESS_KEY')
    ]
]);

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_FILES["anyfile"]) && $_FILES["anyfile"]["error"] == 0) {
        // Validate and process the uploaded file...
        $bucket = getenv('AWS_BUCKET_NAME'); // Use an environment variable for your bucket name

        $file_Path = __DIR__ . '/uploads/' . $filename;
        $key = basename($file_Path);
        try {
            // Upload the file to S3
            $result = $s3Client->putObject([
                'Bucket' => $bucket,
                'Key'    => $key,
                'Body'   => fopen($file_Path, 'r'),
                'ACL'    => 'public-read', 
            ]);
            echo "Image uploaded successfully. Image path is: " . $result->get('ObjectURL');
            // Continue with the rest of your code...
        } catch (Aws\S3\Exception\S3Exception $e) {
            echo "There was an error uploading the file.\n";
            echo $e->getMessage();
        }
    } else {
        echo "Error: " . $_FILES["anyfile"]["error"];
    }
}

// Database credentials from environment variables
$servername = getenv('DB_HOST');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');
$dbname = getenv('DB_NAME');

// Connect to the database
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Database logic here...
?>
