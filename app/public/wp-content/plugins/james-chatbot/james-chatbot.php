<?php
/*
Plugin Name: James Chatbot
Description: Domain-based AI Chatbot System
Version: 1.0
*/

if (!defined('ABSPATH')) {
    exit;
}

define('JAMES_CHATBOT_VERSION', '1.0');
define('JCB_PATH', plugin_dir_path(__FILE__));

/* ============================================
   CREATE TABLES ON ACTIVATION
============================================ */

register_activation_hook(__FILE__, 'jcb_activate_plugin');

function jcb_activate_plugin() {

    jcb_create_tables();

    jcb_remove_old_unique_index();

    update_option(
        'jcb_plugin_version',
        JAMES_CHATBOT_VERSION
    );
}

function jcb_remove_old_unique_index() {

    global $wpdb;

    $table_name = $wpdb->prefix . "chatbot_james";

    // Get indexes
    $indexes = $wpdb->get_results(
        "SHOW INDEX FROM $table_name",
        ARRAY_A
    );

    if (!$indexes) {
        return;
    }

    foreach ($indexes as $index) {

        // Skip PRIMARY KEY
        if ($index['Key_name'] === 'PRIMARY') {
            continue;
        }

        // Remove UNIQUE index on user_id
        if (
            $index['Column_name'] === 'user_id'
            &&
            intval($index['Non_unique']) === 0
        ) {

            $index_name = $index['Key_name'];

            $wpdb->query(
                "ALTER TABLE $table_name
                 DROP INDEX $index_name"
            );
        }
    }
}

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('jquery');
});

function jcb_create_tables() {

    global $wpdb;

    $charset = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $main_table = $wpdb->prefix . "chatbot_james";
    $q_table    = $wpdb->prefix . "chatbotjames_questions";

    dbDelta("
        CREATE TABLE $main_table (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT,
            domain VARCHAR(100),
            evaluation_picked TINYINT DEFAULT 0,
            quiz_attempted TINYINT DEFAULT 0,
            evaluated TINYINT DEFAULT 0,
            bot_obt_score DECIMAL(10,2) DEFAULT 0,
            bot_tot_score DECIMAL(10,2) DEFAULT 0,
            bot_obt_perc VARCHAR(20) DEFAULT NULL,
            bot_scre_passed TINYINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset;
    ");

    dbDelta("
        CREATE TABLE $q_table (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            chatbotjames_id BIGINT,
            question TEXT,
            section VARCHAR(40),
            mode VARCHAR(40),
            answer TEXT,
            its_score DECIMAL(10,2) DEFAULT NULL,
            its_evaluation_explanation TEXT DEFAULT NULL
        ) $charset;
    ");
}

/* ============================================
   RUN EVALUATION
============================================ */

add_action(
    "wp_ajax_jcb_run_evaluation",
    "jcb_run_evaluation"
);

function jcb_run_evaluation() {

    if (!is_user_logged_in()) {

        wp_send_json([
            "success" => false
        ]);
    }

    global $wpdb;

    $user_id = get_current_user_id();

    $main_table = $wpdb->prefix . "chatbot_james";
    $q_table    = $wpdb->prefix . "chatbotjames_questions";

    $chatbot = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM $main_table
            WHERE user_id = %d
            ORDER BY id DESC
            LIMIT 1
            ",
            $user_id
        )
    );

    if (!$chatbot) {

        wp_send_json([
            "success" => false
        ]);
    }

    $domain = $chatbot->domain;

    $answers = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT id, question, answer
            FROM $q_table
            WHERE chatbotjames_id = %d
            ",
            $chatbot->id
        ),
        ARRAY_A
    );

    if (!$answers) {

        wp_send_json([
            "success" => false
        ]);
    }

    $response = wp_remote_post(
        "http://192.168.18.85:8007/evaluate_candidate/",
        [
            "headers" => [
                "Content-Type" => "application/json"
            ],

            "body" => json_encode([
                "domain"  => $domain,
                "answers" => $answers
            ]),

            "timeout" => 120
        ]
    );

    if (is_wp_error($response)) {

        wp_send_json([
            "success" => false
        ]);
    }

    $body = wp_remote_retrieve_body($response);

    $data = json_decode($body, true);

    if (!$data || empty($data["success"])) {

        wp_send_json([
            "success" => false
        ]);
    }

    $bot_obt_score = 0;
    $bot_tot_score = count($data["answers"]) * 10;

    foreach ($data["answers"] as $item) {

        $its_score = floatval($item["its_score"]);

        $its_evaluation_explanation =
            $item["its_evaluation_explanation"];

        $bot_obt_score += $its_score;

        $wpdb->update(
            $q_table,
            [
                "its_score" => $its_score,
                "its_evaluation_explanation" =>
                    $its_evaluation_explanation
            ],
            [
                "id" => $item["id"]
            ],
            [
                "%f",
                "%s"
            ],
            [
                "%d"
            ]
        );
    }

    $bot_obt_perc = floor(
        ($bot_obt_score / $bot_tot_score) * 100
    );

    $passed = ($bot_obt_perc > 70) ? 1 : 0;

    $wpdb->update(
        $main_table,
        [
            "bot_obt_score"     => $bot_obt_score,
            "bot_tot_score"     => $bot_tot_score,
            "bot_obt_perc"      => $bot_obt_perc . "%",
            "bot_scre_passed"   => $passed,
            "evaluated"         => 1
        ],
        [
            "id" => $chatbot->id
        ]
    );

    wp_send_json([
        "success" => true,
        "redirect_url" => site_url("/james-chatbot-history/")
    ]);
}

