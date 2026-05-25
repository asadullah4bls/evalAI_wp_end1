<?php
/*
Plugin Name: AI Subscription Manager
Description: Lightweight subscription and credit manager for AI plugins.
Version: 1.0
*/

if (!defined('ABSPATH')) exit;

/* =====================================================
   AUTO ASSIGN FREE PLAN ON USER REGISTER
===================================================== */

add_action('user_register', 'aism_assign_free_plan');

function aism_assign_free_plan($user_id) {

    if (!get_user_meta($user_id, 'ai_plan', true)) {

        update_user_meta($user_id, 'ai_plan', 'free');

        update_user_meta($user_id, 'ai_remaining_entries', 1);

        update_user_meta(
            $user_id,
            'ai_plan_expiry',
            ''
        );
    }
}

add_action('init', 'ai_handle_plan_subscription');

function ai_handle_plan_subscription() {

    if (!isset($_POST['ai_subscribe_plan'])) {
        return;
    }

    if (!is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();

    $plan_name = sanitize_text_field($_POST['plan_name']);
    $entries   = intval($_POST['entries']);

    // UPDATE USER META
    update_user_meta($user_id, 'ai_plan', $plan_name);

    update_user_meta(
        $user_id,
        'ai_remaining_entries',
        $entries
    );

    wp_redirect(site_url('/'));
    exit;
}

add_shortcode(
    'ai_subscription_plans',
    'ai_subscription_plans_shortcode'
);

function ai_subscription_plans_shortcode() {

    if (!is_user_logged_in()) {
        return "<p>Please login.</p>";
    }

    $user_id = get_current_user_id();

    $current_plan = get_user_meta(
        $user_id,
        'ai_plan',
        true
    );

    $remaining_entries = (int) get_user_meta(
        $user_id,
        'ai_remaining_entries',
        true
    );

    ob_start();
    ?>

    <style>

        .aism-wrapper{
            max-width:1200px;
            margin:40px auto;
            padding:20px;
            font-family:Arial,sans-serif;
        }

        .aism-top-card{
            background:linear-gradient(
                135deg,
                #0f172a,
                #1e293b
            );

            color:white;

            border-radius:24px;

            padding:35px;

            margin-bottom:40px;

            box-shadow:0 10px 30px rgba(0,0,0,0.15);
        }

        .aism-top-grid{
            display:flex;
            justify-content:space-between;
            flex-wrap:wrap;
            gap:20px;
        }

        .aism-label{
            font-size:14px;
            opacity:0.8;
            margin-bottom:8px;
        }

        .aism-value{
            font-size:32px;
            font-weight:700;
        }

        .aism-plan-badge{
            display:inline-block;
            padding:8px 16px;
            background:#22c55e;
            border-radius:999px;
            font-size:14px;
            font-weight:700;
            margin-top:12px;
        }

        .aism-heading{
            text-align:center;
            margin-bottom:40px;
        }

        .aism-heading h2{
            font-size:42px;
            margin-bottom:10px;
        }

        .aism-heading p{
            color:#666;
            font-size:18px;
        }

        .aism-pricing-grid{
            display:grid;
            grid-template-columns:
                repeat(auto-fit,minmax(300px,1fr));
            gap:30px;
        }

        .aism-card{
            background:white;
            border-radius:24px;
            padding:35px;
            border:1px solid #e5e7eb;
            position:relative;
            overflow:hidden;
            transition:0.3s;
            box-shadow:0 5px 20px rgba(0,0,0,0.06);
        }

        .aism-card:hover{
            transform:translateY(-6px);
        }

        .aism-popular{
            border:2px solid #3b82f6;
        }

        .aism-popular-tag{
            position:absolute;
            top:15px;
            right:-35px;
            background:#3b82f6;
            color:white;
            padding:8px 40px;
            transform:rotate(45deg);
            font-size:12px;
            font-weight:700;
        }

        .aism-plan-title{
            font-size:28px;
            font-weight:700;
            margin-bottom:10px;
        }

        .aism-price{
            font-size:48px;
            font-weight:800;
            margin-bottom:8px;
        }

        .aism-price small{
            font-size:18px;
            color:#666;
        }

        .aism-desc{
            color:#666;
            margin-bottom:25px;
        }

        .aism-features{
            margin-bottom:30px;
        }

        .aism-feature{
            margin-bottom:14px;
            display:flex;
            align-items:center;
            gap:10px;
            color:#222;
        }

        .aism-btn{
            width:100%;
            border:none;
            border-radius:14px;
            padding:16px;
            font-size:16px;
            font-weight:700;
            cursor:pointer;
            transition:0.3s;
        }

        .aism-btn-basic{
            background:#111827;
            color:white;
        }

        .aism-btn-basic:hover{
            background:#000;
        }

        .aism-btn-pro{
            background:#2563eb;
            color:white;
        }

        .aism-btn-pro:hover{
            background:#1d4ed8;
        }

        .aism-active-box{
            background:#dcfce7;
            border:1px solid #86efac;
            padding:20px;
            border-radius:18px;
            margin-top:25px;
            color:#166534;
            font-weight:600;
        }

    </style>

    <div class="aism-wrapper">

        <!-- TOP STATUS CARD -->

        <div class="aism-top-card">

            <div class="aism-top-grid">

                <div>

                    <div class="aism-label">
                        Current Plan
                    </div>

                    <div class="aism-value">
                        <?php echo esc_html(
                            ucfirst($current_plan)
                        ); ?>
                    </div>

                    <div class="aism-plan-badge">
                        ACTIVE
                    </div>

                </div>

                <div>

                    <div class="aism-label">
                        Remaining Interview Entries
                    </div>

                    <div class="aism-value">
                        <?php echo esc_html(
                            $remaining_entries
                        ); ?>
                    </div>

                </div>

            </div>

        </div>

        <?php if ($remaining_entries > 0): ?>

            <div class="aism-active-box">

                ✅ You still have active interview entries available.
                Complete your current interviews before subscribing again.

            </div>

        <?php else: ?>

            <div class="aism-heading">

                <h2>
                    Upgrade Your Plan
                </h2>

                <p>
                    Unlock more AI interview attempts
                    and premium evaluation access.
                </p>

            </div>

            <div class="aism-pricing-grid">

                <!-- BASIC -->

                <div class="aism-card">

                    <div class="aism-plan-title">
                        Basic
                    </div>

                    <div class="aism-price">
                        $9
                        <small>/plan</small>
                    </div>

                    <div class="aism-desc">
                        Perfect for casual interview practice.
                    </div>

                    <div class="aism-features">

                        <div class="aism-feature">
                            ✅ 2 AI Interview Entries
                        </div>

                        <div class="aism-feature">
                            ✅ AI Score Evaluation
                        </div>

                        <div class="aism-feature">
                            ✅ Interview History
                        </div>

                    </div>

                    <form method="post">

                        <input
                            type="hidden"
                            name="plan_name"
                            value="basic"
                        >

                        <input
                            type="hidden"
                            name="entries"
                            value="2"
                        >

                        <button
                            type="submit"
                            name="ai_subscribe_plan"
                            class="aism-btn aism-btn-basic"
                        >
                            Get Basic Plan
                        </button>

                    </form>

                </div>

                <!-- PRO -->

                <div class="aism-card aism-popular">

                    <div class="aism-popular-tag">
                        MOST POPULAR
                    </div>

                    <div class="aism-plan-title">
                        Pro
                    </div>

                    <div class="aism-price">
                        $19
                        <small>/plan</small>
                    </div>

                    <div class="aism-desc">
                        Best for serious interview preparation.
                    </div>

                    <div class="aism-features">

                        <div class="aism-feature">
                            ✅ 5 AI Interview Entries
                        </div>

                        <div class="aism-feature">
                            ✅ Detailed AI Evaluation
                        </div>

                        <div class="aism-feature">
                            ✅ Unlimited History Access
                        </div>

                        <div class="aism-feature">
                            ✅ Priority Experience
                        </div>

                    </div>

                    <form method="post">

                        <input
                            type="hidden"
                            name="plan_name"
                            value="pro"
                        >

                        <input
                            type="hidden"
                            name="entries"
                            value="3"
                        >

                        <button
                            type="submit"
                            name="ai_subscribe_plan"
                            class="aism-btn aism-btn-pro"
                        >
                            Upgrade to Pro
                        </button>

                    </form>

                </div>

            </div>

        <?php endif; ?>

    </div>

    <?php

    return ob_get_clean();
}

/* =====================================================
   HELPER FUNCTIONS
===================================================== */

function ai_get_user_plan($user_id) {

    return get_user_meta($user_id, 'ai_plan', true);
}

function ai_get_remaining_entries($user_id) {

    return (int) get_user_meta(
        $user_id,
        'ai_remaining_entries',
        true
    );
}

function ai_decrease_entry($user_id, $count = 1) {

    $remaining = ai_get_remaining_entries($user_id);

    if ($remaining < $count) {
        return false;
    }

    $new_remaining = max(0, $remaining - $count);

    update_user_meta(
        $user_id,
        'ai_remaining_entries',
        $new_remaining
    );

    return true;
}

function ai_assign_plan($user_id, $plan) {

    $plan = strtolower($plan);

    $entries = 0;
    $expiry  = '';

    switch ($plan) {

        case 'free':
            $entries = 1;
            break;

        case 'monthly':
            $entries = 3;
            $expiry = date(
                'Y-m-d H:i:s',
                strtotime('+30 days')
            );
            break;

        case 'yearly':
            $entries = 10;
            $expiry = date(
                'Y-m-d H:i:s',
                strtotime('+365 days')
            );
            break;

        default:
            return false;
    }

    update_user_meta($user_id, 'ai_plan', $plan);

    update_user_meta(
        $user_id,
        'ai_remaining_entries',
        $entries
    );

    update_user_meta(
        $user_id,
        'ai_plan_expiry',
        $expiry
    );

    return true;
}

/* =====================================================
   ADMIN USER PROFILE SECTION
===================================================== */

add_action('show_user_profile', 'aism_user_subscription_fields');
add_action('edit_user_profile', 'aism_user_subscription_fields');

function aism_user_subscription_fields($user) {

    $plan = get_user_meta($user->ID, 'ai_plan', true);
    $remaining = get_user_meta(
        $user->ID,
        'ai_remaining_entries',
        true
    );

    ?>

    <h2>AI Subscription</h2>

    <table class="form-table">

        <tr>
            <th><label>Current Plan</label></th>

            <td>

                <select name="ai_plan">

                    <option value="free"
                        <?php selected($plan, 'free'); ?>>
                        Free
                    </option>

                    <option value="monthly"
                        <?php selected($plan, 'monthly'); ?>>
                        Monthly
                    </option>

                    <option value="yearly"
                        <?php selected($plan, 'yearly'); ?>>
                        Yearly
                    </option>

                </select>

            </td>
        </tr>

        <tr>
            <th><label>Remaining Entries</label></th>

            <td>

                <input
                    type="number"
                    name="ai_remaining_entries"
                    value="<?php echo esc_attr($remaining); ?>"
                />

            </td>
        </tr>

    </table>

    <?php
}

/* =====================================================
   SAVE ADMIN PROFILE CHANGES
===================================================== */

add_action(
    'personal_options_update',
    'aism_save_user_subscription_fields'
);

add_action(
    'edit_user_profile_update',
    'aism_save_user_subscription_fields'
);

function aism_save_user_subscription_fields($user_id) {

    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    $plan = sanitize_text_field($_POST['ai_plan']);

    $remaining = intval(
        $_POST['ai_remaining_entries']
    );

    update_user_meta($user_id, 'ai_plan', $plan);

    update_user_meta(
        $user_id,
        'ai_remaining_entries',
        $remaining
    );
}