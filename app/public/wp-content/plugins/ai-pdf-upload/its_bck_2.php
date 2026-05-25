<?php
/*
Plugin Name: AI PDF Upload Bridge
*/

if (!defined('ABSPATH')) exit;

define('AI_PLUGIN_VERSION', '1.2');

function ai_update_plugin_db() {
    global $wpdb;
    $installed_version = get_option('ai_plugin_version');

    if ($installed_version != AI_PLUGIN_VERSION) {
        ai_update_questions_table(); // alter table
        ai_update_quiz_table();
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

            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            bot_obt_score DECIMAL(10,2) DEFAULT 0,

            bot_tot_score DECIMAL(10,2) DEFAULT 0,

            bot_obt_perc VARCHAR(20) DEFAULT NULL,

            bot_scre_passed TINYINT DEFAULT 0

        ) $charset;
        "
    );

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

function ai_update_quiz_table() {

    global $wpdb;

    $quiz_table = $wpdb->prefix . "ai_quizzes";

    $charset = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "
    CREATE TABLE $quiz_table (

        id BIGINT AUTO_INCREMENT PRIMARY KEY,

        user_id BIGINT,

        pdf_names TEXT,

        evaluation_picked TINYINT DEFAULT 0,

        quiz_attempted TINYINT DEFAULT 0,

        evaluated TINYINT DEFAULT 0,

        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

        bot_obt_score DECIMAL(10,2) DEFAULT 0,

        bot_tot_score DECIMAL(10,2) DEFAULT 0,

        bot_obt_perc VARCHAR(20) DEFAULT NULL,

        bot_scre_passed TINYINT DEFAULT 0

    ) $charset;
    ";

    dbDelta($sql);
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

    // IF SPECIFIC QUIZ REQUESTED
    if (isset($_GET['quiz_id'])) {

        $quiz_id = intval($_GET['quiz_id']);

        $quiz = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM $quiz_table
                WHERE id = %d
                AND user_id = %d
                ",
                $quiz_id,
                $user_id
            )
        );
    }
    else {

        // FALLBACK TO LATEST QUIZ

        $quiz = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM $quiz_table
                WHERE user_id=%d
                ORDER BY id DESC
                LIMIT 1
                ",
                $user_id
            )
        );
    }

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


add_shortcode(
    'ai_quiz_history',
    'ai_quiz_history_shortcode'
);