/* ============================================
   SUBMIT ANSWER
============================================ */

add_action(
    'wp_ajax_jcb_submit_answer',
    'jcb_submit_answer'
);

function jcb_submit_answer() {

    if (!is_user_logged_in()) {

        wp_send_json([
            'success' => false
        ], 403);
    }

    global $wpdb;

    $q_table = $wpdb->prefix . "chatbotjames_questions";

    $question_id = intval($_POST['question_id']);

    $answer = sanitize_textarea_field(
        $_POST['answer']
    );

    $wpdb->update(
        $q_table,
        [
            'answer' => $answer
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

    $chatbot_id = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT chatbotjames_id
            FROM $q_table
            WHERE id = %d
            ",
            $question_id
        )
    );

    $next_q = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM $q_table
            WHERE chatbotjames_id = %d
            AND (
                answer IS NULL
                OR answer = ''
            )
            ORDER BY id ASC
            LIMIT 1
            ",
            $chatbot_id
        )
    );

    if ($next_q) {

        wp_send_json([
            'success' => true,

            'question' => [
                'id'        => $next_q->id,
                'text'      => $next_q->question,
                'section'   => $next_q->section,
                'mode'      => $next_q->mode
            ]
        ]);
    }

    wp_send_json([
        'success'   => true,
        'completed' => true
    ]);
}

/* ============================================
   RESULT SHORTCODE
============================================ */

add_shortcode(
    "james_chatbot_result",
    "jcb_result_shortcode"
);

function jcb_result_shortcode() {

    if (!is_user_logged_in()) {

        return "<p>Please login.</p>";
    }

    global $wpdb;

    $user_id = get_current_user_id();

    $main_table = $wpdb->prefix . "chatbot_james";

    $chatbot = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM $main_table
            WHERE user_id = %d
            ORDER BY id DESC
            LIMIT 1
            ",
            $user_id
        )
    );

    if (!$chatbot) {

        return "<p>No evaluation found.</p>";
    }

    ob_start();
    ?>

    <div style="
        padding:20px;
        border:1px solid #ddd;
        border-radius:10px;
    ">

        <p>
            <strong>Domain:</strong>
            <?php echo esc_html($chatbot->domain); ?>
        </p>

        <p>
            <strong>Total Score:</strong>
            <?php echo esc_html($chatbot->bot_tot_score); ?>
        </p>

        <p>
            <strong>Obtained Score:</strong>
            <?php echo esc_html($chatbot->bot_obt_score); ?>
        </p>

        <p>
            <strong>Percentage:</strong>
            <?php echo esc_html($chatbot->bot_obt_perc); ?>
        </p>

        <?php if ($chatbot->bot_scre_passed): ?>

            <p style="color:green;">
                <strong>Status: PASSED ✅</strong>
            </p>

        <?php else: ?>

            <p style="color:red;">
                <strong>Status: FAILED ❌</strong>
            </p>

        <?php endif; ?>

    </div>

    <?php

    return ob_get_clean();
}

