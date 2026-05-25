<?php

function ai_pdf_upload_form_shortcode() {

    if (!is_user_logged_in()) {

        return "<p>Please login first.</p>";
    }

    $user_id = get_current_user_id();

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
        <div
            style="
                width:100%;
                display:flex;
                justify-content:center;
            "
        >

            <div
                style="
                    max-width:950px;
                    margin:70px auto;

                    background:
                        linear-gradient(
                            145deg,
                            #ffffff,
                            #f8fafc
                        );

                    border:1px solid #e5e7eb;

                    border-radius:34px;

                    overflow:hidden;

                    position:relative;

                    box-shadow:
                        0 20px 60px rgba(0,0,0,0.08);
                "
            >

                <!-- GLOW EFFECTS -->

                <div
                    style="
                        position:absolute;
                        top:-140px;
                        right:-140px;

                        width:320px;
                        height:320px;

                        background:
                            radial-gradient(
                                circle,
                                rgba(37,99,235,0.14),
                                transparent 70%
                            );
                    "
                ></div>

                <div
                    style="
                        position:absolute;
                        bottom:-120px;
                        left:-120px;

                        width:280px;
                        height:280px;

                        background:
                            radial-gradient(
                                circle,
                                rgba(124,58,237,0.12),
                                transparent 70%
                            );
                    "
                ></div>

                <div
                    style="
                        position:relative;
                        z-index:2;

                        padding:60px 45px;
                    "
                >

                    <!-- HEADER -->

                    <div
                        style="
                            text-align:center;
                            margin-bottom:45px;
                        "
                    >

                        <div
                            style="
                                font-size:74px;
                                margin-bottom:20px;
                            "
                        >
                            📄
                        </div>

                        <div
                            style="
                                font-size:40px;
                                font-weight:900;
                                color:#111827;

                                margin-bottom:18px;
                            "
                        >
                            Upload Documents for AI Evaluation
                        </div>

                        <div
                            style="
                                max-width:700px;
                                margin:auto;

                                color:#6b7280;

                                font-size:17px;

                                line-height:1.8;
                            "
                        >

                            Upload one or multiple PDF documents and let
                            the AI engine generate intelligent quizzes,
                            automated evaluations, performance scoring,
                            and detailed assessment insights.

                        </div>

                    </div>

                    <!-- STATS -->

                    <div
                        style="
                            display:flex;
                            justify-content:center;
                            gap:20px;
                            flex-wrap:wrap;

                            margin-bottom:40px;
                        "
                    >

                        <div
                            style="
                                background:#eff6ff;
                                color:#1d4ed8;

                                padding:14px 22px;

                                border-radius:16px;

                                font-weight:700;
                                font-size:15px;
                            "
                        >
                            🤖 AI Quiz Generation
                        </div>

                        <div
                            style="
                                background:#f5f3ff;
                                color:#6d28d9;

                                padding:14px 22px;

                                border-radius:16px;

                                font-weight:700;
                                font-size:15px;
                            "
                        >
                            📊 Smart Evaluation
                        </div>

                        <div
                            style="
                                background:#ecfeff;
                                color:#0f766e;

                                padding:14px 22px;

                                border-radius:16px;

                                font-weight:700;
                                font-size:15px;
                            "
                        >
                            ⚡ Instant Processing
                        </div>

                    </div>

                    <!-- FORM -->

                    <form
                        method="post"
                        enctype="multipart/form-data"
                    >

                        <div
                            style="
                                border:2px dashed #cbd5e1;

                                background:#ffffff;

                                border-radius:28px;

                                padding:50px 30px;

                                text-align:center;

                                margin-bottom:30px;

                                transition:0.3s ease;
                            "
                        >

                            <div
                                style="
                                    font-size:58px;
                                    margin-bottom:20px;
                                "
                            >
                                ☁️
                            </div>

                            <div
                                style="
                                    font-size:26px;
                                    font-weight:800;
                                    color:#111827;

                                    margin-bottom:12px;
                                "
                            >
                                Select Your PDF Files
                            </div>

                            <div
                                style="
                                    color:#6b7280;
                                    margin-bottom:28px;
                                    font-size:15px;
                                "
                            >
                                Drag & drop your files or browse from device
                            </div>

                            <input
                                type="file"
                                name="pdf_files[]"
                                multiple
                                accept=".pdf"

                                style="
                                    background:#f8fafc;

                                    border:1px solid #dbeafe;

                                    padding:18px;

                                    border-radius:16px;

                                    width:100%;
                                    max-width:520px;

                                    font-size:15px;
                                "
                            >

                        </div>

                        <!-- FOOTER -->

                        <div
                            style="
                                display:flex;
                                justify-content:space-between;
                                align-items:center;
                                flex-wrap:wrap;
                                gap:20px;
                            "
                        >

                            <div
                                style="
                                    color:#6b7280;
                                    font-size:15px;
                                    line-height:1.7;
                                "
                            >

                                Remaining AI Entries:
                                <strong
                                    style="
                                        color:#111827;
                                    "
                                >
                                    <?php echo esc_html($remaining); ?>
                                </strong>

                            </div>

                            <button
                                type="submit"
                                name="ai_pdf_submit"

                                style="
                                    border:none;

                                    background:
                                        linear-gradient(
                                            135deg,
                                            #2563eb,
                                            #7c3aed
                                        );

                                    color:#fff;

                                    padding:18px 34px;

                                    border-radius:18px;

                                    font-size:16px;

                                    font-weight:800;

                                    cursor:pointer;

                                    box-shadow:
                                        0 16px 35px rgba(37,99,235,0.22);

                                    transition:0.25s ease;
                                "

                                onmouseover="
                                    this.style.transform=
                                    'translateY(-2px)';
                                "

                                onmouseout="
                                    this.style.transform=
                                    'translateY(0)';
                                "
                            >
                                🚀 Generate AI Quiz
                            </button>

                        </div>

                    </form>

                </div>

            </div>
        </div>

    <?php

    return ob_get_clean();
}