function ai_quiz_history_shortcode() {

    if (!is_user_logged_in()) {

        return "<p>Please login.</p>";
    }

    global $wpdb;

    $quiz_table = $wpdb->prefix . "ai_quizzes";

    $user_id = get_current_user_id();

    $quizzes = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT *
            FROM $quiz_table
            WHERE user_id = %d
            ORDER BY id DESC
            ",
            $user_id
        )
    );

    if (!$quizzes) {

        return "<p>No quizzes found.</p>";
    }

    ob_start();
    ?>

        <style>

            .ai-history-wrapper{
                max-width:1100px;
                margin:auto;
            }

            .ai-history-heading{
                font-size:34px;
                font-weight:800;
                margin-bottom:30px;
                color:#111827;
            }

            .ai-history-card{
                background:linear-gradient(
                    135deg,
                    #ffffff,
                    #f8fafc
                );

                border:1px solid #e5e7eb;

                border-radius:24px;

                padding:28px;

                margin-bottom:25px;

                box-shadow:
                    0 10px 30px rgba(0,0,0,0.06);

                transition:0.3s ease;
            }

            .ai-history-card:hover{
                transform:translateY(-3px);
            }

            .ai-history-top{
                display:flex;
                justify-content:space-between;
                align-items:flex-start;
                flex-wrap:wrap;
                gap:20px;
            }

            .ai-history-title{
                font-size:28px;
                font-weight:800;
                color:#111827;
                margin-bottom:6px;
            }

            .ai-history-date{
                color:#6b7280;
                font-size:14px;
            }

            .ai-status-row{
                display:flex;
                gap:10px;
                flex-wrap:wrap;
                margin-top:18px;
            }

            .ai-badge{
                display:inline-flex;
                align-items:center;
                gap:8px;

                padding:10px 16px;

                border-radius:999px;

                font-size:14px;
                font-weight:700;
            }

            .ai-badge-green{
                background:#dcfce7;
                color:#166534;
            }

            .ai-badge-yellow{
                background:#fef3c7;
                color:#92400e;
            }

            .ai-badge-blue{
                background:#dbeafe;
                color:#1d4ed8;
            }

            .ai-badge-red{
                background:#fee2e2;
                color:#b91c1c;
            }

            .ai-score-grid{
                display:grid;
                grid-template-columns:
                    repeat(auto-fit,minmax(180px,1fr));

                gap:18px;

                margin-top:25px;
            }

            .ai-score-box{
                background:#fff;

                border:1px solid #e5e7eb;

                border-radius:18px;

                padding:20px;
            }

            .ai-score-label{
                color:#6b7280;
                font-size:13px;
                margin-bottom:8px;
            }

            .ai-score-value{
                font-size:28px;
                font-weight:800;
                color:#111827;
            }

            .ai-open-btn{
                display:inline-block;

                margin-top:28px;

                background:#2563eb;

                color:#fff !important;

                text-decoration:none;

                padding:14px 24px;

                border-radius:14px;

                font-weight:700;

                transition:0.25s ease;
            }

            .ai-open-btn:hover{
                background:#1d4ed8;
                transform:translateY(-2px);
            }

        </style>

        <div class="ai-history-wrapper">

            <div class="ai-history-heading">
                📚 Your Quiz History
            </div>

            <?php
                $counter = 1;

            foreach ($quizzes as $quiz):

                $attempted = (int)$quiz->quiz_attempted;
                $evaluated = (int)$quiz->evaluated;
                $passed    = (int)$quiz->bot_scre_passed;

                $obt_score = $quiz->bot_obt_score;
                $tot_score = $quiz->bot_tot_score;
                $perc      = $quiz->bot_obt_perc;
            ?>

                <div class="ai-history-card">

                    <div class="ai-history-top">

                        <div>

                            <div class="ai-history-title">

                                Quiz #<?php echo esc_html($counter); ?>

                            </div>

                            <div class="ai-history-date">

                                Created:
                                <?php
                                echo esc_html(
                                    $quiz->created_at
                                );
                                ?>

                            </div>

                        </div>

                    </div>

                    <!-- STATUS BADGES -->

                    <div class="ai-status-row">

                        <?php if ($attempted): ?>

                            <div class="ai-badge ai-badge-green">
                                ✅ Attempt Completed
                            </div>

                        <?php else: ?>

                            <div class="ai-badge ai-badge-yellow">
                                ⏳ Attempt Pending
                            </div>

                        <?php endif; ?>

                        <?php if ($evaluated): ?>

                            <div class="ai-badge ai-badge-blue">
                                🤖 Evaluation Completed
                            </div>

                        <?php else: ?>

                            <div class="ai-badge ai-badge-yellow">
                                🧠 Evaluation Pending
                            </div>

                        <?php endif; ?>

                        <?php if ($evaluated): ?>

                            <?php if ($passed): ?>

                                <div class="ai-badge ai-badge-green">
                                    🎉 Passed
                                </div>

                            <?php else: ?>

                                <div class="ai-badge ai-badge-red">
                                    ❌ Failed
                                </div>

                            <?php endif; ?>

                        <?php endif; ?>

                    </div>

                    <!-- SCORE DETAILS -->

                    <?php if ($evaluated): ?>

                        <div class="ai-score-grid">

                            <div class="ai-score-box">

                                <div class="ai-score-label">
                                    Obtained Score
                                </div>

                                <div class="ai-score-value">
                                    <?php
                                    echo esc_html($obt_score);
                                    ?>
                                </div>

                            </div>

                            <div class="ai-score-box">

                                <div class="ai-score-label">
                                    Total Score
                                </div>

                                <div class="ai-score-value">
                                    <?php
                                    echo esc_html($tot_score);
                                    ?>
                                </div>

                            </div>

                            <div class="ai-score-box">

                                <div class="ai-score-label">
                                    Percentage
                                </div>

                                <div class="ai-score-value">
                                    <?php
                                    echo esc_html($perc);
                                    ?>
                                </div>

                            </div>

                        </div>

                    <?php endif; ?>

                    <a
                        class="ai-open-btn"
                        href="<?php echo site_url('/take-quiz/?quiz_id=' . $quiz->id); ?>"
                    >
                        Open Quiz →
                    </a>

                </div>

            <?php
                $counter++;
            endforeach;
            ?>

        </div>

    <?php

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