/* ============================================
   CHATBOT FORM SHORTCODE
============================================ */

add_shortcode(
    'james_chatbot_form',
    'jcb_domain_form_shortcode'
);

function jcb_domain_form_shortcode() {

    if (!is_user_logged_in()) {

        return "<p>Please login to continue.</p>";
    }

    global $wpdb;

    $main_table = $wpdb->prefix . "chatbot_james";

    $q_table = $wpdb->prefix . "chatbotjames_questions";

    $user_id = get_current_user_id();

    $active_chatbot = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM $main_table
            WHERE user_id = %d
            AND evaluated = 0
            ORDER BY id DESC
            LIMIT 1
            ",
            $user_id
        )
    );

    if (!$active_chatbot) {

        if (!function_exists('ai_get_remaining_entries')) {

            return "<p>Subscription system missing.</p>";
        }

        $remaining = ai_get_remaining_entries($user_id);

        if ($remaining <= 0) {

            return '
                <div style="
                    max-width:700px;
                    margin:40px auto;
                    padding:35px;
                    background:#ffffff;
                    border:1px solid #e5e7eb;
                    border-radius:24px;
                    box-shadow:0 10px 35px rgba(0,0,0,0.06);
                    text-align:center;
                ">

                    <div style="
                        font-size:58px;
                        margin-bottom:15px;
                    ">
                        🚫
                    </div>

                    <h2 style="
                        margin:0 0 12px 0;
                        font-size:32px;
                        font-weight:800;
                        color:#111827;
                    ">
                        No Remaining Entries
                    </h2>

                    <p style="
                        color:#6b7280;
                        font-size:16px;
                        line-height:1.7;
                        margin-bottom:28px;
                    ">
                        Your current subscription entries are finished.
                        Upgrade or renew your subscription to continue
                        using the AI evaluation system.
                    </p>

                    <a
                        href="' . site_url('/subscriptions') . '"
                        style="
                            display:inline-block;
                            background:linear-gradient(
                                135deg,
                                #2563eb,
                                #7c3aed
                            );
                            color:#fff;
                            text-decoration:none;
                            padding:15px 28px;
                            border-radius:14px;
                            font-weight:700;
                            font-size:16px;
                            box-shadow:0 8px 20px rgba(37,99,235,0.25);
                        "
                    >
                        Upgrade Subscription →
                    </a>

                </div>
            ';
        }

        ob_start();
        ?>

        <style>

            .jcb-ai-form-wrapper{

                max-width:760px;

                margin:60px auto;

                background:
                    linear-gradient(
                        145deg,
                        #ffffff,
                        #f8fafc
                    );

                border:1px solid #e5e7eb;

                border-radius:28px;

                padding:45px;

                box-shadow:
                    0 20px 50px rgba(0,0,0,0.08);
            }

            .jcb-ai-badge{

                display:inline-flex;

                align-items:center;

                gap:10px;

                background:#eef2ff;

                color:#4338ca;

                padding:10px 18px;

                border-radius:999px;

                font-size:14px;

                font-weight:700;

                margin-bottom:24px;
            }

            .jcb-ai-title{

                font-size:42px;

                font-weight:900;

                color:#111827;

                line-height:1.15;

                margin-bottom:18px;
            }

            .jcb-ai-desc{

                color:#6b7280;

                font-size:17px;

                line-height:1.8;

                margin-bottom:35px;
            }

            .jcb-ai-label{

                display:block;

                font-size:15px;

                font-weight:700;

                color:#111827;

                margin-bottom:12px;
            }

            .jcb-ai-input{

                width:100%;

                padding:18px 22px;

                border:1px solid #d1d5db;

                border-radius:18px;

                font-size:16px;

                background:#fff;

                transition:0.25s ease;

                box-sizing:border-box;
            }

            .jcb-ai-input:focus{

                outline:none;

                border-color:#6366f1;

                box-shadow:
                    0 0 0 5px rgba(99,102,241,0.15);
            }

            .jcb-ai-submit{

                margin-top:28px;

                width:100%;

                border:none;

                cursor:pointer;

                background:
                    linear-gradient(
                        135deg,
                        #2563eb,
                        #7c3aed
                    );

                color:#fff;

                padding:18px;

                border-radius:18px;

                font-size:17px;

                font-weight:800;

                transition:0.25s ease;

                box-shadow:
                    0 14px 30px rgba(37,99,235,0.22);
            }

            .jcb-ai-submit:hover{

                transform:translateY(-2px);

                opacity:0.96;
            }

            .jcb-ai-helper{

                margin-top:14px;

                color:#9ca3af;

                font-size:13px;
            }

        </style>

        <div class="jcb-ai-form-wrapper">

            <div class="jcb-ai-badge">
                ⚡ AI Powered Evaluation
            </div>

            <div class="jcb-ai-title">
                Test Yourself
            </div>

            <div class="jcb-ai-desc">

                Submit any AI related area/domain/topic and let our AI engine
                intelligently evaluate your  concepts, understanding
                and overall ai tools usage in  your coding.

            </div>

            <form method="post">

                <label class="jcb-ai-label">

                    Enter Any Domain

                </label>

                <input
                    class="jcb-ai-input"
                    type="text"
                    name="jcb_domain"
                    placeholder="xgboost"
                    required
                />

                <div class="jcb-ai-helper">

                    Example:
                    Neural Networks,
                    SVM,
                    LangChain

                </div>

                <input
                    class="jcb-ai-submit"
                    type="submit"
                    name="jcb_submit_domain"
                    value="🚀 Start AI Evaluation"
                />

            </form>

        </div>

        <?php

        return ob_get_clean();
    }

    $first_q = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM $q_table
            WHERE chatbotjames_id = %d
            AND (
                answer IS NULL
                OR answer = ''
            )
            ORDER BY id ASC
            LIMIT 1
            ",
            $active_chatbot->id
        )
    );

    if (!$first_q) {

        return "
            <div>
                Conversation completed.
                Please refresh page.
            </div>
        ";
    }

    ob_start();
    ?>

    <style>

        #chat-container{
            display:flex;
            flex-direction:column;
            height:500px;
            border:1px solid #ddd;
            border-radius:12px;
            overflow:hidden;
        }

        #chat-window{
            flex-grow:1;
            padding:15px;
            overflow-y:auto;
            background:#f5f7fa;
        }

        .chat-message{
            display:flex;
            margin-bottom:12px;
        }

        .chat-message.bot{
            justify-content:flex-end;
        }

        .chat-message.user{
            justify-content:flex-start;
        }

        .bubble{
            max-width:70%;
            padding:10px 14px;
            border-radius:18px;
            font-size:14px;
        }

        .chat-message.bot .bubble{
            background:#0d6efd;
            color:white;
            border-bottom-right-radius:4px;
        }

        .chat-message.user .bubble{
            background:#e9ecef;
            color:black;
            border-bottom-left-radius:4px;
        }

        #chat-input-area{
            display:flex;
            border-top:1px solid #ddd;
        }

        #user-answer{
            flex-grow:1;
            border:none;
            padding:12px;
            resize:none;
        }

        #send-btn{
            width:100px;
            border:none;
            background:#0d6efd;
            color:white;
        }

    </style>

    <div id="chat-container">

        <div id="chat-window"></div>

        <div id="chat-input-area">

            <textarea
                id="user-answer"
                rows="1"
                placeholder="Type your answer..."
            ></textarea>

            <button id="send-btn">
                Send
            </button>

        </div>

    </div>

    <input
        type="hidden"
        id="question-id"
        value="<?php echo esc_attr($first_q->id); ?>"
    >

    <script>

    jQuery(document).ready(function($){

        let ajaxUrl =
            "<?php echo admin_url('admin-ajax.php'); ?>";

        function scrollBottom(){

            $("#chat-window").scrollTop(
                $("#chat-window")[0].scrollHeight
            );
        }

        function appendBot(text){

            $("#chat-window").append(`
                <div class="chat-message bot">
                    <div class="bubble">${text}</div>
                </div>
            `);

            scrollBottom();
        }

        function appendUser(text){

            $("#chat-window").append(`
                <div class="chat-message user">
                    <div class="bubble">${text}</div>
                </div>
            `);

            scrollBottom();
        }

        appendBot(
            `<?php echo esc_js($first_q->question); ?>`
        );

        $("#send-btn").on("click", function(){

            let answer =
                $("#user-answer").val().trim();

            let qid =
                $("#question-id").val();

            if(!answer){
                return;
            }

            appendUser(answer);

            $("#user-answer").val("");

            $.ajax({

                url: ajaxUrl,

                method: "POST",

                data: {
                    action: "jcb_submit_answer",
                    question_id: qid,
                    answer: answer
                },

                success: function(res){

                    if(res.completed){

                        appendBot(
                            "Conversation completed."
                        );

                        $.ajax({

                            url: ajaxUrl,

                            method: "POST",

                            data: {
                                action:
                                    "jcb_run_evaluation"
                            },

                            success: function(evalRes){

                                if(evalRes.success){

                                    window.location.href =
                                        evalRes.redirect_url;
                                }
                                else{

                                    appendBot(
                                        "Evaluation failed."
                                    );
                                }
                            }
                        });

                        return;
                    }

                    appendBot(res.question.text);

                    $("#question-id").val(
                        res.question.id
                    );
                }
            });
        });
    });

    </script>

    <?php

    return ob_get_clean();
}

