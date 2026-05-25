<?php

add_action('init', 'aipu_handle_upload');

function aipu_handle_upload() {

    if (!isset($_POST['aipu_submit'])) return;
    if (!is_user_logged_in()) return;

    if (empty($_FILES['pdfs']['name'][0])) return;

    $uploaded_paths = [];

    require_once ABSPATH . 'wp-admin/includes/file.php';

    foreach ($_FILES['pdfs']['name'] as $key => $value) {

        if ($_FILES['pdfs']['error'][$key] == 0) {

            $file = [
                'name' => $_FILES['pdfs']['name'][$key],
                'type' => $_FILES['pdfs']['type'][$key],
                'tmp_name' => $_FILES['pdfs']['tmp_name'][$key],
                'error' => $_FILES['pdfs']['error'][$key],
                'size' => $_FILES['pdfs']['size'][$key]
            ];

            $uploaded = wp_handle_upload($file, ['test_form' => false]);

            if (!isset($uploaded['error'])) {
                $uploaded_paths[] = $uploaded['file'];
            }
        }
    }

    if (!empty($uploaded_paths)) {
        aipu_send_to_flask($uploaded_paths);
    }
}


function aipu_send_to_flask($paths) {

    $files = [];

    foreach ($paths as $i => $path) {
        $files["files[$i]"] = curl_file_create($path);
    }

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => "http://127.0.0.1:8005/upload-pdfs",
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $files
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    echo "<pre>Flask Response: $response</pre>";
}
