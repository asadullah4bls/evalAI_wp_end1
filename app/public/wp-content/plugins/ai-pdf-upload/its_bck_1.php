<?php
/*
Plugin Name: AI PDF Upload Bridge
*/

if (!defined('ABSPATH')) exit;

define('AI_PLUGIN_VERSION', '1.1');

function ai_update_plugin_db() {
    global $wpdb;
    $installed_version = get_option('ai_plugin_version');

    if ($installed_version != AI_PLUGIN_VERSION) {
        ai_update_questions_table(); // alter table
        update_option('ai_plugin_version', AI_PLUGIN_VERSION);
    }
}

add_action('plugins_loaded', 'ai_update_plugin_db');


 

function ai_create_quiz_tables() {
    global $wpdb;

    $charset = $wpdb->get_charset_collate();

    $quiz_table = $wpdb->prefix . "ai_quizzes";
    $q_table = $wpdb->prefix . "ai_questions";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("
    CREATE TABLE $quiz_table (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT,
        pdf_names TEXT,
        evaluation_picked TINYINT DEFAULT 0,
        quiz_attempted TINYINT DEFAULT 0,
        evaluated TINYINT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset;
    ");

    dbDelta("
    CREATE TABLE $q_table (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        quiz_id BIGINT,
        question TEXT,
        type VARCHAR(10),
        options_json LONGTEXT,
        correct_answer TEXT,
        explanation LONGTEXT,
        source_pdf VARCHAR(255),
        source_cluster VARCHAR(255)
    ) $charset;
    ");

}

 

function ai_update_questions_table() {
    global $wpdb;

    $q_table = $wpdb->prefix . "ai_questions";
    $charset = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE $q_table (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        quiz_id BIGINT,
        question TEXT,
        type VARCHAR(10),
        options_json LONGTEXT,
        correct_answer TEXT,
        explanation LONGTEXT,
        source_pdf VARCHAR(255),
        source_cluster VARCHAR(255),
        user_answer LONGTEXT DEFAULT NULL,
        its_score DECIMAL(10,2) DEFAULT NULL
    ) $charset;";

    dbDelta($sql);
}

register_activation_hook(__FILE__, 'ai_activate_plugin');

function ai_activate_plugin() {
    ai_create_quiz_tables();
    ai_update_questions_table();
    update_option('ai_plugin_version', AI_PLUGIN_VERSION);
}


define('AIPU_PATH', plugin_dir_path(__FILE__));

require_once AIPU_PATH . 'upload-form.php';

add_shortcode('pdf_upload_form', 'ai_pdf_upload_form_shortcode');
add_shortcode('ai_render_quiz', 'ai_render_quiz_shortcode');

function ai_render_quiz_shortcode() {
    if (!is_user_logged_in()) {
        return "<p>Please login to attempt quiz.</p>";
    }

    global $wpdb;
    $quiz_table = $wpdb->prefix . "ai_quizzes";
    $q_table = $wpdb->prefix . "ai_questions";
    $user_id = get_current_user_id();

    // Get latest quiz
    $quiz = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $quiz_table WHERE user_id=%d ORDER BY id DESC LIMIT 1", $user_id)
    );

    if (!$quiz) return "<p>No quiz available.</p>";

    $questions = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $q_table WHERE quiz_id=%d", $quiz->id)
    );

    ob_start();
    echo "<form method='post'>";
    echo "<input type='hidden' name='ai_quiz_id' value='{$quiz->id}'>";

    $i = 1;
    foreach ($questions as $q) {
        echo "<div style='margin-bottom:20px'>";
        echo "<p><strong>Q$i:</strong> {$q->question}</p>";

        // If quiz already attempted, show results
        if ($quiz->quiz_attempted) {
            $user_answer = maybe_unserialize($q->user_answer);
            echo "<p><strong>Your Answer:</strong> {$user_answer}</p>";
            echo "<p><strong>Correct Answer:</strong> {$q->correct_answer}</p>";
            echo "<p><strong>Explanation:</strong> {$q->explanation}</p>";
            if (isset($q->its_score)) {
                echo "<p><strong>Score:</strong> {$q->its_score}</p>";
            }
        }
        // Otherwise, render quiz input fields
        else {
            if ($q->type === "MCQ" && $q->options_json) {
                $opts = json_decode($q->options_json, true);
                foreach ($opts as $key => $val) {
                    echo "<label>
                            <input type='radio' name='answer[{$q->id}]' value='$key' required>
                            $key — $val
                          </label><br>";
                }
            } else {
                echo "<textarea name='answer[{$q->id}]' rows='3' style='width:100%' required></textarea>";
            }
        }

        echo "</div>";
        $i++;
    }

    if (!$quiz->quiz_attempted) {
        echo "<button type='submit' name='ai_quiz_submit'>Submit Quiz</button>";
    }

    echo "</form>";

    if ($msg = get_transient('ai_quiz_saved')) {
        echo "<p>$msg</p>";
        delete_transient('ai_quiz_saved');
    }

    return ob_get_clean();
}


add_action('init', 'ai_handle_pdf_upload');

add_action('init', 'ai_handle_quiz_submission');

function ai_handle_quiz_submission() {
    if (!isset($_POST['ai_quiz_submit'])) return;

    global $wpdb;

    $quiz_id = intval($_POST['ai_quiz_id']);
    $answers = $_POST['answer'];

    $q_table = $wpdb->prefix . "ai_questions";
    $quiz_table = $wpdb->prefix . "ai_quizzes";

    foreach ($answers as $qid => $user_answer) {
        $wpdb->update(
            $q_table,
            ['user_answer' => maybe_serialize($user_answer)],
            ['id' => intval($qid)],
            ['%s'],
            ['%d']
        );
    }

    // ⭐ mark attempted
    $wpdb->update(
        $quiz_table,
        ['quiz_attempted' => 1],
        ['id' => $quiz_id]
    );

    set_transient('ai_quiz_saved', 'Answers saved. Evaluation pending.', 10);
}



function ai_store_quiz_in_db($quiz_array) {
    global $wpdb;

    $quiz_table = $wpdb->prefix . "ai_quizzes";
    $q_table = $wpdb->prefix . "ai_questions";

    $user_id = get_current_user_id();

    // insert quiz
    $wpdb->insert($quiz_table, [
        'pdf_names' => 'Uploaded PDFs Quiz',
        'user_id' => $user_id, 
    ]);

    $quiz_id = $wpdb->insert_id;

    

    foreach ($quiz_array as $q) {

        $options_json = isset($q['options']) 
            ? json_encode($q['options']) 
            : null;

        $wpdb->insert($q_table, [
            'quiz_id' => $quiz_id, 
            'question' => $q['question'],
            'type' => $q['type'],
            'options_json' => $options_json,
            'correct_answer' => $q['correct_answer'] ?? $q['answer'],
            'explanation' => $q['explanation'] ?? '',
            'source_pdf' => $q['source_pdf'] ?? '',
            'source_cluster' => $q['source_cluster'] ?? ''
        ]);
    }
}



function ai_send_multiple_files_to_flask($curl_files) {

    $flask_url = "http://192.168.18.85:8005/upload_pdfs/";

    $post_data = [];

    foreach ($curl_files as $i => $file) {
        $post_data["file[$i]"] = $file;
    }

    // ✅ send logged-in user id
    $post_data["user_id"] = get_current_user_id();

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $flask_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post_data,
        CURLOPT_RETURNTRANSFER => true,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        error_log("Curl error: " . curl_error($ch));
        curl_close($ch);
        return;
    }

    curl_close($ch);

    $data = json_decode($response, true);

    // if (!$data || !isset($data['quiz'])) {
    //     error_log("Invalid quiz response");
    //     return;
    // }

    return;

}




function ai_handle_pdf_upload() {

    if (!isset($_POST['ai_pdf_submit'])) return;

    $files = $_FILES['pdf_files'];
    $curl_files = [];

    for ($i = 0; $i < count($files['name']); $i++) {

        if ($files['error'][$i] !== 0) continue;

        $curl_files[] = new CURLFile(
            $files['tmp_name'][$i],
            'application/pdf',
            $files['name'][$i]
        );
    }

    if (!empty($curl_files)) {

        $user_id = get_current_user_id();

        if (!function_exists('ai_decrease_entry')) {

            wp_die("Subscription system missing.");
        }

        if (!ai_decrease_entry($user_id)) {

            wp_redirect(site_url('/subscriptions'));
            exit;
        }

        ai_send_multiple_files_to_flask($curl_files);
    }
}