/* ============================================
   HISTORY SHORTCODE
============================================ */

add_shortcode(
    'james_chatbot_history',
    'jcb_history_shortcode'
);

function jcb_history_shortcode() {

    if (!is_user_logged_in()) {

        return "<p>Please login.</p>";
    }

    global $wpdb;

    $user_id = get_current_user_id();

    $main_table =
        $wpdb->prefix . "chatbot_james";

    $sessions = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT *
            FROM $main_table
            WHERE user_id = %d
            AND evaluated = 1
            ORDER BY id DESC
            ",
            $user_id
        )
    );

    if (!$sessions) {

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

                        No James chat history Found

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

                        Your James chat history is currently empty.
                        Chat with our james AI engine,
                        try intelligent assessments,
                        evaluations, and performance reports.

                    </div>

                    <a
                        href="' . site_url('/') . '"

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

                        ✨ Try James Chat

                    </a>

                </div>

            </div>
        ';
    }

    ob_start();
    ?>

    <style>

        .jcb-history-wrapper{
            max-width:900px;
            margin:auto;
        }

        .jcb-history-card{
            background:#fff;
            border:1px solid #e5e5e5;
            border-radius:16px;
            padding:24px;
            margin-bottom:20px;
            box-shadow:0 2px 10px rgba(0,0,0,0.05);
        }

        .jcb-history-top{
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:10px;
        }

        .jcb-domain{
            font-size:22px;
            font-weight:700;
        }

        .jcb-score{
            font-size:16px;
            margin-top:8px;
        }

        .jcb-status-pass{
            color:green;
            font-weight:700;
        }

        .jcb-status-fail{
            color:red;
            font-weight:700;
        }

        .jcb-btn{
            display:inline-block;
            margin-top:18px;
            background:#0d6efd;
            color:#fff !important;
            text-decoration:none;
            padding:12px 20px;
            border-radius:10px;
            font-weight:600;
        }

        .jcb-date{
            color:#777;
            font-size:14px;
        }

    </style>

    <div class="jcb-history-wrapper">

        <h2>
            🧠 Your Evaluation History
        </h2>

        <?php foreach ($sessions as $session): ?>

            <div class="jcb-history-card">

                <div class="jcb-history-top">

                    <div>

                        <div class="jcb-domain">

                            <?php
                            echo esc_html(
                                $session->domain
                            );
                            ?>

                        </div>

                        <div class="jcb-score">

                            Score:

                            <?php
                            echo esc_html(
                                $session->bot_obt_score
                            );
                            ?>

                            /

                            <?php
                            echo esc_html(
                                $session->bot_tot_score
                            );
                            ?>

                            —

                            <?php
                            echo esc_html(
                                $session->bot_obt_perc
                            );
                            ?>

                        </div>

                    </div>

                    <div>

                        <?php
                        if (
                            $session->bot_scre_passed
                        ):
                        ?>

                            <div class="jcb-status-pass">
                                PASSED ✅
                            </div>

                        <?php else: ?>

                            <div class="jcb-status-fail">
                                FAILED ❌
                            </div>

                        <?php endif; ?>

                        <div class="jcb-date">

                            <?php
                            echo esc_html(
                                $session->created_at
                            );
                            ?>

                        </div>

                    </div>

                </div>

                <a
                    class="jcb-btn"
                    href="<?php echo site_url('/james-chatbot-session/?session_id=' . $session->id); ?>"
                >
                    View Details
                </a>

            </div>

        <?php endforeach; ?>

    </div>

    <?php

    return ob_get_clean();
}

