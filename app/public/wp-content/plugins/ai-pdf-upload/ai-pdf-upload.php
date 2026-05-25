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
    $q_table    = $wpdb->prefix . "ai_questions";

    $user_id = get_current_user_id();

    // =========================
    // GET QUIZ
    // =========================

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

    if (!$quiz) {

        return "<p>No quiz found.</p>";
    }

    // =========================
    // GET TOTAL QUESTIONS
    // =========================

    $total_questions = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT COUNT(*)
            FROM $q_table
            WHERE quiz_id = %d
            ",
            $quiz->id
        )
    );

    // =========================
    // QUIZ COMPLETED VIEW
    // =========================

    if ($quiz->quiz_attempted) {

        $questions = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT *
                FROM $q_table
                WHERE quiz_id = %d
                ",
                $quiz->id
            )
        );

        ob_start();
        ?>

        <style>

            .ai-result-wrap{
                max-width:1000px;
                margin:auto;
            }

            .ai-result-top{
                background:linear-gradient(
                    135deg,
                    #2563eb,
                    #1e40af
                );

                color:white;

                padding:35px;

                border-radius:24px;

                margin-bottom:30px;
            }

            .ai-result-title{
                font-size:38px;
                font-weight:800;
                margin-bottom:10px;
            }

            .ai-result-grid{
                display:grid;

                grid-template-columns:
                    repeat(auto-fit,minmax(220px,1fr));

                gap:20px;

                margin-top:25px;
            }

            .ai-result-box{
                background:white;

                border-radius:20px;

                padding:22px;

                color:#111827;
            }

            .ai-result-label{
                font-size:14px;
                color:#6b7280;
                margin-bottom:8px;
            }

            .ai-result-value{
                font-size:30px;
                font-weight:800;
            }

            .ai-question-card{
                background:white;

                border:1px solid #e5e7eb;

                border-radius:22px;

                padding:28px;

                margin-bottom:25px;

                box-shadow:
                    0 8px 20px rgba(0,0,0,0.05);
            }

            .ai-question{
                font-size:22px;
                font-weight:700;
                margin-bottom:22px;
            }

            .ai-answer-grid{
                display:grid;

                grid-template-columns:
                    repeat(auto-fit,minmax(280px,1fr));

                gap:18px;

                margin-top:28px;
            }

            .ai-answer-card{
                position:relative;

                overflow:hidden;

                border-radius:24px;

                padding:24px;

                border:1px solid #e5e7eb;

                background:
                    linear-gradient(
                        145deg,
                        #ffffff,
                        #f8fafc
                    );

                box-shadow:
                    0 12px 30px rgba(15,23,42,0.06);

                transition:0.28s ease;
            }

            .ai-answer-card:hover{
                transform:translateY(-3px);

                box-shadow:
                    0 18px 40px rgba(15,23,42,0.10);
            }

            .ai-answer-glow{
                position:absolute;

                top:-70px;
                right:-70px;

                width:160px;
                height:160px;

                border-radius:999px;

                opacity:0.25;
            }

            .ai-answer-user .ai-answer-glow{
                background:
                    radial-gradient(
                        circle,
                        #3b82f6,
                        transparent 70%
                    );
            }

            .ai-answer-correct .ai-answer-glow{
                background:
                    radial-gradient(
                        circle,
                        #22c55e,
                        transparent 70%
                    );
            }

            .ai-answer-explanation .ai-answer-glow{
                background:
                    radial-gradient(
                        circle,
                        #8b5cf6,
                        transparent 70%
                    );
            }

            .ai-answer-score .ai-answer-glow{
                background:
                    radial-gradient(
                        circle,
                        #f59e0b,
                        transparent 70%
                    );
            }

            .ai-answer-top{
                position:relative;
                z-index:2;

                display:flex;
                align-items:center;
                gap:14px;

                margin-bottom:18px;
            }

            .ai-answer-icon{
                width:52px;
                height:52px;

                display:flex;
                align-items:center;
                justify-content:center;

                border-radius:18px;

                font-size:22px;

                flex-shrink:0;
            }

            .ai-answer-user .ai-answer-icon{
                background:#dbeafe;
                color:#1d4ed8;
            }

            .ai-answer-correct .ai-answer-icon{
                background:#dcfce7;
                color:#15803d;
            }

            .ai-answer-explanation .ai-answer-icon{
                background:#ede9fe;
                color:#7c3aed;
            }

            .ai-answer-score .ai-answer-icon{
                background:#fef3c7;
                color:#d97706;
            }

            .ai-answer-options .ai-answer-glow{
                background:
                    radial-gradient(
                        circle,
                        #06b6d4,
                        transparent 70%
                    );
            }

            .ai-answer-options .ai-answer-icon{
                background:#cffafe;
                color:#0f766e;
            }

            .ai-options-list{
                display:flex;
                flex-direction:column;
                gap:14px;
            }

            .ai-option-item{
                display:flex;
                align-items:flex-start;
                gap:14px;

                padding:16px;

                border-radius:18px;

                background:
                    rgba(255,255,255,0.7);

                border:1px solid #e5e7eb;

                transition:0.25s ease;
            }

            .ai-option-item:hover{
                transform:translateX(3px);

                border-color:#93c5fd;

                background:#f8fbff;
            }

            .ai-option-key{
                width:38px;
                height:38px;

                border-radius:14px;

                display:flex;
                align-items:center;
                justify-content:center;

                flex-shrink:0;

                font-size:15px;
                font-weight:800;

                background:#e0f2fe;

                color:#0369a1;
            }

            .ai-option-text{
                flex:1;

                color:#1f2937;

                font-size:14px;

                line-height:1.8;
            }

            .ai-option-correct{
                border:2px solid #22c55e;

                background:
                    linear-gradient(
                        145deg,
                        #f0fdf4,
                        #dcfce7
                    );
            }

            .ai-option-correct .ai-option-key{
                background:#22c55e;
                color:white;
            }

            .ai-answer-heading{
                font-size:15px;

                font-weight:800;

                color:#111827;

                margin-bottom:4px;
            }

            .ai-answer-sub{
                font-size:13px;

                color:#6b7280;
            }

            .ai-answer-content{
                position:relative;
                z-index:2;

                color:#1f2937;

                line-height:1.9;

                font-size:15px;

                word-break:break-word;
            }

            .ai-score-number{
                font-size:52px;

                line-height:1;

                font-weight:900;

                color:#111827;

                margin-bottom:10px;
            }

            .ai-score-pill{
                display:inline-flex;
                align-items:center;
                gap:8px;

                padding:10px 16px;

                border-radius:999px;

                background:#fef3c7;

                color:#92400e;

                font-size:13px;

                font-weight:800;
            }

        </style>

        <div class="ai-result-wrap">

            <div class="ai-result-top">

                <div class="ai-result-title">
                    🎉 Quiz Completed
                </div>

                <div>
                    AI evaluation summary for your quiz.
                </div>

                <?php if ($quiz->evaluated): ?>

                    <div class="ai-result-grid">

                        <div class="ai-result-box">

                            <div class="ai-result-label">
                                Obtained Score
                            </div>

                            <div class="ai-result-value">
                                <?php
                                echo esc_html(
                                    $quiz->bot_obt_score
                                );
                                ?>
                            </div>

                        </div>

                        <div class="ai-result-box">

                            <div class="ai-result-label">
                                Total Score
                            </div>

                            <div class="ai-result-value">
                                <?php
                                echo esc_html(
                                    $quiz->bot_tot_score
                                );
                                ?>
                            </div>

                        </div>

                        <div class="ai-result-box">

                            <div class="ai-result-label">
                                Percentage
                            </div>

                            <div class="ai-result-value">
                                <?php
                                echo esc_html(
                                    $quiz->bot_obt_perc
                                );
                                ?>
                            </div>

                        </div>

                        <div class="ai-result-box">

                            <div class="ai-result-label">
                                Status
                            </div>

                            <div class="ai-result-value">

                                <?php if ($quiz->bot_scre_passed): ?>

                                    ✅ Passed

                                <?php else: ?>

                                    ❌ Failed

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                <?php else: ?>

                    <div style="
                        margin-top:25px;
                        font-size:18px;
                    ">
                        🧠 Evaluation Pending...
                    </div>

                <?php endif; ?>

            </div>

            <?php
                $counter = 1;

            foreach ($questions as $q):

                $user_answer =
                    maybe_unserialize($q->user_answer);
            ?>

                <div class="ai-question-card">

                    <div class="ai-question">

                        Q<?php echo $counter; ?>.
                        <?php echo esc_html($q->question); ?>

                    </div>

                    <div class="ai-answer-grid">

                        <!-- YOUR ANSWER -->

                        <div class="ai-answer-card ai-answer-user">

                            <div class="ai-answer-glow"></div>

                            <div class="ai-answer-top">

                                <div class="ai-answer-icon">
                                    ✍️
                                </div>

                                <div>

                                    <div class="ai-answer-heading">
                                        Your Answer
                                    </div>

                                    <div class="ai-answer-sub">
                                        Submitted response
                                    </div>

                                </div>

                            </div>

                            <div class="ai-answer-content">

                                <?php
                                echo nl2br(
                                    esc_html($user_answer)
                                );
                                ?>

                            </div>

                        </div>

                        <!-- CORRECT ANSWER -->

                        <div class="ai-answer-card ai-answer-correct">

                            <div class="ai-answer-glow"></div>

                            <div class="ai-answer-top">

                                <div class="ai-answer-icon">
                                    ✅
                                </div>

                                <div>

                                    <div class="ai-answer-heading">
                                        Correct Answer
                                    </div>

                                    <div class="ai-answer-sub">
                                        AI verified solution
                                    </div>

                                </div>

                            </div>

                            <div class="ai-answer-content">

                                <?php
                                echo nl2br(
                                    esc_html(
                                        $q->correct_answer
                                    )
                                );
                                ?>

                            </div>

                        </div>

                        <?php
                            $decoded_options = [];

                            if (
                                $q->type === "MCQ"
                                &&
                                !empty($q->options_json)
                            ) {

                                $decoded_options = json_decode(
                                    $q->options_json,
                                    true
                                );
                            }
                            ?>

                            <!-- OPTIONS CARD -->

                            <?php if (!empty($decoded_options)): ?>

                                <div class="ai-answer-card ai-answer-options">

                                    <div class="ai-answer-glow"></div>

                                    <div class="ai-answer-top">

                                        <div class="ai-answer-icon">
                                            📚
                                        </div>

                                        <div>

                                            <div class="ai-answer-heading">
                                                Given Options
                                            </div>

                                            <div class="ai-answer-sub">
                                                Available MCQ choices
                                            </div>

                                        </div>

                                    </div>

                                    <div class="ai-answer-content">

                                        <div class="ai-options-list">

                                            <?php
                                            foreach ($decoded_options as $opt_key => $opt_value):

                                                $is_correct =
                                                    trim($opt_key)
                                                    ===
                                                    trim($q->correct_answer);
                                            ?>

                                                <div class="
                                                    ai-option-item
                                                    <?php
                                                    echo $is_correct
                                                        ? 'ai-option-correct'
                                                        : '';
                                                    ?>
                                                ">

                                                    <div class="ai-option-key">

                                                        <?php
                                                        echo esc_html($opt_key);
                                                        ?>

                                                    </div>

                                                    <div class="ai-option-text">

                                                        <?php
                                                        echo esc_html($opt_value);
                                                        ?>

                                                    </div>

                                                </div>

                                            <?php endforeach; ?>

                                        </div>

                                    </div>

                                </div>

                        <?php endif; ?>

                        <!-- EXPLANATION -->

                        <div class="ai-answer-card ai-answer-explanation">

                            <div class="ai-answer-glow"></div>

                            <div class="ai-answer-top">

                                <div class="ai-answer-icon">
                                    🧠
                                </div>

                                <div>

                                    <div class="ai-answer-heading">
                                        AI Explanation
                                    </div>

                                    <div class="ai-answer-sub">
                                        Concept breakdown
                                    </div>

                                </div>

                            </div>

                            <div class="ai-answer-content">

                                <?php
                                echo nl2br(
                                    esc_html(
                                        $q->explanation
                                    )
                                );
                                ?>

                            </div>

                        </div>

                        <!-- SCORE -->

                        <?php if ($q->its_score !== null): ?>

                            <div class="ai-answer-card ai-answer-score">

                                <div class="ai-answer-glow"></div>

                                <div class="ai-answer-top">

                                    <div class="ai-answer-icon">
                                        ⚡
                                    </div>

                                    <div>

                                        <div class="ai-answer-heading">
                                            AI Score
                                        </div>

                                        <div class="ai-answer-sub">
                                            Intelligent evaluation
                                        </div>

                                    </div>

                                </div>

                                <div class="ai-answer-content">

                                    <div class="ai-score-number">

                                        <?php
                                        echo esc_html(
                                            $q->its_score
                                        );
                                        ?>

                                        <span style="
                                            font-size:24px;
                                            color:#6b7280;
                                        ">
                                            /10
                                        </span>

                                    </div>

                                    <div class="ai-score-pill">

                                        🤖 AI Assessment Complete

                                    </div>

                                </div>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            <?php
                $counter++;
            endforeach;
            ?>

        </div>

        <?php

        return ob_get_clean();
    }

    // =========================
    // GET NEXT UNANSWERED QUESTION
    // =========================

    $question = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM $q_table
            WHERE quiz_id = %d
            AND user_answer IS NULL
            ORDER BY id ASC
            LIMIT 1
            ",
            $quiz->id
        )
    );

    if (!$question) {

        $wpdb->update(
            $quiz_table,
            [
                'quiz_attempted' => 1
            ],
            [
                'id' => $quiz->id
            ]
        );

        wp_redirect(
            site_url(
                '/take-quiz/?quiz_id=' . $quiz->id
            )
        );

        exit;
    }

    // =========================
    // PROGRESS
    // =========================

    $answered_count = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT COUNT(*)
            FROM $q_table
            WHERE quiz_id = %d
            AND user_answer IS NOT NULL
            ",
            $quiz->id
        )
    );

    $current_number = $answered_count + 1;

    $progress =
        ($answered_count / $total_questions) * 100;

    ob_start();
    ?>

    <style>

        .ai-quiz-wrap{
            max-width:850px;
            margin:auto;
        }

        .ai-progress{
            background:#e5e7eb;
            height:14px;
            border-radius:999px;
            overflow:hidden;
            margin-bottom:28px;
        }

        .ai-progress-fill{
            background:#2563eb;
            height:100%;
        }

        .ai-quiz-card{
            background:white;

            border-radius:28px;

            padding:40px;

            box-shadow:
                0 10px 30px rgba(0,0,0,0.06);

            border:1px solid #e5e7eb;
        }

        .ai-question-count{
            color:#2563eb;
            font-weight:700;
            margin-bottom:14px;
        }

        .ai-question-title{
            font-size:30px;
            font-weight:800;
            line-height:1.5;
            margin-bottom:30px;
            color:#111827;
        }

        .ai-option{
            display:block;

            padding:18px;

            border:2px solid #e5e7eb;

            border-radius:16px;

            margin-bottom:16px;

            cursor:pointer;

            transition:0.25s ease;
        }

        .ai-option:hover{
            border-color:#2563eb;
            background:#eff6ff;
        }

        .ai-textarea{
            width:100%;

            min-height:180px;

            border:2px solid #e5e7eb;

            border-radius:18px;

            padding:20px;

            font-size:16px;
        }

        .ai-next-btn{
            margin-top:28px;

            background:#2563eb;

            color:white;

            border:none;

            padding:16px 30px;

            border-radius:16px;

            font-size:17px;

            font-weight:700;

            cursor:pointer;
        }

    </style>

    <div class="ai-quiz-wrap">

        <div style="
            margin-bottom:15px;
            font-weight:700;
        ">

            Question
            <?php echo $current_number; ?>
            of
            <?php echo $total_questions; ?>

        </div>

        <div class="ai-progress">

            <div
                class="ai-progress-fill"
                style="width:
                <?php echo $progress; ?>%;
                "
            ></div>

        </div>

        <div class="ai-quiz-card">

            <div class="ai-question-count">

                <!-- Quiz #<?php echo esc_html($quiz->id); ?> -->

            </div>

            <div class="ai-question-title">

                <?php
                echo esc_html($question->question);
                ?>

            </div>

            <form method="post">

                <input
                    type="hidden"
                    name="ai_question_id"
                    value="<?php echo $question->id; ?>"
                >

                <input
                    type="hidden"
                    name="ai_quiz_id"
                    value="<?php echo $quiz->id; ?>"
                >

                <?php
                if (
                    $question->type === "MCQ"
                    &&
                    $question->options_json
                ):

                    $opts = json_decode(
                        $question->options_json,
                        true
                    );

                    foreach ($opts as $key => $val):
                ?>

                    <label class="ai-option">

                        <input
                            type="radio"
                            name="user_answer"
                            value="<?php echo esc_attr($key); ?>"
                            required
                        >

                        <strong>
                            <?php echo esc_html($key); ?>
                        </strong>

                        —
                        <?php echo esc_html($val); ?>

                    </label>

                <?php
                    endforeach;

                else:
                ?>

                    <textarea
                        class="ai-textarea"
                        name="user_answer"
                        required
                    ></textarea>

                <?php endif; ?>

                <button
                    class="ai-next-btn"
                    type="submit"
                    name="ai_next_question"
                >

                    Next Question →

                </button>

            </form>

        </div>

    </div>

    <?php

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

    $pending_requests = (int) get_user_meta(
        $user_id,
        'ai_quiz_request',
        true
    );

    if (!$quizzes) {

        // =========================================
        // NO QUIZZES YET
        // BUT AI REQUESTS ARE PROCESSING
        // =========================================

        if ($pending_requests > 0) {

            return '

                <div
                    style="
                        max-width:1100px;
                        margin:70px auto;

                        position:relative;

                        overflow:hidden;

                        border-radius:30px;

                        padding:42px;

                        background:
                            linear-gradient(
                                135deg,
                                #0f172a,
                                #111827,
                                #1e293b
                            );

                        box-shadow:
                            0 20px 60px rgba(15,23,42,0.28);

                        border:1px solid rgba(255,255,255,0.08);
                    "
                >

                    <!-- GLOW EFFECTS -->

                    <div
                        style="
                            position:absolute;
                            top:-120px;
                            right:-120px;

                            width:260px;
                            height:260px;

                            background:
                                radial-gradient(
                                    circle,
                                    rgba(59,130,246,0.35),
                                    transparent 70%
                                );
                        "
                    ></div>

                    <div
                        style="
                            position:absolute;
                            bottom:-100px;
                            left:-100px;

                            width:240px;
                            height:240px;

                            background:
                                radial-gradient(
                                    circle,
                                    rgba(124,58,237,0.28),
                                    transparent 70%
                                );
                        "
                    ></div>

                    <div
                        style="
                            position:relative;
                            z-index:2;

                            display:flex;
                            justify-content:space-between;
                            align-items:center;
                            gap:35px;
                            flex-wrap:wrap;
                        "
                    >

                        <!-- LEFT -->

                        <div
                            style="
                                flex:1;
                                min-width:300px;
                            "
                        >

                            <div
                                style="
                                    display:inline-flex;
                                    align-items:center;
                                    gap:10px;

                                    padding:10px 16px;

                                    border-radius:999px;

                                    background:
                                        rgba(255,255,255,0.08);

                                    color:#cbd5e1;

                                    font-size:13px;

                                    font-weight:700;

                                    margin-bottom:22px;

                                    backdrop-filter:blur(8px);
                                "
                            >

                                ⚡ AI Processing Queue Active

                            </div>

                            <div
                                style="
                                    font-size:38px;
                                    font-weight:900;

                                    line-height:1.3;

                                    color:#ffffff;

                                    margin-bottom:18px;
                                "
                            >

                                Your AI quiz is currently
                                being generated

                            </div>

                            <div
                                style="
                                    max-width:720px;

                                    color:#cbd5e1;

                                    font-size:16px;

                                    line-height:1.9;
                                "
                            >

                                Our AI engine is processing your uploaded
                                PDF documents, extracting concepts,
                                generating intelligent questions,
                                and preparing your quiz experience.

                                Once processing is completed,
                                your generated quizzes will automatically
                                appear here in your history dashboard.

                            </div>

                        </div>

                        <!-- RIGHT -->

                        <div
                            style="
                                min-width:240px;

                                background:
                                    rgba(255,255,255,0.08);

                                border:1px solid rgba(255,255,255,0.08);

                                backdrop-filter:blur(10px);

                                border-radius:28px;

                                padding:34px 28px;

                                text-align:center;
                            "
                        >

                            <div
                                style="
                                    font-size:16px;
                                    font-weight:700;

                                    color:#cbd5e1;

                                    margin-bottom:16px;
                                "
                            >

                                Pending Requests

                            </div>

                            <div
                                style="
                                    font-size:68px;
                                    font-weight:900;

                                    line-height:1;

                                    color:#ffffff;

                                    margin-bottom:16px;
                                "
                            >

                                ' . esc_html($pending_requests) . '

                            </div>

                            <div
                                style="
                                    display:inline-flex;
                                    align-items:center;
                                    justify-content:center;
                                    gap:8px;

                                    background:
                                        rgba(34,197,94,0.16);

                                    color:#86efac;

                                    padding:10px 16px;

                                    border-radius:999px;

                                    font-size:13px;

                                    font-weight:800;
                                "
                            >

                                🧠 AI Engine Running

                            </div>

                        </div>

                    </div>

                </div>
            ';
        }

        // =========================================
        // NO QUIZZES + NO PENDING REQUESTS
        // =========================================


        return '

            <div
                style="
                    max-width:900px;
                    margin:70px auto;
                    padding:60px 40px;

                    background:
                        linear-gradient(
                            145deg,
                            #ffffff,
                            #f8fafc
                        );

                    border:1px solid #e5e7eb;

                    border-radius:32px;

                    box-shadow:
                        0 20px 50px rgba(0,0,0,0.08);

                    text-align:center;

                    position:relative;

                    overflow:hidden;
                "
            >

                <div
                    style="
                        position:absolute;
                        top:-120px;
                        right:-120px;

                        width:260px;
                        height:260px;

                        background:
                            radial-gradient(
                                circle,
                                rgba(99,102,241,0.15),
                                transparent 70%
                            );
                    "
                ></div>

                <div
                    style="
                        position:absolute;
                        bottom:-120px;
                        left:-120px;

                        width:260px;
                        height:260px;

                        background:
                            radial-gradient(
                                circle,
                                rgba(37,99,235,0.12),
                                transparent 70%
                            );
                    "
                ></div>

                <div
                    style="
                        position:relative;
                        z-index:2;
                    "
                >

                    <div
                        style="
                            font-size:72px;
                            margin-bottom:20px;
                        "
                    >
                        🧠
                    </div>

                    <div
                        style="
                            font-size:38px;
                            font-weight:900;
                            color:#111827;

                            margin-bottom:18px;
                        "
                    >

                        No Quizzes Found

                    </div>

                    <div
                        style="
                            max-width:620px;
                            margin:auto;

                            color:#6b7280;

                            font-size:17px;

                            line-height:1.8;

                            margin-bottom:35px;
                        "
                    >

                        Your AI quiz history is currently empty.
                        Upload your documents and let the AI engine
                        generate intelligent assessments,
                        evaluations, and performance reports.

                    </div>

                    <a
                        href="' . site_url('/upload-documents-for-evaluation/') . '"

                        style="
                            display:inline-flex;
                            align-items:center;
                            justify-content:center;
                            gap:10px;

                            background:
                                linear-gradient(
                                    135deg,
                                    #2563eb,
                                    #7c3aed
                                );

                            color:#fff;

                            text-decoration:none;

                            padding:18px 30px;

                            border-radius:18px;

                            font-size:16px;

                            font-weight:800;

                            box-shadow:
                                0 14px 30px rgba(37,99,235,0.22);

                            transition:0.25s ease;
                        "

                        onmouseover="
                            this.style.transform=
                            \'translateY(-2px)\';

                            this.style.opacity=
                            \'0.96\';
                        "

                        onmouseout="
                            this.style.transform=
                            \'translateY(0)\';

                            this.style.opacity=
                            \'1\';
                        "
                    >

                        ✨ Try New Quiz

                    </a>

                </div>

            </div>
        ';
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

            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                    gap:20px;
                    flex-wrap:wrap;
                    margin-bottom:30px;
                "
            >

                <?php

                    $pending_requests = (int) get_user_meta(
                        $user_id,
                        'ai_quiz_request',
                        true
                    );

                    if ($pending_requests > 0):
                    ?>

                        <div
                            style="
                                position:relative;

                                overflow:hidden;

                                margin-bottom:35px;

                                border-radius:30px;

                                padding:38px;

                                background:
                                    linear-gradient(
                                        135deg,
                                        #0f172a,
                                        #111827,
                                        #1e293b
                                    );

                                box-shadow:
                                    0 20px 60px rgba(15,23,42,0.28);

                                border:1px solid rgba(255,255,255,0.08);
                            "
                        >

                            <!-- GLOW -->

                            <div
                                style="
                                    position:absolute;
                                    top:-120px;
                                    right:-120px;

                                    width:260px;
                                    height:260px;

                                    background:
                                        radial-gradient(
                                            circle,
                                            rgba(59,130,246,0.35),
                                            transparent 70%
                                        );
                                "
                            ></div>

                            <div
                                style="
                                    position:absolute;
                                    bottom:-100px;
                                    left:-100px;

                                    width:240px;
                                    height:240px;

                                    background:
                                        radial-gradient(
                                            circle,
                                            rgba(124,58,237,0.28),
                                            transparent 70%
                                        );
                                "
                            ></div>

                            <div
                                style="
                                    position:relative;
                                    z-index:2;

                                    display:flex;
                                    justify-content:space-between;
                                    align-items:center;
                                    gap:30px;
                                    flex-wrap:wrap;
                                "
                            >

                                <!-- LEFT -->

                                <div
                                    style="
                                        flex:1;
                                        min-width:280px;
                                    "
                                >

                                    <div
                                        style="
                                            display:inline-flex;
                                            align-items:center;
                                            gap:10px;

                                            padding:10px 16px;

                                            border-radius:999px;

                                            background:
                                                rgba(255,255,255,0.08);

                                            color:#cbd5e1;

                                            font-size:13px;

                                            font-weight:700;

                                            margin-bottom:20px;

                                            backdrop-filter:blur(8px);
                                        "
                                    >

                                        ⚡ AI Processing Queue Active

                                    </div>

                                    <div
                                        style="
                                            font-size:34px;
                                            font-weight:900;

                                            line-height:1.3;

                                            color:#ffffff;

                                            margin-bottom:18px;
                                        "
                                    >

                                        Your AI quiz generation
                                        request is being processed

                                    </div>

                                    <div
                                        style="
                                            max-width:700px;

                                            color:#cbd5e1;

                                            font-size:16px;

                                            line-height:1.9;
                                        "
                                    >

                                        Our AI engine is currently analyzing your uploaded
                                        PDF documents, extracting important concepts,
                                        generating intelligent quiz questions,
                                        and preparing evaluation data.

                                        Your generated quizzes will automatically appear
                                        in your history once processing is completed.

                                    </div>

                                </div>

                                <!-- RIGHT -->

                                <div
                                    style="
                                        min-width:220px;

                                        background:
                                            rgba(255,255,255,0.08);

                                        border:1px solid rgba(255,255,255,0.08);

                                        backdrop-filter:blur(10px);

                                        border-radius:28px;

                                        padding:30px 26px;

                                        text-align:center;
                                    "
                                >

                                    <div
                                        style="
                                            font-size:16px;
                                            font-weight:700;

                                            color:#cbd5e1;

                                            margin-bottom:14px;
                                        "
                                    >

                                        Pending Requests

                                    </div>

                                    <div
                                        style="
                                            font-size:64px;
                                            font-weight:900;

                                            line-height:1;

                                            color:#ffffff;

                                            margin-bottom:14px;
                                        "
                                    >

                                        <?php echo esc_html($pending_requests); ?>

                                    </div>

                                    <div
                                        style="
                                            display:inline-flex;
                                            align-items:center;
                                            justify-content:center;
                                            gap:8px;

                                            background:
                                                rgba(34,197,94,0.16);

                                            color:#86efac;

                                            padding:10px 16px;

                                            border-radius:999px;

                                            font-size:13px;

                                            font-weight:800;
                                        "
                                    >

                                        🧠 AI Engine Running

                                    </div>

                                </div>

                            </div>

                        </div>

                    <?php endif; ?>

                <div class="ai-history-heading">

                    📚 Your Quiz History

                </div>

                <a
                    href="<?php echo site_url('/upload-documents-for-evaluation/'); ?>"
                    style="
                        display:inline-flex;
                        align-items:center;
                        gap:10px;

                        background:
                            linear-gradient(
                                135deg,
                                #2563eb,
                                #7c3aed
                            );

                        color:#fff;

                        text-decoration:none;

                        padding:14px 22px;

                        border-radius:14px;

                        font-weight:700;

                        font-size:15px;

                        box-shadow:
                            0 10px 25px rgba(37,99,235,0.25);

                        transition:0.25s ease;
                    "

                    onmouseover="
                        this.style.transform='translateY(-2px)';
                        this.style.opacity='0.95';
                    "

                    onmouseout="
                        this.style.transform='translateY(0)';
                        this.style.opacity='1';
                    "
                >

                    ✨ Try New Quiz

                </a>

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

    if (!isset($_POST['ai_next_question'])) {

        return;
    }

    global $wpdb;

    $quiz_table = $wpdb->prefix . "ai_quizzes";
    $q_table    = $wpdb->prefix . "ai_questions";

    $quiz_id     = intval($_POST['ai_quiz_id']);
    $question_id = intval($_POST['ai_question_id']);

    $user_answer = sanitize_textarea_field(
        $_POST['user_answer']
    );

    // SAVE ANSWER

    $wpdb->update(
        $q_table,
        [
            'user_answer' =>
                maybe_serialize($user_answer)
        ],
        [
            'id' => $question_id
        ],
        [
            '%s'
        ],
        [
            '%d'
        ]
    );

    // CHECK REMAINING QUESTIONS

    $remaining = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT COUNT(*)
            FROM $q_table
            WHERE quiz_id = %d
            AND user_answer IS NULL
            ",
            $quiz_id
        )
    );

    // IF FINISHED

    if ($remaining == 0) {

        $wpdb->update(
            $quiz_table,
            [
                'quiz_attempted' => 1
            ],
            [
                'id' => $quiz_id
            ]
        );
    }

    wp_redirect(
        site_url(
            '/take-quiz/?quiz_id=' . $quiz_id
        )
    );

    exit;
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



function ai_send_multiple_files_to_flask($curl_files,$user_id) {

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
        return  false;
    }

    curl_close($ch);

    $data = json_decode($response, true);

    // if (!$data || !isset($data['quiz'])) {
    //     error_log("Invalid quiz response");
    //     return;
    // }

    $current_requests = (int) get_user_meta(
        $user_id,
        'ai_quiz_request',
        true
    );

    update_user_meta(
        $user_id,
        'ai_quiz_request',
        $current_requests + 1
    );

    return  true;

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

        $success =  ai_send_multiple_files_to_flask($curl_files,$user_id);
        if ($success) {

            wp_redirect(
                site_url('/quizez-history/')
            );

            exit;
        }

        wp_die(
            "AI processing failed. Please try again."
        );
    }
}