/* ============================================
   SINGLE SESSION SHORTCODE
============================================ */

add_shortcode(
    'james_chatbot_session',
    'jcb_single_session_shortcode'
);

function jcb_single_session_shortcode() {

    if (!is_user_logged_in()) {

        return "<p>Please login.</p>";
    }

    if (!isset($_GET['session_id'])) {

        return "<p>Session missing.</p>";
    }

    global $wpdb;

    $session_id =
        intval($_GET['session_id']);

    $user_id =
        get_current_user_id();

    $main_table =
        $wpdb->prefix . "chatbot_james";

    $q_table =
        $wpdb->prefix . "chatbotjames_questions";

    $session = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM $main_table
            WHERE id = %d
            AND user_id = %d
            ",
            $session_id,
            $user_id
        )
    );

    if (!$session) {

        return "<p>Session not found.</p>";
    }

    $records = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT *
            FROM $q_table
            WHERE chatbotjames_id = %d
            ORDER BY id ASC
            ",
            $session_id
        )
    );

    ob_start();
    ?>

    <style>

        .history-wrapper{
            max-width:900px;
            margin:auto;
        }

        .session-card{
            border:1px solid #ddd;
            border-radius:12px;
            padding:20px;
            background:#fff;
        }

        .session-header{
            margin-bottom:20px;
            padding-bottom:10px;
            border-bottom:1px solid #eee;
        }

        .chat-row{
            display:flex;
            margin-bottom:15px;
        }

        .right{
            justify-content:flex-end;
        }

        .left{
            justify-content:flex-start;
        }

        .bubble{
            max-width:70%;
            padding:12px;
            border-radius:16px;
            font-size:14px;
        }

        .bot-bubble{
            background:#0d6efd;
            color:white;
            border-bottom-right-radius:4px;
        }

        .user-bubble{
            background:#e9ecef;
            color:black;
            border-bottom-left-radius:4px;
        }

        .score-box{
            margin-top:8px;
            padding:8px;
            background:#fff3cd;
            border-radius:8px;
            font-size:13px;
        }

    </style>

    <div class="history-wrapper">

        <div class="session-card">

            <div class="session-header">

                <h2>

                    <?php
                    echo esc_html(
                        $session->domain
                    );
                    ?>

                </h2>

                <p>

                    <strong>Score:</strong>

                    <?php
                    echo esc_html(
                        $session->bot_obt_score
                    );
                    ?>

                    /

                    <?php
                    echo esc_html(
                        $session->bot_tot_score
                    );
                    ?>

                </p>

                <p>

                    <strong>Percentage:</strong>

                    <?php
                    echo esc_html(
                        $session->bot_obt_perc
                    );
                    ?>

                </p>

            </div>

            <?php foreach ($records as $row): ?>

                <div class="chat-row right">

                    <div class="bubble bot-bubble">

                        <?php
                        echo esc_html(
                            $row->question
                        );
                        ?>

                    </div>

                </div>

                <div class="chat-row left">

                    <div class="bubble user-bubble">

                        <strong>
                            Your Answer:
                        </strong>

                        <br><br>

                        <?php
                        echo nl2br(
                            esc_html(
                                $row->answer
                            )
                        );
                        ?>

                        <div class="score-box">

                            <strong>Score:</strong>

                            <?php
                            echo esc_html(
                                $row->its_score
                            );
                            ?>

                            <br><br>

                            <strong>
                                Evaluation:
                            </strong>

                            <br>

                            <?php
                            echo esc_html(
                                $row->its_evaluation_explanation
                            );
                            ?>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

    <?php

    return ob_get_clean();
}

/* ============================================
   STORE QUESTIONS
============================================ */

function jcb_store_questions(
    $chatbot_id,
    $questions
) {

    global $wpdb;

    $q_table =
        $wpdb->prefix . "chatbotjames_questions";

    foreach ($questions as $q) {

        $wpdb->insert(
            $q_table,
            [
                'chatbotjames_id' =>
                    $chatbot_id,

                'question' =>
                    $q['question'] ?? '',

                'section' =>
                    $q['section'] ?? '',

                'mode' =>
                    $q['mode'] ?? '',

                'answer' => '',

                'its_score' => null
            ]
        );
    }
}

/* ============================================
   SEND DOMAIN TO FLASK
============================================ */

function jcb_send_domain_to_flask(
    $domain,
    $user_id
) {

    $flask_url =
        "http://192.168.18.85:8007/generate_james_bot_qs/";

    $payload = json_encode([
        "domain"  => $domain,
        "user_id" => $user_id
    ]);

    $ch = curl_init($flask_url);

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_POST => true,

        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],

        CURLOPT_POSTFIELDS => $payload
    ]);

    $response = curl_exec($ch);

    if ($response === false) {

        error_log(
            "Flask Curl Error: " .
            curl_error($ch)
        );

        curl_close($ch);

        return false;
    }

    curl_close($ch);

    return json_decode($response, true);
}

/* ============================================
   HANDLE DOMAIN SUBMISSION
============================================ */

add_action(
    'init',
    'jcb_handle_domain_submission'
);

function jcb_handle_domain_submission() {

    if (!isset($_POST['jcb_submit_domain'])) {
        return;
    }

    if (!is_user_logged_in()) {
        return;
    }

    global $wpdb;

    $main_table =
        $wpdb->prefix . "chatbot_james";

    $user_id =
        get_current_user_id();

    $domain =
        sanitize_text_field(
            $_POST['jcb_domain']
        );

    if (!function_exists('ai_decrease_entry')) {

        wp_die(
            "Subscription system missing."
        );
    }

    if (!ai_decrease_entry($user_id)) {

        wp_redirect(
            site_url('/subscriptions')
        );

        exit;
    }

    $wpdb->insert(
        $main_table,
        [
            'user_id' => $user_id,
            'domain'  => $domain
        ]
    );

    $chatbot_id =
        $wpdb->insert_id;

    $flask_response =
        jcb_send_domain_to_flask(
            $domain,
            $user_id
        );

    if (
        !$flask_response ||
        empty($flask_response['success'])
    ) {

        error_log(
            "Flask did not return success."
        );

        return;
    }

    if (!empty($flask_response['questions'])) {

        jcb_store_questions(
            $chatbot_id,
            $flask_response['questions']
        );
    }

    wp_redirect(
        site_url('/james_chatbot/')
    );

    exit;
}